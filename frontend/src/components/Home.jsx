import { useState, useEffect, useCallback, useRef } from 'react'
import PropTypes from 'prop-types'
import './Home.css'
import RecordForm from './RecordForm'
import StarRating from './StarRating'
import OverallRatingChart from './OverallRatingChart'
import OverallRatingPieChart from './OverallRatingPieChart'
// import ShopTypeChart from './ShopTypeChart' // 将来使用する可能性があるためコメントアウト
import { getApiUrl, fetchWithAuth, getAuthToken, handleAuthError } from '../utils/api'

function Home({ user, onLogout, currentPage, onRecordAdded, onRecordsLoaded, onShopClick, onGirlClick }) {
  const [records, setRecords] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)
  const [expandedCards, setExpandedCards] = useState(new Set())
  const [editingRecord, setEditingRecord] = useState(null)
  const [deleteConfirmRecord, setDeleteConfirmRecord] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [publishingRecord, setPublishingRecord] = useState(null)
  const [publishedUrl, setPublishedUrl] = useState(null)
  const [publishOptions, setPublishOptions] = useState({
    record: null,
    includeShopName: false,
    includeGirlName: false,
    publicReview: ''
  })
  const [unpublishingRecord, setUnpublishingRecord] = useState(null)
  const [recordPublicUrls, setRecordPublicUrls] = useState({})
  const fetchingRef = useRef(false)

  const fetchRecords = useCallback(async () => {
    if (!user?.id) return

    // 既にローディング中の場合は何もしない
    if (fetchingRef.current) {
      console.log('Already fetching records, skipping duplicate call')
      return
    }

    // 認証トークンがない場合はAPIを呼び出さない
    const authToken = getAuthToken()
    if (!authToken) {
      console.warn('No auth token, skipping API call')
      return
    }

    fetchingRef.current = true
    setIsLoading(true)
    setError(null)

    try {
      const response = await fetchWithAuth(getApiUrl('/api/records'), { method: 'GET' })
      
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
      
      // 記録数を親コンポーネントに通知
      if (onRecordsLoaded) {
        onRecordsLoaded(sortedRecords.length)
      }
    } catch (error) {
      console.error('Fetch records error:', error)
      setError(error.message || '記録の取得中にエラーが発生しました')
    } finally {
      setIsLoading(false)
      fetchingRef.current = false
    }
  }, [user?.id, onRecordsLoaded])

  useEffect(() => {
    // ホーム画面の時のみfetchRecordsを呼ぶ
    if (currentPage === 'home') {
      fetchRecords()
    }
  }, [user?.id, currentPage, fetchRecords])

  // 公開済みのレビューの公開URLを取得
  useEffect(() => {
    const fetchPublicUrls = async () => {
      if (!user?.id || records.length === 0) return

      const authToken = getAuthToken()
      if (!authToken) return

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
    fetchRecords()
    setEditingRecord(null)
    if (onRecordAdded) {
      onRecordAdded()
    }
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

  const handlePublishClick = (record) => {
    // 公開オプション選択モーダルを表示
    setPublishOptions({
      record: record,
      includeShopName: false,
      includeGirlName: false,
      publicReview: record.review || ''
    })
  }

  const handleCancelPublishOptions = () => {
    setPublishOptions({
      record: null,
      includeShopName: false,
      includeGirlName: false,
      publicReview: ''
    })
  }

  const handleConfirmPublish = async () => {
    if (!publishOptions.record) return

    // 認証トークンがない場合は処理を中断
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
          public_review: publishOptions.publicReview
        }),
      })

      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || '公開に失敗しました')
      }

      // 公開URLを表示
      setPublishedUrl({
        url: data.public_url,
        recordId: publishOptions.record.id
      })

      // 記録を更新してpublic_tokenを反映
      setRecords(prev => prev.map(r => 
        r.id === publishOptions.record.id 
          ? { ...r, public_token: data.public_token }
          : r
      ))

      // 公開URLを更新
      setRecordPublicUrls(prev => ({
        ...prev,
        [publishOptions.record.id]: data.public_url
      }))

      // 公開オプションをリセット
      setPublishOptions({
        record: null,
        includeShopName: false,
        includeGirlName: false,
        publicReview: ''
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

    // 認証トークンがない場合は処理を中断
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

      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || '削除に失敗しました')
      }

      // 記録を更新してpublic_tokenを削除
      setRecords(prev => prev.map(r => 
        r.id === record.id 
          ? { ...r, public_token: null }
          : r
      ))

      // 公開URLを削除
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

  const handleConfirmDelete = async () => {
    if (!deleteConfirmRecord) return

    // 認証トークンがない場合は処理を中断
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

      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || '削除に失敗しました')
      }

      // 記録を削除
      setRecords(prev => prev.filter(r => r.id !== deleteConfirmRecord.id))
      
      // 記録数を親コンポーネントに通知
      if (onRecordsLoaded) {
        const newCount = records.length - 1
        onRecordsLoaded(newCount)
      }

      setDeleteConfirmRecord(null)
    } catch (error) {
      console.error('Delete record error:', error)
      setError(error.message || '削除中にエラーが発生しました')
    } finally {
      setIsDeleting(false)
    }
  }

  const formatDate = (dateString) => {
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    const day = date.getDate()
    return `${year}年${month}月${day}日`
  }

  const getPreviewText = (text, maxLines = 2) => {
    if (!text) {
      return {
        text: '',
        hasMore: false,
        fullText: ''
      }
    }
    const lines = text.split('\n')
    const previewLines = lines.slice(0, maxLines)
    const hasMore = lines.length > maxLines
    return {
      text: previewLines.join('\n'),
      hasMore: hasMore,
      fullText: text
    }
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

  return (
    <div className="home-container">
      {user && (currentPage === 'create' || editingRecord) && (
        <RecordForm 
          userId={user.id} 
          onRecordAdded={handleRecordAdded}
          editingRecord={editingRecord}
          onCancelEdit={handleCancelEdit}
        />
      )}
      
      {deleteConfirmRecord && (
        <div className="delete-confirm-overlay" onClick={handleCancelDelete}>
          <div className="delete-confirm-dialog" onClick={(e) => e.stopPropagation()}>
            <h3 className="delete-confirm-title">削除の確認</h3>
            <p className="delete-confirm-message">
              この記録を削除してもよろしいですか？<br />
              この操作は取り消せません。
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
                className="delete-confirm-btn delete-confirm-btn-delete"
                onClick={handleConfirmDelete}
                disabled={isDeleting}
              >
                {isDeleting ? '削除中...' : '削除する'}
              </button>
            </div>
          </div>
        </div>
      )}

      {publishOptions.record && (
        <div className="delete-confirm-overlay" onClick={handleCancelPublishOptions}>
          <div className="delete-confirm-dialog" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '500px' }}>
            <h3 className="delete-confirm-title">
              {publishOptions.record.public_token ? '再公開オプション' : '公開オプション'}
            </h3>
            {publishOptions.record.public_token && recordPublicUrls[publishOptions.record.id] && (
              <div style={{ 
                marginBottom: '16px', 
                padding: '12px', 
                background: 'rgba(74, 144, 226, 0.1)', 
                borderRadius: '8px',
                border: '1px solid rgba(74, 144, 226, 0.3)'
              }}>
                <div style={{ fontSize: '12px', color: '#6f8cff', marginBottom: '8px', fontWeight: '500' }}>
                  現在の公開URL
                </div>
                <a 
                  href={recordPublicUrls[publishOptions.record.id]} 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="public-url-link"
                  style={{ 
                    textDecoration: 'underline',
                    fontSize: '13px',
                    wordBreak: 'break-all',
                    display: 'block'
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
                marginBottom: '16px',
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
                  : (publishOptions.record.public_token ? '再公開する' : '公開する')}
              </button>
            </div>
          </div>
        </div>
      )}

      {publishedUrl && (
        <div className="delete-confirm-overlay" onClick={handleClosePublishedUrl}>
          <div className="delete-confirm-dialog" onClick={(e) => e.stopPropagation()}>
            <h3 className="delete-confirm-title">レビューを公開しました</h3>
            <p className="delete-confirm-message" style={{ marginBottom: '24px' }}>
              レビューを公開しました。
            </p>
            <div style={{ 
              display: 'flex', 
              gap: '8px', 
              justifyContent: 'center',
              flexWrap: 'wrap'
            }}>
              <button
                className="delete-confirm-btn"
                onClick={(e) => {
                  e.stopPropagation()
                  navigator.clipboard.writeText(publishedUrl.url)
                  alert('URLをクリップボードにコピーしました')
                }}
                style={{ 
                  background: 'rgba(111, 140, 255, 0.1)',
                  border: '1px solid rgba(111, 140, 255, 0.4)',
                  color: '#6f8cff',
                  flex: '1',
                  minWidth: '100px'
                }}
              >
                URLをコピー
              </button>
              <button
                className="delete-confirm-btn"
                onClick={(e) => {
                  e.stopPropagation()
                  window.open(publishedUrl.url, '_blank')
                }}
                style={{ 
                  background: 'rgba(74, 144, 226, 0.1)',
                  border: '1px solid rgba(74, 144, 226, 0.4)',
                  color: '#4a90e2',
                  flex: '1',
                  minWidth: '100px'
                }}
              >
                見る
              </button>
            </div>
            <div style={{ marginTop: '16px', textAlign: 'center' }}>
              <button
                className="delete-confirm-btn delete-confirm-btn-cancel"
                onClick={handleClosePublishedUrl}
                style={{ 
                  background: 'transparent',
                  border: '1px solid rgba(255, 255, 255, 0.2)',
                  color: '#a0a0a0'
                }}
              >
                閉じる
              </button>
            </div>
          </div>
        </div>
      )}

      {currentPage === 'home' && !editingRecord && (
      <div className="logs-section">
        {!isLoading && records.length > 0 && (
          <>
            <div className="chart-section">
              <h2 className="chart-section-title">総合評価の推移</h2>
              <OverallRatingChart user={user} />
            </div>
            <div className="chart-section">
              <h2 className="chart-section-title">総合評価の割合</h2>
              <OverallRatingPieChart user={user} />
            </div>
            {/* 利用したお店のタイプの円グラフは将来使用する可能性があるためコメントアウト */}
            {/* <div className="chart-section">
              <h2 className="chart-section-title">利用したお店のタイプ</h2>
              <ShopTypeChart user={user} />
            </div> */}
          </>
        )}
        {isLoading && (
          <div className="loading-message">読み込み中...</div>
        )}
        {!isLoading && records.length === 0 && (
          <div className="empty-message">まだ記録がありません。新しい記録を登録してください。</div>
        )}
        {!isLoading && records.length > 0 && (
          <>
            <h2 className="logs-section-title">最近の出会い</h2>
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
                            navigator.clipboard.writeText(recordPublicUrls[record.id])
                            alert('URLをクリップボードにコピーしました')
                          }}
                          style={{ 
                            flexShrink: 0,
                            padding: '8px 16px',
                            background: 'rgba(111, 140, 255, 0.1)',
                            border: '1px solid rgba(111, 140, 255, 0.4)',
                            borderRadius: '6px',
                            color: '#6f8cff',
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
                            e.currentTarget.style.background = 'rgba(111, 140, 255, 0.2)'
                            e.currentTarget.style.borderColor = 'rgba(111, 140, 255, 0.6)'
                            e.currentTarget.style.transform = 'scale(1.02)'
                          }}
                          onMouseLeave={(e) => {
                            e.currentTarget.style.background = 'rgba(111, 140, 255, 0.1)'
                            e.currentTarget.style.borderColor = 'rgba(111, 140, 255, 0.4)'
                            e.currentTarget.style.transform = 'scale(1)'
                          }}
                        >
                          コピー
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
                          修正
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
                            if (!unpublishingRecord) {
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
                      <>
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
                        {!record.public_token && (
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
                      </>
                    )}
                    <div className="log-card-footer-right">
                      {isExpanded && (
                        <button 
                          className="log-card-btn log-card-btn-edit" 
                          onClick={(e) => {
                            e.stopPropagation()
                            handleEditRecord(record)
                          }}
                          title="編集"
                        >
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17 3C17.2652 3 17.5196 3.10536 17.7071 3.29289L20.7071 6.29289C20.8946 6.48043 21 6.73478 21 7C21 7.26522 20.8946 7.51957 20.7071 7.70711L8.70711 19.7071C8.51957 19.8946 8.26522 20 8 20H3C2.44772 20 2 19.5523 2 19V14C2 13.7348 2.10536 13.4804 2.29289 13.2929L14.2929 1.29289C14.4804 1.10536 14.7348 1 15 1H17V3Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                          </svg>
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
          </>
        )}
      </div>
      )}
    </div>
  )
}

Home.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
  onLogout: PropTypes.func,
  currentPage: PropTypes.string.isRequired,
  onRecordAdded: PropTypes.func,
  onRecordsLoaded: PropTypes.func,
  onShopClick: PropTypes.func,
  onGirlClick: PropTypes.func,
}

export default Home

