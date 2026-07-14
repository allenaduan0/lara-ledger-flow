# LedgerFlow Technical Design

**Status:** Proposed  
**Target:** Laravel 12, PHP 8.2+, MySQL 8.x locally, PostgreSQL-compatible deployment  
**Purpose:** A production-oriented digital wallet and double-entry ledger, designed as a modular monolith.

## 1. Executive summary

LedgerFlow exposes wallet funding, transfers, withdrawals, balances, statements, and operational reconciliation while maintaining an immutable, balanced accounting journal.

The design separates three concepts that are often incorrectly collapsed:

1. A **payment transaction** is the business workflow and its state (requested, pending, completed, failed, reversed).
2. A **journal transaction** is a posted accounting fact. It is immutable and contains at least two entries whose debits equal credits per currency.
3. An **account balance** is a lockable, rebuildable projection used for fast authorization and reads. Journal entries remain the source of truth.

LedgerFlow begins as a modular monolith. This retains one ACID boundary for money movement while making domain boundaries explicit enough to extract later. Synchronous commands perform only correctness-critical database work. Notifications, analytics, and reconciliation orchestration run after commit through Laravel events, an outbox, and database queues.

## 2. Scope and invariants

### 2.1 Initial scope

- Customer wallets with one account per supported currency.
- Internal wallet-to-wallet transfers.
- Funding and withdrawal workflows through abstract external providers.
- Holds/reservations for delayed capture.
- Immutable statements and transaction history.
- Reversals and refunds through compensating entries.
- Idempotent write APIs.
- Audit trail, daily reconciliation, and operational exception handling.

KYC, card-data handling, foreign exchange, interest, lending, and real bank connectivity are integration boundaries, not initial implementations. Multi-currency transfers must not be posted until an explicit FX trade domain is designed; a journal transaction balances independently in each currency.

### 2.2 Non-negotiable invariants

- Monetary amounts are positive integers in a currency's minor unit; no floating-point arithmetic.
- Every posted journal transaction has at least two entries and `sum(debits) = sum(credits)` for each currency.
- A journal entry references exactly one journal transaction and one ledger account.
- Posted journal transactions and entries are never updated or deleted.
- Corrections use a new reversing journal transaction linked to the original.
- Each ledger account has exactly one currency and one owner/purpose.
- Available balance may never be negative unless the account explicitly permits overdraft.
- A payment transaction can produce multiple journal transactions over its lifecycle, but each operation type is unique (for example, one capture and one full reversal).
- The idempotency key plus authenticated client scope identifies one semantic request and cannot be reused with a different payload.
- External side effects never occur inside the database transaction.
- Events/jobs become visible only after the money transaction commits.

## 3. Architecture

### 3.1 Style

Use a **modular monolith with clean/hexagonal boundaries**:

```text
HTTP / CLI / Scheduler
        |
Application commands and queries
        |
Domain aggregates, policies, value objects, domain events
        |
Repository / clock / ID / provider ports
        |
Eloquent, SQL transaction manager, queue, provider adapters
```

Laravel remains the composition root and delivery framework. Controllers validate transport concerns and invoke one use case. Domain rules do not depend on HTTP, Eloquent models, queues, or facades. Eloquent models live in infrastructure and map persistence records to domain concepts. Pragmatically, query/read models may use Eloquent directly; command paths go through domain-facing repositories.

Suggested namespace layout:

```text
app/
  Modules/<Module>/Domain
  Modules/<Module>/Application
  Modules/<Module>/Infrastructure
  Modules/<Module>/Presentation/Http
  Shared/Domain
  Shared/Application
  Shared/Infrastructure
```

Each module owns its migrations conceptually, service provider, routes, policies, and tests. Cross-module calls use application interfaces or published events, not another module's Eloquent model.

### 3.2 Module boundaries

