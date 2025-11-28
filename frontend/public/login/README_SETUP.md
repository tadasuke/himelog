# 静的ログインページのセットアップ手順

## 問題: ボタンをクリックしても何も起きない場合

### 原因
`config.js`ファイルが存在しないか、Google Client IDが設定されていません。

### 解決方法

#### 方法1: config.jsファイルを作成（推奨）

1. `config.js.example`をコピーして`config.js`を作成:
```bash
cd backend/public/login
cp config.js.example config.js
```

2. `config.js`を編集して、実際のClient IDを設定:
```javascript
// Google Client ID（バックエンドの.envのGOOGLE_CLIENT_IDと同じ値）
window.GOOGLE_CLIENT_ID = 'your-google-client-id-here';

// X Client ID（バックエンドの.envのX_CLIENT_IDと同じ値）
window.X_CLIENT_ID = 'your-x-client-id-here';
```

3. ブラウザをリロードして再試行

#### 方法2: HTMLファイルに直接埋め込む（一時的な解決策）

`backend/public/login/index.html`を開き、以下のコメントを解除して、実際のClient IDを設定:

```html
<!-- 以下のコメントを解除して、Client IDを直接設定 -->
<script>
    window.GOOGLE_CLIENT_ID = 'your-google-client-id-here';
    window.X_CLIENT_ID = 'your-x-client-id-here';
</script>
```

### デバッグ方法

ブラウザの開発者ツール（F12）を開き、コンソールタブで以下を確認:

1. `config.js`が読み込まれているか
2. `window.GOOGLE_CLIENT_ID`が設定されているか
3. エラーメッセージがあるか

以下のコマンドで確認:
```javascript
// コンソールで実行
console.log('Google Client ID:', window.GOOGLE_CLIENT_ID);
console.log('X Client ID:', window.X_CLIENT_ID);
```

### 注意事項

- `config.js`ファイルには機密情報が含まれるため、`.gitignore`に追加することを推奨します
- 本番環境では、サーバーサイドで環境変数から動的に設定を読み込むことを検討してください

