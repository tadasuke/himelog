import { useState, useEffect } from 'react'
import PropTypes from 'prop-types'
import StarRating from './StarRating'
import { getApiUrl, fetchWithAuth, getAuthToken, handleAuthError } from '../utils/api'
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
    shopType: editingRecord?.shop_type_id || editingRecord?.shop_type || '',
    shopName: editingRecord?.shop_name || '',
    girlName: editingRecord?.girl_name || '',
    visitDate: editingRecord ? formatDateForInput(editingRecord.visit_date) : getTodayString(),
    faceRating: editingRecord?.face_rating || 1,
    styleRating: editingRecord?.style_rating || 1,
    serviceRating: editingRecord?.service_rating || 1,
    overallRating: editingRecord?.overall_rating || 1,
    review: editingRecord?.review || '',
    price: editingRecord?.price || '',
    course: editingRecord?.course || '',
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
      // 認証トークンがない場合はAPIを呼び出さない
      const authToken = getAuthToken()
      if (!authToken) {
        setIsLoadingShopTypes(false)
        return
      }

      try {
        const url = getApiUrl('/api/shop-types')
        const response = await fetchWithAuth(url, { method: 'GET' })
        
        // 401エラーの場合は認証エラー処理を実行
        if (response.status === 401) {
          handleAuthError(response)
          return
        }
        
        const data = await response.json()

        if (response.ok && data.success) {
          setShopTypes(data.shop_types || [])
          // shopTypesが読み込まれた後、既に選択されているshopTypeがあればお店名を取得
          if (formData.shopType) {
            const selectedShopType = (data.shop_types || []).find(st => String(st.id) === String(formData.shopType))
            if (selectedShopType && selectedShopType.name !== 'その他') {
              console.log('Shop types loaded, fetching shop names for selected type:', formData.shopType)
              fetchShopNames(formData.shopType)
            }
          }
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
      const shopType = editingRecord.shop_type_id || editingRecord.shop_type
      const shopTypeName = editingRecord.shop_type
      if (shopType && shopTypeName !== 'その他') {
        // shop_type_idが使える場合はそれを使い、なければ名前を使う
        fetchShopNames(shopType)
      }
      // お店の種類とお店名が設定されている場合、女の子の名前を取得
      if (shopType && editingRecord.shop_name) {
        fetchGirlNames(shopType, editingRecord.shop_name)
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
      if (value) {
        // 型の不一致を防ぐため、文字列に変換して比較
        const selectedShopType = shopTypes.find(st => String(st.id) === String(value))
        console.log('Shop type selected:', { value, selectedShopType, shopTypes, shopTypesLength: shopTypes.length })
        if (selectedShopType && selectedShopType.name !== 'その他') {
          console.log('Fetching shop names for shop type:', value)
          fetchShopNames(value)
        } else {
          console.log('Setting shop names to empty (その他 or not found)', { selectedShopType })
          setShopNames([])
        }
      } else {
        setShopNames([])
      }
    }
  }

  const fetchShopNames = async (shopType) => {
    console.log('fetchShopNames called:', { shopType, userId })
    if (!userId || !shopType) {
      console.log('fetchShopNames: Missing userId or shopType')
      return
    }

    // 認証トークンがない場合はAPIを呼び出さない
    const authToken = getAuthToken()
    if (!authToken) {
      console.log('fetchShopNames: No auth token')
      return
    }

    setIsLoadingShopNames(true)
    try {
      const url = getApiUrl(`/api/records/shop-names?shop_type=${encodeURIComponent(shopType)}`)
      console.log('fetchShopNames: Fetching from URL:', url)
      const response = await fetchWithAuth(url, { method: 'GET' })
      
      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
      const data = await response.json()
      console.log('fetchShopNames: Response:', { status: response.status, data })

      if (response.ok && data.success) {
        const shopNamesList = data.shop_names || []
        console.log('fetchShopNames: Shop names list:', shopNamesList)
        // 編集モードの場合、既存のお店名がリストに含まれていない場合は追加
        if (editingRecord && editingRecord.shop_name && !shopNamesList.includes(editingRecord.shop_name)) {
          shopNamesList.push(editingRecord.shop_name)
        }
        setShopNames(shopNamesList)
        setIsNewShopName(false)
        console.log('fetchShopNames: Set shopNames to:', shopNamesList)
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

    // 認証トークンがない場合はAPIを呼び出さない
    const authToken = getAuthToken()
    if (!authToken) {
      return
    }

    setIsLoadingGirlNames(true)
    try {
      const response = await fetchWithAuth(getApiUrl(`/api/records/girl-names?shop_type=${encodeURIComponent(shopType)}&shop_name=${encodeURIComponent(shopName)}`), { method: 'GET' })
      
      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }
      
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
        // エラーの詳細をログに出力
        console.error('Failed to fetch girl names:', {
          status: response.status,
          statusText: response.statusText,
          data: data
        })
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

    // 認証トークンがない場合は処理を中断
    const authToken = getAuthToken()
    if (!authToken) {
      handleAuthError({ status: 401 })
      return
    }

    // バリデーション
    if (!formData.visitDate) {
      setError('来店日を入力してください')
      return
    }
    // shopTypeは数値（ID）なので、trim()ではなく値の存在チェック
    if (!formData.shopType || (typeof formData.shopType === 'string' && !formData.shopType.trim())) {
      setError('お店の種類を入力してください')
      return
    }
    if (!formData.shopName || (typeof formData.shopName === 'string' && !formData.shopName.trim())) {
      setError('お店の名前を入力してください')
      return
    }

    setIsSubmitting(true)

    try {
      const url = editingRecord 
        ? getApiUrl(`/api/records/${editingRecord.id}`)
        : getApiUrl('/api/records')
      const method = editingRecord ? 'PUT' : 'POST'
      
      const requestBody = {
        shop_type_id: formData.shopType,
        shop_name: formData.shopName,
        girl_name: formData.girlName.trim() || null,
        visit_date: formData.visitDate,
        face_rating: formData.faceRating >= 1 ? formData.faceRating : null,
        style_rating: formData.styleRating >= 1 ? formData.styleRating : null,
        service_rating: formData.serviceRating >= 1 ? formData.serviceRating : null,
        overall_rating: formData.overallRating >= 1 ? formData.overallRating : null,
        review: formData.review || null,
        price: formData.price ? parseInt(formData.price, 10) : null,
        course: formData.course?.trim() || null,
      }

      const response = await fetchWithAuth(url, {
        method: method,
        body: JSON.stringify(requestBody),
      })

      // 401エラーの場合は認証エラー処理を実行
      if (response.status === 401) {
        handleAuthError(response)
        return
      }

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
          faceRating: 1,
          styleRating: 1,
          serviceRating: 1,
          overallRating: 1,
          review: '',
          price: '',
          course: '',
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
              <option key={shopType.id} value={shopType.id}>
                {shopType.name}
              </option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label htmlFor="shopName" className="form-label">
            お店の名前 <span className="required">*</span>
          </label>
          {(() => {
            // 型の不一致を防ぐため、文字列に変換して比較
            const selectedShopType = shopTypes.find(st => String(st.id) === String(formData.shopType))
            const isOtherShopType = selectedShopType && selectedShopType.name === 'その他'
            console.log('Shop name field render:', { 
              shopType: formData.shopType, 
              selectedShopType, 
              isOtherShopType, 
              shopNames, 
              shopNamesLength: shopNames.length, 
              isNewShopName, 
              isLoadingShopNames 
            })
            return isOtherShopType ? (
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
            )
          })()}
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
          <label htmlFor="course" className="form-label">
            コース
          </label>
          <input
            type="text"
            id="course"
            name="course"
            value={formData.course}
            onChange={handleInputChange}
            className="form-input"
            placeholder="コースを入力（任意）"
            disabled={isSubmitting}
          />
        </div>

        <div className="form-group">
          <label htmlFor="price" className="form-label">
            利用料金
          </label>
          <input
            type="number"
            id="price"
            name="price"
            value={formData.price}
            onChange={handleInputChange}
            className="form-input"
            placeholder="利用料金を入力（任意）"
            min="0"
            step="1"
            disabled={isSubmitting}
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

RecordForm.propTypes = {
  userId: PropTypes.string.isRequired,
  onRecordAdded: PropTypes.func.isRequired,
  editingRecord: PropTypes.shape({
    id: PropTypes.string.isRequired,
    shop_type_id: PropTypes.string,
    shop_type: PropTypes.string,
    shop_name: PropTypes.string,
    girl_name: PropTypes.string,
    visit_date: PropTypes.string,
    face_rating: PropTypes.number,
    style_rating: PropTypes.number,
    service_rating: PropTypes.number,
    overall_rating: PropTypes.number,
    review: PropTypes.string,
    price: PropTypes.number,
    course: PropTypes.string,
  }),
  onCancelEdit: PropTypes.func,
}

export default RecordForm

