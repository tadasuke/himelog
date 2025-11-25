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
        Schema::create('x_users', function (Blueprint $table) {
            $table->string('x_user_id')->primary()->comment('X User ID');
            $table->string('name')->nullable()->comment('表示名');
            $table->string('username')->nullable()->comment('ユーザー名（@username）');
            $table->string('avatar')->nullable()->comment('プロフィール画像URL');
            $table->timestamp('last_verified_at')->nullable()->comment('最終検証日時');
            $table->timestamps();
            
            // インデックス
            $table->index('last_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_users');
    }
};
