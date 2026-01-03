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
            $table->text('public_review')->nullable()->after('review')->comment('公開用の感想');
            $table->boolean('public_include_shop_name')->default(false)->after('public_review')->comment('公開ページにお店の名前を含めるか');
            $table->boolean('public_include_girl_name')->default(false)->after('public_include_shop_name')->comment('公開ページにヒメの名前を含めるか');
            $table->boolean('public_include_course')->default(false)->after('public_include_girl_name')->comment('公開ページにコースを含めるか');
            $table->boolean('public_include_price')->default(false)->after('public_include_course')->comment('公開ページに料金を含めるか');
            $table->string('public_met_date')->nullable()->after('public_include_price')->comment('公開ページに表示する出会った日');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn([
                'public_review',
                'public_include_shop_name',
                'public_include_girl_name',
                'public_include_course',
                'public_include_price',
                'public_met_date',
            ]);
        });
    }
};
