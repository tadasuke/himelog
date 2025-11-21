# GitHub Actions OIDC 設定手順

GitHub Actions から AWS リソースにアクセスするために、OIDC（OpenID Connect）を使用した認証を設定する必要があります。

## エラー

```
Could not assume role with OIDC: Not authorized to perform sts:AssumeRoleWithWebIdentity
```

このエラーは、IAM ロールの信頼ポリシーが GitHub OIDC を許可していない場合に発生します。

## 解決手順

### 1. GitHub OIDC プロバイダーの確認・作成

AWS IAM コンソールで GitHub の OIDC プロバイダーが設定されているか確認します。

#### 既に設定されている場合
- IAM コンソール → アイデンティティプロバイダー → `token.actions.githubusercontent.com` が存在することを確認

#### 設定されていない場合
以下のコマンドで OIDC プロバイダーを作成：

```bash
aws iam create-open-id-connect-provider \
  --url https://token.actions.githubusercontent.com \
  --client-id-list sts.amazonaws.com \
  --thumbprint-list 6938fd4d98bab03faadb97b34396831e3780aea1 \
  --tags Key=Name,Value=GitHubActions
```

### 2. IAM ロールの信頼ポリシーの設定

バックエンドデプロイ用の IAM ロール `himelog_ec2_develop` の信頼ポリシーを以下のように設定します。

#### AWS コンソールでの設定

1. IAM コンソール → ロール → `himelog_ec2_develop` を選択
2. 「信頼関係」タブを開く
3. 「信頼ポリシーの編集」をクリック
4. 以下の信頼ポリシーに置き換え（リポジトリ名を実際のものに変更）

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::034427362829:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
        },
        "StringLike": {
          "token.actions.githubusercontent.com:sub": "repo:tadasuke/himelog:*"
        }
      }
    }
  ]
}
```

#### より厳密な設定（推奨）

特定のブランチや環境からのみアクセスを許可する場合：

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::034427362829:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
        },
        "StringLike": {
          "token.actions.githubusercontent.com:sub": [
            "repo:tadasuke/himelog:ref:refs/heads/develop",
            "repo:tadasuke/himelog:ref:refs/heads/main"
          ]
        }
      }
    }
  ]
}
```

#### AWS CLI での設定

```bash
# 信頼ポリシーファイルを作成
cat > trust-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::034427362829:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
        },
        "StringLike": {
          "token.actions.githubusercontent.com:sub": "repo:tadasuke/himelog:*"
        }
      }
    }
  ]
}
EOF

# 信頼ポリシーを更新
aws iam update-assume-role-policy \
  --role-name himelog_ec2_develop \
  --policy-document file://trust-policy.json
```

### 3. フロントエンドデプロイ用ロールの設定

同様に、`himelog_s3_develop` ロールにも同じ信頼ポリシーを設定してください。

### 4. 設定の確認

GitHub Actions のワークフローを再実行して、エラーが解消されることを確認してください。

## 注意事項

- リポジトリ名（`tadasuke/himelog`）は実際のリポジトリ名に合わせて変更してください
- OIDC プロバイダーの ARN のアカウント ID（`034427362829`）が正しいことを確認してください
- 信頼ポリシーの条件でブランチを指定する場合、`workflow_dispatch` で他のブランチから実行する際にエラーになる可能性があります

