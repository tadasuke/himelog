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
        Schema::create('shops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id')->comment('Google User ID');
            $table->unsignedBigInteger('shop_type_id')->comment('お店の種類ID');
            $table->string('shop_name')->comment('お店の名前');
            $table->text('memo')->nullable()->comment('お店の感想');
            $table->timestamps();
            
            // インデックス
            $table->index('user_id');
            $table->index(['user_id', 'shop_type_id', 'shop_name']);
            
            // 外部キー制約
            $table->foreign('shop_type_id')->references('id')->on('shop_types')->onDelete('cascade');
            
            // ユニーク制約（同じユーザー、同じお店の種類、同じお店名の組み合わせは1つだけ）
            $table->unique(['user_id', 'shop_type_id', 'shop_name'], 'shops_user_shop_type_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
