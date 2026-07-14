<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->foreignUlid('source_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->foreignUlid('destination_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reference', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->foreignUlid('ledger_transaction_id')->nullable()->unique()->constrained('ledger_transactions')->restrictOnDelete();
            $table->ulid('refunded_transaction_id')->nullable()->unique();
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['initiated_by', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreign('refunded_transaction_id')->references('id')->on('transactions')->restrictOnDelete();
        });

        Schema::create('transaction_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at');
            $table->index(['transaction_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100);
            $table->string('subject_id', 64);
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('transaction_status_history');
        Schema::dropIfExists('transactions');
    }
};
