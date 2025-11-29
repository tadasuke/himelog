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
        // records テーブルに内部ユーザUUIDカラムを追加
        Schema::table('records', function (Blueprint $table) {
            $table->uuid('internal_user_id')
                ->nullable()
                ->after('user_id')
                ->comment('Internal user UUID (users.id)');

            $table->index('internal_user_id');
        });

        // shops テーブルに内部ユーザUUIDカラムを追加
        Schema::table('shops', function (Blueprint $table) {
            $table->uuid('internal_user_id')
                ->nullable()
                ->after('user_id')
                ->comment('Internal user UUID (users.id)');

            $table->index('internal_user_id');
        });

        // girls テーブルに内部ユーザUUIDカラムを追加
        Schema::table('girls', function (Blueprint $table) {
            $table->uuid('internal_user_id')
                ->nullable()
                ->after('user_id')
                ->comment('Internal user UUID (users.id)');

            $table->index('internal_user_id');
        });

        // login_histories テーブルに内部ユーザUUIDカラムを追加
        Schema::table('login_histories', function (Blueprint $table) {
            $table->uuid('internal_user_id')
                ->nullable()
                ->after('user_id')
                ->comment('Internal user UUID (users.id)');

            $table->index('internal_user_id');
        });

        // 既存データを users テーブルと紐付け（ベストエフォート）
        if (Schema::hasTable('users')) {
            // records
            DB::table('records')
                ->whereNull('internal_user_id')
                ->whereNotNull('user_id')
                ->orderBy('created_at')
                ->chunkById(100, function ($recordsChunk) {
                    foreach ($recordsChunk as $record) {
                        $user = DB::table('users')
                            ->where('provider_user_id', $record->user_id)
                            ->orderBy('created_at')
                            ->first();

                        if ($user) {
                            DB::table('records')
                                ->where('id', $record->id)
                                ->update(['internal_user_id' => $user->id]);
                        }
                    }
                }, 'id');

            // shops
            DB::table('shops')
                ->whereNull('internal_user_id')
                ->whereNotNull('user_id')
                ->orderBy('created_at')
                ->chunkById(100, function ($shopsChunk) {
                    foreach ($shopsChunk as $shop) {
                        $user = DB::table('users')
                            ->where('provider_user_id', $shop->user_id)
                            ->orderBy('created_at')
                            ->first();

                        if ($user) {
                            DB::table('shops')
                                ->where('id', $shop->id)
                                ->update(['internal_user_id' => $user->id]);
                        }
                    }
                }, 'id');

            // girls
            DB::table('girls')
                ->whereNull('internal_user_id')
                ->whereNotNull('user_id')
                ->orderBy('created_at')
                ->chunkById(100, function ($girlsChunk) {
                    foreach ($girlsChunk as $girl) {
                        $user = DB::table('users')
                            ->where('provider_user_id', $girl->user_id)
                            ->orderBy('created_at')
                            ->first();

                        if ($user) {
                            DB::table('girls')
                                ->where('id', $girl->id)
                                ->update(['internal_user_id' => $user->id]);
                        }
                    }
                }, 'id');

            // login_histories
            DB::table('login_histories')
                ->whereNull('internal_user_id')
                ->whereNotNull('user_id')
                ->orderBy('created_at')
                ->chunkById(100, function ($historiesChunk) {
                    foreach ($historiesChunk as $history) {
                        $user = DB::table('users')
                            ->where('provider_user_id', $history->user_id)
                            ->orderBy('created_at')
                            ->first();

                        if ($user) {
                            DB::table('login_histories')
                                ->where('id', $history->id)
                                ->update(['internal_user_id' => $user->id]);
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
            if (Schema::hasColumn('records', 'internal_user_id')) {
                $table->dropIndex(['internal_user_id']);
                $table->dropColumn('internal_user_id');
            }
        });

        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'internal_user_id')) {
                $table->dropIndex(['internal_user_id']);
                $table->dropColumn('internal_user_id');
            }
        });

        Schema::table('girls', function (Blueprint $table) {
            if (Schema::hasColumn('girls', 'internal_user_id')) {
                $table->dropIndex(['internal_user_id']);
                $table->dropColumn('internal_user_id');
            }
        });

        Schema::table('login_histories', function (Blueprint $table) {
            if (Schema::hasColumn('login_histories', 'internal_user_id')) {
                $table->dropIndex(['internal_user_id']);
                $table->dropColumn('internal_user_id');
            }
        });
    }
};


