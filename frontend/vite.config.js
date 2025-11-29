import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import { writeFileSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // 環境変数を読み込む
  const env = loadEnv(mode, process.cwd(), '')
  
  // ビルド時にconfig.jsを生成
  const generateConfigJs = () => {
    const apiBaseUrl = env.VITE_API_BASE_URL || ''
    const googleClientId = env.VITE_GOOGLE_CLIENT_ID || ''
    const xClientId = env.VITE_X_CLIENT_ID || ''
    const reactAppUrl = env.VITE_REACT_APP_URL || '/'
    
    const configContent = `// このファイルは自動生成されます。手動で編集しないでください。
// 環境変数から生成: ${mode}

// APIベースURL
window.API_BASE_URL = ${JSON.stringify(apiBaseUrl)};

// Google Client ID
window.GOOGLE_CLIENT_ID = ${JSON.stringify(googleClientId)};

// X Client ID  
window.X_CLIENT_ID = ${JSON.stringify(xClientId)};

// ReactアプリのURL（認証成功後のリダイレクト先）
window.REACT_APP_URL = ${JSON.stringify(reactAppUrl)};
`
    
    const configPath = resolve(__dirname, 'public/login/config.js')
    writeFileSync(configPath, configContent, 'utf-8')
    console.log('Generated config.js with environment variables')
  }
  
  return {
    plugins: [
      react(),
      {
        name: 'generate-config-js',
        buildStart() {
          generateConfigJs()
        },
        configureServer() {
          // 開発サーバー起動時にも生成
          generateConfigJs()
        }
      }
    ],
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path,
        configure: (proxy, _options) => {
          proxy.on('error', (err, req, res) => {
            console.error('Proxy error:', err);
            console.error('Request URL:', req.url);
            console.error('Request method:', req.method);
            if (res && !res.headersSent) {
              res.writeHead(500, {
                'Content-Type': 'application/json',
              });
              res.end(JSON.stringify({ error: 'Proxy error', message: err.message }));
            }
          });
          proxy.on('proxyReq', (proxyReq, req, _res) => {
            console.log('Sending Request to the Target:', req.method, req.url);
            console.log('Target URL:', proxyReq.path);
          });
          proxy.on('proxyRes', (proxyRes, req, _res) => {
            console.log('Received Response from the Target:', proxyRes.statusCode, req.url);
          });
        },
      },
    },
    // SPAのルーティングをサポート（すべてのルートをindex.htmlにリダイレクト）
    // Viteはデフォルトでこれをサポートしているが、明示的に設定
    strictPort: false,
    open: false,
  },
  }
})
