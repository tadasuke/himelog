import { useState, useEffect, useRef, useMemo } from 'react'
import PropTypes from 'prop-types'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js'
import { Line } from 'react-chartjs-2'
import { getApiUrl, fetchWithAuth, getAuthToken, handleAuthError } from '../utils/api'
import './OverallRatingChart.css'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

function OverallRatingChart({ user, onGirlClick }) {
  const [chartData, setChartData] = useState(null)
  const [records, setRecords] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)
  const chartRef = useRef(null)
  const tooltipRef = useRef(null)
  const tooltipDataRef = useRef(null)

  useEffect(() => {
    if (!user?.id) return

    const fetchChartData = async () => {
      const authToken = getAuthToken()
      if (!authToken) {
        return
      }

      setIsLoading(true)
      setError(null)

      try {
        const response = await fetchWithAuth(getApiUrl('/api/records/recent-for-chart?limit=10'), {
          method: 'GET'
        })

        if (response.status === 401) {
          handleAuthError(response)
          return
        }

        const data = await response.json()

        if (!response.ok) {
          throw new Error(data.message || data.error || 'データの取得に失敗しました')
        }

        const records = data.records || []

        if (records.length === 0) {
          setChartData(null)
          setRecords([])
          setIsLoading(false)
          return
        }

        // レコードデータを状態に保存
        setRecords(records)

        // ラベル（投稿順）と総合評価のデータを準備
        const labels = records.map((record, index) => {
          // 来店日がある場合は来店日を使用、なければ作成日
          const date = record.visit_date || record.created_at
          if (date) {
            const dateObj = new Date(date)
            const month = dateObj.getMonth() + 1
            const day = dateObj.getDate()
            return `${month}/${day}`
          }
          return `投稿${index + 1}`
        })

        const ratings = records.map(record => record.overall_rating || 0)

        setChartData({
          labels,
          datasets: [
            {
              label: '総合評価',
              data: ratings,
              borderColor: 'rgba(74, 144, 226, 1)',
              backgroundColor: 'rgba(74, 144, 226, 0.1)',
              borderWidth: 2,
              fill: true,
              tension: 0.4,
              pointRadius: 4,
              pointHoverRadius: 6,
              pointBackgroundColor: 'rgba(74, 144, 226, 1)',
              pointBorderColor: '#ffffff',
              pointBorderWidth: 2
            }
          ]
        })
      } catch (error) {
        console.error('Fetch chart data error:', error)
        setError(error.message || 'データの取得中にエラーが発生しました')
      } finally {
        setIsLoading(false)
      }
    }

    fetchChartData()
  }, [user?.id])


  const chartOptions = useMemo(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: 'index',
      intersect: false,
    },
    plugins: {
      legend: {
        display: false
      },
        tooltip: {
        enabled: false,
        external: function(context) {
          if (!tooltipRef.current) return

          const tooltipModel = context.tooltip
          tooltipDataRef.current = tooltipModel

          if (tooltipModel.opacity === 0) {
            tooltipRef.current.style.opacity = 0
            tooltipRef.current.style.pointerEvents = 'none'
            return
          }

          const chart = context.chart
          const position = chart.canvas.getBoundingClientRect()
          const tooltipEl = tooltipRef.current

          // ツールチップのコンテンツを更新
          const dataIndex = tooltipModel.dataPoints[0]?.dataIndex
          if (dataIndex !== undefined && records[dataIndex]) {
            const record = records[dataIndex]
            const rating = tooltipModel.dataPoints[0]?.parsed.y
            const girlName = record?.girl_name || record?.girl?.girl_name || ''

            tooltipEl.innerHTML = `
              <div style="padding: 12px; background: rgba(0, 0, 0, 0.8); border: 1px solid rgba(74, 144, 226, 0.5); border-radius: 4px; color: #e0e0e0; white-space: nowrap;">
                <div style="color: #ffffff; margin-bottom: 8px;">総合評価: ${rating}</div>
                ${girlName ? `<div style="color: #4a90e2; cursor: pointer; text-decoration: underline;" class="tooltip-girl-name" data-girl-name="${girlName}">${girlName}</div>` : ''}
              </div>
            `

            // 位置を設定
            tooltipEl.style.opacity = 1
            tooltipEl.style.left = position.left + tooltipModel.caretX + 'px'
            tooltipEl.style.top = position.top + tooltipModel.caretY - 10 + 'px'
            tooltipEl.style.position = 'fixed'
            tooltipEl.style.pointerEvents = 'auto'
            tooltipEl.style.transform = 'translate(-50%, -100%)'

            // 姫の名前のクリックイベントを設定（イベント委譲を使用）
            if (!tooltipEl._hasClickHandler && onGirlClick) {
              const handleTooltipClick = (e) => {
                const girlNameElement = e.target.closest('.tooltip-girl-name')
                if (girlNameElement) {
                  const clickedGirlName = girlNameElement.getAttribute('data-girl-name')
                  if (clickedGirlName) {
                    e.preventDefault()
                    e.stopPropagation()
                    onGirlClick(clickedGirlName)
                  }
                }
              }
              tooltipEl.addEventListener('click', handleTooltipClick)
              tooltipEl._hasClickHandler = true
            }
          }
        }
      }
    },
    scales: {
      y: {
        min: 0,
        max: 10,
        ticks: {
          display: false
        },
        grid: {
          color: 'rgba(255, 255, 255, 0.1)',
          lineWidth: 1
        }
      },
      x: {
        ticks: {
          color: '#a0a0a0',
          font: {
            size: 11
          }
        },
        grid: {
          color: 'rgba(255, 255, 255, 0.1)',
          lineWidth: 1
        }
      }
    },
    onHover: (event, activeElements) => {
      if (event.native?.target) {
        event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default'
      }
    }
  }), [records, onGirlClick])

  if (isLoading) {
    return (
      <div className="chart-container">
        <div className="chart-loading">読み込み中...</div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="chart-container">
        <div className="chart-error">エラー: {error}</div>
      </div>
    )
  }

  if (!chartData) {
    return (
      <div className="chart-container">
        <div className="chart-empty">データがありません</div>
      </div>
    )
  }

  return (
    <div className="chart-container">
      <div className="chart-wrapper" style={{ position: 'relative' }}>
        <Line 
          ref={chartRef}
          data={chartData} 
          options={chartOptions}
        />
        <div
          ref={tooltipRef}
          onClick={(e) => e.stopPropagation()}
          style={{
            opacity: 0,
            position: 'fixed',
            pointerEvents: 'none',
            zIndex: 1000,
            transform: 'translate(-50%, -100%)'
          }}
        />
      </div>
    </div>
  )
}

OverallRatingChart.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
  onGirlClick: PropTypes.func
}

export default OverallRatingChart

