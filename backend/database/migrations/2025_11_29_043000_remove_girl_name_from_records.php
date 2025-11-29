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
            // girl_nameカラムを削除（girlsテーブルを参照するため）
            $table->dropColumn('girl_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            // girl_nameカラムを復元（nullable）
            $table->string('girl_name')->nullable()->after('girl_id');
        });
    }
};

