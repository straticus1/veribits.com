'use client'

import { useState } from 'react'
import { useAuth } from './AuthProvider'
import { Shield, Menu, X, LogOut, User } from 'lucide-react'

export default function Navbar() {
  const { user, logout, isAuthenticated } = useAuth()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  return (
    <nav className="bg-white shadow-sm border-b border-gray-200">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          {/* Logo and brand */}
          <div className="flex items-center">
            <div className="flex-shrink-0 flex items-center">
              <Shield className="h-8 w-8 text-primary-600" />
              <span className="ml-2 text-xl font-bold text-gray-900">VeriBits</span>
            </div>
          </div>

          {/* Desktop menu */}
          <div className="hidden md:flex items-center space-x-4">
            {isAuthenticated ? (
              <>
                <span className="text-sm text-gray-700">
                  Welcome, {user?.email}
                </span>
                <button
                  onClick={logout}
                  className="flex items-center text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium"
                >
                  <LogOut className="h-4 w-4 mr-1" />
                  Sign out
                </button>
              </>
            ) : (
              <div className="flex items-center space-x-2">
                <span className="text-sm text-gray-700">
                  Not signed in
                </span>
              </div>
            )}
          </div>

          {/* Mobile menu button */}
          <div className="md:hidden flex items-center">
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500"
            >
              {mobileMenuOpen ? (
                <X className="h-6 w-6" />
              ) : (
                <Menu className="h-6 w-6" />
              )}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile menu */}
      {mobileMenuOpen && (
        <div className="md:hidden">
          <div className="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-white border-t border-gray-200">
            {isAuthenticated ? (
              <>
                <div className="px-3 py-2 text-sm text-gray-700">
                  {user?.email}
                </div>
                <button
                  onClick={logout}
                  className="w-full text-left flex items-center px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50"
                >
                  <LogOut className="h-4 w-4 mr-2" />
                  Sign out
                </button>
              </>
            ) : (
              <div className="px-3 py-2 text-sm text-gray-700">
                Not signed in
              </div>
            )}
          </div>
        </div>
      )}
    </nav>
  )
}