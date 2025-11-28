/**
 * APIエンドポイントのベースURLを取得
 * 優先順位:
 * 1. 環境変数 VITE_API_BASE_URL が設定されている場合はそれを使用
 * 2. localhost または 127.0.0.1 の場合は相対パスを使用（Viteのプロキシを使用）
 * 環境変数が設定されていない場合はエラーをスロー
 */
export const getApiBaseUrl = () => {
  const hostname = window.location.hostname
  const isLocalhost = hostname === 'localhost' || hostname === '127.0.0.1'
  const envApiBaseUrl = import.meta.env.VITE_API_BASE_URL
  
  // デバッグ情報を出力
  console.log('getApiBaseUrl debug:', {
    hostname,
    isLocalhost,
    VITE_API_BASE_URL: envApiBaseUrl,
  })
  
  // ローカル環境の場合
  if (isLocalhost) {
    // ローカル環境では、.env.localの設定（http://localhost:8000）を優先
    // ただし、.env.developmentの本番URLが設定されている場合は無視する
    if (envApiBaseUrl) {
      // localhostを含むURL（.env.localの設定）の場合は使用
      if (envApiBaseUrl.includes('localhost') || envApiBaseUrl.includes('127.0.0.1')) {
        console.log('Using VITE_API_BASE_URL from .env.local:', envApiBaseUrl)
        return envApiBaseUrl
      } else {
        // 本番URL（.env.developmentの設定）の場合は無視して、.env.localの設定を探すか相対パスを使用
        console.warn('Ignoring production URL in localhost environment, using .env.local or relative path')
        // .env.localに設定がある場合はそれを使用（Viteが正しく読み込んでいれば）
        // ここでは相対パスを使用（Viteのプロキシが動作）
        console.log('Using relative path for localhost (Vite proxy)')
        return ''
      }
    } else {
      // 環境変数が設定されていない場合は相対パスを使用（Viteのプロキシが動作）
      console.log('Using relative path for localhost (Vite proxy)')
      return ''
    }
  }
  
  // ローカル環境以外の場合
  if (envApiBaseUrl) {
    console.log('Using VITE_API_BASE_URL from env:', envApiBaseUrl)
    return envApiBaseUrl
  }
  
  // 環境変数が設定されていない場合は警告を表示してエラーをスロー
  console.error(
    'VITE_API_BASE_URL is not set. Please configure it in .env.local, .env.development, or .env.production file.'
  )
  throw new Error(
    'サーバーへの接続に失敗しました。しばらくしてから再度お試しください。'
  )
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
 * Google IDトークン（JWT）の有効期限を取得
 * @returns {number|null} 有効期限（Unixタイムスタンプ、ミリ秒） または null
 */
export const getGoogleTokenExpiry = () => {
  const token = getAuthToken()
  if (!token) return null
  
  try {
    const parts = token.split('.')
    if (parts.length !== 3) return null
    
    // base64urlデコード
    const payloadBase64 = parts[1].replace(/-/g, '+').replace(/_/g, '/')
    const padding = (4 - (payloadBase64.length % 4)) % 4
    const payloadJson = atob(payloadBase64 + '='.repeat(padding))
    const payload = JSON.parse(payloadJson)
    
    // expは秒単位なのでミリ秒に変換
    return payload.exp ? payload.exp * 1000 : null
  } catch (error) {
    console.error('Failed to parse Google token:', error)
    return null
  }
}

/**
 * Google IDトークンが有効期限内かどうかを確認
 * @returns {boolean} トークンが有効期限内の場合 true
 */
export const isGoogleTokenValid = () => {
  const expiry = getGoogleTokenExpiry()
  if (!expiry) return false
  // 5分のマージンを設ける（期限切れの5分前でも無効とみなす）
  return Date.now() < (expiry - 5 * 60 * 1000)
}

/**
 * Google Identity Servicesから新しいIDトークンを取得
 * @returns {Promise<string|null>} 新しいIDトークン または null
 */
export const refreshGoogleToken = async () => {
  return new Promise((resolve) => {
    if (typeof window.google === 'undefined' || !window.google.accounts) {
      console.warn('Google Identity Services not loaded')
      resolve(null)
      return
    }
    
    // Google Identity Servicesが初期化されているか確認
    if (!window.google.accounts.id) {
      console.warn('Google Identity Services ID not initialized')
      resolve(null)
      return
    }
    
    const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID
    if (!clientId) {
      console.warn('VITE_GOOGLE_CLIENT_ID is not set')
      resolve(null)
      return
    }
    
    let resolved = false
    
    // コールバック関数を定義
    const callback = (response) => {
      if (resolved) return
      resolved = true
      
      if (response.credential) {
        console.log('New Google token obtained')
        setAuthToken(response.credential)
        resolve(response.credential)
      } else {
        console.warn('Failed to obtain new Google token')
        resolve(null)
      }
    }
    
    // Google Identity Servicesを初期化
    try {
      window.google.accounts.id.initialize({
        client_id: clientId,
        callback: callback,
        ux_mode: 'popup',
      })
      
      // プロンプトを表示せずにトークンを取得を試みる
      window.google.accounts.id.prompt((notification) => {
        if (resolved) return
        
        // プロンプトが表示されなかった場合（ユーザーが既にログインしている場合）
        // またはスキップされた場合、手動でトークンを取得する必要がある
        if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
          console.warn('Google prompt not displayed, user may need to re-authenticate')
          if (!resolved) {
            resolved = true
            resolve(null)
          }
        }
      })
      
      // タイムアウトを設定（3秒）
      setTimeout(() => {
        if (!resolved) {
          resolved = true
          console.warn('Google token refresh timeout')
          resolve(null)
        }
      }, 3000)
    } catch (error) {
      console.error('Error refreshing Google token:', error)
      if (!resolved) {
        resolved = true
        resolve(null)
      }
    }
  })
}

