<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->char('code', 3)->primary();
            $table->string('name', 80);
            $table->unsignedTinyInteger('minor_unit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->string('name', 100)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('frozen_at')->nullable();
            $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('freeze_reason', 255)->nullable();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['user_id', 'currency_code']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('wallet_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('wallet_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('per_transaction_minor')->nullable();
            $table->unsignedBigInteger('daily_outgoing_minor')->nullable();
            $table->unsignedBigInteger('monthly_outgoing_minor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_limits');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('currencies');
    }
};
