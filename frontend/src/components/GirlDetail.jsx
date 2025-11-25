import { useState, useEffect, useMemo } from 'react'
import PropTypes from 'prop-types'
import './GirlDetail.css'
import StarRating from './StarRating'
import RecordForm from './RecordForm'
import { getApiUrl, fetchWithAuth, getAuthToken, handleAuthError } from '../utils/api'

function GirlDetail({ user, girlName, onShopClick }) {
  const [records, setRecords] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)
  const [expandedCards, setExpandedCards] = useState(new Set())
  const [girl, setGirl] = useState(null)
  const [isLoadingGirl, setIsLoadingGirl] = useState(false)
  const [isEditing, setIsEditing] = useState(false)
  const [memo, setMemo] = useState('')
  const [urls, setUrls] = useState([''])
  const [imageUrls, setImageUrls] = useState([''])
  const [validImageUrls, setValidImageUrls] = useState([])
  const [currentImageIndex, setCurrentImageIndex] = useState(0)
  const [isSaving, setIsSaving] = useState(false)
  const [editingRecord, setEditingRecord] = useState(null)
  const [popupImageIndex, setPopupImageIndex] = useState(null)
  const [urlTitles, setUrlTitles] = useState({})

  const fetchGirlRecords = async () => {
    if (!user?.id || !girlName) return

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
        girl_name: girlName,
      })
      const response = await fetchWithAuth(
        getApiUrl(`/api/records/girl-records?${params}`),
        { method: 'GET' }
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
      console.error('Fetch girl records error:', error)
      setError(error.message || '記録の取得中にエラーが発生しました')
    } finally {
      setIsLoading(false)
    }
  }

  const fetchGirl = async () => {
    if (!user?.id || !girlName) return

    const authToken = getAuthToken()
    if (!authToken) {
      console.warn('No auth token, skipping API call')
      return
    }

    setIsLoadingGirl(true)

    try {
      const params = new URLSearchParams({
        girl_name: girlName,
      })
      const response = await fetchWithAuth(
        getApiUrl(`/api/girls?${params}`),
        { method: 'GET' }
      )
      
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || 'ヒメ情報の取得に失敗しました')
      }

      if (data.girl) {
        setGirl(data.girl)
        setMemo(data.girl.memo || '')
        setUrls(data.girl.girl_urls && data.girl.girl_urls.length > 0 
          ? data.girl.girl_urls.map(url => url.url)
          : [''])
        const imageUrlsList = data.girl.girl_image_urls && data.girl.girl_image_urls.length > 0
          ? data.girl.girl_image_urls.map(img => img.image_url)
          : []
        setImageUrls(imageUrlsList.length > 0 ? imageUrlsList : [''])
        setValidImageUrls([])
        setCurrentImageIndex(0)
      } else {
        setGirl(null)
        setMemo('')
        setUrls([''])
        setImageUrls([''])
        setValidImageUrls([])
        setCurrentImageIndex(0)
      }
    } catch (error) {
      console.error('Fetch girl error:', error)
      // エラーは表示しない（新規作成の場合もあるため）
    } finally {
      setIsLoadingGirl(false)
    }
  }

  useEffect(() => {
    fetchGirlRecords()
    fetchGirl()
  }, [user?.id, girlName])

  // 画像URLが変更されたときに、有効な画像を検証
  useEffect(() => {
    if (!isEditing && girl?.girl_image_urls) {
      const imageUrlsList = girl.girl_image_urls.map(img => img.image_url)
      setValidImageUrls([])
      setCurrentImageIndex(0)
      
      // 各画像URLの読み込みを試みる
      imageUrlsList.forEach(imageUrl => {
        if (imageUrl) {
          const img = new Image()
          img.onload = () => {
            setValidImageUrls(prev => {
              if (!prev.includes(imageUrl)) {
                return [...prev, imageUrl]
              }
              return prev
            })
          }
          img.onerror = () => {
            // エラーの場合は何もしない（スライドショーに含めない）
          }
          img.src = imageUrl
        }
      })
    }
  }, [girl?.girl_image_urls, isEditing])

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
    fetchGirlRecords()
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

  const handleAddImageUrl = () => {
    setImageUrls([...imageUrls, ''])
  }

  const handleRemoveImageUrl = (index) => {
    if (imageUrls.length > 1) {
      setImageUrls(imageUrls.filter((_, i) => i !== index))
    } else {
      setImageUrls([''])
    }
  }

  const handleImageUrlChange = (index, value) => {
    const newImageUrls = [...imageUrls]
    newImageUrls[index] = value
    setImageUrls(newImageUrls)
  }

  const handleImageLoad = (imageUrl) => {
    setValidImageUrls(prev => {
      if (!prev.includes(imageUrl)) {
        return [...prev, imageUrl]
      }
      return prev
    })
  }

  const handleImageError = (imageUrl) => {
    setValidImageUrls(prev => prev.filter(url => url !== imageUrl))
  }

  const handleNextImage = () => {
    setCurrentImageIndex(prev => (prev + 1) % validImageUrls.length)
  }

  const handlePrevImage = () => {
    setCurrentImageIndex(prev => (prev - 1 + validImageUrls.length) % validImageUrls.length)
  }

  const handleImageClick = (index) => {
    setPopupImageIndex(index)
  }

  const handleClosePopup = () => {
    setPopupImageIndex(null)
  }

  const handlePopupNextImage = () => {
    setPopupImageIndex(prev => (prev + 1) % validImageUrls.length)
  }

  const handlePopupPrevImage = () => {
    setPopupImageIndex(prev => (prev - 1 + validImageUrls.length) % validImageUrls.length)
  }

  // URLのタイトルを取得する関数
  const fetchUrlTitle = async (url) => {
    if (!url || urlTitles[url]) return urlTitles[url]

    const authToken = getAuthToken()
    if (!authToken) {
      return url
    }

    try {
      const params = new URLSearchParams({
        url: url,
      })
      const response = await fetchWithAuth(
        getApiUrl(`/api/url-title?${params}`),
        { method: 'GET' }
      )
      
      if (response.status === 401) {
        handleAuthError(response)
        return url
      }
      
      const data = await response.json()

      if (response.ok && data.success && data.title) {
        setUrlTitles(prev => ({ ...prev, [url]: data.title }))
        return data.title
      }
    } catch (error) {
      console.error('Failed to fetch URL title:', error)
    }
    
    return url
  }

  // URLタイトルを取得するuseEffect
  useEffect(() => {
    if (girl?.girl_urls && girl.girl_urls.length > 0 && !isEditing) {
      girl.girl_urls.forEach((girlUrl) => {
        if (girlUrl.url && !urlTitles[girlUrl.url]) {
          fetchUrlTitle(girlUrl.url)
        }
      })
    }
  }, [girl?.girl_urls, isEditing])

  const handleSave = async () => {
    if (!user?.id || !girlName) return

    const authToken = getAuthToken()
    if (!authToken) {
      console.warn('No auth token, skipping API call')
      return
    }

    setIsSaving(true)
    setError(null)

    try {
      const response = await fetch(
        getApiUrl('/api/girls'),
        getAuthHeaders({
          method: 'POST',
          body: JSON.stringify({
            girl_name: girlName,
            memo: memo.trim() || null,
            urls: urls.filter(url => url.trim()),
            image_urls: imageUrls.filter(url => url.trim()),
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

      setGirl(data.girl)
      const imageUrlsList = data.girl.girl_image_urls && data.girl.girl_image_urls.length > 0
        ? data.girl.girl_image_urls.map(img => img.image_url)
        : []
      setImageUrls(imageUrlsList.length > 0 ? imageUrlsList : [''])
      setValidImageUrls([])
      setCurrentImageIndex(0)
      setIsEditing(false)
    } catch (error) {
      console.error('Save girl error:', error)
      setError(error.message || '保存中にエラーが発生しました')
    } finally {
      setIsSaving(false)
    }
  }

  const handleCancel = () => {
    if (girl) {
      setMemo(girl.memo || '')
      setUrls(girl.girl_urls && girl.girl_urls.length > 0 
        ? girl.girl_urls.map(url => url.url)
        : [''])
      const imageUrlsList = girl.girl_image_urls && girl.girl_image_urls.length > 0
        ? girl.girl_image_urls.map(img => img.image_url)
        : []
      setImageUrls(imageUrlsList.length > 0 ? imageUrlsList : [''])
      setValidImageUrls([])
      setCurrentImageIndex(0)
    } else {
      setMemo('')
      setUrls([''])
      setImageUrls([''])
      setValidImageUrls([])
      setCurrentImageIndex(0)
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

  // 出会った回数を計算
  const visitCount = useMemo(() => {
    return records.length
  }, [records])

  // ヒメが所属しているお店の情報を集約（利用回数と総合評価の平均を含む）
  const shops = useMemo(() => {
    if (!records || records.length === 0) return []
    
    const shopMap = new Map()
    records.forEach(record => {
      if (record.shop_name && record.shop_type) {
        const shopType = typeof record.shop_type === 'string' 
          ? record.shop_type 
          : record.shop_type?.name || record.shop_type_id || ''
        const key = `${shopType}_${record.shop_name}`
        
        if (!shopMap.has(key)) {
          shopMap.set(key, {
            shop_type: shopType,
            shop_name: record.shop_name,
            records: []
          })
        }
        
        // このお店のレビューを追加
        shopMap.get(key).records.push(record)
      }
    })
    
    // 各お店について利用回数と総合評価の平均を計算
    return Array.from(shopMap.values()).map(shop => {
      const visitCount = shop.records.length
      const ratings = shop.records
        .map(record => record.overall_rating)
        .filter(rating => rating !== null && rating !== undefined && rating > 0)
      
      const averageRating = ratings.length > 0
        ? Math.round((ratings.reduce((sum, rating) => sum + rating, 0) / ratings.length) * 10) / 10
        : 0
      
      return {
        shop_type: shop.shop_type,
        shop_name: shop.shop_name,
        visit_count: visitCount,
        average_rating: averageRating,
      }
    })
  }, [records])

  return (
    <div className="girl-detail-container">
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
      <div className="girl-detail-header">
        {!isEditing && validImageUrls.length > 0 && (
          <div className="girl-detail-image-container">
            <div className="girl-detail-image-slider">
              {validImageUrls.map((imageUrl, index) => (
                <img
                  key={imageUrl}
                  src={imageUrl}
                  alt={`${girlName} ${index + 1}`}
                  className={`girl-detail-image ${index === currentImageIndex ? 'active' : ''}`}
                  onLoad={() => handleImageLoad(imageUrl)}
                  onError={() => handleImageError(imageUrl)}
                  onClick={() => handleImageClick(index)}
                  style={{ display: index === currentImageIndex ? 'block' : 'none' }}
                />
              ))}
              {validImageUrls.length > 1 && (
                <>
                  <button
                    className="girl-detail-image-nav girl-detail-image-nav-prev"
                    onClick={handlePrevImage}
                    aria-label="前の画像"
                  >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    </svg>
                  </button>
                  <button
                    className="girl-detail-image-nav girl-detail-image-nav-next"
                    onClick={handleNextImage}
                    aria-label="次の画像"
                  >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M9 18L15 12L9 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    </svg>
                  </button>
                  <div className="girl-detail-image-indicator">
                    {currentImageIndex + 1} / {validImageUrls.length}
                  </div>
                </>
              )}
            </div>
          </div>
        )}
        <div className="girl-detail-title-section">
          <h2 className="girl-detail-title">{girlName}</h2>
          {records.length > 0 && (
            <div className="girl-detail-visit-count">
              <span className="girl-detail-visit-count-label">出会った回数</span>
              <span className="girl-detail-visit-count-value">{visitCount}回</span>
            </div>
          )}
          {records.length > 0 && totalPrice > 0 && (
            <div className="girl-detail-total-price">
              <span className="girl-detail-total-price-label">利用料金合計</span>
              <span className="girl-detail-total-price-value">¥{totalPrice.toLocaleString()}</span>
            </div>
          )}
          {records.length > 0 && averageOverallRating > 0 && (
            <div className="girl-detail-average-rating">
              <span className="girl-detail-average-rating-label">平均評価</span>
              <div className="girl-detail-average-rating-content">
                <StarRating rating={averageOverallRating} readonly={true} />
                <span className="girl-detail-average-rating-value">
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
      {/* ヒメ情報セクション */}
      <div className="girl-detail-info-section">
        {!isLoadingGirl && (
          <>
            {!isEditing ? (
              <div className="girl-detail-info-display">
                <div className="girl-detail-info-header">
                  <h3 className="girl-detail-info-title">ヒメ情報</h3>
                  <button 
                    className="girl-detail-edit-btn"
                    onClick={() => setIsEditing(true)}
                  >
                    編集
                  </button>
                </div>
                
                {girl?.memo && (
                  <div className="girl-detail-memo-display">
                    <h4 className="girl-detail-memo-label">ヒメの感想</h4>
                    <p className="girl-detail-memo-text">{girl.memo}</p>
                  </div>
                )}
                
                {girl?.girl_urls && girl.girl_urls.length > 0 && (
                  <div className="girl-detail-urls-display">
                    <ul className="girl-detail-urls-list">
                      {girl.girl_urls.map((girlUrl, index) => (
                        <li key={girlUrl.id || index} className="girl-detail-url-item">
                          <a 
                            href={girlUrl.url} 
                            target="_blank" 
                            rel="noopener noreferrer"
                            className="girl-detail-url-link"
                          >
                            {urlTitles[girlUrl.url] || girlUrl.url}
                          </a>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                
                {(!girl?.memo && (!girl?.girl_urls || girl.girl_urls.length === 0) && (!girl?.girl_image_urls || girl.girl_image_urls.length === 0)) && (
                  <div className="girl-detail-empty-info">
                    <p>ヒメ情報が登録されていません。</p>
                    <button 
                      className="girl-detail-add-info-btn"
                      onClick={() => setIsEditing(true)}
                    >
                      情報を追加
                    </button>
                  </div>
                )}
              </div>
            ) : (
              <div className="girl-detail-info-edit">
                <div className="girl-detail-info-header">
                  <h3 className="girl-detail-info-title">ヒメ情報を編集</h3>
                </div>
                
                <div className="girl-detail-form-group">
                  <label htmlFor="girl-memo" className="girl-detail-form-label">
                    ヒメの感想
                  </label>
                  <textarea
                    id="girl-memo"
                    className="girl-detail-form-textarea"
                    value={memo}
                    onChange={(e) => setMemo(e.target.value)}
                    placeholder="ヒメの感想を入力してください"
                    rows={5}
                  />
                </div>
                
                <div className="girl-detail-form-group">
                  <label className="girl-detail-form-label">
                    画像URL
                  </label>
                  {imageUrls.map((imageUrl, index) => (
                    <div key={index} className="girl-detail-image-url-item">
                      <div className="girl-detail-url-input-group">
                        <input
                          type="url"
                          className="girl-detail-form-input"
                          value={imageUrl}
                          onChange={(e) => handleImageUrlChange(index, e.target.value)}
                          placeholder="https://example.com/image.jpg"
                        />
                        {imageUrls.length > 1 && (
                          <button
                            type="button"
                            className="girl-detail-url-remove-btn"
                            onClick={() => handleRemoveImageUrl(index)}
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                            </svg>
                          </button>
                        )}
                      </div>
                      {imageUrl && (
                        <div className="girl-detail-image-preview">
                          <img 
                            src={imageUrl} 
                            alt={`プレビュー ${index + 1}`}
                            className="girl-detail-image-preview-img"
                            onLoad={() => handleImageLoad(imageUrl)}
                            onError={() => handleImageError(imageUrl)}
                          />
                        </div>
                      )}
                    </div>
                  ))}
                  <button
                    type="button"
                    className="girl-detail-url-add-btn"
                    onClick={handleAddImageUrl}
                  >
                    + 画像URLを追加
                  </button>
                </div>
                
                <div className="girl-detail-form-group">
                  <label className="girl-detail-form-label">
                    その他URL
                  </label>
                  {urls.map((url, index) => (
                    <div key={index} className="girl-detail-url-input-group">
                      <input
                        type="url"
                        className="girl-detail-form-input"
                        value={url}
                        onChange={(e) => handleUrlChange(index, e.target.value)}
                        placeholder="https://example.com"
                      />
                      {urls.length > 1 && (
                        <button
                          type="button"
                          className="girl-detail-url-remove-btn"
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
                    className="girl-detail-url-add-btn"
                    onClick={handleAddUrl}
                  >
                    + URLを追加
                  </button>
                </div>
                
                {error && (
                  <div className="girl-detail-error-message">{error}</div>
                )}
                
                <div className="girl-detail-edit-actions">
                  <button 
                    className="girl-detail-cancel-btn"
                    onClick={handleCancel}
                    disabled={isSaving}
                  >
                    キャンセル
                  </button>
                  <button 
                    className="girl-detail-save-btn"
                    onClick={handleSave}
                    disabled={isSaving}
                  >
                    {isSaving ? '保存中...' : '保存'}
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* お店情報セクション */}
      {shops.length > 0 && (
        <div className="girl-detail-shops-section">
          <h3 className="girl-detail-shops-title">所属しているお店</h3>
          <div className="girl-detail-shops-list">
            {shops.map((shop, index) => (
              <div 
                key={index} 
                className="girl-detail-shop-item"
                onClick={(e) => {
                  e.stopPropagation()
                  if (onShopClick && shop.shop_name) {
                    onShopClick(shop.shop_type, shop.shop_name)
                  }
                }}
              >
                <div className="girl-detail-shop-item-header">
                  <span className="girl-detail-shop-type">{shop.shop_type}</span>
                  <span className="girl-detail-shop-name">{shop.shop_name}</span>
                </div>
                <div className="girl-detail-shop-item-stats">
                  <div className="girl-detail-shop-item-stat">
                    <span className="girl-detail-shop-item-stat-label">利用回数</span>
                    <span className="girl-detail-shop-item-stat-value">{shop.visit_count}回</span>
                  </div>
                  {shop.average_rating > 0 && (
                    <div className="girl-detail-shop-item-stat">
                      <span className="girl-detail-shop-item-stat-label">平均評価</span>
                      <div className="girl-detail-shop-item-rating">
                        <StarRating rating={shop.average_rating} readonly={true} />
                        <span className="girl-detail-shop-item-rating-value">{shop.average_rating}</span>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* 記録一覧セクション */}
      {!isLoading && !error && records.length === 0 && (
        <div className="empty-message">このヒメの記録はまだありません。</div>
      )}
      {!isLoading && !error && records.length > 0 && (
        <div className="girl-detail-records">
          <div className="girl-detail-records-header">
            <h3 className="girl-detail-records-title">出会いの履歴</h3>
          </div>
          <div className="logs-grid">
            {records.map((record) => {
              const isExpanded = expandedCards.has(record.id)
              return (
                <div key={record.id} className="log-card">
                  <div className="log-card-header">
                    <span className="log-card-date">
                      {record.visit_date ? formatDate(record.visit_date) : formatDate(record.created_at)}
                    </span>
                  </div>
                  <div className="log-card-info">
                    <h3 className="log-card-title">{record.girl_name}</h3>
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
      {/* 画像ポップアップ */}
      {popupImageIndex !== null && validImageUrls.length > 0 && (
        <div className="girl-detail-image-popup-overlay" onClick={handleClosePopup}>
          <div className="girl-detail-image-popup-container" onClick={(e) => e.stopPropagation()}>
            <button
              className="girl-detail-image-popup-close"
              onClick={handleClosePopup}
              aria-label="閉じる"
            >
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
              </svg>
            </button>
            {validImageUrls.length > 1 && (
              <>
                <button
                  className="girl-detail-image-popup-nav girl-detail-image-popup-nav-prev"
                  onClick={handlePopupPrevImage}
                  aria-label="前の画像"
                >
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </button>
                <button
                  className="girl-detail-image-popup-nav girl-detail-image-popup-nav-next"
                  onClick={handlePopupNextImage}
                  aria-label="次の画像"
                >
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 18L15 12L9 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </button>
                <div className="girl-detail-image-popup-indicator">
                  {popupImageIndex + 1} / {validImageUrls.length}
                </div>
              </>
            )}
            <img
              src={validImageUrls[popupImageIndex]}
              alt={`${girlName} ${popupImageIndex + 1}`}
              className="girl-detail-image-popup-img"
            />
          </div>
        </div>
      )}
    </div>
  )
}

GirlDetail.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
  girlName: PropTypes.string.isRequired,
  onShopClick: PropTypes.func,
}

export default GirlDetail
