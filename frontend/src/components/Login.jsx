import { useEffect, useRef } from 'react'
import PropTypes from 'prop-types'
import './Login.css'

function Login({ onGoogleLogin }) {
  const googleButtonRef = useRef(null)

  useEffect(() => {
    // Google Identity Services が読み込まれるまで待つ
    const initGoogleSignIn = () => {
      const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID || ''
      
      if (!clientId) {
        console.error('VITE_GOOGLE_CLIENT_ID is not set. Please check your .env file.')
        if (googleButtonRef.current) {
          googleButtonRef.current.innerHTML = '<p style="color: #ff6b6b; text-align: center; padding: 12px;">Google Client ID が設定されていません</p>'
        }
        return
      }

      if (window.google && googleButtonRef.current) {
        try {
          window.google.accounts.id.initialize({
            client_id: clientId,
            callback: (response) => {
              if (response.credential) {
                onGoogleLogin(response.credential)
              } else {
                console.error('No credential in response:', response)
              }
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
          if (googleButtonRef.current) {
            googleButtonRef.current.innerHTML = '<p style="color: #ff6b6b; text-align: center; padding: 12px;">Google認証の初期化に失敗しました</p>'
          }
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
        if (!window.google && googleButtonRef.current) {
          googleButtonRef.current.innerHTML = '<p style="color: #ff6b6b; text-align: center; padding: 12px;">Google Identity Services の読み込みに失敗しました</p>'
        }
      }, 10000)
    }

    // クリーンアップ
    return () => {
      if (googleButtonRef.current) {
        googleButtonRef.current.innerHTML = ''
      }
    }
  }, [onGoogleLogin])

  return (
    <div className="login-container">
      <div className="login-card">
        <div className="login-header">
          <h1 className="login-title">ヒメログ</h1>
          <p className="login-subtitle">プライベートログサービス</p>
        </div>
        <div ref={googleButtonRef} className="google-signin-button"></div>
      </div>
    </div>
  )
}

Login.propTypes = {
  onGoogleLogin: PropTypes.func.isRequired,
}

export default Login

