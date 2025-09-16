'use client'

import { useState } from 'react'
import AuthProvider, { useAuth } from './components/AuthProvider'
import Navbar from './components/Navbar'
import Dashboard from './components/Dashboard'
import LandingPage from './components/LandingPage'

export default function Home() {
  return (
    <AuthProvider>
      <div className="min-h-screen bg-gray-50">
        <Navbar />
        <main>
          <AuthenticatedContent />
        </main>
      </div>
    </AuthProvider>
  )
}

function AuthenticatedContent() {
  const { isAuthenticated, loading } = useAuth()

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    )
  }

  return isAuthenticated ? <Dashboard /> : <LandingPage />
}

