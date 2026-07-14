<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('event_id', 180)->nullable()->unique()->after('id');
        });

        Schema::create('transaction_notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_id', 180)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->string('type', 80);
            $table->json('payload');
            $table->timestamp('delivered_at');
            $table->timestamps();
            $table->index(['user_id', 'delivered_at']);
        });

        Schema::create('statement_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_id', 180)->unique();
            $table->foreignUlid('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->foreignUlid('ledger_entry_id')->unique()->constrained('ledger_entries')->restrictOnDelete();
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['wallet_id', 'occurred_at']);
        });

        Schema::create('reporting_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->date('business_date');
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('transactions_created')->default(0);
            $table->unsignedBigInteger('transfers_completed')->default(0);
            $table->unsignedBigInteger('transfers_failed')->default(0);
            $table->unsignedBigInteger('transferred_minor')->default(0);
            $table->timestamps();
            $table->unique(['business_date', 'currency_code']);
        });

        Schema::create('processed_reporting_events', function (Blueprint $table): void {
            $table->string('event_id', 180)->primary();
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_reporting_events');
        Schema::dropIfExists('reporting_daily_metrics');
        Schema::dropIfExists('statement_entries');
        Schema::dropIfExists('transaction_notifications');
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropUnique(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
