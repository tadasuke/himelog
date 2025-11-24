import { useState, useEffect, useMemo } from 'react'
import PropTypes from 'prop-types'
import './ShopDetail.css'
import StarRating from './StarRating'
import RecordForm from './RecordForm'
import { getApiUrl, getAuthHeaders, getAuthToken, handleAuthError } from '../utils/api'

function ShopDetail({ user, shopType, shopName, onGirlClick }) {
  const [records, setRecords] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)
  const [expandedCards, setExpandedCards] = useState(new Set())
  const [shop, setShop] = useState(null)
  const [isLoadingShop, setIsLoadingShop] = useState(false)
  const [isEditing, setIsEditing] = useState(false)
  const [memo, setMemo] = useState('')
  const [urls, setUrls] = useState([''])
  const [isSaving, setIsSaving] = useState(false)
  const [editingRecord, setEditingRecord] = useState(null)

  const fetchShopRecords = async () => {
    if (!user?.id || !shopType || !shopName) return

    // 認証トークンがない場合はAPIを呼び出さない
    const authToken = getAuthToken()
    if (!authToken) {
      console.warn('No auth token, skipping API call')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const params = new URLSearchParams({
        shop_type: shopType,
        shop_name: shopName,
      })
      const response = await fetch(
        getApiUrl(`/api/records/shop-records?${params}`),
        getAuthHeaders()
      )
      
      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || '記録の取得に失敗しました')
      }

      // 来店日の降順でソート（来店日がない場合は作成日を使用）
      const sortedRecords = (data.records || []).sort((a, b) => {
        const dateA = a.visit_date || a.created_at
        const dateB = b.visit_date || b.created_at
        return new Date(dateB) - new Date(dateA)
      })

      setRecords(sortedRecords)
    } catch (error) {
      console.error('Fetch shop records error:', error)
      setError(error.message || '記録の取得中にエラーが発生しました')
    } finally {
      setIsLoading(false)
    }
  }

  const fetchShop = async () => {
    if (!user?.id || !shopType || !shopName) return

    const authToken = getAuthToken()
    if (!authToken) {
      console.warn('No auth token, skipping API call')
      return
    }

    setIsLoadingShop(true)

    try {
      const params = new URLSearchParams({
        shop_type: shopType,
        shop_name: shopName,
      })
      const response = await fetch(
        getApiUrl(`/api/shops?${params}`),
        getAuthHeaders()
      )
      
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || 'お店情報の取得に失敗しました')
      }

      if (data.shop) {
        setShop(data.shop)
        setMemo(data.shop.memo || '')
        setUrls(data.shop.shop_urls && data.shop.shop_urls.length > 0 
          ? data.shop.shop_urls.map(url => url.url)
          : [''])
      } else {
        setShop(null)
        setMemo('')
        setUrls([''])
      }
    } catch (error) {
      console.error('Fetch shop error:', error)
      // エラーは表示しない（新規作成の場合もあるため）
    } finally {
      setIsLoadingShop(false)
    }
  }

  useEffect(() => {
    fetchShopRecords()
    fetchShop()
  }, [user?.id, shopType, shopName])

  const formatDate = (dateString) => {
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    const day = date.getDate()
    return `${year}年${month}月${day}日`
  }

  const getPreviewText = (text, maxLines = 2) => {
    if (!text) return ''
    const lines = text.split('\n')
    return lines.slice(0, maxLines).join('\n')
  }

  const toggleCard = (recordId) => {
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

  const handleEditRecord = (record) => {
    setEditingRecord(record)
  }

  const handleCancelEdit = () => {
    setEditingRecord(null)
  }

  const handleRecordUpdated = () => {
    fetchShopRecords()
    setEditingRecord(null)
  }

  const handleAddUrl = () => {
    setUrls([...urls, ''])
  }

  const handleRemoveUrl = (index) => {
    if (urls.length > 1) {
      setUrls(urls.filter((_, i) => i !== index))
    } else {
      setUrls([''])
    }
  }

  const handleUrlChange = (index, value) => {
    const newUrls = [...urls]
    newUrls[index] = value
    setUrls(newUrls)
  }

  const handleSave = async () => {
    if (!user?.id || !shopType || !shopName) return

    const authToken = getAuthToken()
    if (!authToken) {
      console.warn('No auth token, skipping API call')
      return
    }

    setIsSaving(true)
    setError(null)

    try {
      const response = await fetch(
        getApiUrl('/api/shops'),
        getAuthHeaders({
          method: 'POST',
          body: JSON.stringify({
            shop_type: shopType,
            shop_name: shopName,
            memo: memo.trim() || null,
            urls: urls.filter(url => url.trim()),
          }),
        })
      )
      
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || '保存に失敗しました')
      }

      setShop(data.shop)
      setIsEditing(false)
    } catch (error) {
      console.error('Save shop error:', error)
      setError(error.message || '保存中にエラーが発生しました')
    } finally {
      setIsSaving(false)
    }
  }

  const handleCancel = () => {
    if (shop) {
      setMemo(shop.memo || '')
      setUrls(shop.shop_urls && shop.shop_urls.length > 0 
        ? shop.shop_urls.map(url => url.url)
        : [''])
    } else {
      setMemo('')
      setUrls([''])
    }
    setIsEditing(false)
    setError(null)
  }

  // 総合評価の平均値を計算
  const averageOverallRating = useMemo(() => {
    if (!records || records.length === 0) return 0
    
    const ratingsWithValue = records
      .map(record => record.overall_rating)
      .filter(rating => rating !== null && rating !== undefined && rating > 0)
    
    if (ratingsWithValue.length === 0) return 0
    
    const sum = ratingsWithValue.reduce((acc, rating) => acc + rating, 0)
    return sum / ratingsWithValue.length
  }, [records])

  // 利用料金の合計を計算
  const totalPrice = useMemo(() => {
    if (!records || records.length === 0) return 0
    
    return records
      .map(record => record.price || 0)
      .reduce((acc, price) => acc + price, 0)
  }, [records])

  return (
    <div className="shop-detail-container">
      {user && editingRecord && (
        <RecordForm 
          userId={user.id} 
          onRecordAdded={handleRecordUpdated}
          editingRecord={editingRecord}
          onCancelEdit={handleCancelEdit}
        />
      )}
      {!editingRecord && (
        <>
      <div className="shop-detail-header">
        <div className="shop-detail-title-section">
          <h2 className="shop-detail-title">{shopName}</h2>
          <p className="shop-detail-subtitle">{shopType}</p>
          {records.length > 0 && (
            <div className="shop-detail-visit-count">
              <span className="shop-detail-visit-count-label">利用回数</span>
              <span className="shop-detail-visit-count-value">{records.length}回</span>
            </div>
          )}
          {records.length > 0 && totalPrice > 0 && (
            <div className="shop-detail-total-price">
              <span className="shop-detail-total-price-label">利用料金合計</span>
              <span className="shop-detail-total-price-value">¥{totalPrice.toLocaleString()}</span>
            </div>
          )}
          {records.length > 0 && averageOverallRating > 0 && (
            <div className="shop-detail-average-rating">
              <span className="shop-detail-average-rating-label">平均評価</span>
              <div className="shop-detail-average-rating-content">
                <StarRating rating={averageOverallRating} readonly={true} />
                <span className="shop-detail-average-rating-value">
                  {averageOverallRating.toFixed(1)}
                </span>
              </div>
            </div>
          )}
        </div>
      </div>

      {isLoading && (
        <div className="loading-message">読み込み中...</div>
      )}
      {error && (
        <div className="error-message">{error}</div>
      )}
      {/* お店情報セクション */}
      <div className="shop-detail-info-section">
        {!isLoadingShop && (
          <>
            {!isEditing ? (
              <div className="shop-detail-info-display">
                <div className="shop-detail-info-header">
                  <h3 className="shop-detail-info-title">お店情報</h3>
                  <button 
                    className="shop-detail-edit-btn"
                    onClick={() => setIsEditing(true)}
                  >
                    編集
                  </button>
                </div>
                
                {shop?.memo && (
                  <div className="shop-detail-memo-display">
                    <h4 className="shop-detail-memo-label">お店の感想</h4>
                    <p className="shop-detail-memo-text">{shop.memo}</p>
                  </div>
                )}
                
                {shop?.shop_urls && shop.shop_urls.length > 0 && (
                  <div className="shop-detail-urls-display">
                    <h4 className="shop-detail-urls-label">お店のURL</h4>
                    <ul className="shop-detail-urls-list">
                      {shop.shop_urls.map((shopUrl, index) => (
                        <li key={shopUrl.id || index} className="shop-detail-url-item">
                          <a 
                            href={shopUrl.url} 
                            target="_blank" 
                            rel="noopener noreferrer"
                            className="shop-detail-url-link"
                          >
                            {shopUrl.url}
                          </a>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                
                {(!shop?.memo && (!shop?.shop_urls || shop.shop_urls.length === 0)) && (
                  <div className="shop-detail-empty-info">
                    <p>お店情報が登録されていません。</p>
                    <button 
                      className="shop-detail-add-info-btn"
                      onClick={() => setIsEditing(true)}
                    >
                      情報を追加
                    </button>
                  </div>
                )}
              </div>
            ) : (
              <div className="shop-detail-info-edit">
                <div className="shop-detail-info-header">
                  <h3 className="shop-detail-info-title">お店情報を編集</h3>
                  <div className="shop-detail-edit-actions">
                    <button 
                      className="shop-detail-cancel-btn"
                      onClick={handleCancel}
                      disabled={isSaving}
                    >
                      キャンセル
                    </button>
                    <button 
                      className="shop-detail-save-btn"
                      onClick={handleSave}
                      disabled={isSaving}
                    >
                      {isSaving ? '保存中...' : '保存'}
                    </button>
                  </div>
                </div>
                
                <div className="shop-detail-form-group">
                  <label htmlFor="shop-memo" className="shop-detail-form-label">
                    お店の感想
                  </label>
                  <textarea
                    id="shop-memo"
                    className="shop-detail-form-textarea"
                    value={memo}
                    onChange={(e) => setMemo(e.target.value)}
                    placeholder="お店の感想を入力してください"
                    rows={5}
                  />
                </div>
                
                <div className="shop-detail-form-group">
                  <label className="shop-detail-form-label">
                    お店のURL
                  </label>
                  {urls.map((url, index) => (
                    <div key={index} className="shop-detail-url-input-group">
                      <input
                        type="url"
                        className="shop-detail-form-input"
                        value={url}
                        onChange={(e) => handleUrlChange(index, e.target.value)}
                        placeholder="https://example.com"
                      />
                      {urls.length > 1 && (
                        <button
                          type="button"
                          className="shop-detail-url-remove-btn"
                          onClick={() => handleRemoveUrl(index)}
                        >
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                          </svg>
                        </button>
                      )}
                    </div>
                  ))}
                  <button
                    type="button"
                    className="shop-detail-url-add-btn"
                    onClick={handleAddUrl}
                  >
                    + URLを追加
                  </button>
                </div>
                
                {error && (
                  <div className="shop-detail-error-message">{error}</div>
                )}
              </div>
            )}
          </>
        )}
      </div>

      {/* 記録一覧セクション */}
      {!isLoading && !error && records.length === 0 && (
        <div className="empty-message">このお店の記録はまだありません。</div>
      )}
      {!isLoading && !error && records.length > 0 && (
        <div className="shop-detail-records">
          <div className="shop-detail-records-header">
            <h3 className="shop-detail-records-title">記録一覧</h3>
          </div>
          <div className="logs-grid">
            {records.map((record) => {
              const isExpanded = expandedCards.has(record.id)
              return (
                <div key={record.id} className="log-card">
                  <div className="log-card-header">
                    <div className="log-card-shop">
                      <span className="log-card-shop-type">
                        {typeof record.shop_type === 'string' 
                          ? record.shop_type 
                          : record.shop_type?.name || record.shop_type_id || ''}
                      </span>
                      <span className="log-card-shop-name">{record.shop_name}</span>
                    </div>
                    <span className="log-card-date">
                      {record.visit_date ? formatDate(record.visit_date) : formatDate(record.created_at)}
                    </span>
                  </div>
                  <div className="log-card-info">
                    {record.girl_name ? (
                      <h3 
                        className="log-card-title log-card-title-clickable"
                        onClick={() => onGirlClick && onGirlClick(record.girl_name)}
                        style={{ cursor: onGirlClick ? 'pointer' : 'default' }}
                      >
                        {record.girl_name}
                      </h3>
                    ) : (
                      <h3 className="log-card-title">-</h3>
                    )}
                  </div>
                  <div className="log-card-ratings">
                    <div className="log-card-rating-item">
                      <span className="log-card-rating-label">総合</span>
                      <StarRating rating={record.overall_rating || 0} readonly={true} />
                    </div>
                    {isExpanded && (
                      <>
                        <div className="log-card-rating-item">
                          <span className="log-card-rating-label">顔</span>
                          <StarRating rating={record.face_rating || 0} readonly={true} />
                        </div>
                        <div className="log-card-rating-item">
                          <span className="log-card-rating-label">スタイル</span>
                          <StarRating rating={record.style_rating || 0} readonly={true} />
                        </div>
                        <div className="log-card-rating-item">
                          <span className="log-card-rating-label">接客</span>
                          <StarRating rating={record.service_rating || 0} readonly={true} />
                        </div>
                      </>
                    )}
                  </div>
                  {isExpanded && record.course && (
                    <div className="log-card-price">
                      <span className="log-card-price-label">コース</span>
                      <span className="log-card-price-value">{record.course}</span>
                    </div>
                  )}
                  {isExpanded && record.price && (
                    <div className="log-card-price">
                      <span className="log-card-price-label">利用料金</span>
                      <span className="log-card-price-value">¥{record.price.toLocaleString()}</span>
                    </div>
                  )}
                  {record.review && (
                    <p className={`log-card-preview ${isExpanded ? 'expanded' : 'collapsed'}`}>
                      {isExpanded ? record.review : getPreviewText(record.review, 2)}
                    </p>
                  )}
                  <div className="log-card-footer">
                    {isExpanded && (
                      <button 
                        className="log-card-btn log-card-btn-edit" 
                        onClick={() => handleEditRecord(record)}
                        title="編集"
                      >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M17 3C17.2652 3 17.5196 3.10536 17.7071 3.29289L20.7071 6.29289C20.8946 6.48043 21 6.73478 21 7C21 7.26522 20.8946 7.51957 20.7071 7.70711L8.70711 19.7071C8.51957 19.8946 8.26522 20 8 20H3C2.44772 20 2 19.5523 2 19V14C2 13.7348 2.10536 13.4804 2.29289 13.2929L14.2929 1.29289C14.4804 1.10536 14.7348 1 15 1H17V3Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        </svg>
                      </button>
                    )}
                    <div className="log-card-footer-right">
                      <button 
                        className="log-card-btn" 
                        onClick={() => toggleCard(record.id)}
                        title={isExpanded ? '折りたたむ' : '続きを見る'}
                      >
                        {isExpanded ? (
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 15L12 9L6 15" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                          </svg>
                        ) : (
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 9L12 15L18 9" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                          </svg>
                        )}
                      </button>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      )}
        </>
      )}
    </div>
  )
}

ShopDetail.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
  shopType: PropTypes.string.isRequired,
  shopName: PropTypes.string.isRequired,
  onGirlClick: PropTypes.func,
}

export default ShopDetail

