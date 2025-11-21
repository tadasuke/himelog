# GitHub Actions 環境変数・シークレット設定ガイド

現在のワークフローファイルは全ての値をハードコードしているため、**環境変数やシークレットの設定は必須ではありません**。

ただし、より柔軟性を持たせるために、以下の環境変数・シークレットを設定することができます。

## 設定方法

GitHub リポジトリの Settings → Secrets and variables → Actions から設定します。

## バックエンドデプロイ用（deploy-backend.yml）

### 推奨設定（任意）

以下の環境変数を設定することで、ワークフローをより柔軟にできます：

| 環境変数名 | 説明 | 現在の値 | 必須 |
|----------|------|---------|------|
| `EC2_INSTANCE_ID` | EC2インスタンスID | `i-0c78bb1a7c6be7fa9` | ❌ |
| `DEPLOY_PATH` | デプロイ先パス | `/mf` | ❌ |
| `REPO_PATH` | リポジトリの一時パス | `/tmp/himelog-repo` | ❌ |
| `AWS_ROLE_ARN` | IAMロールARN | `arn:aws:iam::034427362829:role/himelog_ec2_develop` | ❌ |
| `AWS_REGION` | AWSリージョン | `ap-northeast-1` | ❌ |
| `GITHUB_REPO` | GitHubリポジトリ（owner/repo形式） | `tadasuke/himelog` | ❌ |

### 環境変数を設定する場合のワークフロー変更例

環境変数を設定した場合は、以下のようにワークフローファイルを変更します：

```yaml
- name: Deploy via SSM - Git pull and deploy backend only
  id: deploy
  env:
    EC2_INSTANCE_ID: ${{ vars.EC2_INSTANCE_ID || 'i-0c78bb1a7c6be7fa9' }}
    DEPLOY_PATH: ${{ vars.DEPLOY_PATH || '/mf' }}
    REPO_PATH: ${{ vars.REPO_PATH || '/tmp/himelog-repo' }}
    GITHUB_REPO: ${{ vars.GITHUB_REPO || 'tadasuke/himelog' }}
  run: |
    # ... 環境変数を使用
```

## フロントエンドデプロイ用（deploy-frontend.yml）

### 推奨設定（任意）

| 環境変数名 | 説明 | 現在の値 | 必須 |
|----------|------|---------|------|
| `AWS_ROLE_ARN` | IAMロールARN | `arn:aws:iam::034427362829:role/himelog_s3_develop` | ❌ |
| `AWS_REGION` | AWSリージョン | `ap-northeast-1` | ❌ |
| `S3_BUCKET_NAME` | S3バケット名 | `himelog.develop` | ❌ |

## 注意事項

1. **現在の設定では環境変数は不要です**
   - すべての値がワークフローファイルに直接記述されているため、そのままで動作します

2. **環境変数を設定するメリット**
   - 環境ごと（develop/staging/production）で値を変更しやすい
   - シークレット情報（API キーなど）を安全に管理できる
   - ワークフローファイルの値の変更が不要

3. **環境変数を設定する場合**
   - `vars` は Repository variables（公開値用）
   - `secrets` は Secrets（機密情報用）
   - 環境変数は `${{ vars.VARIABLE_NAME }}` または `${{ secrets.SECRET_NAME }}` で参照

## 現在の設定で必要なもの

**何も設定する必要はありません。** 現在のワークフローファイルはそのままで動作します。

ただし、以下のAWS側の設定が必要です：
- IAMロールのOIDC信頼ポリシー設定（`.github/OIDC_SETUP.md` を参照）

