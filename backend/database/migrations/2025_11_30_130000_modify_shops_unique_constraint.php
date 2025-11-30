<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 古いユニーク制約を削除（user_id、shop_type_id、shop_nameの組み合わせ）
        // 制約が存在する場合のみ削除
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();
        
        $constraintExists = $connection->selectOne(
            "SELECT COUNT(*) as count FROM information_schema.TABLE_CONSTRAINTS 
             WHERE CONSTRAINT_SCHEMA = ? 
             AND TABLE_NAME = 'shops' 
             AND CONSTRAINT_NAME = 'shops_user_shop_type_name_unique'",
            [$databaseName]
        );
        
        if ($constraintExists && $constraintExists->count > 0) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropUnique('shops_user_shop_type_name_unique');
            });
        }
        
        // 新しいユニーク制約を追加（internal_user_id、shop_type_id、shop_nameの組み合わせ）
        // 同じユーザー、同じお店の種類、同じお店名の組み合わせは1つだけ
        Schema::table('shops', function (Blueprint $table) {
            $table->unique(['internal_user_id', 'shop_type_id', 'shop_name'], 'shops_internal_user_shop_type_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // 新しいユニーク制約を削除
            $table->dropUnique('shops_internal_user_shop_type_name_unique');
        });
        
        Schema::table('shops', function (Blueprint $table) {
            // 古いユニーク制約を復元（user_idが存在する場合のみ）
            // ただし、user_idカラムは既に削除されているため、この復元は実行されない可能性が高い
            if (Schema::hasColumn('shops', 'user_id')) {
                $table->unique(['user_id', 'shop_type_id', 'shop_name'], 'shops_user_shop_type_name_unique');
            }
        });
    }
};
