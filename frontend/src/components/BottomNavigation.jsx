import { useState } from 'react'
import PropTypes from 'prop-types'
import './BottomNavigation.css'

function BottomNavigation({ user, currentPage, onNavigate }) {
  const menuItems = [
    {
      id: 'home',
      label: 'ホーム',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M3 12L5 10M5 10L12 3L19 10M5 10V20C5 20.5523 5.44772 21 6 21H9M19 10L21 12M19 10V20C19 20.5523 18.5523 21 18 21H15M9 21C9.55228 21 10 20.5523 10 20V16C10 15.4477 10.4477 15 11 15H13C13.5523 15 14 15.4477 14 16V20C14 20.5523 14.4477 21 15 21M9 21H15" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      )
    },
    {
      id: 'discover',
      label: '思い出',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="11" cy="11" r="8" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
          <path d="M21 21L16.65 16.65" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      )
    },
    {
      id: 'create',
      label: 'かく',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M17 3C17.2652 3 17.5196 3.10536 17.7071 3.29289L20.7071 6.29289C20.8946 6.48043 21 6.73478 21 7C21 7.26522 20.8946 7.51957 20.7071 7.70711L8.70711 19.7071C8.51957 19.8946 8.26522 20 8 20H3C2.44772 20 2 19.5523 2 19V14C2 13.7348 2.10536 13.4804 2.29289 13.2929L14.2929 1.29289C14.4804 1.10536 14.7348 1 15 1H17V3Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      )
    },
    {
      id: 'girls',
      label: 'ヒメ',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="7.5" r="3.5" stroke="currentColor" strokeWidth="2"/>
          <path d="M6 21C6 18.2386 8.23858 16 11 16H13C15.7614 16 18 18.2386 18 21" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
          <path d="M8 11C8 11 9 10 12 10C15 10 16 11 16 11" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
          <path d="M10 2.5L10.5 1.5L12 1.5L13.5 1.5L14 2.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
          <path d="M10.5 1.5L10.5 3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
          <path d="M13.5 1.5L13.5 3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
          <path d="M7 16L12 20L17 16" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      )
    },
    {
      id: 'mypage',
      label: 'マイページ',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="8" r="4" stroke="currentColor" strokeWidth="2"/>
          <path d="M6 21C6 17.6863 8.68629 15 12 15C15.3137 15 18 17.6863 18 21" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
        </svg>
      )
    }
  ]

  return (
    <nav className="bottom-navigation">
      {menuItems.map((item) => {
        const isActive = currentPage === item.id
        return (
          <button
            key={item.id}
            className={`nav-item ${isActive ? 'active' : ''}`}
            onClick={() => onNavigate && onNavigate(item.id)}
          >
            <div className="nav-icon">
              {item.icon}
            </div>
            <span className="nav-label">{item.label}</span>
          </button>
        )
      })}
    </nav>
  )
}

BottomNavigation.propTypes = {
  user: PropTypes.shape({
    id: PropTypes.string.isRequired,
    name: PropTypes.string,
    email: PropTypes.string,
    avatar: PropTypes.string,
  }),
  currentPage: PropTypes.string.isRequired,
  onNavigate: PropTypes.func,
}

export default BottomNavigation

