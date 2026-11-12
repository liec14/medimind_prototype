<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dose_logs', function (Blueprint $table) {
            $table->dateTime('reminded_at')->nullable()->after('confirmed_at');
            $table->dateTime('snoozed_until')->nullable()->after('reminded_at');
        });

        // Laravel's database notification channel (the in-app inbox).
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('dose_logs', function (Blueprint $table) {
            $table->dropColumn(['reminded_at', 'snoozed_until']);
        });
    }
};
