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
        Schema::create('girl_urls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('girl_id')->comment('ヒメID');
            $table->string('url')->comment('URL');
            $table->integer('display_order')->default(0)->comment('表示順');
            $table->timestamps();
            
            // インデックス
            $table->index('girl_id');
            
            // 外部キー制約
            $table->foreign('girl_id')->references('id')->on('girls')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('girl_urls');
    }
};