| Module | Responsibility | Owns |
|---|---|---|
| Identity & Access | Authentication, API clients, authorization | users, api_clients |
| Customers | Customer profile and lifecycle | customers |
| Wallets | Wallet/account provisioning, wallet status, balance queries | wallets, wallet_accounts |
| Ledger | Chart of accounts, journal posting, balance projection, reversals | ledger_accounts, journal_transactions, journal_entries, account_balances |
| Payments | Transfer/funding/withdrawal/hold workflows and state machines | payment_transactions, holds, provider_attempts |
| Idempotency | Request deduplication and response replay | idempotency_keys |
| Reconciliation | Internal proof, external settlement comparison, discrepancies | reconciliation_runs, reconciliation_items |
| Audit | Actor/action metadata and tamper-evident operational history | audit_logs |
| Integration | Transactional outbox, provider adapters, webhooks | outbox_messages, inbound_webhooks |

Ledger is deliberately unaware of wallets as a business concept: it posts authorized journals against ledger account IDs. Payments constructs posting instructions using Ledger's application interface.

### 3.3 Consistency boundaries

The payment transaction, journal transaction/entries, balance projection changes, idempotency completion, audit row, and outbox messages commit in one database transaction. Queued consumers and external providers are eventually consistent. No distributed transaction is attempted.

## 4. Domain model

### 4.1 Value objects

- `Money(amountMinor: int, currency: CurrencyCode)` rejects zero/negative values where inappropriate and currency mismatches.
- `CurrencyCode` is an uppercase ISO-4217 code plus configured exponent. Currency metadata is configuration/reference data; amounts remain integers.
- Strong IDs use application-generated UUIDv7/ULID strings, stored as `CHAR(36)` UUIDs for maximum cross-engine clarity. Do not rely on engine-specific UUID types.
- `IdempotencyKey`, `AccountCode`, and `ExternalReference` validate format and length.

PHP integers safely cover signed 64-bit on the supported runtime. Database amounts use signed `BIGINT`; application policies impose lower operational limits and checked arithmetic to prevent overflow.

### 4.2 Aggregates

**Wallet** controls status (`active`, `suspended`, `closed`) and its currency account associations. It does not calculate authoritative ledger balances.

**PaymentTransaction** is the workflow aggregate. Types include `internal_transfer`, `funding`, `withdrawal`, `hold`, `capture`, and `refund`. Its state machine prevents illegal transitions. Failure before posting has no journal; failure after posting requires compensation.

**JournalTransaction** is append-only. It is created in draft form only in memory and persisted atomically as `posted`; there is no durable half-posted state. A posting policy validates entries, currency balancing, account status, and references.

**Hold** reserves available funds without changing ledger ownership. It is stateful (`active`, `captured`, `released`, `expired`) and affects the balance projection. Capture converts the reservation into journal movement in the same transaction that closes/reduces the hold.

**ReconciliationRun** represents a repeatable comparison for a scope and business date. Discrepancies are tracked to resolution without mutating financial history.

## 5. Accounting and ledger design

### 5.1 Chart of accounts

Ledger accounts have a type: `asset`, `liability`, `equity`, `revenue`, or `expense`, and a normal side: debit for assets/expenses, credit for liabilities/equity/revenue. Customer wallet balances are liabilities owed by the platform.

Minimum system accounts per currency:

- Customer wallet liability subaccounts.
- External cash/settlement asset account.
- Funding/withdrawal clearing accounts.
- Fee revenue account.
- Suspense account, restricted to controlled reconciliation workflows.

Examples:

| Operation | Debit | Credit |
|---|---|---|
| Fund customer 100 USD after confirmed settlement | Cash/settlement asset 100 | Customer wallet liability 100 |
| Transfer 40 USD from A to B | Customer A liability 40 | Customer B liability 40 |
| Withdraw 25 USD after provider acceptance | Customer liability 25 | Withdrawal clearing liability 25 |
| Settle withdrawal | Withdrawal clearing liability 25 | Cash/settlement asset 25 |

The first release should avoid fees unless their recognition timing is specified. When introduced, fees are additional entries in the same journal transaction and must still balance.

### 5.2 Debit/credit representation

`journal_entries` stores `direction` (`debit` or `credit`) and a strictly positive `amount_minor`. This is easier to audit than signed amounts. Domain validation is authoritative; database checks enforce positive amounts and valid enumerated strings where both engines support equivalent syntax.

