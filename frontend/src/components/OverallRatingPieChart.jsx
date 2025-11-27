import { useState, useEffect } from 'react'
import PropTypes from 'prop-types'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend
} from 'chart.js'
import { Doughnut } from 'react-chartjs-2'
import { getApiUrl, fetchWithAuth, getAuthToken, handleAuthError } from '../utils/api'
import './OverallRatingPieChart.css'

ChartJS.register(ArcElement, Tooltip, Legend)

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

        // 色のパレットを定義（評価が高いほど明るい色）
        const colors = [
          'rgba(255, 107, 107, 1)',      // 1星: 赤
          'rgba(255, 159, 64, 1)',       // 2星: オレンジ
          'rgba(255, 206, 84, 1)',       // 3星: 黄
          'rgba(255, 205, 86, 1)',       // 4星: イエロー
          'rgba(201, 203, 207, 1)',      // 5星: グレー
          'rgba(75, 192, 192, 1)',       // 6星: シアン
          'rgba(54, 162, 235, 1)',       // 7星: ライトブルー
          'rgba(74, 144, 226, 1)',       // 8星: 青
          'rgba(111, 140, 255, 1)',      // 9星: ライトブルー
          'rgba(232, 106, 255, 1)'       // 10星: 紫
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
        display: true,
        position: 'right',
        labels: {
          color: '#e0e0e0',
          font: {
            size: 12
          },
          padding: 15,
          generateLabels: function(chart) {
            const data = chart.data
            if (data.labels.length && data.datasets.length) {
              return data.labels.map((label, i) => {
                const dataset = data.datasets[0]
                const value = dataset.data[i]
                // 「星」を削除して数字のみ表示
                const labelWithoutStar = label.replace('星', '')
                return {
                  text: `${labelWithoutStar} (${value}件)`,
                  fillStyle: dataset.backgroundColor[i],
                  strokeStyle: dataset.borderColor[i],
                  lineWidth: dataset.borderWidth,
                  fontColor: '#e0e0e0',
                  textColor: '#e0e0e0',
                  hidden: false,
                  index: i
                }
              })
            }
            return []
          }
        }
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
      }
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

OverallRatingPieChart.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  })
}

export default OverallRatingPieChart

