import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as rds from 'aws-cdk-lib/aws-rds';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import * as logs from 'aws-cdk-lib/aws-logs';
import { Construct } from 'constructs';

export class Mf03InfraStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    // VPCの作成（手動でサブネットを作成するため、CfnVPCを使用）
    const vpc = new ec2.CfnVPC(this, 'Mf03Vpc', {
      cidrBlock: '10.7.0.0/16',
      enableDnsHostnames: true,
      enableDnsSupport: true,
      tags: [{ key: 'Name', value: 'mf03-vpc' }],
    });

    // VPCオブジェクトを後で使用するために作成
    const vpcRef = ec2.Vpc.fromVpcAttributes(this, 'Mf03VpcRef', {
      vpcId: vpc.ref,
      availabilityZones: ['ap-northeast-1a', 'ap-northeast-1c'],
      publicSubnetIds: [], // 手動で作成するため空
      privateSubnetIds: [], // 手動で作成するため空
    });

    // インターネットゲートウェイの作成
    const igw = new ec2.CfnInternetGateway(this, 'Mf03Igw', {
      tags: [{ key: 'Name', value: 'mf03-igw' }],
    });
    new ec2.CfnVPCGatewayAttachment(this, 'Mf03IgwAttachment', {
      vpcId: vpc.ref,
      internetGatewayId: igw.ref,
    });

    // パブリックルートテーブルの作成
    const publicRouteTable = new ec2.CfnRouteTable(this, 'Mf03PublicRouteTable', {
      vpcId: vpc.ref,
      tags: [{ key: 'Name', value: 'mf03-public-rt' }],
    });
    new ec2.CfnRoute(this, 'Mf03PublicRoute', {
      routeTableId: publicRouteTable.ref,
      destinationCidrBlock: '0.0.0.0/0',
      gatewayId: igw.ref,
    });

    // Publicサブネットの作成（AZ-A: 10.7.1.0/24, AZ-C: 10.7.10.0/24）
    const publicSubnetA = new ec2.CfnSubnet(this, 'Mf03PublicSubnetA', {
      vpcId: vpc.ref,
      availabilityZone: 'ap-northeast-1a',
      cidrBlock: '10.7.1.0/24',
      mapPublicIpOnLaunch: true,
      tags: [{ key: 'Name', value: 'mf03-public-subnet-a' }],
    });
    new ec2.CfnSubnetRouteTableAssociation(this, 'Mf03PublicSubnetAAssociation', {
      subnetId: publicSubnetA.ref,
      routeTableId: publicRouteTable.ref,
    });

    const publicSubnetC = new ec2.CfnSubnet(this, 'Mf03PublicSubnetC', {
      vpcId: vpc.ref,
      availabilityZone: 'ap-northeast-1c',
      cidrBlock: '10.7.10.0/24',
      mapPublicIpOnLaunch: true,
      tags: [{ key: 'Name', value: 'mf03-public-subnet-c' }],
    });
    new ec2.CfnSubnetRouteTableAssociation(this, 'Mf03PublicSubnetCAssociation', {
      subnetId: publicSubnetC.ref,
      routeTableId: publicRouteTable.ref,
    });

    // Privateサブネットの作成（AZ-A: 10.7.2.0/24, AZ-C: 10.7.20.0/24）
    // 当面は使わないが、作成しておく
    const privateRouteTable = new ec2.CfnRouteTable(this, 'Mf03PrivateRouteTable', {
      vpcId: vpc.ref,
      tags: [{ key: 'Name', value: 'mf03-private-rt' }],
    });

    const privateSubnetA = new ec2.CfnSubnet(this, 'Mf03PrivateSubnetA', {
      vpcId: vpc.ref,
      availabilityZone: 'ap-northeast-1a',
      cidrBlock: '10.7.2.0/24',
      tags: [{ key: 'Name', value: 'mf03-private-subnet-a' }],
    });
    new ec2.CfnSubnetRouteTableAssociation(this, 'Mf03PrivateSubnetAAssociation', {
      subnetId: privateSubnetA.ref,
      routeTableId: privateRouteTable.ref,
    });

    const privateSubnetC = new ec2.CfnSubnet(this, 'Mf03PrivateSubnetC', {
      vpcId: vpc.ref,
      availabilityZone: 'ap-northeast-1c',
      cidrBlock: '10.7.20.0/24',
      tags: [{ key: 'Name', value: 'mf03-private-subnet-c' }],
    });
    new ec2.CfnSubnetRouteTableAssociation(this, 'Mf03PrivateSubnetCAssociation', {
      subnetId: privateSubnetC.ref,
      routeTableId: privateRouteTable.ref,
    });

    // Backendサブネットの作成（AZ-A: 10.7.3.0/24, AZ-C: 10.7.30.0/24）
    const backendRouteTable = new ec2.CfnRouteTable(this, 'Mf03BackendRouteTable', {
      vpcId: vpc.ref,
      tags: [{ key: 'Name', value: 'mf03-backend-rt' }],
    });

    const backendSubnetA = new ec2.CfnSubnet(this, 'Mf03BackendSubnetA', {
      vpcId: vpc.ref,
      availabilityZone: 'ap-northeast-1a',
      cidrBlock: '10.7.3.0/24',
      tags: [{ key: 'Name', value: 'mf03-backend-subnet-a' }],
    });
    new ec2.CfnSubnetRouteTableAssociation(this, 'Mf03BackendSubnetAAssociation', {
      subnetId: backendSubnetA.ref,
      routeTableId: backendRouteTable.ref,
    });

    const backendSubnetC = new ec2.CfnSubnet(this, 'Mf03BackendSubnetC', {
      vpcId: vpc.ref,
      availabilityZone: 'ap-northeast-1c',
      cidrBlock: '10.7.30.0/24',
      tags: [{ key: 'Name', value: 'mf03-backend-subnet-c' }],
    });
    new ec2.CfnSubnetRouteTableAssociation(this, 'Mf03BackendSubnetCAssociation', {
      subnetId: backendSubnetC.ref,
      routeTableId: backendRouteTable.ref,
    });

    // セキュリティグループの作成
    // SSH接続用（アクセス元IP制限なし）
    const sshSecurityGroup = new ec2.SecurityGroup(this, 'Mf03SshSg', {
      vpc: vpcRef,
      description: 'Security group for SSH access (no IP restriction)',
      allowAllOutbound: true,
    });
    sshSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(),
      ec2.Port.tcp(22),
      'Allow SSH from anywhere'
    );

    // HTTPS接続用（アクセス元IP制限なし）
    const httpsSecurityGroup = new ec2.SecurityGroup(this, 'Mf03HttpsSg', {
      vpc: vpcRef,
      description: 'Security group for HTTPS access (no IP restriction)',
      allowAllOutbound: true,
    });
    httpsSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(),
      ec2.Port.tcp(443),
      'Allow HTTPS from anywhere'
    );

    // MySQL接続用（VPC内からのみ）
    const mysqlSecurityGroup = new ec2.SecurityGroup(this, 'Mf03MysqlSg', {
      vpc: vpcRef,
      description: 'Security group for MySQL access from VPC',
      allowAllOutbound: true,
    });
    mysqlSecurityGroup.addIngressRule(
      ec2.Peer.ipv4('10.7.0.0/16'),
      ec2.Port.tcp(3306),
      'Allow MySQL from VPC'
    );

    // EC2用のキーペアを作成
    const keyPair = new ec2.KeyPair(this, 'Mf03KeyPair', {
      type: ec2.KeyPairType.RSA,
      format: ec2.KeyPairFormat.PEM,
      keyPairName: 'mf03-dev-keypair',
    });

    // EC2インスタンスの作成（複数のセキュリティグループを適用）
    const ec2Instance = new ec2.Instance(this, 'Mf03Dev', {
      vpc: vpcRef,
      vpcSubnets: {
        subnets: [
          ec2.Subnet.fromSubnetAttributes(this, 'PublicSubnetAId', {
            subnetId: publicSubnetA.ref,
            availabilityZone: 'ap-northeast-1a',
          }),
        ],
      },
      instanceType: ec2.InstanceType.of(
        ec2.InstanceClass.T3,
        ec2.InstanceSize.MICRO
      ),
      machineImage: ec2.MachineImage.latestAmazonLinux2023(),
      securityGroup: sshSecurityGroup,
      keyPair: keyPair,
    });
    
    // HTTPSセキュリティグループも追加
    ec2Instance.addSecurityGroup(httpsSecurityGroup);

    // インスタンス名を設定
    cdk.Tags.of(ec2Instance).add('Name', 'mf03_dev');

    // EIPの作成とアタッチ
    const eip = new ec2.CfnEIP(this, 'Mf03Eip', {
      domain: 'vpc',
      instanceId: ec2Instance.instanceId,
    });

    // RDS用のSecrets Managerシークレットを作成
    const dbSecret = new secretsmanager.Secret(this, 'Mf03DbSecret', {
      secretName: 'mf03/rds/admin',
      description: 'RDS master user credentials for mf03',
      generateSecretString: {
        secretStringTemplate: JSON.stringify({ username: 'admin' }),
        generateStringKey: 'password',
        excludeCharacters: '"@/\\',
        includeSpace: false,
        passwordLength: 32,
      },
    });

    // RDS Aurora Serverless v2クラスターの作成
    const dbCluster = new rds.DatabaseCluster(this, 'Mf03AuroraCluster', {
      engine: rds.DatabaseClusterEngine.auroraMysql({
        version: rds.AuroraMysqlEngineVersion.VER_3_08_2, // MySQL 8.0系（Serverless v2対応）
      }),
      credentials: rds.Credentials.fromSecret(dbSecret),
      defaultDatabaseName: 'dev_himelog',
      serverlessV2MinCapacity: 0, // Aurora Serverless v2の最小ACU（0に設定可能）
      serverlessV2MaxCapacity: 1,
      writer: rds.ClusterInstance.serverlessV2('writer', {
        scaleWithWriter: true,
      }),
      vpc: vpcRef,
      vpcSubnets: {
        subnets: [
          ec2.Subnet.fromSubnetAttributes(this, 'BackendSubnetAId', {
            subnetId: backendSubnetA.ref,
            availabilityZone: 'ap-northeast-1a',
          }),
          ec2.Subnet.fromSubnetAttributes(this, 'BackendSubnetCId', {
            subnetId: backendSubnetC.ref,
            availabilityZone: 'ap-northeast-1c',
          }),
        ],
      },
      securityGroups: [mysqlSecurityGroup],
      removalPolicy: cdk.RemovalPolicy.DESTROY, // 開発環境用
      backup: {
        retention: cdk.Duration.days(7),
        preferredWindow: '03:00-04:00',
      },
      cloudwatchLogsExports: ['error', 'general', 'slowquery', 'audit'],
      cloudwatchLogsRetention: logs.RetentionDays.ONE_WEEK,
    });

    // 出力
    new cdk.CfnOutput(this, 'VpcId', {
      value: vpc.ref,
      description: 'VPC ID',
    });

    new cdk.CfnOutput(this, 'Ec2InstanceId', {
      value: ec2Instance.instanceId,
      description: 'EC2 Instance ID',
    });

    new cdk.CfnOutput(this, 'ElasticIp', {
      value: eip.ref,
      description: 'Elastic IP Address',
    });

    new cdk.CfnOutput(this, 'DbClusterEndpoint', {
      value: dbCluster.clusterEndpoint.hostname,
      description: 'RDS Aurora Cluster Endpoint',
    });

    new cdk.CfnOutput(this, 'DbSecretArn', {
      value: dbSecret.secretArn,
      description: 'RDS Secret ARN',
    });

    new cdk.CfnOutput(this, 'KeyPairId', {
      value: keyPair.keyPairId,
      description: 'EC2 Key Pair ID',
    });
  }
}

