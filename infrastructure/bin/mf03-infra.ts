#!/usr/bin/env node
import 'source-map-support/register';
import * as cdk from 'aws-cdk-lib';
import { Mf03InfraStack } from '../lib/mf03-infra-stack';

const app = new cdk.App();
new Mf03InfraStack(app, 'Mf03InfraStack', {
  env: {
    region: 'ap-northeast-1',
  },
});

