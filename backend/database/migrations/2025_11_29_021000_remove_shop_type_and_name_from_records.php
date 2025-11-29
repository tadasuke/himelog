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
            // 既にshop_idでshopsテーブルと紐付くため、冗長になったカラムを削除
            if (Schema::hasColumn('records', 'shop_type_id')) {
                $table->dropForeign(['shop_type_id']);
                $table->dropIndex(['shop_type_id']);
                $table->dropColumn('shop_type_id');
            }

            if (Schema::hasColumn('records', 'shop_name')) {
                $table->dropColumn('shop_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            // 復元用に最低限の情報でshop_type_idとshop_nameを戻す
            if (!Schema::hasColumn('records', 'shop_type_id')) {
                $table->unsignedBigInteger('shop_type_id')->nullable()->after('user_id')->comment('お店の種類ID');
                $table->index('shop_type_id');
            }

            if (!Schema::hasColumn('records', 'shop_name')) {
                $table->string('shop_name')->nullable()->after('shop_type_id')->comment('お店の名前');
            }
        });
    }
};


