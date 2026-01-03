/**
 * X(Twitter)認証用のユーティリティ関数
 * OAuth 1.0aを使用（Log in with X）
 */

/**
 * URLからoauth_tokenを取得
 * @returns {string|null} oauth_token または null
 */
export const getOAuthTokenFromUrl = () => {
  const params = new URLSearchParams(window.location.search)
  return params.get('oauth_token')
}

/**
 * URLからoauth_verifierを取得
 * @returns {string|null} oauth_verifier または null
 */
export const getOAuthVerifierFromUrl = () => {
  const params = new URLSearchParams(window.location.search)
  return params.get('oauth_verifier')
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

/**
 * X認証URLを生成（OAuth 1.0a）
 * @param {string} oauthToken - OAuth request token
 * @returns {string} X認証URL
 */
export const generateXAuthUrl = (oauthToken) => {
  return `https://api.x.com/oauth/authenticate?oauth_token=${encodeURIComponent(oauthToken)}`
}

