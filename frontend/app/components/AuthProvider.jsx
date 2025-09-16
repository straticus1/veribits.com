'use client'

import { createContext, useContext, useState, useEffect } from 'react'
import axios from 'axios'

const AuthContext = createContext()

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return context
}

export default function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [token, setToken] = useState(null)
  const [loading, setLoading] = useState(true)

  const apiBaseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'

  useEffect(() => {
    const storedToken = localStorage.getItem('veribits_token')
    if (storedToken) {
      setToken(storedToken)
      axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`
      loadProfile()
    } else {
      setLoading(false)
    }
  }, [])

  const loadProfile = async () => {
    try {
      const response = await axios.get(`${apiBaseUrl}/api/v1/auth/profile`)
      setUser(response.data.data.user)
    } catch (error) {
      console.error('Failed to load profile:', error)
      logout()
    } finally {
      setLoading(false)
    }
  }

  const login = async (email, password) => {
    try {
      const response = await axios.post(`${apiBaseUrl}/api/v1/auth/login`, {
        email,
        password
      })

      const { access_token, user: userData } = response.data.data

      setToken(access_token)
      setUser(userData)

      localStorage.setItem('veribits_token', access_token)
      axios.defaults.headers.common['Authorization'] = `Bearer ${access_token}`

      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error?.message || 'Login failed'
      }
    }
  }

  const register = async (email, password) => {
    try {
      const response = await axios.post(`${apiBaseUrl}/api/v1/auth/register`, {
        email,
        password
      })

      return {
        success: true,
        data: response.data.data
      }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error?.message || 'Registration failed'
      }
    }
  }

  const logout = async () => {
    try {
      if (token) {
        await axios.post(`${apiBaseUrl}/api/v1/auth/logout`)
      }
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      setUser(null)
      setToken(null)
      localStorage.removeItem('veribits_token')
      delete axios.defaults.headers.common['Authorization']
    }
  }

  const value = {
    user,
    token,
    loading,
    login,
    register,
    logout,
    isAuthenticated: !!user,
    apiBaseUrl
  }

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  )
}