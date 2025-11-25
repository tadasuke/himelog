import { useState, useEffect } from 'react'
import PropTypes from 'prop-types'
import './ShopList.css'
import StarRating from './StarRating'
import { getApiUrl, getAuthHeaders, getAuthToken, handleAuthError } from '../utils/api'

function ShopList({ user, onShopClick }) {
  const [shops, setShops] = useState([])
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

      setShops(data.shops || [])
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

  const formatDate = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    const day = date.getDate()
    return `${year}年${month}月${day}日`
  }

  return (
    <div className="shop-list-container">
      <h2 className="shop-list-title">お店一覧</h2>
      {isLoading && (
        <div className="loading-message">読み込み中...</div>
      )}
      {error && (
        <div className="error-message">{error}</div>
      )}
      {!isLoading && !error && shops.length === 0 && (
        <div className="empty-message">まだお店が登録されていません。</div>
      )}
      {!isLoading && !error && shops.length > 0 && (
        <div className="shop-list-content">
          <ul className="shop-name-list">
            {shops.map((shop, index) => {
              const shopName = shop.name || ''
              const shopType = shop.shop_type || ''
              const visitCount = shop.visit_count || 0
              const averageRating = shop.average_rating || 0
              const lastVisitDate = shop.last_visit_date || ''
              
              return (
                <li 
                  key={index} 
                  className="shop-name-item"
                  onClick={() => onShopClick && onShopClick(shopType, shopName)}
                >
                  <div className="shop-name-item-header">
                    <span className="shop-name-item-name">{shopName}</span>
                  </div>
                  <div className="shop-name-item-stats">
                    <div className="shop-name-item-stat">
                      <span className="shop-name-item-stat-label">利用回数</span>
                      <span className="shop-name-item-stat-value">{visitCount}回</span>
                    </div>
                    {averageRating > 0 && (
                      <div className="shop-name-item-stat">
                        <span className="shop-name-item-stat-label">平均評価</span>
                        <div className="shop-name-item-rating">
                          <StarRating rating={averageRating} readonly={true} />
                          <span className="shop-name-item-rating-value">{averageRating}</span>
                        </div>
                      </div>
                    )}
                    {lastVisitDate && (
                      <div className="shop-name-item-stat">
                        <span className="shop-name-item-stat-label">最終利用日</span>
                        <span className="shop-name-item-stat-value">{formatDate(lastVisitDate)}</span>
                      </div>
                    )}
                  </div>
                </li>
              )
            })}
          </ul>
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
  onShopClick: PropTypes.func,
}

export default ShopList

