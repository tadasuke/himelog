import { useState, useEffect } from 'react'
import StarRating from './StarRating'
import './RecordForm.css'

function RecordForm({ userId, onRecordAdded, editingRecord, onCancelEdit }) {
  // 現在日をYYYY-MM-DD形式で取得
  const getTodayString = () => {
    const today = new Date()
    const year = today.getFullYear()
    const month = String(today.getMonth() + 1).padStart(2, '0')
    const day = String(today.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  // 日付をYYYY-MM-DD形式に変換
  const formatDateForInput = (dateString) => {
    if (!dateString) return getTodayString()
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  const [formData, setFormData] = useState({
    shopType: editingRecord?.shop_type || '',
    shopName: editingRecord?.shop_name || '',
    girlName: editingRecord?.girl_name || '',
    visitDate: editingRecord ? formatDateForInput(editingRecord.visit_date) : getTodayString(),
    faceRating: editingRecord?.face_rating || 0,
    styleRating: editingRecord?.style_rating || 0,
    serviceRating: editingRecord?.service_rating || 0,
    overallRating: editingRecord?.overall_rating || 0,
    review: editingRecord?.review || '',
  })
  const [shopTypes, setShopTypes] = useState([])
  const [isLoadingShopTypes, setIsLoadingShopTypes] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState(null)
  const [shopNames, setShopNames] = useState([])
  const [isLoadingShopNames, setIsLoadingShopNames] = useState(false)
  const [isNewShopName, setIsNewShopName] = useState(false)
  const [girlNames, setGirlNames] = useState([])
  const [isLoadingGirlNames, setIsLoadingGirlNames] = useState(false)
  const [isNewGirlName, setIsNewGirlName] = useState(false)
  
  const NEW_SHOP_NAME_OPTION = '__NEW_SHOP_NAME__'
  const NEW_GIRL_NAME_OPTION = '__NEW_GIRL_NAME__'

  useEffect(() => {
    const fetchShopTypes = async () => {
      try {
        const url = userId 
          ? `/api/shop-types?user_id=${encodeURIComponent(userId)}`
          : '/api/shop-types'
        const response = await fetch(url)
        const data = await response.json()

        if (response.ok && data.success) {
          setShopTypes(data.shop_types || [])
        } else {
          console.error('Failed to fetch shop types:', data)
        }
      } catch (error) {
        console.error('Error fetching shop types:', error)
      } finally {
        setIsLoadingShopTypes(false)
      }
    }

    fetchShopTypes()
  }, [userId])

  // 編集モードの場合、初期データを読み込む
  useEffect(() => {
    if (editingRecord && userId) {
      // お店の種類が設定されている場合、お店名を取得
      if (editingRecord.shop_type && editingRecord.shop_type !== 'その他') {
        fetchShopNames(editingRecord.shop_type)
      }
      // お店の種類とお店名が設定されている場合、女の子の名前を取得
      if (editingRecord.shop_type && editingRecord.shop_name) {
        fetchGirlNames(editingRecord.shop_type, editingRecord.shop_name)
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editingRecord, userId])

  const handleInputChange = (e) => {
    const { name, value } = e.target
    setError(null)

    // お店名のセレクトボックスで「新規登録」が選択された場合
    if (name === 'shopName' && value === NEW_SHOP_NAME_OPTION) {
      setIsNewShopName(true)
      setFormData(prev => ({
        ...prev,
        shopName: ''
      }))
      return
    }

    // お店名が変更された場合（新規登録モードでない場合）
    if (name === 'shopName' && !isNewShopName) {
      setFormData(prev => ({
        ...prev,
        [name]: value
      }))
      // お店の種類とお店の名前が設定されたら、女の子の名前を取得
      if (formData.shopType && value) {
        fetchGirlNames(formData.shopType, value)
      } else {
        setGirlNames([])
        setIsNewGirlName(false)
        setFormData(prev => ({
          ...prev,
          girlName: ''
        }))
      }
      return
    }

    // その他の入力フィールド
    setFormData(prev => ({
      ...prev,
      [name]: value
    }))

    // お店名がテキストフィールドで入力された場合（新規登録モード）
    if (name === 'shopName' && isNewShopName) {
      // お店の種類とお店の名前が設定されたら、女の子の名前を取得
      if (formData.shopType && value && value.trim()) {
        fetchGirlNames(formData.shopType, value.trim())
      } else {
        setGirlNames([])
        setIsNewGirlName(false)
        setFormData(prev => ({
          ...prev,
          girlName: ''
        }))
      }
      return
    }

    // 女の子の名前のセレクトボックスで「新規登録」が選択された場合
    if (name === 'girlName' && value === NEW_GIRL_NAME_OPTION) {
      setIsNewGirlName(true)
      setFormData(prev => ({
        ...prev,
        girlName: ''
      }))
      return
    }

    // 女の子の名前が変更された場合（新規登録モードでない場合）
    if (name === 'girlName' && !isNewGirlName) {
      setFormData(prev => ({
        ...prev,
        [name]: value
      }))
      return
    }

    // お店の種類が変更された場合、登録済みのお店名を取得
    if (name === 'shopType') {
      setIsNewShopName(false)
      setGirlNames([])
      setIsNewGirlName(false)
      setFormData(prev => ({
        ...prev,
        shopName: '',
        girlName: ''
      }))
      if (value && value !== 'その他') {
        fetchShopNames(value)
      } else {
        setShopNames([])
      }
    }
  }

  const fetchShopNames = async (shopType) => {
    if (!userId || !shopType) {
      return
    }

    setIsLoadingShopNames(true)
    try {
      const response = await fetch(`/api/records/shop-names?user_id=${userId}&shop_type=${encodeURIComponent(shopType)}`)
      const data = await response.json()

      if (response.ok && data.success) {
        const shopNamesList = data.shop_names || []
        // 編集モードの場合、既存のお店名がリストに含まれていない場合は追加
        if (editingRecord && editingRecord.shop_name && !shopNamesList.includes(editingRecord.shop_name)) {
          shopNamesList.push(editingRecord.shop_name)
        }
        setShopNames(shopNamesList)
        setIsNewShopName(false)
        // 編集モードでない場合のみお店名をリセット
        if (!editingRecord) {
          setFormData(prev => ({
            ...prev,
            shopName: ''
          }))
          // 女の子の名前もリセット
          setGirlNames([])
          setIsNewGirlName(false)
          setFormData(prev => ({
            ...prev,
            girlName: ''
          }))
        }
      } else {
        console.error('Failed to fetch shop names:', data)
        setShopNames([])
      }
    } catch (error) {
      console.error('Error fetching shop names:', error)
      setShopNames([])
    } finally {
      setIsLoadingShopNames(false)
    }
  }

  const fetchGirlNames = async (shopType, shopName) => {
    if (!userId || !shopType || !shopName) {
      return
    }

    setIsLoadingGirlNames(true)
    try {
      const response = await fetch(`/api/records/girl-names?user_id=${userId}&shop_type=${encodeURIComponent(shopType)}&shop_name=${encodeURIComponent(shopName)}`)
      const data = await response.json()

      if (response.ok && data.success) {
        const girlNamesList = data.girl_names || []
        // 編集モードの場合、既存の女の子の名前がリストに含まれていない場合は追加
        if (editingRecord && editingRecord.girl_name && !girlNamesList.includes(editingRecord.girl_name)) {
          girlNamesList.push(editingRecord.girl_name)
        }
        setGirlNames(girlNamesList)
        setIsNewGirlName(false)
        // 編集モードでない場合のみ女の子の名前をリセット
        if (!editingRecord) {
          setFormData(prev => ({
            ...prev,
            girlName: ''
          }))
        }
      } else {
        console.error('Failed to fetch girl names:', data)
        setGirlNames([])
      }
    } catch (error) {
      console.error('Error fetching girl names:', error)
      setGirlNames([])
    } finally {
      setIsLoadingGirlNames(false)
    }
  }

  const handleRatingChange = (ratingType, rating) => {
    setFormData(prev => ({
      ...prev,
      [ratingType]: rating
    }))
    setError(null)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError(null)

    // バリデーション
    if (!formData.visitDate) {
      setError('来店日を入力してください')
      return
    }
    if (!formData.shopType.trim()) {
      setError('お店の種類を入力してください')
      return
    }
    if (!formData.shopName.trim()) {
      setError('お店の名前を入力してください')
      return
    }

    setIsSubmitting(true)

    try {
      const url = editingRecord 
        ? `/api/records/${editingRecord.id}`
        : '/api/records'
      const method = editingRecord ? 'PUT' : 'POST'
      
      const requestBody = editingRecord
        ? {
            shop_type: formData.shopType,
            shop_name: formData.shopName,
            girl_name: formData.girlName.trim() || null,
            visit_date: formData.visitDate,
            face_rating: formData.faceRating > 0 ? formData.faceRating : null,
            style_rating: formData.styleRating > 0 ? formData.styleRating : null,
            service_rating: formData.serviceRating > 0 ? formData.serviceRating : null,
            overall_rating: formData.overallRating > 0 ? formData.overallRating : null,
            review: formData.review || null,
          }
        : {
            user_id: userId,
            shop_type: formData.shopType,
            shop_name: formData.shopName,
            girl_name: formData.girlName.trim() || null,
            visit_date: formData.visitDate,
            face_rating: formData.faceRating > 0 ? formData.faceRating : null,
            style_rating: formData.styleRating > 0 ? formData.styleRating : null,
            service_rating: formData.serviceRating > 0 ? formData.serviceRating : null,
            overall_rating: formData.overallRating > 0 ? formData.overallRating : null,
            review: formData.review || null,
          }

      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody),
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || data.error || (editingRecord ? '更新に失敗しました' : '登録に失敗しました'))
      }

      // フォームをリセット（編集モードでない場合のみ）
      if (!editingRecord) {
        setFormData({
          shopType: '',
          shopName: '',
          girlName: '',
          visitDate: getTodayString(),
          faceRating: 0,
          styleRating: 0,
          serviceRating: 0,
          overallRating: 0,
          review: '',
        })
        setShopNames([])
        setIsNewShopName(false)
        setGirlNames([])
        setIsNewGirlName(false)
      }

      // 親コンポーネントに通知
      if (onRecordAdded) {
        onRecordAdded()
      }
    } catch (error) {
      console.error('Record submission error:', error)
      setError(error.message || (editingRecord ? '更新中にエラーが発生しました' : '登録中にエラーが発生しました'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="record-form-container">
      <h2 className="record-form-title">
        {editingRecord ? '記録を編集' : '新しい記録を登録'}
      </h2>
      {editingRecord && onCancelEdit && (
        <button
          type="button"
          onClick={onCancelEdit}
          className="form-cancel-btn"
          disabled={isSubmitting}
        >
          キャンセル
        </button>
      )}
      <form onSubmit={handleSubmit} className="record-form">
        <div className="form-group">
          <label htmlFor="visitDate" className="form-label">
            来店日 <span className="required">*</span>
          </label>
          <input
            type="date"
            id="visitDate"
            name="visitDate"
            value={formData.visitDate}
            onChange={handleInputChange}
            className="form-input"
            max={getTodayString()}
            disabled={isSubmitting}
          />
        </div>

        <div className="form-group">
          <label htmlFor="shopType" className="form-label">
            お店の種類 <span className="required">*</span>
          </label>
          <select
            id="shopType"
            name="shopType"
            value={formData.shopType}
            onChange={handleInputChange}
            className="form-select"
            disabled={isSubmitting || isLoadingShopTypes}
          >
            <option value="">選択してください</option>
            {shopTypes.map((shopType) => (
              <option key={shopType.id} value={shopType.name}>
                {shopType.name}
              </option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label htmlFor="shopName" className="form-label">
            お店の名前 <span className="required">*</span>
          </label>
          {formData.shopType === 'その他' ? (
            <input
              type="text"
              id="shopName"
              name="shopName"
              value={formData.shopName}
              onChange={handleInputChange}
              className="form-input"
              placeholder="お店の名前を入力"
              disabled={isSubmitting}
            />
          ) : shopNames.length > 0 && !isNewShopName ? (
            <select
              id="shopName"
              name="shopName"
              value={formData.shopName}
              onChange={handleInputChange}
              className="form-select"
              disabled={isSubmitting || isLoadingShopNames}
            >
              <option value="">選択してください</option>
              {shopNames.map((shopName, index) => (
                <option key={index} value={shopName}>
                  {shopName}
                </option>
              ))}
              <option value={NEW_SHOP_NAME_OPTION}>新規登録</option>
            </select>
          ) : (
            <input
              type="text"
              id="shopName"
              name="shopName"
              value={formData.shopName}
              onChange={handleInputChange}
              className="form-input"
              placeholder={isLoadingShopNames ? '読み込み中...' : 'お店の名前を入力'}
              disabled={isSubmitting || isLoadingShopNames}
            />
          )}
        </div>

        <div className="form-group">
          <label htmlFor="girlName" className="form-label">
            ヒメの名前
          </label>
          {girlNames.length > 0 && !isNewGirlName ? (
            <select
              id="girlName"
              name="girlName"
              value={formData.girlName}
              onChange={handleInputChange}
              className="form-select"
              disabled={isSubmitting || isLoadingGirlNames}
            >
              <option value="">選択してください</option>
              {girlNames.map((girlName, index) => (
                <option key={index} value={girlName}>
                  {girlName}
                </option>
              ))}
              <option value={NEW_GIRL_NAME_OPTION}>新規登録</option>
            </select>
          ) : (
            <input
              type="text"
              id="girlName"
              name="girlName"
              value={formData.girlName}
              onChange={handleInputChange}
              className="form-input"
              placeholder={isLoadingGirlNames ? '読み込み中...' : 'ヒメの名前を入力'}
              disabled={isSubmitting || isLoadingGirlNames}
            />
          )}
        </div>

        <div className="form-group">
          <label className="form-label">
            総合
          </label>
          <StarRating
            rating={formData.overallRating}
            onRatingChange={(rating) => handleRatingChange('overallRating', rating)}
            readonly={isSubmitting}
          />
        </div>

        <div className="form-group">
          <label className="form-label">
            顔
          </label>
          <StarRating
            rating={formData.faceRating}
            onRatingChange={(rating) => handleRatingChange('faceRating', rating)}
            readonly={isSubmitting}
          />
        </div>

        <div className="form-group">
          <label className="form-label">
            スタイル
          </label>
          <StarRating
            rating={formData.styleRating}
            onRatingChange={(rating) => handleRatingChange('styleRating', rating)}
            readonly={isSubmitting}
          />
        </div>

        <div className="form-group">
          <label className="form-label">
            接客
          </label>
          <StarRating
            rating={formData.serviceRating}
            onRatingChange={(rating) => handleRatingChange('serviceRating', rating)}
            readonly={isSubmitting}
          />
        </div>

        <div className="form-group">
          <label htmlFor="review" className="form-label">
            感想
          </label>
          <textarea
            id="review"
            name="review"
            value={formData.review}
            onChange={handleInputChange}
            className="form-textarea"
            placeholder="感想を入力してください（任意）"
            rows="5"
            disabled={isSubmitting}
          />
        </div>

        {error && (
          <div className="form-error">
            {error}
          </div>
        )}

        <button
          type="submit"
          className="form-submit-btn"
          disabled={isSubmitting}
        >
          {isSubmitting ? (editingRecord ? '更新中...' : '登録中...') : (editingRecord ? '更新する' : '登録する')}
        </button>
      </form>
    </div>
  )
}

export default RecordForm

