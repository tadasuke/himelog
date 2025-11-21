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
        Schema::create('shop_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('お店の種類名');
            $table->integer('display_order')->default(0)->comment('表示順');
            $table->timestamps();
        });

        // 初期データをインサート
        $shopTypes = [
            ['name' => 'キャバクラ', 'display_order' => 1],
            ['name' => 'クラブ', 'display_order' => 2],
            ['name' => 'ガールズバー', 'display_order' => 3],
            ['name' => 'スナック', 'display_order' => 4],
            ['name' => 'パブ', 'display_order' => 5],
            ['name' => 'ラウンジ', 'display_order' => 6],
            ['name' => 'ホストクラブ', 'display_order' => 7],
            ['name' => 'デリヘル', 'display_order' => 8],
            ['name' => 'ソープランド', 'display_order' => 9],
            ['name' => 'ファッションヘルス', 'display_order' => 10],
            ['name' => 'ピンクサロン', 'display_order' => 11],
            ['name' => 'メンズエステ', 'display_order' => 12],
            ['name' => 'その他', 'display_order' => 99],
        ];

        foreach ($shopTypes as $shopType) {
            DB::table('shop_types')->insert([
                'name' => $shopType['name'],
                'display_order' => $shopType['display_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_types');
    }
};
