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
        Schema::create('shop_urls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shop_id')->comment('お店ID');
            $table->string('url')->comment('URL');
            $table->integer('display_order')->default(0)->comment('表示順');
            $table->timestamps();
            
            // インデックス
            $table->index('shop_id');
            
            // 外部キー制約
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_urls');
    }
};
