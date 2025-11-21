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
        Schema::create('records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id')->comment('Google User ID');
            $table->string('shop_type')->comment('お店の種類');
            $table->string('shop_name')->comment('お店の名前');
            $table->string('girl_name')->comment('女の子の名前');
            $table->tinyInteger('rating')->unsigned()->comment('評価（1-5）');
            $table->text('review')->nullable()->comment('感想');
            $table->timestamps();
            
            // インデックス
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
