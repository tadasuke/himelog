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

/**
 * 認証トークンを取得
 * @returns {string|null} 認証トークン または null
 */
export const getAuthToken = () => {
  return localStorage.getItem('authToken')
}

/**
 * 認証トークンを保存
 * @param {string} token - 認証トークン
 */
export const setAuthToken = (token) => {
  localStorage.setItem('authToken', token)
}

/**
 * 認証トークンを削除
 */
export const removeAuthToken = () => {
  localStorage.removeItem('authToken')
}

/**
 * 認証ヘッダーを含むfetchオプションを取得
 * @param {object} options - fetchオプション
 * @returns {object} 認証ヘッダーを含むfetchオプション
 */
export const getAuthHeaders = (options = {}) => {
  const token = getAuthToken()
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  }
  
  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }
  
  return {
    ...options,
    headers,
  }
}

/**
 * 認証エラーが発生した場合の処理
 * 401エラーの場合は認証トークンを削除し、ページをリロード
 */
export const handleAuthError = (response) => {
  if (response.status === 401) {
    removeAuthToken()
    localStorage.removeItem('user')
    localStorage.removeItem('isLoggedIn')
    // ページをリロードしてログイン画面に戻す
    window.location.reload()
  }
}

