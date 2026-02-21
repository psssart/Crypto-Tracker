<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('hash')->unique();
            $table->string('from_address');
            $table->string('to_address');
            $table->decimal('amount', 36, 18);
            $table->decimal('fee', 36, 18)->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->timestamp('mined_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
