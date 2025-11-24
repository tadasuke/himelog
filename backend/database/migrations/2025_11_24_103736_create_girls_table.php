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
        Schema::create('girls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id')->comment('Google User ID');
            $table->string('girl_name')->comment('ヒメ（女の子）の名前');
            $table->text('memo')->nullable()->comment('ヒメの感想');
            $table->timestamps();
            
            // インデックス
            $table->index('user_id');
            $table->index(['user_id', 'girl_name']);
            
            // ユニーク制約（同じユーザー、同じヒメ名の組み合わせは1つだけ）
            $table->unique(['user_id', 'girl_name'], 'girls_user_girl_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('girls');
    }
};
