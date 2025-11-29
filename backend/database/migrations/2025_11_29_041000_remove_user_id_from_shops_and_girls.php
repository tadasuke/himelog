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
        // shops テーブルの user_id を削除
        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'user_id')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }

        // girls テーブルの user_id を削除
        if (Schema::hasTable('girls') && Schema::hasColumn('girls', 'user_id')) {
            Schema::table('girls', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // shops テーブルに user_id を復元（必要であれば）
        if (Schema::hasTable('shops') && ! Schema::hasColumn('shops', 'user_id')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->string('user_id')->nullable()->after('id')->comment('Deprecated user identifier');
            });
        }

        // girls テーブルに user_id を復元（必要であれば）
        if (Schema::hasTable('girls') && ! Schema::hasColumn('girls', 'user_id')) {
            Schema::table('girls', function (Blueprint $table) {
                $table->string('user_id')->nullable()->after('id')->comment('Deprecated user identifier');
            });
        }
    }
};


