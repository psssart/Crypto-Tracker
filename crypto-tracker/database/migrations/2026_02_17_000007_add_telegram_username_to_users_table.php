<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('chat_id')->unique();
            $table->string('telegram_id')->nullable();
            $table->string('username')->nullable();
            $table->string('type')->default('private');
            $table->timestamps();
        });

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_chat_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->text('text')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_chats');
    }
};
