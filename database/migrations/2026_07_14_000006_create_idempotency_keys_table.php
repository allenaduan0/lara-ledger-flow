<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('key', 128);
            $table->char('request_hash', 64);
            $table->string('request_method', 10);
            $table->string('request_path', 255);
            $table->string('status', 20);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response')->nullable();
            $table->foreignUlid('transaction_id')->nullable()->constrained('transactions')->restrictOnDelete();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'key']);
            $table->index(['status', 'locked_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
