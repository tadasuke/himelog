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
 * リフレッシュトークンを取得
 * @returns {string|null} リフレッシュトークン または null
 */
export const getRefreshToken = () => {
  return localStorage.getItem('refreshToken')
}

/**
 * リフレッシュトークンを保存
 * @param {string} token - リフレッシュトークン
 */
export const setRefreshToken = (token) => {
  localStorage.setItem('refreshToken', token)
}

/**
 * リフレッシュトークンを削除
 */
export const removeRefreshToken = () => {
  localStorage.removeItem('refreshToken')
}

/**
 * トークンの有効期限を取得
 * @returns {number|null} 有効期限（Unixタイムスタンプ） または null
 */
export const getTokenExpiry = () => {
  const expiry = localStorage.getItem('tokenExpiry')
  return expiry ? parseInt(expiry, 10) : null
}

/**
 * トークンの有効期限を保存
 * @param {number} expiresIn - 有効期限（秒）
 */
export const setTokenExpiry = (expiresIn) => {
  const expiry = Date.now() + (expiresIn * 1000)
  localStorage.setItem('tokenExpiry', expiry.toString())
}

/**
 * トークンの有効期限を削除
 */
export const removeTokenExpiry = () => {
  localStorage.removeItem('tokenExpiry')
}

/**
 * トークンが有効期限内かどうかを確認
 * @returns {boolean} トークンが有効期限内の場合 true
 */
export const isTokenValid = () => {
  const expiry = getTokenExpiry()
  if (!expiry) return false
  // 5分のマージンを設ける（期限切れの5分前でも無効とみなす）
  return Date.now() < (expiry - 5 * 60 * 1000)
}

/**
 * リフレッシュトークンを使用してアクセストークンを更新
 * @returns {Promise<{access_token: string, refresh_token: string, expires_in: number}|null>} 更新されたトークン情報 または null
 */
export const refreshAccessToken = async () => {
  const refreshToken = getRefreshToken()
  if (!refreshToken) {
    console.error('No refresh token available')
    return null
  }

  try {
    const response = await fetch(getApiUrl('/api/auth/x/refresh'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ refresh_token: refreshToken }),
    })

    if (!response.ok) {
      const data = await response.json().catch(() => ({}))
      console.error('Token refresh failed:', data)
      // リフレッシュトークンも無効な場合はログアウト
      if (response.status === 401) {
        removeAuthToken()
        removeRefreshToken()
        removeTokenExpiry()
        localStorage.removeItem('user')
        localStorage.removeItem('isLoggedIn')
        window.location.reload()
      }
      return null
    }

    const data = await response.json()
    
    // 新しいトークンを保存
    if (data.access_token) {
      setAuthToken(data.access_token)
    }
    if (data.refresh_token) {
      setRefreshToken(data.refresh_token)
    }
    if (data.expires_in) {
      setTokenExpiry(data.expires_in)
    }

    return data
  } catch (error) {
    console.error('Token refresh error:', error)
    return null
  }
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
 * 認証付きでfetchを実行（401エラー時に自動的にトークンを更新して再試行）
 * @param {string} url - リクエストURL
 * @param {object} options - fetchオプション
 * @returns {Promise<Response>} fetchレスポンス
 */
export const fetchWithAuth = async (url, options = {}) => {
  // トークンが期限切れの場合は事前に更新
  if (!isTokenValid()) {
    const refreshed = await refreshAccessToken()
    if (!refreshed) {
      // トークンの更新に失敗した場合は通常のfetchを実行（401エラーになる）
    }
  }

  const response = await fetch(url, getAuthHeaders(options))

  // 401エラーの場合、トークンを更新して再試行
  if (response.status === 401) {
    const refreshed = await refreshAccessToken()
    if (refreshed) {
      // トークンを更新したので、再度リクエストを送信
      return fetch(url, getAuthHeaders(options))
    } else {
      // トークンの更新に失敗した場合は、認証エラー処理を実行
      handleAuthError(response)
      return response
    }
  }

  return response
}

/**
 * 認証エラーが発生した場合の処理
 * 401エラーの場合は認証トークンを削除し、ページをリロード
 */
export const handleAuthError = (response) => {
  if (response.status === 401) {
    removeAuthToken()
    removeRefreshToken()
    removeTokenExpiry()
    localStorage.removeItem('user')
    localStorage.removeItem('isLoggedIn')
    // ページをリロードしてログイン画面に戻す
    window.location.reload()
  }
}

