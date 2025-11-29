import { useState, useEffect, useCallback } from 'react'
import PropTypes from 'prop-types'
import './RecordRanking.css'
import StarRating from './StarRating'
import OverallRatingChart from './OverallRatingChart'
import OverallRatingPieChart from './OverallRatingPieChart'
import ShopTypeChart from './ShopTypeChart'
import { getApiUrl, fetchWithAuth, getAuthToken, handleAuthError } from '../utils/api'

function RecordRanking({ user, onShopClick, onGirlClick }) {
  const [rankingType, setRankingType] = useState('overall_rating') // 'overall_rating', 'face_rating', 'style_rating', 'service_rating', 'visit_count'
  const [records, setRecords] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)
  const [expandedCards, setExpandedCards] = useState(new Set())

  const fetchRanking = useCallback(async (type) => {
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
      const response = await fetchWithAuth(getApiUrl(`/api/records/ranking?type=${type}&limit=5`), { method: 'GET' })
      
      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || 'ランキングの取得に失敗しました')
      }

      setRecords(data.records || [])
    } catch (error) {
      console.error('Fetch ranking error:', error)
      setError(error.message || 'ランキングの取得中にエラーが発生しました')
    } finally {
      setIsLoading(false)
    }
  }, [user?.id])

  useEffect(() => {
    fetchRanking(rankingType)
  }, [user?.id, rankingType, fetchRanking])

  const formatDate = (dateString) => {
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    const day = date.getDate()
    return `${year}年${month}月${day}日`
  }

  const toggleCard = (recordId) => {
    // 利用回数ランキングの場合は展開しない
    if (rankingType === 'visit_count') {
      return
    }
    setExpandedCards(prev => {
      const newSet = new Set(prev)
      if (newSet.has(recordId)) {
        newSet.delete(recordId)
      } else {
        newSet.add(recordId)
      }
      return newSet
    })
  }

  const isExpanded = (recordId) => {
    return expandedCards.has(recordId)
  }

  return (
    <div className="record-ranking-container">
      <h3 className="ranking-section-title">最高の思い出</h3>
      
      <div className="ranking-type-selector">
        <select
          className="ranking-type-select"
          value={rankingType}
          onChange={(e) => setRankingType(e.target.value)}
        >
          <option value="overall_rating">総合評価</option>
          <option value="face_rating">顔の評価</option>
          <option value="style_rating">スタイルの評価</option>
          <option value="service_rating">接客の評価</option>
          <option value="visit_count">利用回数</option>
        </select>
      </div>

      <div className="ranking-section">
        {isLoading && (
          <div className="loading-message">読み込み中...</div>
        )}
        {!isLoading && error && (
          <div className="error-message">{error}</div>
        )}
        {!isLoading && !error && records.length === 0 && (
          <div className="empty-message">まだ記録がありません。新しい記録を登録してください。</div>
        )}
        {!isLoading && !error && records.length > 0 && (
          <div className="ranking-list">
            {records.map((record, index) => {
              const expanded = isExpanded(record.id)
              const isClickable = rankingType !== 'visit_count'
              return (
                <div 
                  key={record.id} 
                  className={`ranking-item ${isClickable ? 'clickable' : ''} ${expanded ? 'expanded' : ''}`}
                  onClick={() => isClickable && toggleCard(record.id)}
                >
                  <div className="ranking-rank">#{index + 1}</div>
                  <div className="ranking-content">
                    <div className="ranking-top-section">
                      {record.girl_image_url && (
                        <div className="ranking-image-container">
                          <img
                            src={record.girl_image_url}
                            alt={record.girl_name || record.girl?.girl_name || '画像'}
                            className="ranking-image"
                            onError={(e) => {
                              e.target.style.display = 'none'
                            }}
                          />
                        </div>
                      )}
                      <div className="ranking-info">
                        <div className="ranking-header">
                          <div className="ranking-shop">
                            <span className="ranking-shop-type">
                              {record.shop_type || ''}
                            </span>
                            {(() => {
                              const shopName = record.shop?.shop_name || record.shop_name
                              const shopType = record.shop_type
                              return onShopClick && shopType && shopName ? (
                                <span 
                                  className="ranking-shop-name clickable"
                                  onClick={(e) => {
                                    e.stopPropagation()
                                    onShopClick(shopType, shopName)
                                  }}
                                >
                                  {shopName}
                                </span>
                              ) : (
                                <span className="ranking-shop-name">
                                  {shopName || ''}
                                </span>
                              )
                            })()}
                          </div>
                          <span className="ranking-date">
                            {formatDate(record.visit_date || record.created_at)}
                          </span>
                        </div>
                        {(record.girl_name || record.girl?.girl_name) && (
                          <div className="ranking-girl-name">
                            {(() => {
                              const girlName = record.girl_name || record.girl?.girl_name
                              return onGirlClick ? (
                                <span 
                                  className="ranking-girl-name-link clickable"
                                  onClick={(e) => {
                                    e.stopPropagation()
                                    onGirlClick(girlName)
                                  }}
                                >
                                  {girlName}
                                </span>
                              ) : (
                                <span>{girlName}</span>
                              )
                            })()}
                          </div>
                        )}
                      </div>
                    </div>
                    {/* 総合評価と展開時の追加情報 - 画像の下に横幅いっぱいに表示 */}
                    <div className="ranking-bottom-section">
                      <div className="ranking-details">
                        {rankingType === 'overall_rating' && record.overall_rating && (
                          <div className="ranking-rating">
                            <span className="ranking-rating-label">総合評価</span>
                            <StarRating rating={record.overall_rating} readonly={true} />
                          </div>
                        )}
                        {rankingType === 'face_rating' && record.face_rating && (
                          <div className="ranking-rating">
                            <span className="ranking-rating-label">顔の評価</span>
                            <StarRating rating={record.face_rating} readonly={true} />
                          </div>
                        )}
                        {rankingType === 'style_rating' && record.style_rating && (
                          <div className="ranking-rating">
                            <span className="ranking-rating-label">スタイルの評価</span>
                            <StarRating rating={record.style_rating} readonly={true} />
                          </div>
                        )}
                        {rankingType === 'service_rating' && record.service_rating && (
                          <div className="ranking-rating">
                            <span className="ranking-rating-label">接客の評価</span>
                            <StarRating rating={record.service_rating} readonly={true} />
                          </div>
                        )}
                        {rankingType === 'visit_count' && record.visit_count && (
                          <div className="ranking-visit-count">
                            <span className="ranking-visit-count-label">利用回数</span>
                            <span className="ranking-visit-count-value">{record.visit_count}回</span>
                          </div>
                        )}
                      </div>
                      {/* 展開時の追加情報 */}
                      {expanded && (
                        <div className="ranking-expanded-content">
                        {/* その他の評価 */}
                        {((rankingType !== 'face_rating' && record.face_rating) ||
                          (rankingType !== 'style_rating' && record.style_rating) ||
                          (rankingType !== 'service_rating' && record.service_rating) ||
                          (rankingType !== 'overall_rating' && record.overall_rating)) && (
                          <div className="ranking-other-ratings">
                            {rankingType !== 'face_rating' && record.face_rating && (
                              <div className="ranking-rating">
                                <span className="ranking-rating-label">顔</span>
                                <StarRating rating={record.face_rating} readonly={true} />
                              </div>
                            )}
                            {rankingType !== 'style_rating' && record.style_rating && (
                              <div className="ranking-rating">
                                <span className="ranking-rating-label">スタイル</span>
                                <StarRating rating={record.style_rating} readonly={true} />
                              </div>
                            )}
                            {rankingType !== 'service_rating' && record.service_rating && (
                              <div className="ranking-rating">
                                <span className="ranking-rating-label">接客</span>
                                <StarRating rating={record.service_rating} readonly={true} />
                              </div>
                            )}
                            {rankingType !== 'overall_rating' && record.overall_rating && (
                              <div className="ranking-rating">
                                <span className="ranking-rating-label">総合</span>
                                <StarRating rating={record.overall_rating} readonly={true} />
                              </div>
                            )}
                          </div>
                        )}
                        {/* 価格 */}
                        {record.price && (
                          <div className="ranking-price">
                            <span className="ranking-price-label">利用料金</span>
                            <span className="ranking-price-value">¥{record.price.toLocaleString()}</span>
                          </div>
                        )}
                        {/* コース */}
                        {record.course && (
                          <div className="ranking-course">
                            <span className="ranking-course-label">コース</span>
                            <span className="ranking-course-value">{record.course}</span>
                          </div>
                        )}
                        {/* 感想 */}
                        {record.review && (
                          <div className="ranking-review">
                            <div className="ranking-review-label">感想</div>
                            <div className="ranking-review-text">{record.review}</div>
                          </div>
                        )}
                      </div>
                      )}
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>

      {/* グラフセクション */}
      {!isLoading && records.length > 0 && (
        <div className="ranking-charts-section">
          <div className="ranking-chart-container">
            <h3 className="ranking-chart-title">総合評価の推移</h3>
            <OverallRatingChart user={user} />
          </div>
          <div className="ranking-chart-container">
            <h3 className="ranking-chart-title">総合評価の割合</h3>
            <OverallRatingPieChart user={user} />
          </div>
          <div className="ranking-chart-container">
            <h3 className="ranking-chart-title">投稿したお店の種類</h3>
            <ShopTypeChart user={user} />
          </div>
        </div>
      )}
    </div>
  )
}

RecordRanking.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
  onShopClick: PropTypes.func,
  onGirlClick: PropTypes.func,
}

export default RecordRanking