Balances are stored from the customer's useful perspective:

- `posted_balance_minor`: net ledger balance after all posted journals.
- `pending_debit_minor`: active holds/reservations.
- `available_balance_minor = posted_balance_minor - pending_debit_minor` calculated by the domain, not persisted as a generated column.
- `version`: optimistic diagnostic/rebuild version, while writes use pessimistic row locking.

Each journal entry may store `balance_after_minor` for statement rendering, but only if posting locks each account and assigns a deterministic per-account sequence. The authoritative projection remains `account_balances`, and reconciliation can rebuild it from ordered entries.

### 5.3 Immutability

Application protections:

- No update/delete repository methods for journals or entries.
- Eloquent models reject update/delete lifecycle operations.
- Authorization prevents ordinary operators from direct ledger writes.
- Database credentials used by the application should eventually have INSERT/SELECT but not UPDATE/DELETE on journal tables; migrations use a separate role.

Database triggers can provide defense in depth in production, but portable migrations should implement equivalent MySQL and PostgreSQL trigger variants. Trigger installation is verified in deployment tests. Backups and privileged DBA access remain part of the trust boundary, so audit export and database access logging are also required.

## 6. Database schema

Use `utf8mb4`/binary-sensitive identifiers on MySQL and UTF-8 on PostgreSQL. Store timestamps in UTC with microsecond precision (`timestamp(6)` semantics), while avoiding database-specific generated columns, partial indexes, enums, arrays, and JSON query dependencies. JSON is acceptable only for opaque metadata/snapshots.

### 6.1 Core tables

| Table | Important columns | Constraints/indexes |
|---|---|---|
| `users` | id, name, email, password, timestamps | unique email |
| `api_clients` | id, owner_id, name, credential_hash, status | unique credential identifier |
| `customers` | id, user_id nullable, external_ref, status, timestamps | unique external_ref |
| `wallets` | id, customer_id, status, timestamps | index customer/status |
| `ledger_accounts` | id, code, name, currency, type, normal_side, owner_type, owner_id nullable, purpose, allow_overdraft, status, timestamps | unique code; unique(owner_type, owner_id, currency, purpose); index owner |
| `wallet_accounts` | wallet_id, ledger_account_id, currency, timestamps | PK wallet_id/currency; unique ledger_account_id |
| `account_balances` | ledger_account_id, posted_balance_minor, pending_debit_minor, version, updated_at | PK/FK ledger_account_id; nonnegative pending debit |
| `payment_transactions` | id, type, status, customer_id, source_account_id nullable, destination_account_id nullable, amount_minor, currency, reference, description, failure_code nullable, initiated_at, completed_at nullable, reversed_at nullable, metadata | unique reference; indexes customer/time, status/time, accounts/time |
| `journal_transactions` | id, payment_transaction_id nullable, operation, reference, description, effective_at, posted_at, reversal_of_id nullable, created_by_type/id, metadata | unique reference; unique reversal_of_id for full reversal; indexes payment/effective time |
| `journal_entries` | id, journal_transaction_id, ledger_account_id, direction, amount_minor, currency, account_sequence, balance_after_minor, created_at | unique(account_id, account_sequence); indexes journal, account/time; amount > 0 |
| `holds` | id, payment_transaction_id, ledger_account_id, amount_minor, captured_minor, currency, status, expires_at, released_at nullable, version, timestamps | unique payment_transaction_id; indexes account/status/expiry |

Do not use polymorphic foreign keys where referential integrity is financially important. `owner_type/owner_id` and actor fields are audit descriptors; wallet-account ownership is enforced through `wallet_accounts`, and system accounts have no owner. If more owner types become transactional, introduce explicit association tables.

### 6.2 Reliability and operations tables

