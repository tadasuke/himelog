import { useState, useEffect } from 'react'
import Login from './components/Login'
import Home from './components/Home'
import MyPage from './components/MyPage'
import BottomNavigation from './components/BottomNavigation'
import { getApiUrl } from './utils/api'
import './App.css'

function App() {
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [user, setUser] = useState(null)
  const [currentPage, setCurrentPage] = useState('home')

  // ページロード時にローカルストレージからログイン状態を復元
  useEffect(() => {
    const savedUser = localStorage.getItem('user')
    const savedIsLoggedIn = localStorage.getItem('isLoggedIn')
    
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
      }
    }
  }, [])

  // Google Identity Services のスクリプトを読み込む
  useEffect(() => {
    const script = document.createElement('script')
    script.src = 'https://accounts.google.com/gsi/client'
    script.async = true
    script.defer = true
    document.body.appendChild(script)

    return () => {
      document.body.removeChild(script)
    }
  }, [])

  const handleGoogleLogin = async (credential) => {
    try {
      console.log('Sending token to backend...', { tokenLength: credential?.length })
      const response = await fetch(getApiUrl('/api/auth/google/login'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ token: credential }),
      })
      
      console.log('Response status:', response.status)
      console.log('Response headers:', Object.fromEntries(response.headers.entries()))
      
      let data
      try {
        data = await response.json()
      } catch (jsonError) {
        const text = await response.text()
        console.error('Failed to parse JSON:', text)
        alert(`ログインに失敗しました: サーバーからの応答が無効です (${response.status})`)
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
        
        setUser(data.user)
        setIsLoggedIn(true)
        setCurrentPage('home') // ログイン時はホームに設定
        console.log('Login successful:', data.user)
      } else {
        console.error('Login failed - invalid response:', data)
        alert('ログインに失敗しました: 無効なレスポンス')
      }
    } catch (error) {
      console.error('Login error:', error)
      alert(`ログイン中にエラーが発生しました: ${error.message}`)
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
      
      // フロントエンドの状態をリセット
      setUser(null)
      setIsLoggedIn(false)
      setCurrentPage('home') // ログアウト時もホームにリセット
      console.log('Logout successful')
    } catch (error) {
      console.error('Logout error:', error)
      // エラーが発生しても状態はリセットする
      localStorage.removeItem('user')
      localStorage.removeItem('isLoggedIn')
      setUser(null)
      setIsLoggedIn(false)
      setCurrentPage('home') // ログアウト時もホームにリセット
    }
  }

  const handleNavigate = (pageId) => {
    setCurrentPage(pageId)
    // ここで各ページへの遷移処理を追加できます
    console.log('Navigate to:', pageId)
  }

  const handleRecordAdded = () => {
    // 登録完了後はホームに戻る
    setCurrentPage('home')
  }

  const handleRecordsLoaded = (recordCount) => {
    // 記録が0件の場合は「かく」画面を表示
    if (recordCount === 0 && currentPage === 'home') {
      setCurrentPage('create')
    }
  }

  return (
    <div className="app">
      {!isLoggedIn ? (
        <Login onGoogleLogin={handleGoogleLogin} />
      ) : (
        <>
          {currentPage === 'mypage' ? (
            <MyPage user={user} onLogout={handleLogout} />
          ) : (
            <Home 
              user={user} 
              onLogout={handleLogout} 
              currentPage={currentPage}
              onRecordAdded={handleRecordAdded}
              onRecordsLoaded={handleRecordsLoaded}
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
