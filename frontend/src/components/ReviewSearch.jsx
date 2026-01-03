import { useState, useEffect, useRef, useCallback } from 'react'
import PropTypes from 'prop-types'
import './ReviewSearch.css'
import '../components/Home.css'
import StarRating from './StarRating'
import RecordForm from './RecordForm'
import { getApiUrl, fetchWithAuth, getAuthToken, handleAuthError } from '../utils/api'

function ReviewSearch({ user, onShopClick, onGirlClick }) {
  const [shopTypes, setShopTypes] = useState([])
  const [isLoadingShopTypes, setIsLoadingShopTypes] = useState(true)
  const [searchFilters, setSearchFilters] = useState({
    shopTypeIds: [],
    overallRatingMin: 1,
    overallRatingMax: 10,
    visitDateFrom: '',
    visitDateTo: '',
  })
  const [records, setRecords] = useState([])
  const [allRecords, setAllRecords] = useState([]) // サーバーから取得した全記録を保持
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)
  const [freeWordSearch, setFreeWordSearch] = useState('') // フリーワード検索
  const [expandedCards, setExpandedCards] = useState(new Set())
  const [recordPublicUrls, setRecordPublicUrls] = useState({})
  const [editingRecord, setEditingRecord] = useState(null)
  const [deleteConfirmRecord, setDeleteConfirmRecord] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [publishingRecord, setPublishingRecord] = useState(null)
  const [publishedUrl, setPublishedUrl] = useState(null)
  const [publishOptions, setPublishOptions] = useState({
    record: null,
    includeShopName: false,
    includeGirlName: false,
    includeCourse: false,
    includePrice: false,
    publicReview: '',
    metDate: ''
  })
  const [unpublishingRecord, setUnpublishingRecord] = useState(null)
  const hasInitialSearchRef = useRef(false)

  // お店の種類を取得
  useEffect(() => {
    const fetchShopTypes = async () => {
      if (!user?.id) return

      const authToken = getAuthToken()
      if (!authToken) {
        console.warn('No auth token, skipping API call')
        return
      }

      setIsLoadingShopTypes(true)
      try {
        // レビューを登録したことがあるお店の種類だけを取得
        const response = await fetchWithAuth(getApiUrl('/api/shop-types?only_reviewed=true'), { method: 'GET' })
        
        if (response.status === 401) {
          handleAuthError(response)
          return
        }
        
        const data = await response.json()

        if (!response.ok) {
          throw new Error(data.message || data.error || 'お店の種類の取得に失敗しました')
        }

        const fetchedShopTypes = data.shop_types || []
        setShopTypes(fetchedShopTypes)
        
        // お店の種類を取得したら、全てをチェック済みにする
        if (fetchedShopTypes.length > 0) {
          const allShopTypeIds = fetchedShopTypes.map(st => st.id)
          setSearchFilters(prev => ({
            ...prev,
            shopTypeIds: allShopTypeIds
          }))
        }
      } catch (error) {
        console.error('Fetch shop types error:', error)
        setError(error.message || 'お店の種類の取得中にエラーが発生しました')
      } finally {
        setIsLoadingShopTypes(false)
      }
    }

    fetchShopTypes()
  }, [user?.id])

  // 検索実行
  const handleSearch = useCallback(async () => {
    if (!user?.id) return

    const authToken = getAuthToken()
    if (!authToken) {
      console.warn('No auth token, skipping API call')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const params = new URLSearchParams()
      
      // お店の種類ID（複数選択）
      if (searchFilters.shopTypeIds.length > 0) {
        searchFilters.shopTypeIds.forEach(id => {
          params.append('shop_type_ids[]', id)
        })
      }
      
      // 総合評価の範囲
      if (searchFilters.overallRatingMin > 0) {
        params.append('overall_rating_min', searchFilters.overallRatingMin)
      }
      if (searchFilters.overallRatingMax > 0) {
        params.append('overall_rating_max', searchFilters.overallRatingMax)
      }
      
      // 利用日の範囲
      if (searchFilters.visitDateFrom) {
        params.append('visit_date_from', searchFilters.visitDateFrom)
      }
      if (searchFilters.visitDateTo) {
        params.append('visit_date_to', searchFilters.visitDateTo)
      }

      const queryString = params.toString()
      const url = queryString 
        ? `${getApiUrl('/api/records/search')}?${queryString}`
        : getApiUrl('/api/records/search')

      const response = await fetchWithAuth(url, { method: 'GET' })
      
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || '検索に失敗しました')
      }

      // 来店日の降順でソート（来店日がない場合は作成日を使用）
      const sortedRecords = (data.records || []).sort((a, b) => {
        const dateA = a.visit_date || a.created_at
        const dateB = b.visit_date || b.created_at
        return new Date(dateB) - new Date(dateA)
      })

      // 全記録を保持
      setAllRecords(sortedRecords)
    } catch (error) {
      console.error('Search records error:', error)
      setError(error.message || '検索中にエラーが発生しました')
    } finally {
      setIsLoading(false)
    }
  }, [user?.id, searchFilters])

  // 初期表示時に自動的に検索を実行
  useEffect(() => {
    // お店の種類の取得が完了したら、初期表示時に自動検索を実行（初回のみ）
    if (!hasInitialSearchRef.current && !isLoadingShopTypes) {
      // お店の種類がある場合は全てチェック済みになったら検索
      // お店の種類が0件の場合も検索を実行（全ての記録を取得）
      if (shopTypes.length === 0 || (shopTypes.length > 0 && searchFilters.shopTypeIds.length === shopTypes.length)) {
        hasInitialSearchRef.current = true
        handleSearch()
      }
    }
  }, [shopTypes.length, searchFilters.shopTypeIds.length, isLoadingShopTypes, handleSearch])

  // 公開済みのレビューの公開URLを取得
  useEffect(() => {
    const fetchPublicUrls = async () => {
      if (!user?.id || records.length === 0) return

      const authToken = getAuthToken()
      if (!authToken) return

      // 公開済みのレビューのみをフィルタ
      const publishedRecords = records.filter(r => r.public_token)
      if (publishedRecords.length === 0) return

      const urls = {}
      for (const record of publishedRecords) {
        try {
          const response = await fetchWithAuth(getApiUrl(`/api/records/${record.id}/public-url`), { method: 'GET' })
          if (response.ok) {
            const data = await response.json()
            if (data.is_published && data.public_url) {
              urls[record.id] = data.public_url
            }
          }
        } catch (error) {
          console.error('Failed to fetch public URL for record:', record.id, error)
        }
      }
      setRecordPublicUrls(urls)
    }

    fetchPublicUrls()
  }, [records, user?.id])

  const handleRecordAdded = () => {
    handleSearch()
    setEditingRecord(null)
  }

  const handleEditRecord = (record) => {
    setEditingRecord(record)
  }

  const handleCancelEdit = () => {
    setEditingRecord(null)
  }

  const handleDeleteClick = (record) => {
    setDeleteConfirmRecord(record)
  }

  const handleCancelDelete = () => {
    setDeleteConfirmRecord(null)
  }

  const handleConfirmDelete = async () => {
    if (!deleteConfirmRecord) return

    const authToken = getAuthToken()
    if (!authToken) {
      handleAuthError({ status: 401 })
      return
    }

    setIsDeleting(true)
    setError(null)

    try {
      const response = await fetchWithAuth(getApiUrl(`/api/records/${deleteConfirmRecord.id}`), {
        method: 'DELETE',
      })

      if (response.status === 401) {
        handleAuthError(response)
        return
      }

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || '削除に失敗しました')
      }

      setRecords(prev => prev.filter(r => r.id !== deleteConfirmRecord.id))
      setDeleteConfirmRecord(null)
      setExpandedCards(prev => {
        const newSet = new Set(prev)
        newSet.delete(deleteConfirmRecord.id)
        return newSet
      })
    } catch (error) {
      console.error('Delete record error:', error)
      setError(error.message || '削除中にエラーが発生しました')
    } finally {
      setIsDeleting(false)
    }
  }

  const handlePublishClick = (record) => {
    // 出会った日の初期値はレビュー登録日（created_at）をYYYY年M月形式で設定
    const initialMetDate = formatDateForMetDate(record.created_at || new Date().toISOString())
    setPublishOptions({
      record: record,
      includeShopName: false,
      includeGirlName: false,
      includeCourse: false,
      includePrice: false,
      publicReview: record.review || '',
      metDate: initialMetDate
    })
  }

  const handleCancelPublishOptions = () => {
    setPublishOptions({
      record: null,
      includeShopName: false,
      includeGirlName: false,
      includeCourse: false,
      includePrice: false,
      publicReview: '',
      metDate: ''
    })
  }

  const handleConfirmPublish = async () => {
    if (!publishOptions.record) return

    const authToken = getAuthToken()
    if (!authToken) {
      handleAuthError({ status: 401 })
      return
    }

    setPublishingRecord(publishOptions.record.id)
    setError(null)

    try {
      const response = await fetchWithAuth(getApiUrl(`/api/records/${publishOptions.record.id}/publish`), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          include_shop_name: publishOptions.includeShopName,
          include_girl_name: publishOptions.includeGirlName,
          include_course: publishOptions.includeCourse,
          include_price: publishOptions.includePrice,
          public_review: publishOptions.publicReview,
          met_date: publishOptions.metDate
        }),
      })

      if (response.status === 401) {
        handleAuthError(response)
        return
      }

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || '公開に失敗しました')
      }

      setPublishedUrl({
        url: data.public_url,
        recordId: publishOptions.record.id
      })

      setRecords(prev => prev.map(r => 
        r.id === publishOptions.record.id 
          ? { ...r, public_token: data.public_token }
          : r
      ))

      setRecordPublicUrls(prev => ({
        ...prev,
        [publishOptions.record.id]: data.public_url
      }))

      setPublishOptions({
        record: null,
        includeShopName: false,
        includeGirlName: false,
        includeCourse: false,
        includePrice: false,
        publicReview: '',
        metDate: ''
      })
    } catch (error) {
      console.error('Publish record error:', error)
      setError(error.message || '公開中にエラーが発生しました')
    } finally {
      setPublishingRecord(null)
    }
  }

  const handleClosePublishedUrl = () => {
    setPublishedUrl(null)
  }

  const handleUnpublishClick = async (record) => {
    if (!window.confirm('公開ページを削除してもよろしいですか？')) {
      return
    }

    const authToken = getAuthToken()
    if (!authToken) {
      handleAuthError({ status: 401 })
      return
    }

    setUnpublishingRecord(record.id)
    setError(null)

    try {
      const response = await fetchWithAuth(getApiUrl(`/api/records/${record.id}/publish`), {
        method: 'DELETE',
      })

      if (response.status === 401) {
        handleAuthError(response)
        return
      }

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || '削除に失敗しました')
      }

      setRecords(prev => prev.map(r => 
        r.id === record.id 
          ? { ...r, public_token: null }
          : r
      ))

      setRecordPublicUrls(prev => {
        const newUrls = { ...prev }
        delete newUrls[record.id]
        return newUrls
      })
    } catch (error) {
      console.error('Unpublish record error:', error)
      setError(error.message || '削除中にエラーが発生しました')
    } finally {
      setUnpublishingRecord(null)
    }
  }

  // 検索条件の変更
  const handleFilterChange = (name, value) => {
    setSearchFilters(prev => ({
      ...prev,
      [name]: value
    }))
  }

  // 総合評価の変更
  const handleRatingChange = (type, value) => {
    setSearchFilters(prev => {
      const newFilters = { ...prev }
      
      if (type === 'overallRatingMin') {
        // 最小値を設定
        newFilters.overallRatingMin = value
        // 最大値が最小値より小さい場合は、最大値を最小値に合わせる
        if (newFilters.overallRatingMax > 0 && newFilters.overallRatingMax < value) {
          newFilters.overallRatingMax = value
        }
      } else if (type === 'overallRatingMax') {
        // 最大値を設定
        newFilters.overallRatingMax = value
        // 最小値が最大値より大きい場合は、最小値を最大値に合わせる
        if (newFilters.overallRatingMin > value) {
          newFilters.overallRatingMin = value
        }
      }
      
      return newFilters
    })
  }

  // お店の種類のチェックボックス変更
  const handleShopTypeToggle = (shopTypeId) => {
    setSearchFilters(prev => {
      const currentIds = prev.shopTypeIds || []
      const isSelected = currentIds.includes(shopTypeId)
      return {
        ...prev,
        shopTypeIds: isSelected
          ? currentIds.filter(id => id !== shopTypeId)
          : [...currentIds, shopTypeId]
      }
    })
  }

  // フリーワード検索でフィルタリング
  const filterRecordsByFreeWord = useCallback((recordsToFilter, searchWord) => {
    if (!searchWord || searchWord.trim() === '') {
      setRecords(recordsToFilter)
      return
    }

    const searchLower = searchWord.toLowerCase().trim()
    const filtered = recordsToFilter.filter(record => {
      // 店の名前で検索
      const shopName = (record.shop?.shop_name || record.shop_name || '').toLowerCase()
      // ヒメの名前で検索
      const girlName = (record.girl_name || record.girl?.girl_name || '').toLowerCase()
      // 感想で検索
      const review = (record.review || '').toLowerCase()

      return shopName.includes(searchLower) || 
             girlName.includes(searchLower) || 
             review.includes(searchLower)
    })

    setRecords(filtered)
  }, [])

  // フリーワード検索が変更されたときにフィルタリングを実行
  useEffect(() => {
    if (allRecords.length > 0) {
      filterRecordsByFreeWord(allRecords, freeWordSearch)
    }
  }, [freeWordSearch, allRecords, filterRecordsByFreeWord])

  // カードの展開/折りたたみ
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

  // 日付フォーマット
  const formatDate = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    const day = date.getDate()
    return `${year}年${month}月${day}日`
  }

  // 日付をYYYY年M月の形式に変換（出会った日用）
  const formatDateForMetDate = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    return `${year}年${month}月`
  }

  // レビューのプレビューテキスト取得
  const getPreviewText = (text, maxLines = 2) => {
    if (!text) return { text: '', hasMore: false, fullText: '' }
    const lines = text.split('\n')
    const previewLines = lines.slice(0, maxLines)
    const previewText = previewLines.join('\n')
    const hasMore = lines.length > maxLines || text.length > previewText.length
    return {
      text: previewText,
      hasMore,
      fullText: text
    }
  }

  // 今日の日付をYYYY-MM-DD形式で取得
  const getTodayString = () => {
    const today = new Date()
    const year = today.getFullYear()
    const month = String(today.getMonth() + 1).padStart(2, '0')
    const day = String(today.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  return (
    <div className="review-search-container">
      {editingRecord ? (
        <RecordForm
          userId={user?.id}
          onRecordAdded={handleRecordAdded}
          editingRecord={editingRecord}
          onCancelEdit={handleCancelEdit}
        />
      ) : (
        <>
      {/* 検索フォーム */}
      <div className="review-search-form">
        <div className="search-form-group">
          <label className="search-form-label">お店の種類</label>
          <div className="shop-type-checkboxes">
            {isLoadingShopTypes ? (
              <div className="loading-message">読み込み中...</div>
            ) : (
              shopTypes.map((shopType) => (
                <label key={shopType.id} className="shop-type-checkbox-label">
                  <input
                    type="checkbox"
                    checked={searchFilters.shopTypeIds.includes(shopType.id)}
                    onChange={() => handleShopTypeToggle(shopType.id)}
                    className="shop-type-checkbox"
                  />
                  <span>{shopType.name}</span>
                </label>
              ))
            )}
          </div>
        </div>

        <div className="search-form-group">
          <label className="search-form-label">総合評価</label>
          <div className="rating-range">
            <div className="rating-range-item">
              <label className="rating-range-label">最小</label>
              <StarRating
                rating={searchFilters.overallRatingMin}
                onRatingChange={(value) => handleRatingChange('overallRatingMin', value)}
                readonly={false}
              />
            </div>
            <div className="rating-range-item">
              <label className="rating-range-label">最大</label>
              <StarRating
                rating={searchFilters.overallRatingMax}
                onRatingChange={(value) => handleRatingChange('overallRatingMax', value)}
                readonly={false}
              />
            </div>
          </div>
        </div>

        <button
          type="button"
          onClick={handleSearch}
          className="search-button"
          disabled={isLoading}
        >
          {isLoading ? '検索中...' : '検索'}
        </button>
      </div>

      {/* エラーメッセージ */}
      {error && (
        <div className="error-message">{error}</div>
      )}

      {/* 検索結果 */}
      {!isLoading && allRecords.length > 0 && (
        <div className="search-results">
          <h3 className="search-results-title">思い出 ({records.length}件)</h3>
          <div className="search-form-group" style={{ marginTop: '16px', marginBottom: '24px' }}>
            <label className="search-form-label">フリーワード検索</label>
            <input
              type="text"
              value={freeWordSearch}
              onChange={(e) => setFreeWordSearch(e.target.value)}
              placeholder="店名・ヒメ名・感想で検索"
              className="free-word-search-input"
            />
          </div>
          {records.length > 0 && (
            <div className="logs-grid">
              {records.map((record) => {
                const isExpanded = expandedCards.has(record.id)
                const reviewPreview = record.review ? getPreviewText(record.review, 2) : {
                  text: '',
                  hasMore: false,
                  fullText: ''
                }
                return (
                  <div 
                    key={record.id} 
                    className="log-card"
                    onClick={() => toggleCard(record.id)}
                  >
                  <div className="log-card-header" style={{ position: 'relative' }}>
                    {record.public_token && (
                      <div style={{
                        position: 'absolute',
                        top: '8px',
                        right: '8px',
                        width: '24px',
                        height: '24px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: 'rgba(74, 144, 226, 0.2)',
                        borderRadius: '50%',
                        zIndex: 10
                      }} title="公開済み">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8-11-8-11-8z" stroke="#4a90e2" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                          <circle cx="12" cy="12" r="3" stroke="#4a90e2" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        </svg>
                      </div>
                    )}
                    {record.girl_image_url && (
                      <div className="log-card-image">
                        <img 
                          src={record.girl_image_url} 
                          alt={record.girl_name || record.girl?.girl_name || 'ヒメの画像'}
                          className="log-card-image-img"
                        />
                      </div>
                    )}
                    <div className="log-card-header-content">
                      <div className="log-card-shop">
                        <span className="log-card-shop-type">
                          {typeof record.shop_type === 'string' 
                            ? record.shop_type 
                            : record.shop_type?.name || record.shop_type_id || ''}
                        </span>
                        <span 
                          className="log-card-shop-name clickable"
                          onClick={(e) => {
                            e.stopPropagation()
                            if (onShopClick) {
                              const shopType = typeof record.shop_type === 'string' 
                                ? record.shop_type 
                                : record.shop_type?.name || record.shop_type_id || ''
                              onShopClick(shopType, record.shop?.shop_name || record.shop_name || '')
                            }
                          }}
                        >
                          {record.shop?.shop_name || record.shop_name || ''}
                        </span>
                      </div>
                      <span className="log-card-date">
                        {record.visit_date ? formatDate(record.visit_date) : formatDate(record.created_at)}
                      </span>
                    </div>
                  </div>
                  <div className="log-card-info">
                    <h3 
                      className="log-card-title clickable"
                      onClick={(e) => {
                        e.stopPropagation()
                        const girlName = record.girl_name || record.girl?.girl_name
                        if (onGirlClick && girlName) {
                          onGirlClick(girlName)
                        }
                      }}
                    >
                      {record.girl_name || record.girl?.girl_name || ''}
                    </h3>
                  </div>
                  <div 
                    className="log-card-ratings"
                    style={{
                      display: 'grid',
                      gridTemplateColumns: '1fr',
                      rowGap: '12px'
                    }}
                  >
                    <div className="log-card-rating-item" style={{ width: '100%' }}>
                      <span className="log-card-rating-label">総合</span>
                      <StarRating rating={record.overall_rating || 0} readonly={true} />
                    </div>
                    <div className="log-card-rating-item" style={{ width: '100%' }}>
                      <span className="log-card-rating-label">顔</span>
                      <StarRating rating={record.face_rating || 0} readonly={true} />
                    </div>
                    <div className="log-card-rating-item" style={{ width: '100%' }}>
                      <span className="log-card-rating-label">スタイル</span>
                      <StarRating rating={record.style_rating || 0} readonly={true} />
                    </div>
                    <div className="log-card-rating-item" style={{ width: '100%' }}>
                      <span className="log-card-rating-label">接客</span>
                      <StarRating rating={record.service_rating || 0} readonly={true} />
                    </div>
                  </div>
                  {record.course && (
                    <div className="log-card-price">
                      <span className="log-card-price-label">コース</span>
                      <span className="log-card-price-value">{record.course}</span>
                    </div>
                  )}
                  {record.price && (
                    <div className="log-card-price">
                      <span className="log-card-price-label">利用料金</span>
                      <span className="log-card-price-value">¥{record.price.toLocaleString()}</span>
                    </div>
                  )}
                  {record.review && (
                    <div className={`log-card-review ${isExpanded ? 'expanded' : 'collapsed'}`}>
                      <p className="log-card-preview">
                        {isExpanded ? reviewPreview.fullText : reviewPreview.text}
                        {!isExpanded && reviewPreview.hasMore && (
                          <span className="log-card-review-ellipsis">...</span>
                        )}
                      </p>
                    </div>
                  )}
                  {!isExpanded && (
                    <div className="log-card-expand-container">
                      <button 
                        className="log-card-expand-btn" 
                        onClick={(e) => {
                          e.stopPropagation()
                          toggleCard(record.id)
                        }}
                        title="続きを読む"
                      >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M6 9L12 15L18 9" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        </svg>
                      </button>
                    </div>
                  )}
                  {isExpanded && record.public_token && recordPublicUrls[record.id] && (
                    <div className="log-card-public-info" style={{ 
                      marginBottom: '12px', 
                      padding: '12px', 
                      background: 'rgba(74, 144, 226, 0.1)', 
                      borderRadius: '8px',
                      border: '1px solid rgba(74, 144, 226, 0.3)'
                    }}>
                      <div style={{ 
                        fontSize: '14px', 
                        color: '#4a90e2', 
                        marginBottom: '16px',
                        fontWeight: '600',
                        letterSpacing: '0.3px'
                      }}>
                        レビュー公開済み
                      </div>
                      <div style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        gap: '8px',
                        justifyContent: 'flex-start',
                        flexWrap: 'wrap'
                      }}>
                        <button 
                          onClick={(e) => {
                            e.stopPropagation()
                            window.open(recordPublicUrls[record.id], '_blank')
                          }}
                          style={{ 
                            flexShrink: 0,
                            padding: '8px 16px',
                            background: 'rgba(74, 144, 226, 0.1)',
                            border: '1px solid rgba(74, 144, 226, 0.4)',
                            borderRadius: '6px',
                            color: '#4a90e2',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: 'all 0.3s ease',
                            WebkitTapHighlightColor: 'transparent',
                            fontSize: '13px',
                            fontWeight: '500',
                            whiteSpace: 'nowrap'
                          }}
                          onMouseEnter={(e) => {
                            e.currentTarget.style.background = 'rgba(74, 144, 226, 0.2)'
                            e.currentTarget.style.borderColor = 'rgba(74, 144, 226, 0.6)'
                            e.currentTarget.style.transform = 'scale(1.02)'
                          }}
                          onMouseLeave={(e) => {
                            e.currentTarget.style.background = 'rgba(74, 144, 226, 0.1)'
                            e.currentTarget.style.borderColor = 'rgba(74, 144, 226, 0.4)'
                            e.currentTarget.style.transform = 'scale(1)'
                          }}
                        >
                          見る
                        </button>
                        <button 
                          onClick={(e) => {
                            e.stopPropagation()
                            handlePublishClick(record)
                          }}
                          style={{ 
                            flexShrink: 0,
                            padding: '8px 16px',
                            background: 'rgba(232, 106, 255, 0.1)',
                            border: '1px solid rgba(232, 106, 255, 0.4)',
                            borderRadius: '6px',
                            color: '#e86aff',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: 'all 0.3s ease',
                            WebkitTapHighlightColor: 'transparent',
                            fontSize: '13px',
                            fontWeight: '500',
                            whiteSpace: 'nowrap'
                          }}
                          onMouseEnter={(e) => {
                            e.currentTarget.style.background = 'rgba(232, 106, 255, 0.2)'
                            e.currentTarget.style.borderColor = 'rgba(232, 106, 255, 0.6)'
                            e.currentTarget.style.transform = 'scale(1.02)'
                          }}
                          onMouseLeave={(e) => {
                            e.currentTarget.style.background = 'rgba(232, 106, 255, 0.1)'
                            e.currentTarget.style.borderColor = 'rgba(232, 106, 255, 0.4)'
                            e.currentTarget.style.transform = 'scale(1)'
                          }}
                        >
                          再レビュー
                        </button>
                        <button 
                          onClick={(e) => {
                            e.stopPropagation()
                            handleUnpublishClick(record)
                          }}
                          disabled={unpublishingRecord === record.id}
                          style={{ 
                            flexShrink: 0,
                            padding: '8px 16px',
                            background: 'rgba(255, 107, 107, 0.1)',
                            border: '1px solid rgba(255, 107, 107, 0.4)',
                            borderRadius: '6px',
                            color: '#ff6b6b',
                            cursor: unpublishingRecord === record.id ? 'not-allowed' : 'pointer',
                            opacity: unpublishingRecord === record.id ? 0.5 : 1,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: 'all 0.3s ease',
                            WebkitTapHighlightColor: 'transparent',
                            fontSize: '13px',
                            fontWeight: '500',
                            whiteSpace: 'nowrap'
                          }}
                          onMouseEnter={(e) => {
                            if (unpublishingRecord !== record.id) {
                              e.currentTarget.style.background = 'rgba(255, 107, 107, 0.2)'
                              e.currentTarget.style.borderColor = 'rgba(255, 107, 107, 0.6)'
                              e.currentTarget.style.transform = 'scale(1.02)'
                            }
                          }}
                          onMouseLeave={(e) => {
                            e.currentTarget.style.background = 'rgba(255, 107, 107, 0.1)'
                            e.currentTarget.style.borderColor = 'rgba(255, 107, 107, 0.4)'
                            e.currentTarget.style.transform = 'scale(1)'
                          }}
                        >
                          {unpublishingRecord === record.id ? '削除中...' : '削除'}
                        </button>
                      </div>
                    </div>
                  )}
                  <div className="log-card-footer">
                    {isExpanded && (
                      <button 
                        className="log-card-btn log-card-btn-delete" 
                        onClick={(e) => {
                          e.stopPropagation()
                          handleDeleteClick(record)
                        }}
                        title="削除"
                      >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M3 6H5H21M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                          <path d="M10 11V17M14 11V17" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        </svg>
                      </button>
                    )}
                    <div className="log-card-footer-right">
                      {isExpanded && !record.public_token && (
                        <button 
                          className="log-card-btn" 
                          onClick={(e) => {
                            e.stopPropagation()
                            handlePublishClick(record)
                          }}
                          disabled={publishingRecord === record.id}
                          title="公開する"
                          style={{ 
                            opacity: publishingRecord === record.id ? 0.5 : 1
                          }}
                        >
                          {publishingRecord === record.id ? '公開中...' : '公開する'}
                        </button>
                      )}
                      {isExpanded && (
                        <button 
                          className="log-card-btn log-card-btn-edit" 
                          onClick={(e) => {
                            e.stopPropagation()
                            handleEditRecord(record)
                          }}
                          title="修正する"
                        >
                          修正する
                        </button>
                      )}
                      {isExpanded && (
                        <button 
                          className="log-card-btn" 
                          onClick={(e) => {
                            e.stopPropagation()
                            toggleCard(record.id)
                          }}
                          title="折りたたむ"
                        >
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 15L12 9L6 15" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                          </svg>
                        </button>
                      )}
                    </div>
                  </div>
                </div>
                )
              })}
            </div>
          )}
        </div>
      )}

      {/* 検索結果が0件の場合（初期表示以外） */}
      {!isLoading && records.length === 0 && (
        (searchFilters.shopTypeIds.length > 0 && searchFilters.shopTypeIds.length < shopTypes.length) ||
        (searchFilters.overallRatingMin > 1) ||
        (searchFilters.overallRatingMax < 10) ||
        searchFilters.visitDateFrom ||
        searchFilters.visitDateTo
      ) && (
        <div className="empty-message">検索条件に一致するレビューが見つかりませんでした。</div>
      )}

      {/* 削除確認モーダル */}
      {deleteConfirmRecord && (
        <div className="delete-confirm-overlay" onClick={handleCancelDelete}>
          <div className="delete-confirm-dialog" onClick={(e) => e.stopPropagation()}>
            <h3 className="delete-confirm-title">レビューを削除</h3>
            <p className="delete-confirm-message">
              このレビューを削除してもよろしいですか？この操作は取り消せません。
            </p>
            <div className="delete-confirm-buttons">
              <button
                className="delete-confirm-btn delete-confirm-btn-cancel"
                onClick={handleCancelDelete}
                disabled={isDeleting}
              >
                キャンセル
              </button>
              <button
                className="delete-confirm-btn"
                onClick={handleConfirmDelete}
                disabled={isDeleting}
              >
                {isDeleting ? '削除中...' : '削除'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 公開オプション選択モーダル */}
      {publishOptions.record && (
        <div className="delete-confirm-overlay" onClick={handleCancelPublishOptions}>
          <div className="delete-confirm-dialog" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '500px' }}>
            <h3 className="delete-confirm-title">
              {publishOptions.record.public_token ? '再公開オプション' : '公開オプション'}
            </h3>
            {publishedUrl && publishedUrl.recordId === publishOptions.record.id && (
              <div style={{ 
                marginBottom: '16px', 
                padding: '12px', 
                background: 'rgba(74, 144, 226, 0.1)', 
                borderRadius: '8px',
                border: '1px solid rgba(74, 144, 226, 0.3)'
              }}>
                <div style={{ fontSize: '12px', color: '#a0a0a0', marginBottom: '8px' }}>
                  公開URL:
                </div>
                <a
                  href={publishedUrl.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  style={{
                    color: '#4a90e2',
                    wordBreak: 'break-all',
                    textDecoration: 'underline',
                    fontSize: '13px'
                  }}
                  onClick={(e) => e.stopPropagation()}
                >
                  {recordPublicUrls[publishOptions.record.id]}
                </a>
                <div style={{ fontSize: '11px', color: '#a0a0a0', marginTop: '8px' }}>
                  再公開してもURLは変更されません
                </div>
              </div>
            )}
            <p className="delete-confirm-message" style={{ marginBottom: '16px' }}>
              公開ページに含める情報を選択してください。
            </p>
            <div style={{ marginBottom: '16px' }}>
              <label style={{ 
                display: 'flex', 
                alignItems: 'center', 
                gap: '8px', 
                marginBottom: '12px',
                cursor: 'pointer',
                color: '#e0e0e0'
              }}>
                <input
                  type="checkbox"
                  checked={publishOptions.includeShopName}
                  onChange={(e) => setPublishOptions(prev => ({
                    ...prev,
                    includeShopName: e.target.checked
                  }))}
                  style={{ width: '18px', height: '18px', cursor: 'pointer' }}
                />
                <span>お店の名前を含める</span>
              </label>
              <label style={{ 
                display: 'flex', 
                alignItems: 'center', 
                gap: '8px',
                marginBottom: '12px',
                cursor: 'pointer',
                color: '#e0e0e0'
              }}>
                <input
                  type="checkbox"
                  checked={publishOptions.includeGirlName}
                  onChange={(e) => setPublishOptions(prev => ({
                    ...prev,
                    includeGirlName: e.target.checked
                  }))}
                  style={{ width: '18px', height: '18px', cursor: 'pointer' }}
                />
                <span>ヒメの名前を含める</span>
              </label>
              {publishOptions.record?.course && (
                <label style={{ 
                  display: 'flex', 
                  alignItems: 'center', 
                  gap: '8px',
                  marginBottom: '12px',
                  cursor: 'pointer',
                  color: '#e0e0e0'
                }}>
                  <input
                    type="checkbox"
                    checked={publishOptions.includeCourse}
                    onChange={(e) => setPublishOptions(prev => ({
                      ...prev,
                      includeCourse: e.target.checked
                    }))}
                    style={{ width: '18px', height: '18px', cursor: 'pointer' }}
                  />
                  <span>コースを含める</span>
                </label>
              )}
              {publishOptions.record?.price && (
                <label style={{ 
                  display: 'flex', 
                  alignItems: 'center', 
                  gap: '8px',
                  marginBottom: '16px',
                  cursor: 'pointer',
                  color: '#e0e0e0'
                }}>
                  <input
                    type="checkbox"
                    checked={publishOptions.includePrice}
                    onChange={(e) => setPublishOptions(prev => ({
                      ...prev,
                      includePrice: e.target.checked
                    }))}
                    style={{ width: '18px', height: '18px', cursor: 'pointer' }}
                  />
                  <span>料金を含める</span>
                </label>
              )}
              <div style={{ marginTop: '16px', marginBottom: '16px' }}>
                <label style={{ 
                  display: 'block',
                  marginBottom: '8px',
                  color: '#e0e0e0',
                  fontSize: '14px',
                  fontWeight: '500'
                }}>
                  出会った日
                </label>
                <input
                  type="text"
                  value={publishOptions.metDate}
                  onChange={(e) => setPublishOptions(prev => ({
                    ...prev,
                    metDate: e.target.value
                  }))}
                  placeholder="例: 2025年1月"
                  style={{
                    width: '100%',
                    padding: '12px',
                    background: 'rgba(0, 0, 0, 0.3)',
                    border: '1px solid rgba(255, 255, 255, 0.2)',
                    borderRadius: '8px',
                    color: '#e0e0e0',
                    fontSize: '14px',
                    fontFamily: 'inherit'
                  }}
                />
              </div>
              <div style={{ marginTop: '16px' }}>
                <label style={{ 
                  display: 'block',
                  marginBottom: '8px',
                  color: '#e0e0e0',
                  fontSize: '14px',
                  fontWeight: '500'
                }}>
                  公開用の感想
                </label>
                <textarea
                  value={publishOptions.publicReview}
                  onChange={(e) => setPublishOptions(prev => ({
                    ...prev,
                    publicReview: e.target.value
                  }))}
                  placeholder="公開ページに表示する感想を入力してください"
                  style={{
                    width: '100%',
                    minHeight: '120px',
                    padding: '12px',
                    background: 'rgba(0, 0, 0, 0.3)',
                    border: '1px solid rgba(255, 255, 255, 0.2)',
                    borderRadius: '8px',
                    color: '#e0e0e0',
                    fontSize: '14px',
                    fontFamily: 'inherit',
                    resize: 'vertical',
                    lineHeight: '1.6'
                  }}
                />
              </div>
            </div>
            <div className="delete-confirm-buttons">
              <button
                className="delete-confirm-btn delete-confirm-btn-cancel"
                onClick={handleCancelPublishOptions}
                disabled={publishingRecord === publishOptions.record.id}
              >
                キャンセル
              </button>
              <button
                className="delete-confirm-btn"
                onClick={handleConfirmPublish}
                disabled={publishingRecord === publishOptions.record.id}
                style={{ background: '#4a90e2', color: '#ffffff' }}
              >
                {publishingRecord === publishOptions.record.id 
                  ? '公開中...' 
                  : publishedUrl && publishedUrl.recordId === publishOptions.record.id
                    ? '再公開'
                    : '公開'}
              </button>
            </div>
          </div>
        </div>
      )}
        </>
      )}
    </div>
  )
}

ReviewSearch.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
  onShopClick: PropTypes.func,
  onGirlClick: PropTypes.func,
}

export default ReviewSearch