| Table | Important columns | Constraints/indexes |
|---|---|---|
| `idempotency_keys` | id, client_id, key, request_method, request_path, request_hash, status, locked_until, resource_type/id nullable, response_status nullable, response_headers/json nullable, response_body/json nullable, expires_at, timestamps | unique(client_id, key); index expiry/status |
| `outbox_messages` | id, event_type, aggregate_type/id, payload, occurred_at, available_at, published_at nullable, attempts, last_error nullable | indexes unpublished/available, aggregate |
| `inbound_webhooks` | id, provider, provider_event_id, payload_hash, payload, status, received_at, processed_at nullable | unique(provider, provider_event_id) |
| `provider_attempts` | id, payment_transaction_id, provider, operation, attempt_no, provider_idempotency_key, external_id nullable, status, request/response snapshots, timestamps | unique(provider, provider_idempotency_key); unique transaction/operation/attempt |
| `audit_logs` | id, occurred_at, actor_type/id, action, subject_type/id, correlation_id, request_id, ip_hash nullable, before_json nullable, after_json nullable, prev_hash, entry_hash, metadata | indexes subject/time, correlation; append-only |
| `reconciliation_runs` | id, type, scope, business_date, status, started_at, completed_at nullable, totals_json, checksum, error nullable | unique(type, scope, business_date, run version) |
| `reconciliation_items` | id, run_id, kind, internal_reference nullable, external_reference nullable, expected_minor, actual_minor, currency, status, resolution, resolved_by/at nullable | indexes run/status, references |

Sensitive provider payloads are minimized, encrypted where retained, and redacted from logs. Idempotent responses should have a bounded retention period; financial resources themselves retain their normal statutory history.

### 6.3 Relationships

```text
Customer 1---* Wallet 1---* WalletAccount *---1 LedgerAccount 1---1 AccountBalance
                                      LedgerAccount 1---* JournalEntry *---1 JournalTransaction
PaymentTransaction 1---* JournalTransaction
PaymentTransaction 1---0..1 Hold
PaymentTransaction 1---* ProviderAttempt
ApiClient 1---* IdempotencyKey
ReconciliationRun 1---* ReconciliationItem
```

No cascade delete is permitted from business entities into ledger data. Customer closure is a status transition and PII erasure/anonymization workflow, not deletion of accounting records.

## 7. Transaction lifecycle

### 7.1 Internal transfer

1. Authenticate/authorize client; validate the `Idempotency-Key`, payload, currency, and positive amount.
2. Begin a database transaction with bounded deadlock retry.
3. Insert or lock the scoped idempotency row. Reject a hash mismatch; replay a completed response.
4. Create the payment transaction in `pending` state.
5. Lock all affected `account_balances` rows in globally sorted ledger-account-ID order.
6. Recheck wallet/account status, currency, limits, and source available funds under the locks.
7. Build the journal posting: debit source customer liability, credit destination customer liability.
8. Validate balanced entries, insert the posted journal and entries, update both balance projections and per-account sequences.
9. Mark the payment transaction `completed`; append audit and outbox records; complete the idempotency record with the canonical response snapshot.
10. Commit. Dispatch after-commit processing. Return `201 Created`.

Any exception rolls back all steps. A deadlock/serialization failure retries the whole closure with jitter a small bounded number of times. A business rejection is persisted only when replay semantics require it; otherwise the idempotency record can retain the deterministic error response in a short independent transaction.

### 7.2 Funding/withdrawal

External workflows are sagas, never long database transactions:

- Record an initiated payment and outbox command.
- A database-queued worker calls the provider with a stable provider idempotency key.
- Persist the provider result in a short transaction.
- Post money only at the explicitly defined provider milestone (for example, confirmed funding or accepted withdrawal), then emit an event.
- Timeouts remain `pending/unknown` and are queried/reconciled; never infer failure and retry with a new provider key.
- Later provider failure after local posting triggers a compensating reversal or an exception workflow.

### 7.3 Reversal

A reversal locks the original payment and affected balance rows, verifies reversibility and remaining refundable amount, then posts a new journal with every debit/credit inverted. It links `reversal_of_id`, records a new business reference, and transitions the payment state. The original rows remain untouched. Partial refunds are distinct payment transactions linked to the original and controlled by an aggregate refunded-total invariant.

