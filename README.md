# ヒメログ プロトタイプ

React（Vite）+ Laravel API で構築されたヒメログサービスのプロトタイプです。

## プロジェクト構成

- `frontend/` - React + Vite フロントエンド
- `backend/` - Laravel 11 バックエンド API

## セットアップ手順

### 1. フロントエンドのセットアップ

```bash
cd frontend
npm install
npm run dev
```

フロントエンドは `http://localhost:5173` で起動します。

### 2. データベースのセットアップ（MySQL on Docker）

#### Docker Desktop の起動

macOS の場合、Docker Desktop を起動してください：

1. **Docker Desktop を起動**
   - アプリケーションから Docker Desktop を起動
   - または、ターミナルから: `open -a Docker`

2. **Docker が起動しているか確認**
   ```bash
   docker ps
   ```

#### MySQL の起動

```bash
# Docker ComposeでMySQLとphpMyAdminを起動
docker-compose up -d

# コンテナが起動しているか確認
docker-compose ps

# ログを確認
docker-compose logs mysql
```

- MySQLは `localhost:3306` で起動します
- phpMyAdminは `http://localhost:8080` でアクセスできます

#### phpMyAdmin でのログイン

1. ブラウザで `http://localhost:8080` にアクセス
2. 以下の情報でログイン：
   - **サーバー**: `mysql` (または `himelog_mysql`)
   - **ユーザー名**: `himelog_user`
   - **パスワード**: `himelog_password`

または、rootユーザーでログイン：
   - **ユーザー名**: `root`
   - **パスワード**: `rootpassword`

**注意**: Docker Desktop が起動していない場合は、上記のコマンドでエラーが発生します。

### 3. バックエンドのセットアップ

#### Composer のインストール（未インストールの場合）

macOS の場合、Homebrew を使用してインストールできます：

```bash
brew install composer
```

#### Laravel のセットアップ

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

#### データベース設定（`backend/.env`）

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=himelog
DB_USERNAME=himelog_user
DB_PASSWORD=himelog_password
```

#### マイグレーションの実行

```bash
cd backend
php artisan migrate
```

#### サーバーの起動

```bash
php artisan serve
```

バックエンドは `http://localhost:8000` で起動します。

**注意**: 初回セットアップ時は `composer install` で依存関係をインストールする必要があります。

## データベース

### ログイン履歴テーブル

ログイン成功時に、以下の情報が `login_histories` テーブルに自動的に保存されます：

- `id` (UUID主キー)
- `user_id` (Google User ID)
- `user_email` (ユーザーメールアドレス)
- `user_name` (ユーザー名)
- `ip_address` (IPアドレス)
- `user_agent` (ユーザーエージェント)
- `logged_in_at` (ログイン日時)
- `created_at`, `updated_at` (タイムスタンプ)

## Google OAuth の設定

### 1. Google Cloud Console でプロジェクトを作成

1. [Google Cloud Console](https://console.cloud.google.com/) にアクセス
2. 新しいプロジェクトを作成
3. 「APIとサービス」→「認証情報」に移動
4. 「認証情報を作成」→「OAuth クライアント ID」を選択
5. アプリケーションの種類を「ウェブアプリケーション」に設定

### 2. 重要な設定項目

#### 承認済みの JavaScript 生成元（必須）
以下のオリジンを追加してください：
- `http://localhost:5173`
- `http://127.0.0.1:5173`

**注意**: この設定がないと「no registered origin」エラーが発生します。

#### 承認済みのリダイレクト URI
以下のURIを追加してください：
- `http://localhost:8000/api/auth/google/callback`
- `http://localhost:5173` (開発環境用)

### 3. 環境変数の設定

#### バックエンド（`backend/.env`）

```env
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

#### フロントエンド（`frontend/.env`）

```env
VITE_GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
```

**注意**: フロントエンドとバックエンドで同じクライアントIDを使用するか、別々のクライアントIDを作成してください。

## 機能

- ログイン画面（Google 認証）
- Google OAuth 認証 API (`/api/auth/google/login`)
- ログイン後のホーム画面（ユーザーID・名前・メールアドレス表示）
- ログ一覧表示
- ログイン履歴の自動保存（MySQL）

## デザイン

- ダークモード UI
- カラーテーマ：
  - 背景: `#0d0f14`
  - カード背景: `#161a22`
  - アクセント: `#e86aff`
  - サブアクセント: `#6f8cff`
  - 文字色: `#e8e8e8`

## 開発メモ

- ログイン状態は React の内部状態で管理
- Google Identity Services (GIS) を使用した Google OAuth 認証を実装
- Laravel Socialite を使用してバックエンドで認証を処理
- ユーザー情報（ID、名前、メールアドレス、アバター）を表示
- Cookie やセッション管理は未実装（トークンベースの認証）

