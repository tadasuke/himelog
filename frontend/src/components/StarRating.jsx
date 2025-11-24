import PropTypes from 'prop-types'
import './StarRating.css'

function StarRating({ rating, onRatingChange, readonly = false }) {
  const handleStarClick = (value) => {
    if (!readonly && onRatingChange) {
      onRatingChange(value)
    }
  }

  return (
    <div className="star-rating">
      {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((value) => (
        <span
          key={value}
          className={`star ${value <= rating ? 'filled' : ''} ${!readonly ? 'clickable' : ''}`}
          onClick={() => handleStarClick(value)}
        >
          ★
        </span>
      ))}
    </div>
  )
}

StarRating.propTypes = {
  rating: PropTypes.number.isRequired,
  onRatingChange: PropTypes.func,
  readonly: PropTypes.bool,
}

export default StarRating

