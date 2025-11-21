/**
 * APIエンドポイントのベースURLを取得
 * localhost または 127.0.0.1 の場合は相対パスを使用
 * それ以外の場合は https://4zklqklybqhu.madfaction.net を使用
 */
export const getApiBaseUrl = () => {
  const hostname = window.location.hostname
  
  // localhost または 127.0.0.1 の場合は相対パスを使用
  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return ''
  }
  
  // それ以外の場合は本番環境のAPIドメインを使用
  return 'https://4zklqklybqhu.madfaction.net'
}

/**
 * APIエンドポイントの完全なURLを生成
 * @param {string} path - APIパス（例: '/api/records'）
 * @returns {string} 完全なURL
 */
export const getApiUrl = (path) => {
  const baseUrl = getApiBaseUrl()
  // pathが既にスラッシュで始まっていることを確認
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  return `${baseUrl}${normalizedPath}`
}