## 8. Idempotency

All money-moving `POST` endpoints require `Idempotency-Key` (recommended opaque UUID, maximum 128 characters). Scope is the authenticated API client, not the user-supplied customer ID.

Canonical request hash inputs are HTTP method, normalized route identifier, content type/version, and canonicalized validated body. Volatile headers and JSON field order are excluded. Store a SHA-256 hash, not secrets.

Behavior:

- First request atomically claims the unique `(client_id, key)` row.
- Same key and same hash, completed: return the stored status/body and original resource ID with `Idempotent-Replayed: true`.
- Same key and different hash: `409 IDEMPOTENCY_KEY_REUSED`.
- Same key currently processing: wait briefly on the row lock, then replay if complete or return `409 REQUEST_IN_PROGRESS` with `Retry-After`.
- An expired processing lease is recoverable only after checking whether its resource/journal already exists. The unique business reference prevents a second posting.

The idempotency record participates in the same commit as an internal transfer. Provider calls use a separate stable key derived from payment ID and operation, allowing safe worker retries.

## 9. Concurrency and consistency

- Use `DB::transaction` and Eloquent `lockForUpdate()` on command paths.
- Lock balances and aggregate rows in deterministic ascending ID order to reduce deadlocks.
- Never make network calls, dispatch visible jobs, or send notifications while locks are held.
- Enforce uniqueness in the database; application prechecks improve messages but are not correctness controls.
- Use engine-default `READ COMMITTED` where configured consistently. Correctness comes from explicit row locks and constraints, not gap-lock behavior. Integration tests run against both MySQL and PostgreSQL.
- Retry only transient database errors (deadlocks, serialization failures, lock timeouts) and retry the entire transaction. Do not blindly retry validation or provider errors.
- Balance rows are created when accounts are provisioned, eliminating missing-row locking races.
- Holds lock the same balance row as postings. Authorization checks `posted - active pending debits` after locking.

Database constraints cannot portably enforce equality across multiple entry rows. Therefore the posting service is the sole writer, validates the complete journal before insertion, and persists journal plus entries atomically. Reconciliation independently proves the invariant and alerts on any breach.

## 10. Events, queues, and outbox

Domain events describe committed facts such as `TransferCompleted`, `FundsHeld`, `JournalPosted`, and `ReconciliationDiscrepancyDetected`. Laravel listeners translate them into notifications/read models.

For financially relevant asynchronous work, persist an `outbox_messages` row in the originating transaction. A scheduled dispatcher claims unpublished rows in small batches using row locks (and `SKIP LOCKED` only behind an engine-capability adapter), dispatches database queue jobs, and marks publication. Consumers are idempotent because delivery is at least once.

Laravel database queue is the only queue backend. Configure after-commit dispatch globally or per job, separate logical queues (`payments`, `outbox`, `reconciliation`, `notifications`), use bounded attempts/backoff/timeouts, and monitor failed jobs and oldest-job age. Queue jobs carry IDs, not serialized Eloquent graphs.

There is a small crash window between queue insertion and marking the outbox row published, so duplicate jobs are expected. Consumer deduplication and unique operation keys make this safe.

## 11. API design

Base path: `/api/v1`. JSON only, UTC RFC 3339 timestamps, UUID resource IDs, integer minor-unit amounts plus currency. Authenticate with Laravel Sanctum or hashed first-party API tokens; authorize every account/customer reference. Apply database-backed rate limiting conservatively because Redis is excluded.

### 11.1 Endpoints

| Method/path | Purpose |
|---|---|
| `POST /customers` | Create customer |
| `POST /customers/{id}/wallets` | Provision wallet/currency accounts |
| `GET /wallets/{id}` | Wallet summary and balances |
| `GET /wallets/{id}/statement` | Cursor-paginated immutable entries |
| `POST /transfers` | Atomic internal transfer |
| `GET /transactions/{id}` | Payment workflow status |
| `POST /transactions/{id}/reversals` | Full reversal |
| `POST /holds` | Reserve funds |
| `POST /holds/{id}/captures` | Capture held funds |
| `POST /holds/{id}/releases` | Release held funds |
| `POST /funding-intents` | Start external funding |
| `POST /withdrawals` | Start external withdrawal |
| `POST /webhooks/{provider}` | Signed provider callback |
| `GET /reconciliation-runs/{id}` | Operational reconciliation result |

