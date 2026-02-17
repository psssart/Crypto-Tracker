<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_wallet', function (Blueprint $table) {
            $table->string('notify_direction')->default('all');
            $table->unsignedInteger('notify_cooldown_minutes')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_wallet', function (Blueprint $table) {
            $table->dropColumn(['notify_direction', 'notify_cooldown_minutes', 'last_notified_at', 'notes']);
        });
    }
};
