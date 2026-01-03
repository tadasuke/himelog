import { useState, useEffect, useMemo } from 'react'
import PropTypes from 'prop-types'
import './ShopDetail.css'
import StarRating from './StarRating'
import RecordForm from './RecordForm'
import { getApiUrl, fetchWithAuth, getAuthToken, handleAuthError, getAuthHeaders } from '../utils/api'

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
  const [recordPublicUrls, setRecordPublicUrls] = useState({})

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
      const response = await fetchWithAuth(
        getApiUrl(`/api/records/shop-records?${params}`),
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
      const response = await fetchWithAuth(
        getApiUrl(`/api/shops?${params}`),
        { method: 'GET' }
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

  const formatDate = (dateString) => {
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

  const handlePublishClick = (record) => {
    // 公開済みの場合はDBに保存された公開用データを使用、未公開の場合はデフォルト値を使用
    if (record.public_token) {
      // 公開済み：DBに保存された公開用データを初期値として使用
      const initialMetDate = record.public_met_date || formatDateForMetDate(record.created_at || new Date().toISOString())
      setPublishOptions({
        record: record,
        includeShopName: record.public_include_shop_name ?? false,
        includeGirlName: record.public_include_girl_name ?? false,
        includeCourse: record.public_include_course ?? false,
        includePrice: record.public_include_price ?? false,
        publicReview: record.public_review ?? '',
        metDate: initialMetDate
      })
    } else {
      // 未公開：デフォルト値を使用
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
          include_course: publishOptions.includeCourse,
          include_price: publishOptions.includePrice,
          public_review: publishOptions.publicReview,
          met_date: publishOptions.metDate
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

      // 記録を更新してpublic_tokenと公開用データを反映
      setRecords(prev => prev.map(r => 
        r.id === publishOptions.record.id 
          ? { 
              ...r, 
              public_token: data.public_token,
              public_review: publishOptions.publicReview,
              public_include_shop_name: publishOptions.includeShopName,
              public_include_girl_name: publishOptions.includeGirlName,
              public_include_course: publishOptions.includeCourse,
              public_include_price: publishOptions.includePrice,
              public_met_date: publishOptions.metDate
            }
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

    // 認証トークンがない場合は処理を中断
    const authToken = getAuthToken()
    if (!authToken) {
      handleAuthError({ status: 401 })
      return
    }

    setUnpublishingRecord(record.id)
    setError(null)

    try {
      const response = await fetchWithAuth(getApiUrl(`/api/records/${record.id}/unpublish`), {
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

  // 1回辺りの平均金額を計算
  const averagePrice = useMemo(() => {
    if (!records || records.length === 0) return 0
    
    const pricesWithValue = records
      .map(record => record.price)
      .filter(price => price !== null && price !== undefined && price > 0)
    
    if (pricesWithValue.length === 0) return 0
    
    const sum = pricesWithValue.reduce((acc, price) => acc + price, 0)
    return sum / pricesWithValue.length
  }, [records])

  // 最終利用日を計算
  const lastVisitDate = useMemo(() => {
    if (!records || records.length === 0) return null
    
    const dates = records
      .map(record => record.visit_date || record.created_at)
      .filter(date => date)
      .sort((a, b) => new Date(b) - new Date(a))
    
    return dates.length > 0 ? dates[0] : null
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
              marginBottom: '24px',
              padding: '12px',
              background: 'rgba(74, 144, 226, 0.05)',
              borderRadius: '8px',
              border: '1px solid rgba(74, 144, 226, 0.2)',
              fontSize: '13px',
              lineHeight: '1.6',
              color: '#e0e0e0'
            }}>
              <ul style={{ 
                margin: '0',
                paddingLeft: '20px',
                listStyleType: 'disc'
              }}>
                <li style={{ marginBottom: '8px' }}>
                  公開されたレビューは世界中から閲覧可能です。
                </li>
                <li style={{ marginBottom: '8px' }}>
                  多くの方にお見せしたい場合はX(旧Twitter)などへの投稿をおすすめします。
                </li>
                <li style={{ marginBottom: '0' }}>
                  レビューはいつでも修正、削除が可能です。
                </li>
              </ul>
            </div>
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

      {!editingRecord && (
        <>
      <div className="shop-detail-header">
        <div className="shop-detail-title-section">
          <h2 className="shop-detail-title">{shopName}</h2>
          <p className="shop-detail-subtitle">{shopType}</p>
          {lastVisitDate && (
            <div className="shop-detail-last-visit-date">
              <span className="shop-detail-last-visit-date-label">最終利用日</span>
              <span className="shop-detail-last-visit-date-value">{formatDate(lastVisitDate)}</span>
            </div>
          )}
          {records.length > 0 && (
            <div className="shop-detail-visit-count">
              <span className="shop-detail-visit-count-label">利用回数</span>
              <span className="shop-detail-visit-count-value">{records.length}回</span>
            </div>
          )}
          {records.length > 0 && averagePrice > 0 && (
            <div className="shop-detail-average-price">
              <span className="shop-detail-average-price-label">1回辺りの平均金額</span>
              <span className="shop-detail-average-price-value">¥{Math.round(averagePrice).toLocaleString()}</span>
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
                
              </div>
            )}
          </>
        )}
      </div>

      {/* 記録一覧セクション */}
      {!isLoading && records.length === 0 && (
        <div className="empty-message">このお店の記録はまだありません。</div>
      )}
      {!isLoading && records.length > 0 && (
        <div className="shop-detail-records">
          <div className="shop-detail-records-header">
            <h3 className="shop-detail-records-title">ヒメとの出会いの記録</h3>
          </div>
          <div className="logs-grid">
            {records.map((record) => {
              const isExpanded = expandedCards.has(record.id)
              const handleCardClick = (e) => {
                // フッターのボタンがクリックされた場合は何もしない
                if (e.target.closest('.log-card-footer')) {
                  return
                }
                // 編集ボタンや展開ボタンがクリックされた場合は何もしない
                if (e.target.closest('.log-card-btn')) {
                  return
                }
                const girlName = record.girl_name || record.girl?.girl_name
                console.log('Card clicked:', { 
                  girl_name: girlName, 
                  hasOnGirlClick: !!onGirlClick,
                  target: e.target,
                  currentTarget: e.currentTarget
                })
                if (girlName && onGirlClick) {
                  console.log('Navigating to girl:', girlName)
                  e.preventDefault()
                  e.stopPropagation()
                  onGirlClick(girlName)
                }
              }
              return (
                <div 
                  key={record.id} 
                  className="log-card"
                  onClick={handleCardClick}
                  style={{ cursor: (record.girl_name || record.girl?.girl_name) && onGirlClick ? 'pointer' : 'default' }}
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
                      <div className="log-card-info">
                        {(record.girl_name || record.girl?.girl_name) ? (
                          <h3 className="log-card-title log-card-title-clickable">
                            {record.girl_name || record.girl?.girl_name || ''}
                          </h3>
                        ) : (
                          <h3 className="log-card-title">-</h3>
                        )}
                      </div>
                      <div className="log-card-date">
                        {record.visit_date ? formatDate(record.visit_date) : formatDate(record.created_at)}
                      </div>
                    </div>
                  </div>
                  <div className="log-card-ratings">
                    <div className="log-card-rating-item">
                      <span className="log-card-rating-label">総合</span>
                      <StarRating rating={record.overall_rating || 0} readonly={true} />
                    </div>
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
                    <p className={`log-card-preview ${isExpanded ? 'expanded' : 'collapsed'}`}>
                      {isExpanded ? record.review : getPreviewText(record.review, 2)}
                    </p>
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
                  <div className="log-card-footer" onClick={(e) => e.stopPropagation()}>
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
                          {publishingRecord === record.id ? (
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" className="spinning">
                              <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2" strokeDasharray="31.416" strokeDashoffset="31.416">
                                <animate attributeName="stroke-dasharray" dur="2s" values="0 31.416;15.708 15.708;0 31.416;0 31.416" repeatCount="indefinite"/>
                                <animate attributeName="stroke-dashoffset" dur="2s" values="0;-15.708;-31.416;-31.416" repeatCount="indefinite"/>
                              </circle>
                            </svg>
                          ) : (
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M18 13V19A2 2 0 0 1 16 21H5A2 2 0 0 1 3 19V8A2 2 0 0 1 5 6H11" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                              <path d="M15 3H21V9" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                              <path d="M10 14L21 3" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                            </svg>
                          )}
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
                      <button 
                        className="log-card-btn" 
                        onClick={(e) => {
                          e.stopPropagation()
                          toggleCard(record.id)
                        }}
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

