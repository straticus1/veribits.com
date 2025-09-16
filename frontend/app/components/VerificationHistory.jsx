'use client'

import { useState, useEffect } from 'react'
import { useAuth } from './AuthProvider'
import { CheckCircle, AlertTriangle, XCircle, Clock } from 'lucide-react'

export default function VerificationHistory() {
  const { apiBaseUrl } = useAuth()
  const [history, setHistory] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Mock data for now since we don't have a history endpoint yet
  useEffect(() => {
    const mockHistory = [
      {
        id: '1',
        type: 'file',
        input: { sha256: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855' },
        veribit_score: 85,
        risk_level: 'low',
        verified_at: '2024-01-15T10:30:00Z'
      },
      {
        id: '2',
        type: 'email',
        input: { email: 'user@example.com' },
        veribit_score: 45,
        risk_level: 'medium',
        verified_at: '2024-01-15T09:15:00Z'
      },
      {
        id: '3',
        type: 'transaction',
        input: { tx: '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', network: 'bitcoin' },
        veribit_score: 15,
        risk_level: 'high',
        verified_at: '2024-01-14T16:45:00Z'
      }
    ]

    setTimeout(() => {
      setHistory(mockHistory)
      setLoading(false)
    }, 1000)
  }, [])

  const getRiskIcon = (riskLevel) => {
    switch (riskLevel) {
      case 'low': return <CheckCircle className="h-5 w-5 text-success-500" />
      case 'medium': return <AlertTriangle className="h-5 w-5 text-warning-500" />
      case 'high':
      case 'critical': return <XCircle className="h-5 w-5 text-danger-500" />
      default: return <Clock className="h-5 w-5 text-gray-500" />
    }
  }

  const getRiskBadgeClass = (riskLevel) => {
    switch (riskLevel) {
      case 'low': return 'badge-success'
      case 'medium': return 'badge-warning'
      case 'high':
      case 'critical': return 'badge-danger'
      default: return 'badge-gray'
    }
  }

  const getDisplayValue = (item) => {
    switch (item.type) {
      case 'file':
        return `${item.input.sha256.substring(0, 16)}...`
      case 'email':
        return item.input.email
      case 'transaction':
        return `${item.input.tx.substring(0, 16)}... (${item.input.network})`
      default:
        return 'Unknown'
    }
  }

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  if (loading) {
    return (
      <div className="card">
        <div className="animate-pulse space-y-4">
          {[1, 2, 3].map((i) => (
            <div key={i} className="flex items-center space-x-4">
              <div className="w-8 h-8 bg-gray-200 rounded-full"></div>
              <div className="flex-1">
                <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                <div className="h-3 bg-gray-200 rounded w-1/2"></div>
              </div>
              <div className="w-16 h-6 bg-gray-200 rounded"></div>
            </div>
          ))}
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="card">
        <div className="text-center py-8">
          <XCircle className="h-12 w-12 text-danger-500 mx-auto mb-4" />
          <p className="text-danger-600">{error}</p>
        </div>
      </div>
    )
  }

  if (history.length === 0) {
    return (
      <div className="card">
        <div className="text-center py-8">
          <Clock className="h-12 w-12 text-gray-400 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-gray-900 mb-2">No verifications yet</h3>
          <p className="text-gray-500">Start verifying files, emails, or transactions to see your history here.</p>
        </div>
      </div>
    )
  }

  return (
    <div className="card">
      <div className="space-y-4">
        {history.map((item) => (
          <div key={item.id} className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors">
            <div className="flex items-center space-x-4">
              {getRiskIcon(item.risk_level)}
              <div>
                <div className="font-medium text-gray-900">
                  {item.type.charAt(0).toUpperCase() + item.type.slice(1)} Verification
                </div>
                <div className="text-sm text-gray-500">
                  {getDisplayValue(item)}
                </div>
                <div className="text-xs text-gray-400">
                  {formatDate(item.verified_at)}
                </div>
              </div>
            </div>

            <div className="flex items-center space-x-3">
              <div className="text-right">
                <div className="text-lg font-semibold text-gray-900">
                  {item.veribit_score}/100
                </div>
                <div className={`${getRiskBadgeClass(item.risk_level)} text-xs`}>
                  {item.risk_level}
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Pagination would go here in a real implementation */}
      <div className="mt-6 text-center">
        <button className="btn-secondary">
          Load More
        </button>
      </div>
    </div>
  )
}