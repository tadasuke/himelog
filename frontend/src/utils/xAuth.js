/**
 * X(Twitter)認証用のユーティリティ関数
 * OAuth 2.0 Authorization Code Flow with PKCEを使用
 */

/**
 * PKCE用のcode_verifierを生成
 * @returns {string} code_verifier
 */
export const generateCodeVerifier = () => {
  const array = new Uint8Array(32)
  crypto.getRandomValues(array)
  return btoa(String.fromCharCode(...array))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '')
}

/**
 * PKCE用のcode_challengeを生成
 * @param {string} verifier - code_verifier
 * @returns {Promise<string>} code_challenge
 */
export const generateCodeChallenge = async (verifier) => {
  const encoder = new TextEncoder()
  const data = encoder.encode(verifier)
  const digest = await crypto.subtle.digest('SHA-256', data)
  return btoa(String.fromCharCode(...new Uint8Array(digest)))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '')
}

/**
 * X認証URLを生成
 * @param {string} clientId - X Client ID
 * @param {string} redirectUri - リダイレクトURI
 * @param {string} codeChallenge - PKCE code_challenge
 * @returns {string} X認証URL
 */
export const generateXAuthUrl = (clientId, redirectUri, codeChallenge) => {
  const params = new URLSearchParams({
    response_type: 'code',
    client_id: clientId,
    redirect_uri: redirectUri,
    scope: 'tweet.read users.read offline.access',
    state: generateState(),
    code_challenge: codeChallenge,
    code_challenge_method: 'S256',
  })
  return `https://twitter.com/i/oauth2/authorize?${params.toString()}`
}

/**
 * ランダムなstateパラメータを生成
 * @returns {string} state
 */
const generateState = () => {
  const array = new Uint8Array(16)
  crypto.getRandomValues(array)
  return btoa(String.fromCharCode(...array))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '')
}

/**
 * URLから認証コードを取得
 * @returns {string|null} 認証コード または null
 */
export const getAuthCodeFromUrl = () => {
  const params = new URLSearchParams(window.location.search)
  return params.get('code')
}

/**
 * URLからstateパラメータを取得
 * @returns {string|null} state または null
 */
export const getStateFromUrl = () => {
  const params = new URLSearchParams(window.location.search)
  return params.get('state')
}

/**
 * URLからエラー情報を取得
 * @returns {object|null} エラー情報 または null
 */
export const getErrorFromUrl = () => {
  const params = new URLSearchParams(window.location.search)
  const error = params.get('error')
  const errorDescription = params.get('error_description')
  if (error) {
    return { error, errorDescription }
  }
  return null
}

