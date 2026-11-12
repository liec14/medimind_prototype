<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('patient')->index()->after('password');
            $table->string('phone', 30)->nullable()->after('role');
            $table->date('birthdate')->nullable()->after('phone');
            $table->string('license_no', 50)->nullable()->after('birthdate');
            $table->timestamp('approved_at')->nullable()->after('license_no');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'birthdate', 'license_no', 'approved_at']);
        });
    }
};
