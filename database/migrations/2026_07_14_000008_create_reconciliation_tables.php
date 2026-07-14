<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->date('business_date');
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('expected_minor')->default(0);
            $table->unsignedBigInteger('ledger_minor')->default(0);
            $table->bigInteger('difference_minor')->default(0);
            $table->unsignedInteger('journal_count')->default(0);
            $table->unsignedInteger('invalid_journal_count')->default(0);
            $table->unsignedInteger('missing_journal_count')->default(0);
            $table->string('status', 20);
            $table->foreignId('generated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['business_date', 'currency_code']);
            $table->index(['status', 'business_date']);
        });

        Schema::create('reconciliation_discrepancies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('reconciliation_report_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('reference', 120)->nullable();
            $table->unsignedBigInteger('expected_minor')->default(0);
            $table->unsignedBigInteger('actual_minor')->default(0);
            $table->bigInteger('difference_minor')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('created_at');
            $table->index(['reconciliation_report_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_discrepancies');
        Schema::dropIfExists('reconciliation_reports');
    }
};
