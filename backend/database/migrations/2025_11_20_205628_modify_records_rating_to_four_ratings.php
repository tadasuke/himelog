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
            // 既存のratingカラムを削除
            $table->dropColumn('rating');
            
            // 4つの評価カラムを追加
            $table->tinyInteger('face_rating')->unsigned()->nullable()->after('visit_date')->comment('顔の評価（1-10）');
            $table->tinyInteger('style_rating')->unsigned()->nullable()->after('face_rating')->comment('スタイルの評価（1-10）');
            $table->tinyInteger('service_rating')->unsigned()->nullable()->after('style_rating')->comment('接客の評価（1-10）');
            $table->tinyInteger('overall_rating')->unsigned()->nullable()->after('service_rating')->comment('総合の評価（1-10）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            // 4つの評価カラムを削除
            $table->dropColumn(['face_rating', 'style_rating', 'service_rating', 'overall_rating']);
            
            // 元のratingカラムを復元
            $table->tinyInteger('rating')->unsigned()->after('visit_date')->comment('評価（1-10）');
        });
    }
};



