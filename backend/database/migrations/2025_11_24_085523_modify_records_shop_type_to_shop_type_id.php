<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // shop_type_idカラムを追加（一時的にnullable）
        Schema::table('records', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_type_id')->nullable()->after('user_id')->comment('お店の種類ID');
        });

        // 既存データのshop_type（名前）をshop_type_id（数値）に変換
        $shopTypes = DB::table('shop_types')->pluck('id', 'name')->toArray();
        
        foreach ($shopTypes as $name => $id) {
            DB::table('records')
                ->where('shop_type', $name)
                ->update(['shop_type_id' => $id]);
        }

        // shop_type_idを非nullableに変更し、外部キー制約を追加
        Schema::table('records', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_type_id')->nullable(false)->change();
            $table->foreign('shop_type_id')
                ->references('id')
                ->on('shop_types')
                ->onDelete('restrict')
                ->onUpdate('cascade');
            $table->index('shop_type_id');
        });

        // shop_typeカラムを削除
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn('shop_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // shop_typeカラムを追加
        Schema::table('records', function (Blueprint $table) {
            $table->string('shop_type')->nullable()->after('user_id')->comment('お店の種類');
        });

        // shop_type_idからshop_type（名前）に変換
        $shopTypes = DB::table('shop_types')->pluck('name', 'id')->toArray();
        
        foreach ($shopTypes as $id => $name) {
            DB::table('records')
                ->where('shop_type_id', $id)
                ->update(['shop_type' => $name]);
        }

        // 外部キー制約とインデックスを削除してからshop_type_idカラムを削除
        Schema::table('records', function (Blueprint $table) {
            $table->dropForeign(['shop_type_id']);
            $table->dropIndex(['shop_type_id']);
            $table->dropColumn('shop_type_id');
        });
    }
};
