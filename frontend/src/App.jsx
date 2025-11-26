import { useState, useEffect, useRef, useCallback } from 'react'
import Login from './components/Login'
import Home from './components/Home'
import MyPage from './components/MyPage'
import ShopList from './components/ShopList'
import ShopDetail from './components/ShopDetail'
import GirlList from './components/GirlList'
import GirlDetail from './components/GirlDetail'
import BottomNavigation from './components/BottomNavigation'
import { getApiUrl, removeAuthToken, getAuthToken, setRefreshToken, setTokenExpiry, removeRefreshToken, removeTokenExpiry } from './utils/api'
import './App.css'

/**
 * エラーメッセージをユーザー向けの安全なメッセージに変換
 * システムの内部構造が分かるような情報を除去する
 */
const sanitizeErrorMessage = (errorMessage) => {
  if (!errorMessage || typeof errorMessage !== 'string') {
    return 'エラーが発生しました。しばらくしてから再度お試しください。'
  }

  // システムの内部構造が分かるキーワードを含む場合は、一般的なメッセージに置き換え
  const sensitiveKeywords = [
    'VITE_',
    '.env',
    'API URL',
    'API endpoint',
    'loggedIn',
    'hasUser',
    'CORS',
    'プロキシ',
    'proxy',
    'サーバーからの応答が空',
    'Failed to fetch',
    'NetworkError',
    'fetch',
    'localhost',
    '127.0.0.1',
    'code_verifier',
    'state',
    'credential',
    'token',
    'refresh_token',
    'access_token',
  ]

  const lowerMessage = errorMessage.toLowerCase()
  for (const keyword of sensitiveKeywords) {
    if (lowerMessage.includes(keyword.toLowerCase())) {
      return 'エラーが発生しました。しばらくしてから再度お試しください。'
    }
  }

  // ステータスコードのみを含む場合は、一般的なメッセージに置き換え
  if (/\(\d{3}\)/.test(errorMessage) && errorMessage.length < 50) {
    return 'エラーが発生しました。しばらくしてから再度お試しください。'
  }

  // その他の場合は、元のメッセージを返す（ただし、長すぎる場合は切り詰める）
  if (errorMessage.length > 200) {
    return 'エラーが発生しました。しばらくしてから再度お試しください。'
  }

  return errorMessage
}

