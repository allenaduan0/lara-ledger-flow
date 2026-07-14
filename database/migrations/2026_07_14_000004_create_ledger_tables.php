<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('wallet_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('code', 120)->unique();
            $table->string('name', 120);
            $table->string('type', 20);
            $table->string('normal_balance', 6);
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['currency_code', 'type', 'status']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('account_id')->constrained()->restrictOnDelete();
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('created_at');
            $table->index(['account_id', 'created_at']);
            $table->index(['ledger_transaction_id', 'direction']);
        });

        $now = now();
        foreach (DB::table('wallets')->select(['id', 'currency_code'])->orderBy('id')->get() as $wallet) {
            DB::table('accounts')->insert([
                'id' => (string) Str::ulid(),
                'wallet_id' => $wallet->id,
                'code' => "wallet:{$wallet->id}",
                'name' => "Wallet {$wallet->id}",
                'type' => 'liability',
                'normal_balance' => 'credit',
                'currency_code' => $wallet->currency_code,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->createImmutabilityTriggers();
    }

    public function down(): void
    {
        $this->dropImmutabilityTriggers();
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('accounts');
    }

    private function createImmutabilityTriggers(): void
    {
        match (DB::getDriverName()) {
            'mysql' => $this->createMySqlTriggers(),
            'pgsql' => $this->createPostgresTriggers(),
            'sqlite' => $this->createSqliteTriggers(),
            default => null,
        };
    }

    private function createMySqlTriggers(): void
    {
        DB::unprepared("CREATE TRIGGER ledger_entries_no_update BEFORE UPDATE ON ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger entries are immutable'");
        DB::unprepared("CREATE TRIGGER ledger_entries_no_delete BEFORE DELETE ON ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger entries are immutable'");
    }

    private function createPostgresTriggers(): void
    {
        DB::unprepared("CREATE OR REPLACE FUNCTION prevent_ledger_entry_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'Ledger entries are immutable'; END; $$ LANGUAGE plpgsql");
        DB::unprepared('CREATE TRIGGER ledger_entries_no_update BEFORE UPDATE ON ledger_entries FOR EACH ROW EXECUTE FUNCTION prevent_ledger_entry_mutation()');
        DB::unprepared('CREATE TRIGGER ledger_entries_no_delete BEFORE DELETE ON ledger_entries FOR EACH ROW EXECUTE FUNCTION prevent_ledger_entry_mutation()');
    }

    private function createSqliteTriggers(): void
    {
        DB::unprepared("CREATE TRIGGER ledger_entries_no_update BEFORE UPDATE ON ledger_entries BEGIN SELECT RAISE(ABORT, 'Ledger entries are immutable'); END");
        DB::unprepared("CREATE TRIGGER ledger_entries_no_delete BEFORE DELETE ON ledger_entries BEGIN SELECT RAISE(ABORT, 'Ledger entries are immutable'); END");
    }

    private function dropImmutabilityTriggers(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_no_update ON ledger_entries');
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_no_delete ON ledger_entries');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_ledger_entry_mutation()');
        }
    }
};
