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
        Schema::table('records', function (Blueprint $table) {
            // 既存のユニークインデックスを削除
            $table->dropUnique(['public_token']);
        });
        
        Schema::table('records', function (Blueprint $table) {
            // public_tokenカラムのサイズを4文字に変更
            $table->string('public_token', 4)->nullable()->change();
        });
        
        Schema::table('records', function (Blueprint $table) {
            // ユニークインデックスを再作成
            $table->unique('public_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            // 既存のユニークインデックスを削除
            $table->dropUnique(['public_token']);
        });
        
        Schema::table('records', function (Blueprint $table) {
            // 元の64文字に戻す
            $table->string('public_token', 64)->nullable()->change();
        });
        
        Schema::table('records', function (Blueprint $table) {
            // ユニークインデックスを再作成
            $table->unique('public_token');
        });
    }
};
