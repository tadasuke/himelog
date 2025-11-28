# APIドメイン重複問題の調査結果

## 問題の概要

develop環境でAPIのドメインが以下のように重複してしまう：
- **実際のURL**: `https://api.7i4hlzyrelaa.hime-log.madfaction.net7i4hlzyrelaa.hime-log.madfaction.net/api/auth/google/login`
- **期待されるURL**: `https://api.7i4hlzyrelaa.hime-log.madfaction.net/api/auth/google/login`

## 調査結果

### 1. 環境変数ファイルの確認

以下の環境変数ファイルを確認しました：

- **`.env.development`**: `VITE_API_BASE_URL=https://api.7i4hlzyrelaa.hime-log.madfaction.net` ✅ **正しい値**
- **`.env.production`**: `VITE_API_BASE_URL=https://api.hime-log.jp` ✅ **正しい値**
- **`.env.dev`**: `VITE_API_BASE_URL=http://localhost:8000` ✅ **正しい値**
- **`.env`**: `VITE_API_BASE_URL`の設定なし
- **`.env.development.local`**: 存在しない

**結論**: 環境変数ファイルには正しい値が設定されています。

### 2. コードの確認

#### `frontend/src/utils/api.js`
- `getApiBaseUrl()`関数は`import.meta.env.VITE_API_BASE_URL`をそのまま返している
- URLを構築する際に追加の処理は行っていない
- コード上では重複を引き起こすロジックは見当たらない

#### `frontend/vite.config.js`
- `loadEnv(mode, process.cwd(), '')`で環境変数を読み込んでいる
- `env.VITE_API_BASE_URL`をそのまま使用している
- ビルド時に`config.js`を生成する際も、環境変数の値をそのまま使用している

**結論**: ソースコード上では重複を引き起こすロジックは見当たりません。

### 3. 考えられる原因

環境変数ファイルには正しい値が設定されているにもかかわらず、実際のURLが重複していることから、以下の可能性が考えられます：

#### 可能性1: デプロイ環境での環境変数設定
- **AWS Systems Manager Parameter Store**や**AWS Secrets Manager**などで環境変数が設定されている場合
- デプロイ時に環境変数が誤って設定されている、または値が結合されている可能性
- **確認方法**: AWSコンソールで環境変数の設定を確認

#### 可能性2: CI/CDパイプラインでの環境変数設定
- **GitHub Actions**、**GitLab CI**、**CircleCI**などのCI/CDパイプラインで環境変数が設定されている場合
- ビルド時に環境変数が結合されている可能性
- **確認方法**: CI/CDの設定ファイル（`.github/workflows/*.yml`など）を確認

#### 可能性3: ビルド時の環境変数の読み込み順序
- Viteの`loadEnv`は複数の環境変数ファイルを読み込む際、後から読み込まれた値で上書きされる
- しかし、値が結合されることは通常ない
- **確認方法**: ビルド時のログを確認

#### 可能性4: ブラウザで実際に読み込まれている値が異なる
- ビルドされたJavaScriptファイルに既に重複した値が埋め込まれている可能性
- **確認方法**: ブラウザのコンソールで`import.meta.env.VITE_API_BASE_URL`の値を確認

## 確認すべきポイント

### 1. ブラウザのコンソールで確認

develop環境のブラウザで以下を実行して、実際に読み込まれている値を確認：

```javascript
// ブラウザのコンソールで実行
console.log('VITE_API_BASE_URL:', import.meta.env.VITE_API_BASE_URL)
console.log('getApiBaseUrl debug:', /* getApiBaseUrl()のログを確認 */)
```

### 2. ビルドされたファイルの確認

ビルドされたJavaScriptファイル（`dist/assets/*.js`）を確認して、環境変数の値がどのように埋め込まれているかを確認：

```bash
cd frontend
npm run build:development
grep -r "7i4hlzyrelaa" dist/
```

### 3. デプロイ環境の設定確認

- **AWS Systems Manager Parameter Store**: `/himelog/develop/VITE_API_BASE_URL`などのパラメータを確認
- **AWS Secrets Manager**: シークレット内の環境変数を確認
- **CI/CDパイプライン**: GitHub Actionsなどのワークフローファイルを確認

### 4. ビルドログの確認

ビルド時のログを確認して、環境変数がどのように読み込まれているかを確認：

```bash
cd frontend
npm run build:development 2>&1 | grep -i "VITE_API_BASE_URL"
```

## 推奨される次のステップ

1. **ブラウザのコンソールで実際の値を確認**
   - develop環境のブラウザで`import.meta.env.VITE_API_BASE_URL`の値を確認
   - `getApiBaseUrl debug:`のログを確認

2. **デプロイ環境の設定を確認**
   - AWSコンソールで環境変数の設定を確認
   - CI/CDパイプラインの設定を確認

3. **ビルドされたファイルを確認**
   - `dist/`ディレクトリ内のJavaScriptファイルを確認
   - 環境変数の値がどのように埋め込まれているかを確認

4. **ビルドログを確認**
   - ビルド時のログを確認して、環境変数の読み込み状況を確認

## 補足情報

- Viteの`loadEnv`関数は、環境変数ファイルを以下の順序で読み込みます：
  1. `.env` (すべての環境)
  2. `.env.local` (すべての環境、gitignoreされる)
  3. `.env.[mode]` (例: `.env.development`)
  4. `.env.[mode].local` (例: `.env.development.local`、gitignoreされる)
- 後から読み込まれた値で上書きされますが、値が結合されることは通常ありません。

