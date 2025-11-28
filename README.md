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

**注意**: この設定がないと「no registered origin」エラーや403エラーが発生します。

#### 承認済みのリダイレクト URI
以下のURIを追加してください：
- `http://localhost:8000/api/auth/google/callback` (旧方式用)
- `http://localhost:5173` (開発環境用)
- `http://localhost:8000/login/callback.html` (静的ログインページ用)
- 本番環境の場合: `https://yourdomain.com/login/callback.html`

### 2-1. 403エラーの解決方法

Googleログインボタンで403エラーが発生する場合、以下の手順を確認してください：

1. **Google Cloud Consoleで「承認済みのJavaScript生成元」を確認**
   - [Google Cloud Console](https://console.cloud.google.com/) → 「APIとサービス」→「認証情報」
   - OAuth 2.0 クライアント IDを選択
   - 「承認済みのJavaScript生成元」に現在のドメインが追加されているか確認
   - 開発環境の場合: `http://localhost:5173` と `http://127.0.0.1:5173` を追加
   - 本番環境の場合: 実際のドメイン（例: `https://yourdomain.com`）を追加

2. **OAuth同意画面の設定を確認**
   - 「OAuth同意画面」でアプリケーション情報が正しく設定されているか確認
   - テストユーザーが追加されているか確認（テストモードの場合）

3. **ブラウザのキャッシュとCookieをクリア**
   - ブラウザのキャッシュとCookieを削除して再試行

4. **複数のGoogleアカウントにログインしている場合**
   - すべてのGoogleアカウントからログアウトし、再度目的のアカウントでログイン

5. **ブラウザのコンソールでエラー詳細を確認**
   - 開発者ツール（F12）を開き、コンソールタブでエラーメッセージを確認

### 3. 環境変数の設定

バックエンドの `.env` ファイルに以下を追加：

```env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

### 3-1. 静的ログインページの設定（別タブが開かないようにするための設定）

静的ログインページを使用する場合、以下の設定が必要です：

#### 設定ファイルの作成

`frontend/public/login/config.js` ファイルを作成し、以下の内容を記述：

```javascript
// Google Client ID
window.GOOGLE_CLIENT_ID = 'your-google-client-id-here';

// X Client ID  
window.X_CLIENT_ID = 'your-x-client-id-here';

// ReactアプリのURL（認証成功後のリダイレクト先）
// 同じドメインなので相対パスを使用
window.REACT_APP_URL = '/';
```

または、`frontend/public/login/config.js.example` を `config.js` にコピーして編集してください。

**注意**: `config.js` ファイルは `.gitignore` に追加することを推奨します（機密情報を含むため）。

#### 静的ログインページへのアクセス

静的ログインページは `/login/index.html` でアクセスできます。

- 開発環境: `http://localhost:5173/login/index.html` (Viteの開発サーバーで配信)
- 本番環境: `https://yourdomain.com/login/index.html` (ビルド後のReactアプリと一緒に配信)

#### Google認証のリダイレクトURI設定

静的ログインページを使用する場合、Google Cloud Consoleの「承認済みのリダイレクト URI」に以下を追加：

- `http://localhost:5173/login/callback.html` (開発環境)
- `https://yourdomain.com/login/callback.html` (本番環境)

**重要なポイント**:
- 静的ログインページとReactアプリは同じドメイン（`http://localhost:5173`）で動作します
- これにより、`sessionStorage`や`localStorage`を共有できるため、認証情報の引き継ぎが簡単になります
- 静的ログインページを使用すると、Google認証時に別タブが開かなくなります（現在のタブでリダイレクトされます）
- 認証成功後、自動的にReactアプリ（`/`）にリダイレクトされます
- 認証情報はセッションストレージからローカルストレージに自動的に移行されます

## サーバー側の初回セットアップ（EC2）

EC2サーバーで初回のみ実行する必要があるコマンドです。

### 前提条件

- EC2インスタンスにSSHまたはSSMでアクセスできること
- デプロイパス: `/mf/himelog-api`

### 接続方法

#### 方法1: SSHで接続（推奨）

**SSH鍵を使用した接続：**

```bash
# SSH鍵のパスとEC2インスタンスのパブリックIPまたはDNS名を指定
ssh -i /path/to/your-key.pem ec2-user@<EC2_PUBLIC_IP_OR_DNS>

# 例: パブリックIPが 54.250.xxx.xxx の場合
ssh -i ~/.ssh/himelog-key.pem ec2-user@54.250.xxx.xxx

# 例: パブリックDNS名を使用する場合
ssh -i ~/.ssh/himelog-key.pem ec2-user@ec2-54-250-xxx-xxx.ap-northeast-1.compute.amazonaws.com
```

**注意事項：**
- Amazon Linux 2023の場合、デフォルトユーザーは `ec2-user` です
- SSH鍵のパーミッションは `600` に設定してください：`chmod 600 /path/to/your-key.pem`
- セキュリティグループでSSH（ポート22）が許可されていることを確認してください

**EC2 Instance Connectを使用する場合：**

1. AWSコンソールでEC2インスタンスを選択
2. 「接続」ボタンをクリック
3. 「EC2 Instance Connect」タブを選択
4. 「接続」をクリックしてブラウザ内のターミナルを開く

#### 方法2: SSM Session Managerで接続

```bash
# AWS CLIでSSM Session Managerを使用して接続
aws ssm start-session --target i-0c78bb1a7c6be7fa9

# または、SSM経由でポートフォワーディングしてSSH接続
aws ssm start-session --target i-0c78bb1a7c6be7fa9 \
  --document-name AWS-StartPortForwardingSession \
  --parameters '{"portNumber":["22"],"localPortNumber":["2222"]}'

# 別のターミナルでSSH接続
ssh -i /path/to/your-key.pem -p 2222 ec2-user@localhost
```

### 初回セットアップコマンド

サーバーに接続後、以下のコマンドを実行してください：

```bash
# デプロイパスを設定
DEPLOY_PATH="/mf/himelog-api"

# ストレージディレクトリを作成
mkdir -p $DEPLOY_PATH/storage/logs
mkdir -p $DEPLOY_PATH/storage/framework/cache
mkdir -p $DEPLOY_PATH/storage/framework/sessions
mkdir -p $DEPLOY_PATH/storage/framework/views

# パーミッションを設定
chmod -R 775 $DEPLOY_PATH/storage
chmod -R 775 $DEPLOY_PATH/bootstrap/cache

# 所有者を設定（nginxまたはwww-data）
chown -R nginx:nginx $DEPLOY_PATH/storage || chown -R www-data:www-data $DEPLOY_PATH/storage
chown -R nginx:nginx $DEPLOY_PATH/bootstrap/cache || chown -R www-data:www-data $DEPLOY_PATH/bootstrap/cache

# ログファイルを作成
touch $DEPLOY_PATH/storage/logs/laravel.log
chmod 664 $DEPLOY_PATH/storage/logs/laravel.log
chown nginx:nginx $DEPLOY_PATH/storage/logs/laravel.log || chown www-data:www-data $DEPLOY_PATH/storage/logs/laravel.log
```

### ログの確認

ログファイルは以下のパスにあります：

```bash
# Laravelのログを確認
tail -f /mf/himelog-api/storage/logs/laravel.log

# nginxのエラーログを確認（設定により異なる場合があります）
tail -f /var/log/nginx/error.log

# PHP-FPMのエラーログを確認（設定により異なる場合があります）
tail -f /var/log/php-fpm/error.log
```
