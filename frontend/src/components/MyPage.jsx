import PropTypes from 'prop-types'
import './MyPage.css'
import { APP_VERSION } from '../config/version'

function MyPage({ user, onLogout }) {
  const handleLogout = () => {
    if (window.confirm('ログアウトしますか？')) {
      onLogout()
    }
  }

  // 表示するユーザー情報を決定（emailまたはusernameのみ）
  const displayInfo = user ? (user.email || user.username) : null

  // Googleアイコン
  const GoogleIcon = () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
      <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
      <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
      <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
    </svg>
  )

  // Xアイコン
  const XIcon = () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" fill="currentColor"/>
    </svg>
  )

  return (
    <div className="mypage-container">
      <div className="mypage-user-section">
        {user && displayInfo && (
          <div className="mypage-user-info">
            <div className="mypage-user-details">
              {user.email && (
                <div className="mypage-user-info-row">
                  <span className="mypage-provider-icon">
                    <GoogleIcon />
                  </span>
                  <p className="mypage-user-email">{user.email}</p>
                </div>
              )}
              {!user.email && user.username && (
                <div className="mypage-user-info-row">
                  <span className="mypage-provider-icon">
                    <XIcon />
                  </span>
                  <p className="mypage-user-email">@{user.username}</p>
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      <div className="mypage-menu-section">
        <div className="mypage-menu-list">
          <button 
            className="mypage-menu-item"
            onClick={handleLogout}
          >
            <span className="mypage-menu-label">ログアウト</span>
            <span className="mypage-menu-arrow">›</span>
          </button>
        </div>
      </div>

      <div className="mypage-menu-section">
        <div className="mypage-menu-list">
          <a
            href="https://x.com/hime_log_jp"
            target="_blank"
            rel="noopener noreferrer"
            className="mypage-menu-item mypage-menu-link"
          >
            <span className="mypage-menu-icon">
              <img src="/favicon.png" alt="ヒメログ" className="mypage-favicon" />
            </span>
            <span className="mypage-menu-label">ヒメログ公式Xアカウント</span>
            <span className="mypage-menu-arrow">›</span>
          </a>
        </div>
      </div>

      <div className="mypage-version">
        <p className="mypage-version-text">v{APP_VERSION}</p>
      </div>
    </div>
  )
}

MyPage.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    username: PropTypes.string,
    avatar: PropTypes.string,
  }),
  onLogout: PropTypes.func.isRequired,
}

export default MyPage

