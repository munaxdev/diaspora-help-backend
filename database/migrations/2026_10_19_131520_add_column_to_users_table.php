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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('address')->nullable()->after('phone_number');
            $table->integer('user_type')->nullable()->after('address');
            $table->string('reset_token')->nullable()->after('password');
            $table->timestamp('reset_token_expires_at')->nullable()->after('reset_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'address', 'user_type', 'reset_token']);
        });
    }
};