Administrative ledger posting is not a general public endpoint. Controlled adjustments require a dedicated permission, reason code, maker-checker approval, and system-account allowlist.

Example transfer request:

```json
{
  "source_wallet_id": "uuid",
  "destination_wallet_id": "uuid",
  "amount": { "minor": 4000, "currency": "USD" },
  "reference": "merchant-order-123",
  "description": "Invoice payment"
}
```

Responses use a stable error envelope with `code`, `message`, `details`, `request_id`. Expected statuses include 201/202, 400, 401, 403, 404, 409, 422, and 429. Never expose account existence across authorization boundaries. Cursor pagination uses `(effective_at, account_sequence, id)` rather than offsets.

## 12. Auditability and reconciliation

### 12.1 Audit

Every privileged or financial command records actor, request/correlation IDs, subject, action, reason, and safe before/after state. Audit logs are append-only and hash chained (`entry_hash = SHA-256(prev_hash + canonical_record)`) to make tampering evident. This does not make the database independently tamper-proof; periodically export signed checkpoints to separate durable storage.

Application logs are structured and include IDs, latency, outcome, and exception class, but never credentials, full PII, or raw provider secrets. Metrics include posting latency, declines, lock retries, idempotency replays/conflicts, queue lag/failures, and reconciliation breaks.

### 12.2 Reconciliation layers

1. **Journal proof:** every journal has entries and balances by currency.
2. **Account proof:** rebuild each account from entries and compare with `account_balances` and entry sequences.
3. **Control-total proof:** sum customer liabilities and compare with corresponding control accounts/expected system position.
4. **Workflow proof:** completed payment operations have the expected journals; journals linked to payments use allowed operation/state combinations.
5. **External proof:** compare provider/bank settlement files or APIs against clearing accounts and provider attempts.

Runs use a cutoff/business date and immutable input checksum, produce totals and itemized discrepancies, and are safe to rerun. Reconciliation detects and reports; it never silently edits journals or balances. Resolution is either evidence-backed matching, a controlled compensating journal, or an operational explanation with approver.

## 13. Security and operational controls

- TLS everywhere; encrypt backups and sensitive columns; keys/secrets come from the deployment secret store.
- Least-privilege database roles, separate migration credentials, no production debug mode.
- Token hashes only; rotate credentials; explicit permissions for transfers, withdrawals, reconciliation, and adjustments.
- Signed webhook verification over the raw body, timestamp tolerance, replay protection, payload hash, and unique provider event ID.
- Per-transaction/daily limits and velocity rules are checked under appropriate locks; risk decisions are recorded.
- PII is separated from immutable financial descriptions. Ledger narration must not contain mutable or erasable PII.
- Dependency, static-analysis, secret, and vulnerability scanning in CI; protected branches and reviewed migrations.

## 14. Testing strategy

Use a layered suite:

- **Domain unit tests:** Money arithmetic, posting balance rules, state transitions, holds, reversals, limits, overflow, currency mismatch.
- **Application tests:** command handlers with fakes for providers/clock/IDs; event and outbox behavior.
- **Database integration tests:** actual MySQL and PostgreSQL containers, schema constraints, locks, deadlock retries, immutable triggers, account sequences, transaction rollback.
- **HTTP feature tests:** auth, authorization, validation, error contracts, idempotent replay/hash conflict/in-progress recovery.
- **Concurrency tests:** parallel transfers against one source, opposing transfers with deterministic locking, duplicate requests, hold-versus-transfer, reversal races. Assert no overdraft and exact journal totals.
- **Property/invariant tests:** generate operation sequences and prove journal balance, projection rebuild equality, conservation of value, and reversal neutrality.
- **Queue/provider tests:** duplicate and out-of-order webhooks, worker crashes, timeout/unknown results, retry with stable provider keys, outbox duplicate delivery.
- **Reconciliation tests:** seeded corruption is detected and never auto-mutated.
- **Migration compatibility tests:** migrate from empty and previous release, rollback only where safe, on both engines.

