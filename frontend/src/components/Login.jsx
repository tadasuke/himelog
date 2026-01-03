import { useEffect, useRef, useState } from 'react'
import PropTypes from 'prop-types'
import './Login.css'
import { generateXAuthUrl, getOAuthTokenFromUrl, getOAuthVerifierFromUrl, getErrorFromUrl } from '../utils/xAuth'
import { getApiUrl, setRefreshToken, setTokenExpiry } from '../utils/api'

function Login({ onGoogleLogin, onXLogin }) {
  const googleButtonRef = useRef(null)
  const [isGoogleLoading, setIsGoogleLoading] = useState(false)
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

      if (window.google) {
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
              setIsGoogleLoading(false)
              if (response.credential) {
                onGoogleLogin(response.credential)
              } else {
                console.error('No credential in response:', response)
              }
            },
            error_callback: (error) => {
              console.error('Google Sign-In error:', error)
              setIsGoogleLoading(false)
              // エラーメッセージは画面に表示しない
            },
          })

          // 非表示のボタンをレンダリングして、カスタムボタンからクリックをトリガー
          if (googleButtonRef.current) {
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
          }
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

  // X認証のコールバック処理（OAuth 1.0a）
  useEffect(() => {
    // 既に処理済みの場合は何もしない
    if (xCallbackProcessed.current) {
      return
    }

    const handleXCallback = async () => {
      const oauthToken = getOAuthTokenFromUrl()
      const oauthVerifier = getOAuthVerifierFromUrl()
      const error = getErrorFromUrl()

      // URLパラメータを即座に削除して、重複リクエストを防ぐ
      window.history.replaceState({}, document.title, window.location.pathname)

      if (error) {
        console.error('X auth error:', error)
        // エラーメッセージは画面に表示しない
        xCallbackProcessed.current = true
        return
      }

      if (oauthToken && oauthVerifier) {
        // 処理済みフラグを設定（重複リクエストを防ぐ）
        xCallbackProcessed.current = true

        // 保存されたoauth_token_secretを取得
        const oauthTokenSecret = localStorage.getItem('x_oauth_token_secret')

        if (!oauthTokenSecret) {
          console.error('X auth: Missing oauth_token_secret')
          // エラーメッセージは画面に表示しない
          return
        }

        try {
          setIsXLoading(true)
          
          const apiUrl = getApiUrl('/api/auth/x/callback')
          const requestBody = {
            oauth_token: oauthToken,
            oauth_verifier: oauthVerifier,
            oauth_token_secret: oauthTokenSecret,
          }
          
          console.log('X callback: Sending request to backend', {
            url: apiUrl,
            oauth_token: oauthToken.substring(0, 20) + '...',
          })
          
          // アクセストークンを取得（バックエンドで処理）
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
            localStorage.removeItem('x_oauth_token_secret')
            // アクセストークンとアクセストークンシークレットをJSON文字列として保存
            const tokenJson = JSON.stringify({
              access_token: data.access_token,
              access_token_secret: data.access_token_secret,
            })
            // ログイン処理
            onXLogin(tokenJson)
          } else {
            throw new Error('無効なレスポンス')
          }
        } catch (error) {
          console.error('X auth callback error:', error)
          // エラーメッセージは画面に表示しない
          // クリーンアップ
          localStorage.removeItem('x_oauth_token_secret')
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

  const handleGoogleLogin = () => {
    if (!window.google || !googleButtonRef.current) {
      console.error('Google Identity Services が読み込まれていません')
      return
    }

    try {
      setIsGoogleLoading(true)
      // 非表示のGoogleボタンをクリックして認証を開始
      // renderButtonで生成されたボタンはdiv要素としてレンダリングされる
      const buttonContainer = googleButtonRef.current
      if (buttonContainer) {
        // ボタンがレンダリングされるまで少し待つ
        setTimeout(() => {
          // Google Identity Servicesのボタンは通常、最初のdiv要素がクリック可能
          const clickableElement = buttonContainer.querySelector('div[role="button"]') || 
                                   buttonContainer.querySelector('div') ||
                                   buttonContainer
          if (clickableElement) {
            // クリックイベントを発火
            clickableElement.click()
          } else {
            setIsGoogleLoading(false)
          }
        }, 100)
      }
    } catch (error) {
      console.error('Google login error:', error)
      setIsGoogleLoading(false)
    }
  }

  const handleXLogin = async () => {
    try {
      setIsXLoading(true)
      const callbackUrl = window.location.origin + window.location.pathname

      // ステップ1: リクエストトークンを取得
      const apiUrl = getApiUrl('/api/auth/x/request-token')
      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          callback_url: callbackUrl,
        }),
      })

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}))
        throw new Error(errorData.error || 'リクエストトークンの取得に失敗しました')
      }

      const tokenData = await response.json()
      const { oauth_token, oauth_token_secret } = tokenData

      if (!oauth_token || !oauth_token_secret) {
        throw new Error('リクエストトークンが取得できませんでした')
      }

      // oauth_token_secretを保存（コールバックで使用するため）
      localStorage.setItem('x_oauth_token_secret', oauth_token_secret)

      // ステップ2: X認証ページにリダイレクト
      const authUrl = generateXAuthUrl(oauth_token)
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
        <div ref={googleButtonRef} className="google-signin-button-hidden"></div>
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
        <div className="login-divider">
          <span className="divider-text">または</span>
        </div>
        <button
          className="google-login-btn"
          onClick={handleGoogleLogin}
          disabled={isGoogleLoading}
        >
          {isGoogleLoading ? (
            <span>認証中...</span>
          ) : (
            <>
              <svg className="google-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
              </svg>
              <span>Googleでログイン</span>
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

