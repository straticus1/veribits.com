'use client'

import { useState } from 'react'
import { useAuth } from '@/app/components/AuthProvider'
import {
  Shield,
  Mail,
  Key,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Info,
  Copy,
  Eye,
  EyeOff,
  Database,
  Clock,
  TrendingUp
} from 'lucide-react'

export default function BreachChecker() {
  const { user } = useAuth()
  const [checkType, setCheckType] = useState('email')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [copied, setCopied] = useState(false)

  const handleCheck = async () => {
    setError('')
    setResult(null)

    const value = checkType === 'email' ? email : password
    if (!value.trim()) {
      setError(`Please enter ${checkType === 'email' ? 'an email address' : 'a password'}`)
      return
    }

    setLoading(true)

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
      const token = localStorage.getItem('auth_token')

      const endpoint = checkType === 'email' ? '/api/v1/hibp/check-email' : '/api/v1/hibp/check-password'
      const body = checkType === 'email'
        ? { email: email.trim() }
        : { password: password }

      const response = await fetch(`${apiUrl}${endpoint}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(token && { 'Authorization': `Bearer ${token}` })
        },
        body: JSON.stringify(body)
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error?.message || 'Check failed')
      }

      setResult(data.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const copyToClipboard = async (text) => {
    try {
      await navigator.clipboard.writeText(text)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch (err) {
      console.error('Failed to copy:', err)
    }
  }

  const renderEmailResults = () => {
    if (!result || checkType !== 'email') return null

    const breachCount = result.breach_count || 0
    const breaches = result.breaches || []

    return (
      <div className="space-y-6">
        {/* Summary Card */}
        <div className={`p-6 rounded-lg border-2 ${
          breachCount > 0
            ? 'bg-red-50 border-red-200'
            : 'bg-green-50 border-green-200'
        }`}>
          <div className="flex items-start gap-4">
            {breachCount > 0 ? (
              <AlertTriangle className="w-12 h-12 text-red-600 flex-shrink-0" />
            ) : (
              <CheckCircle className="w-12 h-12 text-green-600 flex-shrink-0" />
            )}
            <div className="flex-1">
              <h3 className={`text-2xl font-bold mb-2 ${
                breachCount > 0 ? 'text-red-900' : 'text-green-900'
              }`}>
                {breachCount > 0
                  ? `Found in ${breachCount} breach${breachCount !== 1 ? 'es' : ''}!`
                  : 'No breaches found!'}
              </h3>
              <p className={breachCount > 0 ? 'text-red-700' : 'text-green-700'}>
                {breachCount > 0
                  ? `This email address has been compromised in ${breachCount} known data breach${breachCount !== 1 ? 'es' : ''}. Consider changing passwords for associated accounts.`
                  : 'This email address has not been found in any known data breaches.'}
              </p>
              {result.cached && (
                <div className="mt-2 flex items-center gap-2 text-sm text-gray-600">
                  <Clock className="w-4 h-4" />
                  <span>Cached result from {new Date(result.checked_at).toLocaleString()}</span>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Breach Details */}
        {breachCount > 0 && (
          <div className="space-y-4">
            <h4 className="text-xl font-semibold text-gray-900">Breach Details</h4>
            {breaches.map((breach, index) => (
              <div key={index} className="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div className="flex items-start justify-between mb-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                      <h5 className="text-lg font-bold text-gray-900">{breach.Name}</h5>
                      {breach.IsVerified && (
                        <span className="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded">
                          Verified
                        </span>
                      )}
                      {breach.IsSensitive && (
                        <span className="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded">
                          Sensitive
                        </span>
                      )}
                    </div>
                    <p className="text-sm text-gray-600">{breach.Domain}</p>
                  </div>
                  <div className="text-right">
                    <div className="text-sm text-gray-500">Breach Date</div>
                    <div className="font-semibold text-gray-900">{breach.BreachDate}</div>
                  </div>
                </div>

                <p className="text-gray-700 mb-4">{breach.Description?.replace(/<[^>]*>/g, '')}</p>

                {breach.DataClasses && breach.DataClasses.length > 0 && (
                  <div>
                    <div className="text-sm font-semibold text-gray-700 mb-2">Compromised Data:</div>
                    <div className="flex flex-wrap gap-2">
                      {breach.DataClasses.map((dataClass, i) => (
                        <span
                          key={i}
                          className="px-3 py-1 bg-gray-100 text-gray-700 text-sm rounded-full"
                        >
                          {dataClass}
                        </span>
                      ))}
                    </div>
                  </div>
                )}

                <div className="mt-4 pt-4 border-t border-gray-200 flex items-center justify-between text-sm text-gray-500">
                  <div>
                    <Database className="w-4 h-4 inline mr-1" />
                    Added to HIBP: {new Date(breach.AddedDate).toLocaleDateString()}
                  </div>
                  {breach.PwnCount && (
                    <div>
                      <TrendingUp className="w-4 h-4 inline mr-1" />
                      {breach.PwnCount.toLocaleString()} accounts affected
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Recommendations */}
        {breachCount > 0 && (
          <div className="bg-amber-50 border border-amber-200 rounded-lg p-6">
            <h4 className="text-lg font-semibold text-amber-900 mb-3 flex items-center gap-2">
              <Info className="w-5 h-5" />
              Recommended Actions
            </h4>
            <ul className="space-y-2 text-amber-800">
              <li className="flex items-start gap-2">
                <span className="text-amber-600 mt-1">•</span>
                <span>Change passwords for all accounts associated with this email</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-amber-600 mt-1">•</span>
                <span>Enable two-factor authentication (2FA) where available</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-amber-600 mt-1">•</span>
                <span>Monitor your accounts for suspicious activity</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-amber-600 mt-1">•</span>
                <span>Consider using a password manager with unique passwords for each service</span>
              </li>
            </ul>
          </div>
        )}
      </div>
    )
  }

  const renderPasswordResults = () => {
    if (!result || checkType !== 'password') return null

    const isPwned = result.pwned
    const occurrences = result.occurrences || 0

    return (
      <div className="space-y-6">
        {/* Summary Card */}
        <div className={`p-6 rounded-lg border-2 ${
          isPwned
            ? 'bg-red-50 border-red-200'
            : 'bg-green-50 border-green-200'
        }`}>
          <div className="flex items-start gap-4">
            {isPwned ? (
              <XCircle className="w-12 h-12 text-red-600 flex-shrink-0" />
            ) : (
              <CheckCircle className="w-12 h-12 text-green-600 flex-shrink-0" />
            )}
            <div className="flex-1">
              <h3 className={`text-2xl font-bold mb-2 ${
                isPwned ? 'text-red-900' : 'text-green-900'
              }`}>
                {isPwned
                  ? 'Password Compromised!'
                  : 'Password Secure!'}
              </h3>
              <p className={isPwned ? 'text-red-700' : 'text-green-700'}>
                {result.message || (isPwned
                  ? `This password has been seen ${occurrences.toLocaleString()} times in data breaches. Do not use it!`
                  : 'This password has not been found in any known data breaches.')}
              </p>
              {result.cached && (
                <div className="mt-2 flex items-center gap-2 text-sm text-gray-600">
                  <Clock className="w-4 h-4" />
                  <span>Cached result from {new Date(result.checked_at).toLocaleString()}</span>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Security Meter */}
        {isPwned && (
          <div className="bg-white border border-gray-200 rounded-lg p-6">
            <h4 className="text-lg font-semibold text-gray-900 mb-4">Exposure Level</h4>
            <div className="space-y-3">
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-700">Times seen in breaches:</span>
                <span className="font-bold text-red-600">{occurrences.toLocaleString()}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                <div
                  className={`h-full transition-all ${
                    occurrences > 100000 ? 'bg-red-600' :
                    occurrences > 10000 ? 'bg-orange-500' :
                    occurrences > 1000 ? 'bg-yellow-500' :
                    'bg-amber-500'
                  }`}
                  style={{ width: Math.min(100, (Math.log10(occurrences) / 6) * 100) + '%' }}
                />
              </div>
              <div className="text-xs text-gray-500 text-center">
                {occurrences > 100000 && 'Extremely Common - Critical Risk'}
                {occurrences <= 100000 && occurrences > 10000 && 'Very Common - High Risk'}
                {occurrences <= 10000 && occurrences > 1000 && 'Common - Medium Risk'}
                {occurrences <= 1000 && 'Low Frequency - Still Compromised'}
              </div>
            </div>
          </div>
        )}

        {/* Recommendations */}
        <div className={`${isPwned ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'} border rounded-lg p-6`}>
          <h4 className={`text-lg font-semibold mb-3 flex items-center gap-2 ${isPwned ? 'text-red-900' : 'text-green-900'}`}>
            <Info className="w-5 h-5" />
            {isPwned ? 'Immediate Actions Required' : 'Security Best Practices'}
          </h4>
          <ul className={`space-y-2 ${isPwned ? 'text-red-800' : 'text-green-800'}`}>
            {isPwned ? (
              <>
                <li className="flex items-start gap-2">
                  <span className="text-red-600 mt-1">•</span>
                  <span className="font-semibold">Change this password immediately on all accounts</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-red-600 mt-1">•</span>
                  <span>Never reuse passwords across multiple services</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-red-600 mt-1">•</span>
                  <span>Use a password manager to generate and store strong, unique passwords</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-red-600 mt-1">•</span>
                  <span>Enable two-factor authentication (2FA) on all important accounts</span>
                </li>
              </>
            ) : (
              <>
                <li className="flex items-start gap-2">
                  <span className="text-green-600 mt-1">•</span>
                  <span>Still ensure this password is unique to each service</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-green-600 mt-1">•</span>
                  <span>Consider using a passphrase (4+ random words) for better security</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-green-600 mt-1">•</span>
                  <span>Enable two-factor authentication for added protection</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-green-600 mt-1">•</span>
                  <span>Regularly check your passwords for compromise</span>
                </li>
              </>
            )}
          </ul>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
          <Shield className="w-8 h-8 text-red-600" />
          Data Breach Checker
        </h1>
        <p className="text-gray-600 mt-2">
          Check if your email or password has been compromised in known data breaches using the Have I Been Pwned database.
        </p>
        <div className="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <div className="flex items-start gap-2">
            <Info className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div className="text-sm text-blue-800">
              <p className="font-semibold mb-1">Privacy Notice</p>
              <p>Email addresses are sent to the HIBP API. Passwords use k-anonymity - only the first 5 characters of the SHA-1 hash are sent, ensuring your actual password never leaves your device in plaintext.</p>
            </div>
          </div>
        </div>
      </div>

      {/* Input Section */}
      <div className="card max-w-4xl mb-8">
        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-3">
            Check Type
          </label>
          <div className="grid grid-cols-2 gap-4">
            <button
              onClick={() => setCheckType('email')}
              className={`p-4 rounded-lg border-2 transition-all ${
                checkType === 'email'
                  ? 'border-red-500 bg-red-50 text-red-900'
                  : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'
              }`}
            >
              <Mail className="w-6 h-6 mx-auto mb-2" />
              <div className="font-semibold">Email Address</div>
              <div className="text-xs mt-1 opacity-75">Check for breached accounts</div>
            </button>
            <button
              onClick={() => setCheckType('password')}
              className={`p-4 rounded-lg border-2 transition-all ${
                checkType === 'password'
                  ? 'border-red-500 bg-red-50 text-red-900'
                  : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'
              }`}
            >
              <Key className="w-6 h-6 mx-auto mb-2" />
              <div className="font-semibold">Password</div>
              <div className="text-xs mt-1 opacity-75">Check password security</div>
            </button>
          </div>
        </div>

        {checkType === 'email' ? (
          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Email Address
            </label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="example@domain.com"
              className="input"
              onKeyPress={(e) => e.key === 'Enter' && handleCheck()}
            />
          </div>
        ) : (
          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Password
            </label>
            <div className="relative">
              <input
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter password to check"
                className="input pr-12"
                onKeyPress={(e) => e.key === 'Enter' && handleCheck()}
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
              >
                {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
              </button>
            </div>
            <p className="text-xs text-gray-500 mt-1">
              Your password is hashed locally and only the first 5 characters of the hash are sent to the API
            </p>
          </div>
        )}

        <button
          onClick={handleCheck}
          disabled={loading}
          className="btn-primary w-full"
        >
          {loading ? (
            <>
              <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
              Checking...
            </>
          ) : (
            <>
              <Shield className="w-5 h-5 mr-2" />
              Check for Breaches
            </>
          )}
        </button>

        {!user && (
          <div className="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <Info className="w-4 h-4 inline mr-2" />
            Anonymous users: 5 checks per minute. <a href="/signup" className="font-semibold underline">Sign up</a> for 50 checks per minute.
          </div>
        )}
      </div>

      {/* Error Display */}
      {error && (
        <div className="max-w-4xl mb-8 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
          <div className="flex items-start gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 mt-0.5" />
            <div>
              <div className="font-semibold">Error</div>
              <div className="text-sm mt-1">{error}</div>
            </div>
          </div>
        </div>
      )}

      {/* Results Display */}
      {result && (
        <div className="max-w-4xl">
          {checkType === 'email' ? renderEmailResults() : renderPasswordResults()}
        </div>
      )}
    </div>
  )
}