SQLite is acceptable for fast non-financial unit tests only; it must not be the confidence database for locking or constraint behavior. CI gates include formatting, PHPUnit, static analysis at a high level, dependency audit, and both database matrices.

## 15. Deployment and operations

### 15.1 Environments

Local uses MySQL with `QUEUE_CONNECTION=database` and database cache/session. CI tests MySQL and PostgreSQL. Laravel Cloud uses PostgreSQL, separate web and queue processes, and a scheduler running every minute. No Redis-dependent features are required.

### 15.2 Release process

1. Build a pinned, reproducible artifact and run all gates.
2. Back up and verify restore readiness for schema-affecting releases.
3. Run backward-compatible expand migrations with the migration role.
4. Deploy web and workers; restart workers gracefully so code versions converge.
5. Run smoke tests and journal/reconciliation canaries.
6. Later remove deprecated columns in a contract migration.

Financial tables avoid destructive/down migrations in production. Large indexes use engine-specific online/concurrent deployment runbooks rather than a generic transaction. Deployments have rollback procedures for application code and forward-fix procedures for schema.

### 15.3 Reliability

- Health endpoints distinguish liveness from database/queue readiness.
- Automated encrypted backups plus point-in-time recovery; scheduled restore drills define real RPO/RTO evidence.
- Alerts cover error rate, database saturation/locks, queue lag, failed jobs, webhook failures, stale pending transactions, reconciliation discrepancies, and backup failure.
- Scheduled commands expire holds, dispatch outbox messages, poll unknown provider attempts, and run reconciliation. Each scheduler task uses database-backed overlap prevention/advisory locking via a portable abstraction.
- Operational runbooks cover stuck payments, provider outage, duplicate webhook, reconciliation break, balance mismatch, key rotation, and disaster recovery.

## 16. Key decisions and rejected alternatives

| Decision | Rationale |
|---|---|
| Modular monolith first | Preserves a single ACID boundary and remains operationally credible for the project scale. |
| Journal is source of truth; balance is projection | Gives auditability and fast guarded authorization without trusting a mutable number alone. |
| Integer minor units | Exact, portable arithmetic; decimal/floating ambiguity is excluded. |
| Append-only reversals | Preserves history and supports financial audit. |
| Explicit pessimistic locks | Prevents concurrent overspend under both supported engines. |
| Transactional outbox plus DB queue | Prevents committed money changes from losing required asynchronous work; supports at-least-once delivery without Redis. |
| No event sourcing framework | The accounting journal is already an immutable financial record; reconstructing every workflow aggregate from events adds complexity without improving the core guarantee. |
| No microservices initially | Distributed consistency and operations would weaken the portfolio's core accounting guarantees. |

## 17. Delivery phases and acceptance gates

1. **Foundation:** module skeleton, IDs/Money, auth, dual-database CI, observability conventions.
2. **Ledger core:** chart of accounts, posting service, immutable journal, balance projection, proof/rebuild command. Gate: property and concurrency invariants pass on both engines.
3. **Wallets and transfers:** provisioning, idempotent transfer API, statements, reversals. Gate: duplicate/parallel request suite proves exactly-once financial effect.
4. **Holds and external workflows:** holds/capture/release, outbox, provider port, webhook inbox, database workers. Gate: crash/retry/out-of-order scenarios pass.
5. **Reconciliation and operations:** layered reconciliation, dashboards/alerts, audit checkpoints, runbooks, backup/restore drill.
6. **Cloud readiness:** PostgreSQL deployment rehearsal, expand/contract migration, worker/scheduler scaling, load and failure testing.

Production-readiness for this portfolio means invariants are executable tests, operational failure modes are demonstrated, and reconciliation can independently prove the ledger—not merely that endpoints return successful responses.

