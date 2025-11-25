import { useState, useEffect } from 'react'
import PropTypes from 'prop-types'
import './Home.css'
import RecordForm from './RecordForm'
import StarRating from './StarRating'
import { getApiUrl, getAuthHeaders, getAuthToken, handleAuthError } from '../utils/api'

function Home({ user, onLogout, currentPage, onRecordAdded, onRecordsLoaded, onShopClick, onGirlClick }) {
  const [records, setRecords] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)
  const [expandedCards, setExpandedCards] = useState(new Set())
  const [editingRecord, setEditingRecord] = useState(null)
  const [deleteConfirmRecord, setDeleteConfirmRecord] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const fetchRecords = async () => {
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
      const response = await fetch(getApiUrl('/api/records'), getAuthHeaders())
      
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
    }
  }

  useEffect(() => {
    fetchRecords()
  }, [user?.id])

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
      const response = await fetch(getApiUrl(`/api/records/${deleteConfirmRecord.id}`), getAuthHeaders({
        method: 'DELETE',
      }))

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

      {currentPage === 'home' && !editingRecord && (
      <div className="logs-section">
        {isLoading && (
          <div className="loading-message">読み込み中...</div>
        )}
        {error && (
          <div className="error-message">{error}</div>
        )}
        {!isLoading && !error && records.length === 0 && (
          <div className="empty-message">まだ記録がありません。新しい記録を登録してください。</div>
        )}
        {!isLoading && !error && records.length > 0 && (
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
                >
                  <div className="log-card-header">
                    {record.girl_image_url && (
                      <div className="log-card-image">
                        <img 
                          src={record.girl_image_url} 
                          alt={record.girl_name || 'ヒメの画像'}
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
                              onShopClick(shopType, record.shop_name)
                            }
                          }}
                        >
                          {record.shop_name}
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
                        if (onGirlClick && record.girl_name) {
                          onGirlClick(record.girl_name)
                        }
                      }}
                    >
                      {record.girl_name}
                    </h3>
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

