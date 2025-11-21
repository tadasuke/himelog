# mf03 Infrastructure (AWS CDK)

AWS CDKを使用して構築されたmf03プロジェクトのインフラストラクチャコードです。

## 構成

- **VPC**: 10.7.0.0/16
- **リージョン**: ap-northeast-1 (東京)
- **AZ**: AとCの2つ

### サブネット構成

- **Public**: 
  - AZ-A: 10.7.1.0/24
  - AZ-C: 10.7.10.0/24
- **Private**: 
  - AZ-A: 10.7.2.0/24
  - AZ-C: 10.7.20.0/24
- **Backend**: 
  - AZ-A: 10.7.3.0/24
  - AZ-C: 10.7.30.0/24

### リソース

- **EC2**: t3.micro (Amazon Linux 2023)
  - インスタンス名: mf03_dev
  - EIPアタッチ済み
  - キーペア: mf03-dev-keypair
- **RDS**: Aurora MySQL Serverless v2
  - 最小ACU: 0
  - 最大ACU: 1
  - 初期DB名: dev_himelog
  - バックアップ保持期間: 7日
  - CloudWatch Logs保持期間: 7日
  - パスワード管理: AWS Secrets Manager

### セキュリティグループ

- SSH接続（アクセス元IP制限なし）
- HTTPS接続（アクセス元IP制限なし）
- MySQL接続（VPC内からのみ: 10.7.0.0/16）

## セットアップ

### 1. 依存関係のインストール

```bash
cd infrastructure
npm install
```

### 2. AWS認証情報の設定

AWS CLIが設定されていることを確認してください：

```bash
aws configure
```

### 3. CDKのブートストラップ（初回のみ）

```bash
cdk bootstrap aws://ACCOUNT-ID/ap-northeast-1
```

### 4. デプロイ

```bash
# 変更内容を確認
cdk diff

# デプロイ実行
cdk deploy
```

### 5. キーペアの取得

デプロイ後、キーペアの秘密鍵を取得する必要があります。AWSコンソールから「EC2 > キーペアとキー > キーペア」で `mf03-dev-keypair` を選択し、秘密鍵をダウンロードしてください。

## 出力値

デプロイ後、以下の情報が出力されます：

- `VpcId`: VPC ID
- `Ec2InstanceId`: EC2インスタンスID
- `ElasticIp`: アタッチされたElastic IPアドレス
- `DbClusterEndpoint`: RDS Auroraクラスターのエンドポイント
- `DbSecretArn`: RDSのパスワードが保存されているSecrets ManagerのARN
- `KeyPairId`: キーペアID

## その他のコマンド

```bash
# スタックの合成（CloudFormationテンプレートを生成）
cdk synth

# スタックの削除
cdk destroy
```