/**
 * リフレッシュトークンを使用してアクセストークンを更新
 * @returns {Promise<{access_token: string, refresh_token: string, expires_in: number}|null>} 更新されたトークン情報 または null
 */
export const refreshAccessToken = async () => {
  const refreshToken = getRefreshToken()
  if (!refreshToken) {
    // リフレッシュトークンがない場合（Google認証など）
    // アクセストークンが有効であれば、そのまま使用する
    const authToken = getAuthToken()
    if (authToken) {
      console.log('No refresh token available, but access token exists. Using access token.')
      return null // nullを返すが、アクセストークンは使用可能
    }
    // アクセストークンもない場合はログアウト処理を実行
    console.warn('No refresh token and no access token available, logging out')
    removeAuthToken()
    removeRefreshToken()
    removeTokenExpiry()
    localStorage.removeItem('user')
    localStorage.removeItem('isLoggedIn')
    window.location.reload()
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
  const refreshToken = getRefreshToken()
  
  // リフレッシュトークンがない場合（Google認証）、Google IDトークンの有効期限をチェック
  if (!refreshToken) {
    if (!isGoogleTokenValid()) {
      console.log('Google token expired or expiring soon, attempting to refresh...')
      const newToken = await refreshGoogleToken()
      if (!newToken) {
        // 新しいトークンを取得できなかった場合
        console.warn('Failed to refresh Google token, proceeding with current token')
        // ここではログアウトせず、APIリクエストを試みる（バックエンドで401が返される可能性がある）
      }
    }
  } else {
    // リフレッシュトークンがある場合（X認証など）、既存のロジックを使用
    if (!isTokenValid()) {
      const refreshed = await refreshAccessToken()
      // リフレッシュトークンがない場合でも、アクセストークンが存在する場合は続行
      // refreshAccessToken内でログアウト処理が実行された場合は、ページがリロードされるためここには到達しない
      if (!refreshed) {
        // リフレッシュトークンがない場合、アクセストークンが有効かどうか確認
        const authToken = getAuthToken()
        if (!authToken) {
          // アクセストークンもない場合は、既にログアウト処理が実行されているはず
          return new Response(JSON.stringify({ error: 'Unauthorized', message: '認証が必要です' }), {
            status: 401,
            headers: { 'Content-Type': 'application/json' }
          })
        }
        // アクセストークンがある場合は、そのまま使用
      }
    }
  }

  const response = await fetch(url, getAuthHeaders(options))

  // 401エラーの場合、トークンを更新して再試行
  if (response.status === 401) {
    const refreshToken = getRefreshToken()
    
    if (!refreshToken) {
      // Google認証の場合、新しいトークンを取得
      console.log('401 error with Google auth, attempting to refresh token...')
      const newToken = await refreshGoogleToken()
      if (newToken) {
        // トークンを更新したので、再度リクエストを送信
        return fetch(url, getAuthHeaders(options))
      } else {
        // 新しいトークンを取得できなかった場合、認証エラー処理を実行
        handleAuthError(response)
        return response
      }
    } else {
      // X認証の場合、既存のリフレッシュロジックを使用
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

