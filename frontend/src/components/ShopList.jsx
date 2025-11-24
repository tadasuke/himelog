import { useState, useEffect } from 'react'
import PropTypes from 'prop-types'
import './ShopList.css'
import { getApiUrl, getAuthHeaders, getAuthToken, handleAuthError } from '../utils/api'

function ShopList({ user }) {
  const [shops, setShops] = useState({})
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)

  const fetchShops = async () => {
    if (!user?.id) return

    // 認証トークンがない場合はAPIを呼び出さない
    const authToken = getAuthToken()
    if (!authToken) {
      console.warn('No auth token, skipping API call')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const response = await fetch(getApiUrl('/api/records/shops'), getAuthHeaders())
      
      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || 'お店一覧の取得に失敗しました')
      }

      setShops(data.shops || {})
    } catch (error) {
      console.error('Fetch shops error:', error)
      setError(error.message || 'お店一覧の取得中にエラーが発生しました')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    fetchShops()
  }, [user?.id])

  const shopTypes = Object.keys(shops).sort()

  return (
    <div className="shop-list-container">
      <h2 className="shop-list-title">お店一覧</h2>
      {isLoading && (
        <div className="loading-message">読み込み中...</div>
      )}
      {error && (
        <div className="error-message">{error}</div>
      )}
      {!isLoading && !error && shopTypes.length === 0 && (
        <div className="empty-message">まだお店が登録されていません。</div>
      )}
      {!isLoading && !error && shopTypes.length > 0 && (
        <div className="shop-list-content">
          {shopTypes.map((shopType) => (
            <div key={shopType} className="shop-type-group">
              <h3 className="shop-type-title">{shopType}</h3>
              <ul className="shop-name-list">
                {shops[shopType].map((shopName, index) => (
                  <li key={index} className="shop-name-item">
                    {shopName}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

ShopList.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
}

export default ShopList

