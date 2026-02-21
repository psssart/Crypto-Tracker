<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_wallet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('custom_label')->nullable();
            $table->boolean('is_notified')->default(false);
            $table->decimal('notify_threshold_usd', 36, 18)->nullable();
            $table->string('notify_via')->default('email');
            $table->string('notify_direction')->default('all');
            $table->unsignedInteger('notify_cooldown_minutes')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'wallet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wallet');
    }
};