function App() {
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [user, setUser] = useState(null)
  const [currentPage, setCurrentPage] = useState('home')
  const [selectedShop, setSelectedShop] = useState(null)
  const [selectedGirl, setSelectedGirl] = useState(null)

  // ページロード時にローカルストレージからログイン状態を復元
  useEffect(() => {
    const savedUser = localStorage.getItem('user')
    const savedIsLoggedIn = localStorage.getItem('isLoggedIn')
    const authToken = getAuthToken()
    
    // 認証トークンがない場合はログイン状態をクリア
    if (!authToken) {
      if (savedUser || savedIsLoggedIn) {
        localStorage.removeItem('user')
        localStorage.removeItem('isLoggedIn')
        removeAuthToken()
        removeRefreshToken()
        removeTokenExpiry()
        setIsLoggedIn(false)
        setUser(null)
      }
      return
    }
    
    if (savedUser && savedIsLoggedIn === 'true') {
      try {
        const user = JSON.parse(savedUser)
        setUser(user)
        setIsLoggedIn(true)
        setCurrentPage('home') // ログイン復元時もホームに設定
        console.log('Login state restored from localStorage:', user)
      } catch (error) {
        console.error('Failed to parse saved user data:', error)
        // パースに失敗した場合はクリア
        localStorage.removeItem('user')
        localStorage.removeItem('isLoggedIn')
        removeAuthToken()
        removeRefreshToken()
        removeTokenExpiry()
        setIsLoggedIn(false)
        setUser(null)
      }
    } else {
      // ユーザー情報がない場合はログイン状態をクリア
      removeAuthToken()
      removeRefreshToken()
      removeTokenExpiry()
      setIsLoggedIn(false)
      setUser(null)
    }
  }, [])

  // ページ遷移時にトップにスクロール
  useEffect(() => {
    // requestAnimationFrameを使って、次のフレームでスクロール
    // これにより、コンテンツがレンダリングされた後にスクロールが実行される
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        window.scrollTo({ top: 0, behavior: 'auto' })
        // iOS Safariで確実にスクロール位置をリセットするため、document.documentElementも設定
        document.documentElement.scrollTop = 0
        document.body.scrollTop = 0
      })
    })
  }, [currentPage, selectedShop, selectedGirl])

  // Google Identity Services のスクリプトを読み込む
  useEffect(() => {
    const script = document.createElement('script')
    script.src = 'https://accounts.google.com/gsi/client'
    script.async = true
    script.defer = true
    
    // エラーハンドリング
    script.onerror = () => {
      console.error('Failed to load Google Identity Services script')
      console.error('403エラーが発生している可能性があります。以下を確認してください:')
      console.error('1. Google Cloud Consoleで「承認済みのJavaScript生成元」に現在のドメインが追加されているか')
      console.error('2. 現在のURL:', window.location.origin)
      console.error('3. OAuth同意画面が正しく設定されているか')
    }
    
    script.onload = () => {
      console.log('Google Identity Services script loaded successfully')
    }
    
    document.body.appendChild(script)

    return () => {
      if (document.body.contains(script)) {
        document.body.removeChild(script)
      }
    }
  }, [])

  const handleGoogleLogin = useCallback(async (credential) => {
    try {
      console.log('Sending token to backend...', { tokenLength: credential?.length })
      
      if (!credential) {
        console.error('No credential provided')
        alert('ログインに失敗しました: 認証情報が取得できませんでした')
        return
      }
      
      const apiUrl = getApiUrl('/api/auth/google/login')
      console.log('=== Google Login Debug Info ===')
      console.log('API URL:', apiUrl)
      console.log('Current location:', window.location.href)
      console.log('Hostname:', window.location.hostname)
      console.log('VITE_API_BASE_URL from env:', import.meta.env.VITE_API_BASE_URL)
      console.log('================================')
      
      let response
      try {
        response = await fetch(apiUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ token: credential }),
        })
      } catch (fetchError) {
        console.error('Fetch error details:', {
          name: fetchError.name,
          message: fetchError.message,
          stack: fetchError.stack,
          apiUrl: apiUrl,
          hostname: window.location.hostname,
          origin: window.location.origin
        })
        
        // ネットワークエラーの場合
        const errorMessage = 'ネットワークエラーが発生しました。しばらくしてから再度お試しください。'
        alert(`ログインに失敗しました: ${errorMessage}`)
        throw fetchError
      }
      
      console.log('Response status:', response.status)
      console.log('Response headers:', Object.fromEntries(response.headers.entries()))
      
      let data
      try {
        const responseText = await response.text()
        console.log('Response text:', responseText.substring(0, 500))
        
        if (!responseText) {
          console.error('Empty response body')
          alert('ログインに失敗しました: サーバーからの応答が無効です')
          return
        }
        
        data = JSON.parse(responseText)
      } catch (jsonError) {
        console.error('Failed to parse JSON:', jsonError)
        console.error('Response status:', response.status)
        console.error('Response headers:', Object.fromEntries(response.headers.entries()))
        
        let errorMessage = 'サーバーからの応答が無効です'
        if (response.status === 0) {
          errorMessage = 'ネットワークエラーが発生しました。しばらくしてから再度お試しください。'
        } else if (response.status >= 500) {
          errorMessage = 'サーバーエラーが発生しました。しばらくしてから再度お試しください。'
        } else if (response.status === 401) {
          errorMessage = '認証に失敗しました。再度ログインをお試しください。'
        } else if (response.status === 404) {
          errorMessage = 'エラーが発生しました。しばらくしてから再度お試しください。'
        }
        
        alert(`ログインに失敗しました: ${errorMessage}`)
        return
      }
      
      console.log('Response data:', data)
      
      if (!response.ok) {
        // エラーレスポンスの場合
        const errorMessage = data.message || data.error || 'ログインに失敗しました'
        console.error('Login failed:', data)
        alert(`ログインに失敗しました: ${errorMessage}`)
        return
      }
      
      if (data.loggedIn && data.user) {
        // ローカルストレージに保存
        localStorage.setItem('user', JSON.stringify(data.user))
        localStorage.setItem('isLoggedIn', 'true')
        // 認証トークンを保存
        if (credential) {
          localStorage.setItem('authToken', credential)
        }
        
        setUser(data.user)
        setIsLoggedIn(true)
        setCurrentPage('home') // ログイン時はホームに設定
        // ページ遷移時にトップにスクロール（useEffectで処理されるため、ここでは不要だが念のため）
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            window.scrollTo({ top: 0, behavior: 'auto' })
            document.documentElement.scrollTop = 0
            document.body.scrollTop = 0
          })
        })
        console.log('Login successful:', data.user)
      } else {
        console.error('Login failed - invalid response:', data)
        console.error('Expected loggedIn and user, got:', { loggedIn: data.loggedIn, hasUser: !!data.user })
        alert('ログインに失敗しました: 無効なレスポンス')
      }
    } catch (error) {
      console.error('Login error:', error)
      console.error('Error details:', {
        name: error.name,
        message: error.message,
        stack: error.stack
      })
      
      // エラーメッセージを安全なメッセージに変換
      let errorMessage = 'エラーが発生しました。しばらくしてから再度お試しください。'
      
      // ネットワークエラーの場合
      if (error.name === 'TypeError' && error.message && error.message.includes('fetch')) {
        errorMessage = 'ネットワークエラーが発生しました。しばらくしてから再度お試しください。'
      } else if (error.name === 'NetworkError' || (error.message && error.message.includes('NetworkError'))) {
        errorMessage = 'ネットワークエラーが発生しました。しばらくしてから再度お試しください。'
      } else if (error.message) {
        // エラーメッセージをサニタイズ
        errorMessage = sanitizeErrorMessage(error.message)
      }
      
      alert(`ログイン中にエラーが発生しました: ${errorMessage}`)
    }
  }, [])

  // popupモードでは、リダイレクト後のコールバック処理は不要
  // コールバックは直接Loginコンポーネントのinitializeのcallbackで処理される

  const handleXLogin = async (accessToken) => {
    try {
      console.log('Sending X access token to backend...')
      const response = await fetch(getApiUrl('/api/auth/x/login'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ token: accessToken }),
      })
      
      console.log('Response status:', response.status)
      
      let data
      try {
        data = await response.json()
      } catch (jsonError) {
        const text = await response.text()
        console.error('Failed to parse JSON:', text)
        alert('ログインに失敗しました: サーバーからの応答が無効です')
        return
      }
      
      console.log('Response data:', data)
      
      if (!response.ok) {
        // エラーレスポンスの場合
        const errorMessage = data.message || data.error || 'ログインに失敗しました'
        console.error('Login failed:', data)
        alert(`ログインに失敗しました: ${errorMessage}`)
        return
      }
      
      if (data.loggedIn && data.user) {
        // ローカルストレージに保存
        localStorage.setItem('user', JSON.stringify(data.user))
        localStorage.setItem('isLoggedIn', 'true')
        // 認証トークンを保存
        if (accessToken) {
          localStorage.setItem('authToken', accessToken)
        }
        // リフレッシュトークンと有効期限を保存
        if (data.refresh_token) {
          setRefreshToken(data.refresh_token)
        }
        if (data.expires_in) {
          setTokenExpiry(data.expires_in)
        }
        
        setUser(data.user)
        setIsLoggedIn(true)
        setCurrentPage('home') // ログイン時はホームに設定
        // ページ遷移時にトップにスクロール（useEffectで処理されるため、ここでは不要だが念のため）
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            window.scrollTo({ top: 0, behavior: 'auto' })
            document.documentElement.scrollTop = 0
            document.body.scrollTop = 0
          })
        })
        console.log('X login successful:', data.user)
      } else {
        console.error('Login failed - invalid response:', data)
        alert('ログインに失敗しました: 無効なレスポンス')
      }
    } catch (error) {
      console.error('X login error:', error)
      const errorMessage = sanitizeErrorMessage(error.message)
      alert(`ログイン中にエラーが発生しました: ${errorMessage}`)
    }
  }

  const handleLogout = async () => {
    try {
      // バックエンドにログアウトリクエストを送信（オプション）
      try {
        await fetch(getApiUrl('/api/auth/logout'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
        })
      } catch (error) {
        // バックエンドのログアウトが失敗しても、フロントエンドの状態はリセットする
        console.warn('Logout request to backend failed:', error)
      }
      
      // ローカルストレージをクリア
      localStorage.removeItem('user')
      localStorage.removeItem('isLoggedIn')
      removeAuthToken()
      removeRefreshToken()
      removeTokenExpiry()
      
      // フロントエンドの状態をリセット
      setUser(null)
      setIsLoggedIn(false)
      setCurrentPage('home') // ログアウト時もホームにリセット
      // ページ遷移時にトップにスクロール（useEffectで処理されるため、ここでは不要だが念のため）
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          window.scrollTo({ top: 0, behavior: 'auto' })
          document.documentElement.scrollTop = 0
          document.body.scrollTop = 0
        })
      })
      console.log('Logout successful')
    } catch (error) {
      console.error('Logout error:', error)
      // エラーが発生しても状態はリセットする
      localStorage.removeItem('user')
      localStorage.removeItem('isLoggedIn')
      removeAuthToken()
      removeRefreshToken()
      removeTokenExpiry()
      setUser(null)
      setIsLoggedIn(false)
      setCurrentPage('home') // ログアウト時もホームにリセット
      // ページ遷移時にトップにスクロール（useEffectで処理されるため、ここでは不要だが念のため）
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          window.scrollTo({ top: 0, behavior: 'auto' })
          document.documentElement.scrollTop = 0
          document.body.scrollTop = 0
        })
      })
    }
  }

  const handleNavigate = (pageId) => {
    // 詳細画面を閉じて、指定されたページに遷移
    setSelectedShop(null)
    setSelectedGirl(null)
    setCurrentPage(pageId)
    // ページ遷移時にトップにスクロール（useEffectで処理されるため、ここでは不要だが念のため）
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        window.scrollTo({ top: 0, behavior: 'auto' })
        document.documentElement.scrollTop = 0
        document.body.scrollTop = 0
      })
    })
    console.log('Navigate to:', pageId)
  }

  const handleRecordAdded = () => {
    // 登録完了後はホームに戻る
    setCurrentPage('home')
    // ページ遷移時にトップにスクロール（useEffectで処理されるため、ここでは不要だが念のため）
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        window.scrollTo({ top: 0, behavior: 'auto' })
        document.documentElement.scrollTop = 0
        document.body.scrollTop = 0
      })
    })
  }

  const handleRecordsLoaded = (recordCount) => {
    // 記録が0件の場合は「かく」画面を表示
    if (recordCount === 0 && currentPage === 'home') {
      setCurrentPage('create')
    }
  }

  const handleShopClick = (shopType, shopName) => {
    setSelectedShop({ shopType, shopName })
    // お店詳細画面に遷移する際は、ヒメ詳細画面を閉じる
    setSelectedGirl(null)
    // お店詳細画面を表示する際は、お店メニューをアクティブにする
    setCurrentPage('discover')
    // ページ遷移時にトップにスクロール（useEffectで処理されるため、ここでは不要だが念のため）
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        window.scrollTo({ top: 0, behavior: 'auto' })
        document.documentElement.scrollTop = 0
        document.body.scrollTop = 0
      })
    })
  }

  const handleGirlClick = (girlName) => {
    // お店詳細画面を閉じて、ヒメ詳細画面を表示
    setSelectedShop(null)
    setSelectedGirl(girlName)
    // ヒメ詳細画面を表示する際は、ヒメメニューをアクティブにする
    setCurrentPage('girls')
    // ページ遷移時にトップにスクロール（useEffectで処理されるため、ここでは不要だが念のため）
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        window.scrollTo({ top: 0, behavior: 'auto' })
        document.documentElement.scrollTop = 0
        document.body.scrollTop = 0
      })
    })
  }

  const handleGirlDetailBack = () => {
    setSelectedGirl(null)
    // ページ遷移時にトップにスクロール（useEffectで処理されるため、ここでは不要だが念のため）
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        window.scrollTo({ top: 0, behavior: 'auto' })
        document.documentElement.scrollTop = 0
        document.body.scrollTop = 0
      })
    })
  }

  return (
    <div className="app">
      {!isLoggedIn ? (
        <Login onGoogleLogin={handleGoogleLogin} onXLogin={handleXLogin} />
      ) : (
        <>
          {selectedShop ? (
            <ShopDetail 
              user={user}
              shopType={selectedShop.shopType}
              shopName={selectedShop.shopName}
              onGirlClick={handleGirlClick}
            />
          ) : selectedGirl ? (
            <GirlDetail 
              user={user}
              girlName={selectedGirl}
              onShopClick={handleShopClick}
            />
          ) : currentPage === 'mypage' ? (
            <MyPage user={user} onLogout={handleLogout} />
          ) : currentPage === 'discover' ? (
            <ShopList user={user} onShopClick={handleShopClick} />
          ) : currentPage === 'girls' ? (
            <GirlList user={user} onShopClick={handleShopClick} onGirlClick={handleGirlClick} />
          ) : (
            <Home 
              user={user} 
              onLogout={handleLogout} 
              currentPage={currentPage}
              onRecordAdded={handleRecordAdded}
              onRecordsLoaded={handleRecordsLoaded}
              onShopClick={handleShopClick}
              onGirlClick={handleGirlClick}
            />
          )}
          <BottomNavigation 
            user={user} 
            currentPage={currentPage} 
            onNavigate={handleNavigate}
          />
        </>
      )}
    </div>
  )
}

export default App
