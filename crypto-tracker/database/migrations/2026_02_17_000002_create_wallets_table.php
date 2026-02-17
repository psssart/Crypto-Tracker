<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->string('address');
            $table->boolean('is_whale')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->decimal('balance_usd', 36, 18)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['network_id', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
