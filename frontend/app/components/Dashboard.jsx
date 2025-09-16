'use client'

import { useState, useEffect } from 'react'
import { useAuth } from './AuthProvider'
import VerificationForm from './VerificationForm'
import VerificationHistory from './VerificationHistory'
import QuotaDisplay from './QuotaDisplay'
import WebhookManager from './WebhookManager'
import { BarChart3, Shield, Zap, AlertTriangle } from 'lucide-react'

export default function Dashboard() {
  const { user } = useAuth()
  const [activeTab, setActiveTab] = useState('verify')
  const [stats, setStats] = useState({
    totalVerifications: 0,
    monthlyQuota: { used: 0, allowance: 1000 },
    riskDistribution: { low: 0, medium: 0, high: 0, critical: 0 }
  })

  const tabs = [
    { id: 'verify', name: 'Verify', icon: Shield },
    { id: 'history', name: 'History', icon: BarChart3 },
    { id: 'webhooks', name: 'Webhooks', icon: Zap },
  ]

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Welcome Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">
          Welcome back, {user?.email}
        </h1>
        <p className="text-gray-600 mt-2">
          Verify files, emails, and transactions with enterprise-grade security.
        </p>
      </div>

      {/* Quick Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div className="card">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <Shield className="h-8 w-8 text-primary-600" />
            </div>
            <div className="ml-3">
              <p className="text-sm font-medium text-gray-500">Total Verifications</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.totalVerifications}</p>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <BarChart3 className="h-8 w-8 text-success-600" />
            </div>
            <div className="ml-3">
              <p className="text-sm font-medium text-gray-500">Monthly Usage</p>
              <p className="text-2xl font-semibold text-gray-900">
                {stats.monthlyQuota.used} / {stats.monthlyQuota.allowance}
              </p>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <Zap className="h-8 w-8 text-warning-600" />
            </div>
            <div className="ml-3">
              <p className="text-sm font-medium text-gray-500">High Risk Detected</p>
              <p className="text-2xl font-semibold text-gray-900">
                {stats.riskDistribution.high + stats.riskDistribution.critical}
              </p>
            </div>
          </div>
        </div>

        <div className="card">
          <QuotaDisplay quota={stats.monthlyQuota} />
        </div>
      </div>

      {/* Tab Navigation */}
      <div className="border-b border-gray-200 mb-8">
        <nav className="-mb-px flex space-x-8">
          {tabs.map((tab) => {
            const Icon = tab.icon
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`py-2 px-1 border-b-2 font-medium text-sm flex items-center ${
                  activeTab === tab.id
                    ? 'border-primary-500 text-primary-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                <Icon className="h-4 w-4 mr-2" />
                {tab.name}
              </button>
            )
          })}
        </nav>
      </div>

      {/* Tab Content */}
      <div className="tab-content">
        {activeTab === 'verify' && (
          <div>
            <h2 className="text-xl font-semibold text-gray-900 mb-6">Verification Tools</h2>
            <VerificationForm onVerificationComplete={(result) => {
              // Update stats when verification completes
              setStats(prev => ({
                ...prev,
                totalVerifications: prev.totalVerifications + 1,
                monthlyQuota: {
                  ...prev.monthlyQuota,
                  used: prev.monthlyQuota.used + 1
                }
              }))
            }} />
          </div>
        )}

        {activeTab === 'history' && (
          <div>
            <h2 className="text-xl font-semibold text-gray-900 mb-6">Verification History</h2>
            <VerificationHistory />
          </div>
        )}

        {activeTab === 'webhooks' && (
          <div>
            <h2 className="text-xl font-semibold text-gray-900 mb-6">Webhook Management</h2>
            <WebhookManager />
          </div>
        )}
      </div>
    </div>
  )
}