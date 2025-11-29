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
        Schema::table('records', function (Blueprint $table) {
            // お店・ヒメのUUIDを保持するカラムを追加
            $table->uuid('shop_id')
                ->nullable()
                ->after('internal_user_id')
                ->comment('shopsテーブルのUUID');

            $table->uuid('girl_id')
                ->nullable()
                ->after('shop_id')
                ->comment('girlsテーブルのUUID');

            $table->index('shop_id');
            $table->index('girl_id');
        });

        // 外部キー制約を追加（存在する場合のみ）
        Schema::table('records', function (Blueprint $table) {
            if (Schema::hasTable('shops')) {
                $table->foreign('shop_id')
                    ->references('id')
                    ->on('shops')
                    ->onDelete('set null');
            }

            if (Schema::hasTable('girls')) {
                $table->foreign('girl_id')
                    ->references('id')
                    ->on('girls')
                    ->onDelete('set null');
            }
        });

        // 既存データをベストエフォートで紐付け
        if (Schema::hasTable('shops')) {
            DB::table('records')
                ->whereNull('shop_id')
                ->whereNotNull('shop_name')
                ->orderBy('created_at')
                ->chunkById(100, function ($recordsChunk) {
                    foreach ($recordsChunk as $record) {
                        $shopQuery = DB::table('shops')
                            ->where('shop_type_id', $record->shop_type_id)
                            ->where('shop_name', $record->shop_name);

                        // internal_user_id があればそれを優先して紐付け
                        if (!empty($record->internal_user_id) && Schema::hasColumn('shops', 'internal_user_id')) {
                            $shopQuery->where('internal_user_id', $record->internal_user_id);
                        } elseif (!empty($record->user_id)) {
                            $shopQuery->where('user_id', $record->user_id);
                        }

                        $shop = $shopQuery->orderBy('created_at')->first();

                        if ($shop) {
                            DB::table('records')
                                ->where('id', $record->id)
                                ->update(['shop_id' => $shop->id]);
                        }
                    }
                }, 'id');
        }

        if (Schema::hasTable('girls')) {
            DB::table('records')
                ->whereNull('girl_id')
                ->whereNotNull('girl_name')
                ->orderBy('created_at')
                ->chunkById(100, function ($recordsChunk) {
                    foreach ($recordsChunk as $record) {
                        $girlQuery = DB::table('girls')
                            ->where('girl_name', $record->girl_name);

                        if (!empty($record->internal_user_id) && Schema::hasColumn('girls', 'internal_user_id')) {
                            $girlQuery->where('internal_user_id', $record->internal_user_id);
                        } elseif (!empty($record->user_id)) {
                            $girlQuery->where('user_id', $record->user_id);
                        }

                        $girl = $girlQuery->orderBy('created_at')->first();

                        if ($girl) {
                            DB::table('records')
                                ->where('id', $record->id)
                                ->update(['girl_id' => $girl->id]);
                        }
                    }
                }, 'id');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            if (Schema::hasColumn('records', 'shop_id')) {
                $table->dropForeign(['shop_id']);
                $table->dropIndex(['shop_id']);
                $table->dropColumn('shop_id');
            }

            if (Schema::hasColumn('records', 'girl_id')) {
                $table->dropForeign(['girl_id']);
                $table->dropIndex(['girl_id']);
                $table->dropColumn('girl_id');
            }
        });
    }
};


