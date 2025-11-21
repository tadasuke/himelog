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
        Schema::create('login_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id')->comment('Google User ID');
            $table->string('user_email')->nullable()->comment('User Email');
            $table->string('user_name')->nullable()->comment('User Name');
            $table->string('ip_address', 45)->nullable()->comment('IP Address');
            $table->string('user_agent')->nullable()->comment('User Agent');
            $table->timestamp('logged_in_at')->useCurrent()->comment('Login Timestamp');
            $table->timestamps();
            
            // インデックス
            $table->index('user_id');
            $table->index('logged_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
