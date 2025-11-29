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
        Schema::table('girls', function (Blueprint $table) {
            // shop_idカラムを追加（UUID、nullable、外部キー制約）
            $table->uuid('shop_id')->nullable()->after('internal_user_id');
            $table->index('shop_id');
            
            // 外部キー制約を追加（shopsテーブルを参照）
            $table->foreign('shop_id')
                ->references('id')
                ->on('shops')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('girls', function (Blueprint $table) {
            // 外部キー制約を削除
            $table->dropForeign(['shop_id']);
            // インデックスを削除
            $table->dropIndex(['shop_id']);
            // shop_idカラムを削除
            $table->dropColumn('shop_id');
        });
    }
};

