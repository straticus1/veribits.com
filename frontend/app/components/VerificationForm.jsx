'use client'

import { useState } from 'react'
import { useAuth } from './AuthProvider'
import axios from 'axios'
import { FileText, Mail, Coins, Upload, CheckCircle, AlertTriangle, XCircle } from 'lucide-react'

export default function VerificationForm({ onVerificationComplete }) {
  const { apiBaseUrl } = useAuth()
  const [activeType, setActiveType] = useState('file')
  const [formData, setFormData] = useState({
    sha256: '',
    email: '',
    tx: '',
    network: 'bitcoin'
  })
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')

  const verificationTypes = [
    { id: 'file', name: 'File Hash', icon: FileText, description: 'Verify SHA256 file hashes' },
    { id: 'email', name: 'Email', icon: Mail, description: 'Validate email addresses' },
    { id: 'transaction', name: 'Transaction', icon: Coins, description: 'Verify blockchain transactions' }
  ]

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setError('')
    setResult(null)

    try {
      let endpoint = ''
      let payload = {}

      switch (activeType) {
        case 'file':
          endpoint = '/api/v1/verify/file'
          payload = { sha256: formData.sha256 }
          break
        case 'email':
          endpoint = '/api/v1/verify/email'
          payload = { email: formData.email }
          break
        case 'transaction':
          endpoint = '/api/v1/verify/tx'
          payload = { tx: formData.tx, network: formData.network }
          break
      }

      const response = await axios.post(`${apiBaseUrl}${endpoint}`, payload)
      setResult(response.data.data)

      if (onVerificationComplete) {
        onVerificationComplete(response.data.data)
      }
    } catch (err) {
      setError(err.response?.data?.error?.message || 'Verification failed')
    } finally {
      setLoading(false)
    }
  }

  const handleInputChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    })
  }

  const getRiskColor = (riskLevel) => {
    switch (riskLevel) {
      case 'low': return 'text-success-600 bg-success-50'
      case 'medium': return 'text-warning-600 bg-warning-50'
      case 'high': return 'text-danger-600 bg-danger-50'
      case 'critical': return 'text-danger-800 bg-danger-100'
      default: return 'text-gray-600 bg-gray-50'
    }
  }

  const getRiskIcon = (riskLevel) => {
    switch (riskLevel) {
      case 'low': return <CheckCircle className="h-5 w-5" />
      case 'medium': return <AlertTriangle className="h-5 w-5" />
      case 'high':
      case 'critical': return <XCircle className="h-5 w-5" />
      default: return <AlertTriangle className="h-5 w-5" />
    }
  }

  return (
    <div className="space-y-6">
      {/* Type Selection */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {verificationTypes.map((type) => {
          const Icon = type.icon
          return (
            <button
              key={type.id}
              onClick={() => {
                setActiveType(type.id)
                setResult(null)
                setError('')
              }}
              className={`p-4 rounded-lg border-2 transition-colors ${
                activeType === type.id
                  ? 'border-primary-500 bg-primary-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <Icon className={`h-8 w-8 mx-auto mb-2 ${
                activeType === type.id ? 'text-primary-600' : 'text-gray-400'
              }`} />
              <h3 className="font-medium text-gray-900">{type.name}</h3>
              <p className="text-sm text-gray-500 mt-1">{type.description}</p>
            </button>
          )
        })}
      </div>

      {/* Verification Form */}
      <div className="card">
        <form onSubmit={handleSubmit} className="space-y-4">
          {activeType === 'file' && (
            <div>
              <label htmlFor="sha256" className="block text-sm font-medium text-gray-700 mb-1">
                SHA256 Hash
              </label>
              <input
                id="sha256"
                name="sha256"
                type="text"
                required
                value={formData.sha256}
                onChange={handleInputChange}
                className="input"
                placeholder="Enter SHA256 hash (64 characters)"
                pattern="[a-fA-F0-9]{64}"
                maxLength={64}
              />
              <p className="text-xs text-gray-500 mt-1">
                64-character hexadecimal hash of the file you want to verify
              </p>
            </div>
          )}

          {activeType === 'email' && (
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                Email Address
              </label>
              <input
                id="email"
                name="email"
                type="email"
                required
                value={formData.email}
                onChange={handleInputChange}
                className="input"
                placeholder="Enter email address to verify"
              />
              <p className="text-xs text-gray-500 mt-1">
                Check email format, domain reputation, and detect disposable addresses
              </p>
            </div>
          )}

          {activeType === 'transaction' && (
            <div className="space-y-4">
              <div>
                <label htmlFor="network" className="block text-sm font-medium text-gray-700 mb-1">
                  Network
                </label>
                <select
                  id="network"
                  name="network"
                  value={formData.network}
                  onChange={handleInputChange}
                  className="input"
                >
                  <option value="bitcoin">Bitcoin</option>
                  <option value="ethereum">Ethereum</option>
                  <option value="litecoin">Litecoin</option>
                </select>
              </div>
              <div>
                <label htmlFor="tx" className="block text-sm font-medium text-gray-700 mb-1">
                  Transaction Hash
                </label>
                <input
                  id="tx"
                  name="tx"
                  type="text"
                  required
                  value={formData.tx}
                  onChange={handleInputChange}
                  className="input"
                  placeholder="Enter transaction hash"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Blockchain transaction hash to verify
                </p>
              </div>
            </div>
          )}

          {error && (
            <div className="p-3 bg-danger-50 border border-danger-200 rounded-md">
              <p className="text-danger-600 text-sm">{error}</p>
            </div>
          )}

          <button
            type="submit"
            disabled={loading}
            className="w-full btn-primary"
          >
            {loading ? 'Verifying...' : 'Verify'}
          </button>
        </form>
      </div>

      {/* Results */}
      {result && (
        <div className="card">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Verification Result</h3>

          <div className="space-y-4">
            {/* Score and Risk Level */}
            <div className="flex items-center justify-between p-4 rounded-lg border">
              <div>
                <div className="text-3xl font-bold text-gray-900">
                  {result.veribit_score}/100
                </div>
                <div className="text-sm text-gray-500">VeriBit Score</div>
              </div>
              <div className={`flex items-center px-3 py-1 rounded-full ${getRiskColor(result.risk_level)}`}>
                {getRiskIcon(result.risk_level)}
                <span className="ml-1 font-medium capitalize">{result.risk_level} Risk</span>
              </div>
            </div>

            {/* Confidence */}
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-gray-700">Confidence:</span>
              <span className={`px-2 py-1 rounded text-xs font-medium ${
                result.confidence === 'high' ? 'bg-success-100 text-success-800' :
                result.confidence === 'medium' ? 'bg-warning-100 text-warning-800' :
                'bg-gray-100 text-gray-800'
              }`}>
                {result.confidence}
              </span>
            </div>

            {/* Factors */}
            {result.factors && result.factors.length > 0 && (
              <div>
                <h4 className="text-sm font-medium text-gray-700 mb-2">Analysis Factors:</h4>
                <ul className="space-y-1">
                  {result.factors.map((factor, index) => (
                    <li key={index} className="text-sm text-gray-600 flex items-start">
                      <CheckCircle className="h-3 w-3 text-success-500 mt-0.5 mr-2 flex-shrink-0" />
                      {factor}
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {/* Threats */}
            {result.threats && result.threats.length > 0 && (
              <div>
                <h4 className="text-sm font-medium text-gray-700 mb-2">Detected Threats:</h4>
                <ul className="space-y-1">
                  {result.threats.map((threat, index) => (
                    <li key={index} className="text-sm text-danger-600 flex items-start">
                      <AlertTriangle className="h-3 w-3 text-danger-500 mt-0.5 mr-2 flex-shrink-0" />
                      {threat}
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {/* Badge URL */}
            {result.badge_url && (
              <div>
                <h4 className="text-sm font-medium text-gray-700 mb-2">Trust Badge:</h4>
                <div className="flex items-center space-x-2">
                  <input
                    type="text"
                    readOnly
                    value={`${apiBaseUrl}${result.badge_url}`}
                    className="input text-xs flex-1"
                  />
                  <button
                    onClick={() => navigator.clipboard.writeText(`${apiBaseUrl}${result.badge_url}`)}
                    className="btn-secondary text-xs"
                  >
                    Copy
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}