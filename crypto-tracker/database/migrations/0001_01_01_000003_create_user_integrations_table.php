<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_integrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider'); // e.g. 'github', 'openai', 'stripe'

            // Store the API key/token here (will be encrypted at model level)
            $table->text('api_key')->nullable();

            // Arbitrary per-integration settings (PostgreSQL jsonb)
            $table->jsonb('settings')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // One row per user + provider combination
            $table->unique(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_integrations');
    }
};
