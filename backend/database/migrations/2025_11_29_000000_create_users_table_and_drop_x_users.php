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
        // 全ユーザ共通テーブル
        Schema::create('users', function (Blueprint $table) {
            // 内部的に保持するUUID（外部には直接公開しない想定）
            $table->uuid('id')->primary()->comment('Internal UUID (not exposed externally)');

            // 公開用に使用する可能性のあるUUID
            $table->uuid('public_uuid')->nullable()->unique()->comment('Public UUID (may be exposed externally)');

            // ログインのタイプ（認証プロバイダ）
            $table->string('provider', 50)->comment('Login provider (google, x, etc.)');

            // プロバイダ側のユーザID（例: Google sub, X user id）
            $table->string('provider_user_id')->comment('Provider user ID');

            // ユーザの基本情報（将来の拡張も考慮して保持）
            $table->string('name')->nullable()->comment('Display name');
            $table->string('email')->nullable()->comment('Email address');
            $table->string('username')->nullable()->comment('Username / handle');
            $table->string('avatar')->nullable()->comment('Avatar URL');

            // 最終検証日時（X API などの検証結果を保持するため）
            $table->timestamp('last_verified_at')->nullable()->comment('Last verification timestamp');

            // 新規登録日時・最終ログイン日時
            $table->timestamp('registered_at')->useCurrent()->comment('Registered at');
            $table->timestamp('last_login_at')->nullable()->comment('Last login at');

            // ユーザステータス（例: active, suspended など）
            $table->string('status', 20)->default('active')->comment('User status');

            $table->timestamps();

            // インデックス
            $table->unique(['provider', 'provider_user_id'], 'users_provider_user_unique');
            $table->index('status');
            $table->index('last_login_at');
        });

        // 既存の x_users テーブルを削除（存在する場合）
        if (Schema::hasTable('x_users')) {
            Schema::drop('x_users');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // users テーブルを削除
        Schema::dropIfExists('users');

        // x_users テーブルを元に戻す
        Schema::create('x_users', function (Blueprint $table) {
            $table->string('x_user_id')->primary()->comment('X User ID');
            $table->string('name')->nullable()->comment('表示名');
            $table->string('username')->nullable()->comment('ユーザー名（@username）');
            $table->string('avatar')->nullable()->comment('プロフィール画像URL');
            $table->timestamp('last_verified_at')->nullable()->comment('最終検証日時');
            $table->timestamps();

            // インデックス
            $table->index('last_verified_at');
        });
    }
};


