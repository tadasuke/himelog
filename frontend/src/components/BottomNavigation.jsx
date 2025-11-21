import { useState } from 'react'
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
      label: 'みつける',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="2"/>
          <path d="M12 8L14 12L12 16L10 12L12 8Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
          <circle cx="12" cy="12" r="2" fill="currentColor"/>
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
      id: 'notifications',
      label: '通知',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18 8A6 6 0 0 0 6 8C6 11.0909 4.5 13.5 3 15M18 8C18 11.0909 19.5 13.5 21 15M18 8V9C18 10.6569 19.3431 12 21 12M6 8V9C6 10.6569 4.65685 12 3 12M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M21 15H3" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
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

export default BottomNavigation

