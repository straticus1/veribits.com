'use client'

import { useState } from 'react'
import { useAuth } from './AuthProvider'
import { Shield, CheckCircle, Zap, Globe, Eye, EyeOff } from 'lucide-react'

export default function LandingPage() {
  const { login, register } = useAuth()
  const [isLogin, setIsLogin] = useState(true)
  const [formData, setFormData] = useState({ email: '', password: '' })
  const [showPassword, setShowPassword] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setError('')
    setSuccess('')

    const { email, password } = formData

    if (isLogin) {
      const result = await login(email, password)
      if (!result.success) {
        setError(result.error)
      }
    } else {
      const result = await register(email, password)
      if (result.success) {
        setSuccess('Registration successful! Please sign in.')
        setIsLogin(true)
        setFormData({ email: '', password: '' })
      } else {
        setError(result.error)
      }
    }

    setLoading(false)
  }

  const handleInputChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    })
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary-50 to-primary-100">
      {/* Hero Section */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-16">
        <div className="text-center">
          <div className="flex justify-center mb-8">
            <Shield className="h-16 w-16 text-primary-600" />
          </div>
          <h1 className="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
            Trust Verification
            <span className="text-primary-600"> Simplified</span>
          </h1>
          <p className="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
            Verify files, emails, and transactions with enterprise-grade security.
            Get instant trust scores and protect your digital assets.
          </p>

          {/* Features */}
          <div className="grid md:grid-cols-3 gap-8 mb-16">
            <div className="text-center">
              <CheckCircle className="h-12 w-12 text-success-500 mx-auto mb-4" />
              <h3 className="text-lg font-semibold mb-2">File Verification</h3>
              <p className="text-gray-600">Verify file integrity with SHA256 hashes and threat intelligence</p>
            </div>
            <div className="text-center">
              <Zap className="h-12 w-12 text-warning-500 mx-auto mb-4" />
              <h3 className="text-lg font-semibold mb-2">Email Validation</h3>
              <p className="text-gray-600">Check email reputation and detect disposable addresses</p>
            </div>
            <div className="text-center">
              <Globe className="h-12 w-12 text-primary-500 mx-auto mb-4" />
              <h3 className="text-lg font-semibold mb-2">Blockchain Analysis</h3>
              <p className="text-gray-600">Verify cryptocurrency transactions across multiple networks</p>
            </div>
          </div>

          {/* Auth Form */}
          <div className="max-w-md mx-auto bg-white rounded-lg shadow-lg p-8">
            <div className="text-center mb-6">
              <h2 className="text-2xl font-bold text-gray-900">
                {isLogin ? 'Sign In' : 'Create Account'}
              </h2>
              <p className="text-gray-600 mt-2">
                {isLogin ? 'Access your verification dashboard' : 'Start verifying with VeriBits'}
              </p>
            </div>

            {error && (
              <div className="mb-4 p-3 bg-danger-50 border border-danger-200 rounded-md">
                <p className="text-danger-600 text-sm">{error}</p>
              </div>
            )}

            {success && (
              <div className="mb-4 p-3 bg-success-50 border border-success-200 rounded-md">
                <p className="text-success-600 text-sm">{success}</p>
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                  Email address
                </label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  required
                  value={formData.email}
                  onChange={handleInputChange}
                  className="input"
                  placeholder="Enter your email"
                />
              </div>

              <div>
                <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                  Password
                </label>
                <div className="relative">
                  <input
                    id="password"
                    name="password"
                    type={showPassword ? 'text' : 'password'}
                    required
                    value={formData.password}
                    onChange={handleInputChange}
                    className="input pr-10"
                    placeholder="Enter your password"
                    minLength={8}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute inset-y-0 right-0 pr-3 flex items-center"
                  >
                    {showPassword ? (
                      <EyeOff className="h-4 w-4 text-gray-400" />
                    ) : (
                      <Eye className="h-4 w-4 text-gray-400" />
                    )}
                  </button>
                </div>
                {!isLogin && (
                  <p className="mt-1 text-xs text-gray-500">
                    Password must be at least 8 characters long
                  </p>
                )}
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full btn-primary"
              >
                {loading ? 'Processing...' : (isLogin ? 'Sign In' : 'Create Account')}
              </button>
            </form>

            <div className="mt-6 text-center">
              <button
                onClick={() => {
                  setIsLogin(!isLogin)
                  setError('')
                  setSuccess('')
                  setFormData({ email: '', password: '' })
                }}
                className="text-primary-600 hover:text-primary-500 text-sm font-medium"
              >
                {isLogin
                  ? "Don't have an account? Sign up"
                  : 'Already have an account? Sign in'
                }
              </button>
            </div>
          </div>

          {/* Footer */}
          <div className="mt-16 text-center text-gray-500">
            <p>&copy; {new Date().getFullYear()} After Dark Systems. All rights reserved.</p>
          </div>
        </div>
      </div>
    </div>
  )
}