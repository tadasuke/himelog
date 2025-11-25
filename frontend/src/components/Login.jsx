import { useEffect, useRef, useState } from 'react'
import PropTypes from 'prop-types'
import './Login.css'
import { generateCodeVerifier, generateCodeChallenge, generateXAuthUrl } from '../utils/xAuth'
import { getApiUrl, setRefreshToken, setTokenExpiry } from '../utils/api'

function Login({ onGoogleLogin, onXLogin }) {
  const googleButtonRef = useRef(null)
  const [isXLoading, setIsXLoading] = useState(false)
  const xCallbackProcessed = useRef(false)
  const googleCallbackProcessed = useRef(false)

  // popupモードでは、リダイレクト後のコールバック処理は不要
  // コールバックは直接initializeのcallbackで処理される

  useEffect(() => {
    // Google Identity Services が読み込まれるまで待つ
    const initGoogleSignIn = () => {
      const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID || ''
      
      if (!clientId) {
        console.error('VITE_GOOGLE_CLIENT_ID is not set. Please check your .env file.')
        // エラーメッセージは画面に表示しない
        return
      }

      if (window.google && googleButtonRef.current) {
        try {
          // デバッグ情報を出力
          const currentUrl = window.location.href
          const origin = window.location.origin
          const pathname = window.location.pathname
          console.log('Google Sign-In initialization:', {
            currentUrl,
            origin,
            pathname,
            clientId: clientId.substring(0, 20) + '...'
          })

          window.google.accounts.id.initialize({
            client_id: clientId,
            ux_mode: 'popup', // ポップアップモードを使用
            callback: (response) => {
              // ポップアップモードでは、このコールバックが直接呼ばれる
              if (response.credential) {
                onGoogleLogin(response.credential)
              } else {
                console.error('No credential in response:', response)
              }
            },
            error_callback: (error) => {
              console.error('Google Sign-In error:', error)
              // エラーメッセージは画面に表示しない
            },
          })

          window.google.accounts.id.renderButton(
            googleButtonRef.current,
            {
              theme: 'outline',
              size: 'large',
              width: '100%',
              text: 'signin_with',
              locale: 'ja',
            }
          )
        } catch (error) {
          console.error('Error initializing Google Sign-In:', error)
          console.error('現在のURL:', window.location.origin)
          console.error('Client ID:', clientId)
          // エラーメッセージは画面に表示しない
        }
      }
    }

    // Google Identity Services の読み込みを待つ
    if (window.google) {
      initGoogleSignIn()
    } else {
      const checkInterval = setInterval(() => {
        if (window.google) {
          clearInterval(checkInterval)
          initGoogleSignIn()
        }
      }, 100)

      // 10秒後にタイムアウト
      setTimeout(() => {
        clearInterval(checkInterval)
        // エラーメッセージは画面に表示しない
      }, 10000)
    }

    // クリーンアップ
    return () => {
      if (googleButtonRef.current) {
        googleButtonRef.current.innerHTML = ''
      }
    }
  }, [onGoogleLogin])

  // X認証のコールバック処理
  useEffect(() => {
    // 既に処理済みの場合は何もしない
    if (xCallbackProcessed.current) {
      return
    }

    const handleXCallback = async () => {
      const urlParams = new URLSearchParams(window.location.search)
      const code = urlParams.get('code')
      const state = urlParams.get('state')
      const error = urlParams.get('error')

      // URLパラメータを即座に削除して、重複リクエストを防ぐ
      window.history.replaceState({}, document.title, window.location.pathname)

      if (error) {
        console.error('X auth error:', error)
        // エラーメッセージは画面に表示しない
        xCallbackProcessed.current = true
        return
      }

      if (code && state) {
        // 処理済みフラグを設定（重複リクエストを防ぐ）
        xCallbackProcessed.current = true

        // 保存されたstateとcode_verifierを取得
        const savedState = localStorage.getItem('x_auth_state')
        const codeVerifier = localStorage.getItem('x_code_verifier')

        if (!savedState || savedState !== state || !codeVerifier) {
          console.error('X auth: Invalid state or missing code_verifier')
          // エラーメッセージは画面に表示しない
          return
        }

        try {
          setIsXLoading(true)
          
          const apiUrl = getApiUrl('/api/auth/x/callback')
          const requestBody = {
            code,
            code_verifier: codeVerifier,
            redirect_uri: window.location.origin + window.location.pathname,
          }
          
          console.log('X callback: Sending request to backend', {
            url: apiUrl,
            code: code.substring(0, 20) + '...',
            code_verifier: codeVerifier.substring(0, 20) + '...',
            redirect_uri: requestBody.redirect_uri
          })
          
          // 認証コードをアクセストークンに交換（バックエンドで処理）
          const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestBody),
          })

          console.log('X callback: Response received', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok
          })

          let data
          try {
            data = await response.json()
            console.log('X callback: Response data', data)
          } catch (jsonError) {
            const text = await response.text()
            console.error('X callback: Failed to parse JSON', {
              text,
              status: response.status
            })
            throw new Error(`サーバーからの応答が無効です (${response.status})`)
          }

          if (!response.ok) {
            throw new Error(data.message || data.error || 'X認証に失敗しました')
          }

          if (data.loggedIn && data.user) {
            // クリーンアップ
            localStorage.removeItem('x_auth_state')
            localStorage.removeItem('x_code_verifier')
            // リフレッシュトークンと有効期限を保存
            if (data.refresh_token) {
              setRefreshToken(data.refresh_token)
            }
            if (data.expires_in) {
              setTokenExpiry(data.expires_in)
            }
            // ログイン処理
            onXLogin(data.access_token)
          } else {
            throw new Error('無効なレスポンス')
          }
        } catch (error) {
          console.error('X auth callback error:', error)
          // エラーメッセージは画面に表示しない
          // クリーンアップ
          localStorage.removeItem('x_auth_state')
          localStorage.removeItem('x_code_verifier')
          // エラー時は処理済みフラグをリセット（再試行可能にする）
          xCallbackProcessed.current = false
        } finally {
          setIsXLoading(false)
        }
      }
    }

    handleXCallback()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []) // 依存配列を空にして、マウント時のみ実行

  const handleXLogin = async () => {
    try {
      setIsXLoading(true)
      const clientId = import.meta.env.VITE_X_CLIENT_ID || ''
      
      if (!clientId) {
        console.error('X Client ID が設定されていません')
        // エラーメッセージは画面に表示しない
        setIsXLoading(false)
        return
      }

      // PKCEパラメータを生成
      const codeVerifier = generateCodeVerifier()
      const codeChallenge = await generateCodeChallenge(codeVerifier)
      const redirectUri = window.location.origin + window.location.pathname

      // 認証URLを生成
      const authUrl = generateXAuthUrl(clientId, redirectUri, codeChallenge)

      // stateとcode_verifierを保存
      const state = new URLSearchParams(authUrl).get('state')
      localStorage.setItem('x_auth_state', state)
      localStorage.setItem('x_code_verifier', codeVerifier)

      // X認証ページにリダイレクト
      window.location.href = authUrl
    } catch (error) {
      console.error('X login error:', error)
      // エラーメッセージは画面に表示しない
      setIsXLoading(false)
    }
  }

  return (
    <div className="login-container">
      <div className="login-card">
        <div className="login-header">
          <h1 className="login-title">ヒメログ</h1>
          <p className="login-subtitle">プライベートログサービス</p>
        </div>
        <div ref={googleButtonRef} className="google-signin-button"></div>
        <div className="login-divider">
          <span className="divider-text">または</span>
        </div>
        <button
          className="x-login-btn"
          onClick={handleXLogin}
          disabled={isXLoading}
        >
          {isXLoading ? (
            <span>認証中...</span>
          ) : (
            <>
              <svg className="x-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg>
              <span>Xでログイン</span>
            </>
          )}
        </button>
      </div>
    </div>
  )
}

Login.propTypes = {
  onGoogleLogin: PropTypes.func.isRequired,
  onXLogin: PropTypes.func.isRequired,
}

export default Login

