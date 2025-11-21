# SSMエージェント トラブルシューティングガイド

## 現在のエラー状況

SSMエージェントは起動していますが、Systems Managerへの接続に失敗しています。
エラーログに `status code: 400` が表示されている場合、これは通常 **IAMロールの設定問題** を示しています。

## エラーログの確認

インスタンスで以下のコマンドを実行してエラーログを確認：

```bash
sudo tail -f /var/log/amazon/ssm/amazon-ssm-agent.log
```

以下のようなエラーが表示される場合：
```
ERROR EC2RoleProvider Failed to connect to Systems Manager
status code: 400
```

これは **IAMロールの設定問題** を示しています。

## 解決方法

### 1. IAMロールの確認と設定

#### 現在のIAMロールを確認

```bash
# EC2インスタンスから現在のIAMロールを確認
aws sts get-caller-identity

# インスタンスのIAMプロファイルを確認
aws ec2 describe-instances \
  --instance-ids i-0c78bb1a7c6be7fa9 \
  --query 'Reservations[0].Instances[0].IamInstanceProfile.Arn'
```

#### IAMロールにAmazonSSMManagedInstanceCoreポリシーをアタッチ

```bash
# IAMロール名を取得（上記のコマンドの結果から取得）
IAM_ROLE_NAME="your-iam-role-name"

# ポリシーがアタッチされているか確認
aws iam list-attached-role-policies --role-name "$IAM_ROLE_NAME"

# AmazonSSMManagedInstanceCoreポリシーをアタッチ
aws iam attach-role-policy \
  --role-name "$IAM_ROLE_NAME" \
  --policy-arn arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore
```

#### インスタンスにIAMロールがない場合

1. **IAMロールを作成**
```bash
# 1. 信頼ポリシーを作成
cat > trust-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "ec2.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
EOF

# 2. IAMロールを作成
aws iam create-role \
  --role-name EC2-SSM-Role \
  --assume-role-policy-document file://trust-policy.json

# 3. AmazonSSMManagedInstanceCoreポリシーをアタッチ
aws iam attach-role-policy \
  --role-name EC2-SSM-Role \
  --policy-arn arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore

# 4. インスタンスプロファイルを作成
aws iam create-instance-profile \
  --instance-profile-name EC2-SSM-Profile

# 5. インスタンスプロファイルにロールを追加
aws iam add-role-to-instance-profile \
  --instance-profile-name EC2-SSM-Profile \
  --role-name EC2-SSM-Role
```

2. **インスタンスにIAMロールをアタッチ**
```bash
# 既存のIAMロールがある場合、まずデタッチ
aws ec2 disassociate-iam-instance-profile \
  --association-id <existing-association-id>

# 新しいIAMロールをアタッチ
aws ec2 associate-iam-instance-profile \
  --instance-id i-0c78bb1a7c6be7fa9 \
  --iam-instance-profile Name=EC2-SSM-Profile
```

**注意**: インスタンスを再起動する必要はありませんが、SSMエージェントが新しいIAMロールを認識するまで数分かかる場合があります。

### 2. VPCエンドポイントの設定（VPC内のインスタンスの場合）

インスタンスがVPC内にあり、インターネットゲートウェイがない場合、以下のVPCエンドポイントが必要です：

```bash
VPC_ID="your-vpc-id"
SUBNET_IDS="subnet-xxx,subnet-yyy"  # SSMを使うサブネットID
SECURITY_GROUP_ID="sg-xxx"  # VPCエンドポイント用のセキュリティグループ

# 1. SSM エンドポイント
aws ec2 create-vpc-endpoint \
  --vpc-id "$VPC_ID" \
  --vpc-endpoint-type Interface \
  --service-name com.amazonaws.ap-northeast-1.ssm \
  --subnet-ids $SUBNET_IDS \
  --security-group-ids "$SECURITY_GROUP_ID"

# 2. SSM Messages エンドポイント
aws ec2 create-vpc-endpoint \
  --vpc-id "$VPC_ID" \
  --vpc-endpoint-type Interface \
  --service-name com.amazonaws.ap-northeast-1.ssmmessages \
  --subnet-ids $SUBNET_IDS \
  --security-group-ids "$SECURITY_GROUP_ID"

# 3. EC2 Messages エンドポイント
aws ec2 create-vpc-endpoint \
  --vpc-id "$VPC_ID" \
  --vpc-endpoint-type Interface \
  --service-name com.amazonaws.ap-northeast-1.ec2messages \
  --subnet-ids $SUBNET_IDS \
  --security-group-ids "$SECURITY_GROUP_ID"
```

### 3. SSMエージェントの再起動

IAMロールを設定した後、SSMエージェントを再起動：

```bash
sudo systemctl restart amazon-ssm-agent
sudo systemctl status amazon-ssm-agent
```

### 4. 動作確認

数分待ってから、以下で確認：

```bash
# SSMエージェントのログを確認
sudo tail -20 /var/log/amazon/ssm/amazon-ssm-agent.log

# SSMエージェントがオンラインになったか確認
aws ssm describe-instance-information \
  --filters "Key=InstanceIds,Values=i-0c78bb1a7c6be7fa9" \
  --query 'InstanceInformationList[0].PingStatus' \
  --output text
```

`Online` と表示されれば成功です。

## よくある問題

### 問題1: IAMロールがアタッチされていない

**症状**: インスタンスにIAMプロファイルが表示されない

**解決**: 上記の「インスタンスにIAMロールがない場合」の手順を実行

### 問題2: ポリシーがアタッチされていない

**症状**: IAMロールは存在するが、AmazonSSMManagedInstanceCoreポリシーがない

**解決**: 
```bash
aws iam attach-role-policy \
  --role-name <role-name> \
  --policy-arn arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore
```

### 問題3: VPCエンドポイントが設定されていない

**症状**: VPC内のインスタンスでSSMエージェントが接続できない

**解決**: 上記の「VPCエンドポイントの設定」の手順を実行

### 問題4: セキュリティグループでHTTPSがブロックされている

**症状**: アウトバウンドトラフィックがブロックされている

**解決**: セキュリティグループでアウトバウンドのHTTPS (443) を許可

## 参考リンク

- [AWS Systems Manager ドキュメント](https://docs.aws.amazon.com/systems-manager/latest/userguide/sysman-install.html)
- [AmazonSSMManagedInstanceCore ポリシー](https://docs.aws.amazon.com/systems-manager/latest/userguide/setup-instance-profile.html)

