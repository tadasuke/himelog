import { useState, useEffect } from 'react'
import PropTypes from 'prop-types'
import './GirlList.css'
import StarRating from './StarRating'
import { getApiUrl, getAuthHeaders, getAuthToken, handleAuthError } from '../utils/api'

function GirlList({ user, onShopClick, onGirlClick }) {
  const [girls, setGirls] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)

  const fetchGirls = async () => {
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
      const response = await fetch(getApiUrl('/api/girls/list'), getAuthHeaders())
      
      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || 'ヒメ一覧の取得に失敗しました')
      }

      setGirls(data.girls || [])
    } catch (error) {
      console.error('Fetch girls error:', error)
      setError(error.message || 'ヒメ一覧の取得中にエラーが発生しました')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    fetchGirls()
  }, [user?.id])

  const formatDate = (dateString) => {
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    const day = date.getDate()
    return `${year}年${month}月${day}日`
  }

  return (
    <div className="girl-list-container">
      <div className="girl-list-section">
        {isLoading && (
          <div className="loading-message">読み込み中...</div>
        )}
        {error && (
          <div className="error-message">{error}</div>
        )}
        {!isLoading && !error && girls.length === 0 && (
          <div className="empty-message">まだヒメの記録がありません。新しい記録を登録してください。</div>
        )}
        {!isLoading && !error && girls.length > 0 && (
          <div className="logs-grid">
            {girls.map((girl) => {
              return (
                <div key={girl.girl_name} className="log-card">
                  <div className="log-card-header">
                    <div className="log-card-shop">
                      <span className="log-card-shop-type">
                        {girl.shop_type || ''}
                      </span>
                      <span 
                        className="log-card-shop-name clickable"
                        onClick={(e) => {
                          e.stopPropagation()
                          if (onShopClick) {
                            onShopClick(girl.shop_type, girl.shop_name)
                          }
                        }}
                      >
                        {girl.shop_name}
                      </span>
                    </div>
                    <span className="log-card-date">
                      {formatDate(girl.last_registered_at)}
                    </span>
                  </div>
                  <div className="log-card-info">
                    <h3 
                      className="log-card-title clickable"
                      onClick={(e) => {
                        e.stopPropagation()
                        if (onGirlClick && girl.girl_name) {
                          onGirlClick(girl.girl_name)
                        }
                      }}
                    >
                      {girl.girl_name}
                    </h3>
                  </div>
                  <div className="log-card-ratings">
                    <div className="log-card-rating-item">
                      <span className="log-card-rating-label">会った回数</span>
                      <span className="log-card-rating-value">{girl.record_count || 0}回</span>
                    </div>
                    <div className="log-card-rating-item">
                      <span className="log-card-rating-label">総合</span>
                      <StarRating rating={girl.average_overall_rating || 0} readonly={true} />
                      {girl.average_overall_rating !== null && (
                        <span className="log-card-rating-value">({girl.average_overall_rating})</span>
                      )}
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}

GirlList.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
  onShopClick: PropTypes.func,
  onGirlClick: PropTypes.func,
}

export default GirlList

