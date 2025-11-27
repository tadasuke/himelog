import { useState, useEffect } from 'react'
import PropTypes from 'prop-types'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend
} from 'chart.js'
import { Doughnut } from 'react-chartjs-2'
import ChartDataLabels from 'chartjs-plugin-datalabels'
import { getApiUrl, fetchWithAuth, getAuthToken, handleAuthError } from '../utils/api'
import './OverallRatingPieChart.css'

ChartJS.register(ArcElement, Tooltip, Legend, ChartDataLabels)

function OverallRatingPieChart({ user }) {
  const [chartData, setChartData] = useState(null)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)

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
        const response = await fetchWithAuth(getApiUrl('/api/records/overall-rating-statistics'), {
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

        const statistics = data.statistics || []

        if (statistics.length === 0) {
          setChartData(null)
          setIsLoading(false)
          return
        }

        // 色のパレットを定義（評価が高いほど良い色：低評価=青/緑系、高評価=赤系）
        const colors = [
          'rgba(50, 150, 220, 1)',       // 1星: 明るい青（低評価）
          'rgba(60, 180, 200, 1)',       // 2星: 青緑/シアン
          'rgba(80, 200, 150, 1)',       // 3星: 明るい緑
          'rgba(100, 200, 120, 1)',      // 4星: 緑
          'rgba(180, 220, 120, 1)',      // 5星: 黄緑（中間）
          'rgba(255, 220, 100, 1)',      // 6星: 黄色
          'rgba(255, 200, 80, 1)',       // 7星: 黄オレンジ
          'rgba(255, 150, 80, 1)',       // 8星: オレンジ
          'rgba(255, 99, 99, 1)',        // 9星: 赤
          'rgba(200, 50, 50, 1)'         // 10星: 暗い赤（最高評価）
        ]

        const labels = statistics.map(item => item.label)
        const values = statistics.map(item => item.value)
        
        // 評価の高い順に色を割り当て（10星から1星の順）
        const backgroundColors = statistics.map(item => {
          const rating = parseInt(item.label.replace('星', ''))
          return colors[rating - 1] || 'rgba(199, 199, 199, 1)'
        })
        
        // 境界線は白で、より太くして境目を明確に
        const borderColors = Array(statistics.length).fill('rgba(255, 255, 255, 1)')

        setChartData({
          labels,
          datasets: [
            {
              data: values,
              backgroundColor: backgroundColors,
              borderColor: borderColors,
              borderWidth: 3
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

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false // 右側の凡例を非表示
      },
      tooltip: {
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        titleColor: '#ffffff',
        bodyColor: '#e0e0e0',
        borderColor: 'rgba(74, 144, 226, 0.5)',
        borderWidth: 1,
        padding: 12,
        displayColors: true,
        callbacks: {
          label: function(context) {
            const label = context.label || ''
            // 「星」を削除して数字のみ表示
            const labelWithoutStar = label.replace('星', '')
            const value = context.parsed || 0
            const total = context.dataset.data.reduce((a, b) => a + b, 0)
            const percentage = ((value / total) * 100).toFixed(1)
            return `${labelWithoutStar}: ${value}件 (${percentage}%)`
          }
        }
      },
      datalabels: {
        display: function(context) {
          // セグメントが小さすぎる場合は非表示
          const dataset = context.dataset
          const value = dataset.data[context.dataIndex]
          const total = dataset.data.reduce((a, b) => a + b, 0)
          const percentage = (value / total) * 100
          return percentage >= 3 // 3%以上のセグメントのみ表示
        },
        color: '#ffffff',
        font: {
          weight: 'bold',
          size: 13
        },
        formatter: function(value, context) {
          const label = context.chart.data.labels[context.dataIndex]
          const labelWithoutStar = label.replace('星', '')
          const total = context.dataset.data.reduce((a, b) => a + b, 0)
          const percentage = ((value / total) * 100).toFixed(1)
          return `★${labelWithoutStar}\n${percentage}%`
        },
        textAlign: 'center',
        textStrokeColor: 'rgba(0, 0, 0, 0.6)',
        textStrokeWidth: 3,
        padding: 4
      }
    },
    onHover: (event, activeElements) => {
      event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default'
    }
  }

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
      <div className="chart-wrapper">
        <Doughnut 
          data={chartData} 
          options={chartOptions}
        />
      </div>
    </div>
  )
}

OverallRatingPieChart.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  })
}

export default OverallRatingPieChart

