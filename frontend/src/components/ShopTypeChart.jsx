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
import './ShopTypeChart.css'

ChartJS.register(ArcElement, Tooltip, Legend, ChartDataLabels)

function ShopTypeChart({ user }) {
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
        const response = await fetchWithAuth(getApiUrl('/api/records/shop-type-statistics'), {
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

        // 色のパレットを定義（コントラストの高い色）
        const colors = [
          'rgba(74, 144, 226, 1)',      // 青
          'rgba(255, 107, 107, 1)',      // 赤
          'rgba(255, 206, 84, 1)',       // 黄
          'rgba(75, 192, 192, 1)',       // シアン
          'rgba(232, 106, 255, 1)',      // 紫
          'rgba(255, 159, 64, 1)',       // オレンジ
          'rgba(54, 162, 235, 1)',       // ライトブルー
          'rgba(255, 99, 132, 1)',       // ピンク
          'rgba(153, 102, 255, 1)',      // パープル
          'rgba(201, 203, 207, 1)',      // グレー
          'rgba(255, 205, 86, 1)',       // イエロー
          'rgba(75, 192, 192, 0.8)',     // ダークシアン
          'rgba(255, 107, 107, 0.8)'     // ダークレッド
        ]

        const labels = statistics.map(item => item.label)
        const values = statistics.map(item => item.value)
        const backgroundColors = colors.slice(0, statistics.length)
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
            const value = context.parsed || 0
            const total = context.dataset.data.reduce((a, b) => a + b, 0)
            const percentage = ((value / total) * 100).toFixed(1)
            return `${label}: ${value}件 (${percentage}%)`
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
          const total = context.dataset.data.reduce((a, b) => a + b, 0)
          const percentage = ((value / total) * 100).toFixed(1)
          return `${label}\n${percentage}%`
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
        <Doughnut data={chartData} options={chartOptions} />
      </div>
    </div>
  )
}

ShopTypeChart.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  })
}

export default ShopTypeChart

