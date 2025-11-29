<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 読み込み性能を向上させるため、よく使われるクエリパターンに基づいてINDEXを追加
     */
    public function up(): void
    {
        // recordsテーブル - 最もアクセス頻度が高いテーブル
        if (Schema::hasTable('records')) {
            Schema::table('records', function (Blueprint $table) {
                // 一覧取得時のソート用（created_at降順）
                $table->index(['internal_user_id', 'created_at'], 'records_user_created_idx');
                
                // 日付ソート用（visit_date, created_at降順）
                $table->index(['internal_user_id', 'visit_date', 'created_at'], 'records_user_visit_created_idx');
                
                // ランキング用（overall_rating降順）
                $table->index(['internal_user_id', 'overall_rating', 'created_at'], 'records_user_overall_rating_idx');
                
                // ランキング用（face_rating降順）
                $table->index(['internal_user_id', 'face_rating', 'created_at'], 'records_user_face_rating_idx');
                
                // ランキング用（style_rating降順）
                $table->index(['internal_user_id', 'style_rating', 'created_at'], 'records_user_style_rating_idx');
                
                // ランキング用（service_rating降順）
                $table->index(['internal_user_id', 'service_rating', 'created_at'], 'records_user_service_rating_idx');
                
                // 日付フィルタリング用
                $table->index(['internal_user_id', 'visit_date'], 'records_user_visit_date_idx');
                
                // 評価フィルタリング用
                $table->index(['internal_user_id', 'overall_rating'], 'records_user_overall_rating_filter_idx');
                
                // ソフトデリート用（deleted_atがnullのレコードを効率的に取得）
                if (Schema::hasColumn('records', 'deleted_at')) {
                    $table->index(['internal_user_id', 'deleted_at'], 'records_user_deleted_idx');
                }
            });
        }

        // shopsテーブル
        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table) {
                // お店の種類で検索する際に使用
                $table->index(['internal_user_id', 'shop_type_id'], 'shops_user_type_idx');
            });
        }

        // girlsテーブル
        if (Schema::hasTable('girls')) {
            Schema::table('girls', function (Blueprint $table) {
                // お店に紐づくヒメを検索する際に使用
                $table->index(['internal_user_id', 'shop_id'], 'girls_user_shop_idx');
            });
        }

        // girl_image_urlsテーブル - 画像の表示順で取得
        if (Schema::hasTable('girl_image_urls')) {
            Schema::table('girl_image_urls', function (Blueprint $table) {
                // 画像を表示順で取得する際に使用
                $table->index(['girl_id', 'display_order'], 'girl_image_urls_girl_display_order_idx');
            });
        }

        // shop_urlsテーブル - URLの表示順で取得
        if (Schema::hasTable('shop_urls')) {
            Schema::table('shop_urls', function (Blueprint $table) {
                // URLを表示順で取得する際に使用
                $table->index(['shop_id', 'display_order'], 'shop_urls_shop_display_order_idx');
            });
        }

        // girl_urlsテーブル - URLの表示順で取得
        if (Schema::hasTable('girl_urls')) {
            Schema::table('girl_urls', function (Blueprint $table) {
                // URLを表示順で取得する際に使用
                $table->index(['girl_id', 'display_order'], 'girl_urls_girl_display_order_idx');
            });
        }

        // login_historiesテーブル
        if (Schema::hasTable('login_histories')) {
            Schema::table('login_histories', function (Blueprint $table) {
                // ログイン履歴を日付順で取得する際に使用
                if (Schema::hasColumn('login_histories', 'internal_user_id')) {
                    $table->index(['internal_user_id', 'logged_in_at'], 'login_histories_user_logged_in_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // recordsテーブル
        if (Schema::hasTable('records')) {
            Schema::table('records', function (Blueprint $table) {
                $table->dropIndex('records_user_created_idx');
                $table->dropIndex('records_user_visit_created_idx');
                $table->dropIndex('records_user_overall_rating_idx');
                $table->dropIndex('records_user_face_rating_idx');
                $table->dropIndex('records_user_style_rating_idx');
                $table->dropIndex('records_user_service_rating_idx');
                $table->dropIndex('records_user_visit_date_idx');
                $table->dropIndex('records_user_overall_rating_filter_idx');
                if (Schema::hasColumn('records', 'deleted_at')) {
                    $table->dropIndex('records_user_deleted_idx');
                }
            });
        }

        // shopsテーブル
        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropIndex('shops_user_type_idx');
            });
        }

        // girlsテーブル
        if (Schema::hasTable('girls')) {
            Schema::table('girls', function (Blueprint $table) {
                $table->dropIndex('girls_user_shop_idx');
            });
        }

        // girl_image_urlsテーブル
        if (Schema::hasTable('girl_image_urls')) {
            Schema::table('girl_image_urls', function (Blueprint $table) {
                $table->dropIndex('girl_image_urls_girl_display_order_idx');
            });
        }

        // shop_urlsテーブル
        if (Schema::hasTable('shop_urls')) {
            Schema::table('shop_urls', function (Blueprint $table) {
                $table->dropIndex('shop_urls_shop_display_order_idx');
            });
        }

        // girl_urlsテーブル
        if (Schema::hasTable('girl_urls')) {
            Schema::table('girl_urls', function (Blueprint $table) {
                $table->dropIndex('girl_urls_girl_display_order_idx');
            });
        }

        // login_historiesテーブル
        if (Schema::hasTable('login_histories')) {
            Schema::table('login_histories', function (Blueprint $table) {
                if (Schema::hasColumn('login_histories', 'internal_user_id')) {
                    $table->dropIndex('login_histories_user_logged_in_idx');
                }
            });
        }
    }
};
