import PropTypes from 'prop-types'
import './MyPage.css'

function MyPage({ user, onLogout }) {
  const handleLogout = () => {
    if (window.confirm('ログアウトしますか？')) {
      onLogout()
    }
  }

  return (
    <div className="mypage-container">
      <div className="mypage-user-section">
        {user && user.email && (
          <div className="mypage-user-info">
            <div className="mypage-user-details">
              <p className="mypage-user-email">{user.email}</p>
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
    </div>
  )
}

MyPage.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
  onLogout: PropTypes.func.isRequired,
}

export default MyPage

