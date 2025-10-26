'use client'

import { useState } from 'react'
import {
  Mail, Shield, CheckCircle, AlertTriangle, XCircle, Info,
  Server, FileText, Activity, TrendingUp, Zap, Lock, Globe
} from 'lucide-react'

export default function EmailVerificationPage() {
  const [activeTab, setActiveTab] = useState('dea')
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState(null)

  const tabs = [
    { id: 'dea', name: 'Disposable Email', icon: Mail },
    { id: 'spf', name: 'SPF Analyzer', icon: Shield },
    { id: 'dkim', name: 'DKIM Analyzer', icon: Lock },
    { id: 'dmarc', name: 'DMARC Analyzer', icon: CheckCircle },
    { id: 'mx', name: 'MX Records', icon: Server },
    { id: 'headers', name: 'Header Analyzer', icon: FileText },
    { id: 'blacklist', name: 'Blacklist Check', icon: AlertTriangle },
    { id: 'score', name: 'Deliverability Score', icon: TrendingUp },
    { id: 'spf-wizard', name: 'SPF Wizard', icon: Zap },
    { id: 'dmarc-wizard', name: 'DMARC Wizard', icon: Globe }
  ]

  const handleSubmit = async (endpoint, data) => {
    setLoading(true)
    setResult(null)

    try {
      const response = await fetch(`/api/v1/email/${endpoint}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })

      const json = await response.json()

      if (json.success) {
        setResult(json.data)
      } else {
        setResult({ error: json.error || 'Analysis failed' })
      }
    } catch (error) {
      setResult({ error: 'Network error occurred' })
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-gray-900 mb-2">
            Email Verification Suite
          </h1>
          <p className="text-lg text-gray-600">
            Comprehensive email authentication, deliverability, and security analysis tools
          </p>
        </div>

        {/* Tabs */}
        <div className="mb-6 border-b border-gray-200 overflow-x-auto">
          <nav className="flex space-x-4 min-w-max">
            {tabs.map((tab) => {
              const Icon = tab.icon
              return (
                <button
                  key={tab.id}
                  onClick={() => {
                    setActiveTab(tab.id)
                    setResult(null)
                  }}
                  className={`flex items-center px-4 py-3 border-b-2 font-medium text-sm whitespace-nowrap transition-colors ${
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
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          {activeTab === 'dea' && <DEAChecker onSubmit={handleSubmit} loading={loading} result={result} />}
          {activeTab === 'spf' && <SPFAnalyzer onSubmit={handleSubmit} loading={loading} result={result} />}
          {activeTab === 'dkim' && <DKIMAnalyzer onSubmit={handleSubmit} loading={loading} result={result} />}
          {activeTab === 'dmarc' && <DMARCAnalyzer onSubmit={handleSubmit} loading={loading} result={result} />}
          {activeTab === 'mx' && <MXAnalyzer onSubmit={handleSubmit} loading={loading} result={result} />}
          {activeTab === 'headers' && <HeaderAnalyzer onSubmit={handleSubmit} loading={loading} result={result} />}
          {activeTab === 'blacklist' && <BlacklistChecker onSubmit={handleSubmit} loading={loading} result={result} />}
          {activeTab === 'score' && <DeliverabilityScore onSubmit={handleSubmit} loading={loading} result={result} />}
          {activeTab === 'spf-wizard' && <SPFWizard />}
          {activeTab === 'dmarc-wizard' && <DMARCWizard />}
        </div>
      </div>
    </div>
  )
}

// Disposable Email Address Checker
function DEAChecker({ onSubmit, loading, result }) {
  const [input, setInput] = useState('')

  const handleCheck = (e) => {
    e.preventDefault()
    onSubmit('check-disposable', { email: input })
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">Disposable Email Address Detector</h2>
      <p className="text-gray-600 mb-6">Check if an email address or domain is disposable/temporary</p>

      <form onSubmit={handleCheck} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Email Address or Domain
          </label>
          <input
            type="text"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder="user@example.com or example.com"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            required
          />
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full sm:w-auto px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50"
        >
          {loading ? 'Checking...' : 'Check Email'}
        </button>
      </form>

      {result && !result.error && (
        <div className={`mt-6 p-4 rounded-lg ${result.is_disposable ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'}`}>
          <div className="flex items-start">
            {result.is_disposable ? (
              <XCircle className="h-6 w-6 text-red-600 mr-3 mt-0.5" />
            ) : (
              <CheckCircle className="h-6 w-6 text-green-600 mr-3 mt-0.5" />
            )}
            <div className="flex-1">
              <h3 className={`font-semibold mb-2 ${result.is_disposable ? 'text-red-900' : 'text-green-900'}`}>
                {result.is_disposable ? 'Disposable Email Detected' : 'Legitimate Email'}
              </h3>
              <div className="space-y-2 text-sm">
                <div><span className="font-medium">Domain:</span> {result.domain}</div>
                <div><span className="font-medium">Risk Level:</span> <span className="capitalize">{result.risk_level}</span></div>
                <div><span className="font-medium">Confidence:</span> <span className="capitalize">{result.confidence}</span></div>
                <div><span className="font-medium">Source:</span> {result.source.replace('_', ' ')}</div>
                <div className="mt-3 pt-3 border-t border-gray-200">
                  <span className="font-medium">Recommendation:</span> {result.recommendation}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {result?.error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <p className="text-red-800">{result.error}</p>
        </div>
      )}
    </div>
  )
}

// SPF Analyzer
function SPFAnalyzer({ onSubmit, loading, result }) {
  const [domain, setDomain] = useState('')

  const handleAnalyze = (e) => {
    e.preventDefault()
    onSubmit('analyze-spf', { domain })
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">SPF Record Analyzer</h2>
      <p className="text-gray-600 mb-6">Analyze Sender Policy Framework (SPF) records to identify authorized mail servers</p>

      <form onSubmit={handleAnalyze} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Domain Name
          </label>
          <input
            type="text"
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            placeholder="example.com"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            required
          />
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full sm:w-auto px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
        >
          {loading ? 'Analyzing...' : 'Analyze SPF'}
        </button>
      </form>

      {result && !result.error && result.has_spf && (
        <div className="mt-6 space-y-4">
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
            <h3 className="font-semibold text-green-900 mb-2 flex items-center">
              <CheckCircle className="h-5 w-5 mr-2" />
              SPF Record Found
            </h3>
            <div className="bg-gray-900 text-green-400 p-3 rounded font-mono text-sm overflow-x-auto">
              {result.raw_record}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <div className="text-sm text-blue-600 font-medium">Policy Strength</div>
              <div className="text-2xl font-bold text-blue-900 capitalize">{result.policy_strength}</div>
            </div>
            <div className="p-4 bg-purple-50 border border-purple-200 rounded-lg">
              <div className="text-sm text-purple-600 font-medium">DNS Lookups</div>
              <div className="text-2xl font-bold text-purple-900">{result.dns_lookups}/10</div>
            </div>
            <div className="p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
              <div className="text-sm text-indigo-600 font-medium">All Mechanism</div>
              <div className="text-2xl font-bold text-indigo-900">{result.all_mechanism || 'None'}</div>
            </div>
          </div>

          {result.mechanisms && result.mechanisms.length > 0 && (
            <div className="p-4 border border-gray-200 rounded-lg">
              <h4 className="font-semibold text-gray-900 mb-3">SPF Mechanisms</h4>
              <div className="space-y-2">
                {result.mechanisms.map((mech, idx) => (
                  <div key={idx} className="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <span className="font-mono text-sm">{mech.type}: {mech.value}</span>
                    <span className={`px-2 py-1 rounded text-xs font-medium ${
                      mech.qualifier === '+' ? 'bg-green-100 text-green-800' :
                      mech.qualifier === '-' ? 'bg-red-100 text-red-800' :
                      mech.qualifier === '~' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {mech.qualifier === '+' ? 'Pass' : mech.qualifier === '-' ? 'Fail' : mech.qualifier === '~' ? 'SoftFail' : 'Neutral'}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {result.warnings && result.warnings.length > 0 && (
            <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
              <h4 className="font-semibold text-yellow-900 mb-2 flex items-center">
                <AlertTriangle className="h-5 w-5 mr-2" />
                Warnings
              </h4>
              <ul className="list-disc list-inside space-y-1 text-yellow-800">
                {result.warnings.map((warning, idx) => <li key={idx}>{warning}</li>)}
              </ul>
            </div>
          )}
        </div>
      )}

      {result && !result.error && !result.has_spf && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <h3 className="font-semibold text-red-900 mb-2 flex items-center">
            <XCircle className="h-5 w-5 mr-2" />
            No SPF Record Found
          </h3>
          <p className="text-red-800">{result.recommendation}</p>
        </div>
      )}

      {result?.error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <p className="text-red-800">{result.error}</p>
        </div>
      )}
    </div>
  )
}

// DKIM Analyzer
function DKIMAnalyzer({ onSubmit, loading, result }) {
  const [domain, setDomain] = useState('')
  const [selector, setSelector] = useState('')

  const handleAnalyze = (e) => {
    e.preventDefault()
    onSubmit('analyze-dkim', { domain, selector: selector || 'default' })
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">DKIM Record Analyzer</h2>
      <p className="text-gray-600 mb-6">Find and analyze DomainKeys Identified Mail (DKIM) records</p>

      <form onSubmit={handleAnalyze} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Domain Name</label>
          <input
            type="text"
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            placeholder="example.com"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Selector (optional)
          </label>
          <input
            type="text"
            value={selector}
            onChange={(e) => setSelector(e.target.value)}
            placeholder="Leave empty to auto-detect"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
          />
          <p className="mt-1 text-sm text-gray-500">Common selectors: default, google, k1, s1, selector1</p>
        </div>

        <button type="submit" disabled={loading} className="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
          {loading ? 'Analyzing...' : 'Analyze DKIM'}
        </button>
      </form>

      {result && !result.error && (
        <div className="mt-6 space-y-4">
          {result.has_dkim ? (
            <>
              <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
                <h3 className="font-semibold text-green-900 mb-2 flex items-center">
                  <CheckCircle className="h-5 w-5 mr-2" />
                  Found {result.records_found} DKIM Record{result.records_found > 1 ? 's' : ''}
                </h3>
              </div>

              {result.records.map((record, idx) => (
                <div key={idx} className="p-4 border border-gray-200 rounded-lg">
                  <div className="mb-3">
                    <span className="font-semibold">Selector:</span>
                    <code className="ml-2 px-2 py-1 bg-gray-100 rounded">{record.selector}._domainkey.{domain}</code>
                  </div>
                  <div className="bg-gray-900 text-green-400 p-3 rounded font-mono text-xs overflow-x-auto mb-3">
                    {record.record}
                  </div>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div><span className="font-medium">Version:</span> {record.parsed.version || 'DKIM1'}</div>
                    <div><span className="font-medium">Key Type:</span> {record.parsed.key_type}</div>
                    <div><span className="font-medium">Hash Algorithms:</span> {record.parsed.hash_algorithms}</div>
                    <div><span className="font-medium">Service Type:</span> {record.parsed.service_type}</div>
                  </div>
                  {record.parsed.public_key && (
                    <div className="mt-3 pt-3 border-t border-gray-200">
                      <div className="font-medium mb-1">Public Key:</div>
                      <code className="block bg-gray-100 p-2 rounded text-xs break-all">{record.parsed.public_key.substring(0, 100)}...</code>
                    </div>
                  )}
                </div>
              ))}
            </>
          ) : (
            <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
              <h3 className="font-semibold text-yellow-900 mb-2">No DKIM Records Found</h3>
              <p className="text-yellow-800">{result.recommendation}</p>
              <p className="text-sm text-yellow-700 mt-2">Checked selectors: {result.selectors_checked.join(', ')}</p>
            </div>
          )}
        </div>
      )}

      {result?.error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <p className="text-red-800">{result.error}</p>
        </div>
      )}
    </div>
  )
}

// DMARC Analyzer
function DMARCAnalyzer({ onSubmit, loading, result }) {
  const [domain, setDomain] = useState('')

  const handleAnalyze = (e) => {
    e.preventDefault()
    onSubmit('analyze-dmarc', { domain })
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">DMARC Policy Analyzer</h2>
      <p className="text-gray-600 mb-6">Analyze Domain-based Message Authentication, Reporting, and Conformance policies</p>

      <form onSubmit={handleAnalyze} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Domain Name</label>
          <input
            type="text"
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            placeholder="example.com"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            required
          />
        </div>

        <button type="submit" disabled={loading} className="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
          {loading ? 'Analyzing...' : 'Analyze DMARC'}
        </button>
      </form>

      {result && !result.error && result.has_dmarc && (
        <div className="mt-6 space-y-4">
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
            <h3 className="font-semibold text-green-900 mb-2 flex items-center">
              <CheckCircle className="h-5 w-5 mr-2" />
              DMARC Record Found
            </h3>
            <div className="bg-gray-900 text-green-400 p-3 rounded font-mono text-sm overflow-x-auto">
              {result.raw_record}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className={`p-4 border rounded-lg ${
              result.policy === 'reject' ? 'bg-green-50 border-green-200' :
              result.policy === 'quarantine' ? 'bg-yellow-50 border-yellow-200' :
              'bg-red-50 border-red-200'
            }`}>
              <div className="text-sm font-medium mb-1">Policy</div>
              <div className="text-2xl font-bold capitalize">{result.policy}</div>
            </div>
            <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <div className="text-sm font-medium mb-1">Policy Strength</div>
              <div className="text-2xl font-bold capitalize">{result.policy_strength}</div>
            </div>
            <div className="p-4 bg-purple-50 border border-purple-200 rounded-lg">
              <div className="text-sm font-medium mb-1">Percentage</div>
              <div className="text-2xl font-bold">{result.percentage}%</div>
            </div>
          </div>

          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-semibold text-gray-900 mb-3">Alignment Settings</h4>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="font-medium">DKIM Alignment:</span> {result.alignment.dkim === 'r' ? 'Relaxed' : 'Strict'}</div>
              <div><span className="font-medium">SPF Alignment:</span> {result.alignment.spf === 'r' ? 'Relaxed' : 'Strict'}</div>
            </div>
          </div>

          {(result.reporting.aggregate.length > 0 || result.reporting.forensic.length > 0) && (
            <div className="p-4 border border-gray-200 rounded-lg">
              <h4 className="font-semibold text-gray-900 mb-3">Reporting Addresses</h4>
              {result.reporting.aggregate.length > 0 && (
                <div className="mb-2">
                  <div className="font-medium text-sm">Aggregate Reports (RUA):</div>
                  <div className="text-sm text-gray-600">{result.reporting.aggregate.join(', ')}</div>
                </div>
              )}
              {result.reporting.forensic.length > 0 && (
                <div>
                  <div className="font-medium text-sm">Forensic Reports (RUF):</div>
                  <div className="text-sm text-gray-600">{result.reporting.forensic.join(', ')}</div>
                </div>
              )}
            </div>
          )}

          {result.recommendations && result.recommendations.length > 0 && (
            <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <h4 className="font-semibold text-blue-900 mb-2 flex items-center">
                <Info className="h-5 w-5 mr-2" />
                Recommendations
              </h4>
              <ul className="list-disc list-inside space-y-1 text-blue-800">
                {result.recommendations.map((rec, idx) => <li key={idx}>{rec}</li>)}
              </ul>
            </div>
          )}
        </div>
      )}

      {result && !result.error && !result.has_dmarc && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <h3 className="font-semibold text-red-900 mb-2 flex items-center">
            <XCircle className="h-5 w-5 mr-2" />
            No DMARC Record Found
          </h3>
          <p className="text-red-800">{result.recommendation}</p>
        </div>
      )}

      {result?.error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <p className="text-red-800">{result.error}</p>
        </div>
      )}
    </div>
  )
}

// MX Record Analyzer
function MXAnalyzer({ onSubmit, loading, result }) {
  const [domain, setDomain] = useState('')

  const handleAnalyze = (e) => {
    e.preventDefault()
    onSubmit('analyze-mx', { domain })
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">MX Record Analyzer</h2>
      <p className="text-gray-600 mb-6">Analyze mail server configuration and deliverability</p>

      <form onSubmit={handleAnalyze} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Domain Name</label>
          <input
            type="text"
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            placeholder="example.com"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            required
          />
        </div>

        <button type="submit" disabled={loading} className="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
          {loading ? 'Analyzing...' : 'Analyze MX Records'}
        </button>
      </form>

      {result && !result.error && result.has_mx && (
        <div className="mt-6 space-y-4">
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
            <h3 className="font-semibold text-green-900 mb-2 flex items-center">
              <CheckCircle className="h-5 w-5 mr-2" />
              Found {result.mx_count} MX Record{result.mx_count > 1 ? 's' : ''}
            </h3>
            <div className="text-sm text-green-800">
              {result.redundancy ? '✓ Mail server redundancy configured' : '⚠ Single mail server (no redundancy)'}
            </div>
          </div>

          {result.records.map((mx, idx) => (
            <div key={idx} className="p-4 border border-gray-200 rounded-lg">
              <div className="flex items-center justify-between mb-3">
                <h4 className="font-semibold text-gray-900">{mx.hostname}</h4>
                <span className="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                  Priority: {mx.priority}
                </span>
              </div>

              {mx.ip_addresses && mx.ip_addresses.length > 0 && (
                <div className="mb-2">
                  <div className="text-sm font-medium text-gray-700">IP Addresses:</div>
                  <div className="flex flex-wrap gap-2 mt-1">
                    {mx.ip_addresses.map((ip, i) => (
                      <code key={i} className="px-2 py-1 bg-gray-100 rounded text-xs">{ip}</code>
                    ))}
                  </div>
                </div>
              )}

              {mx.reverse_dns && Object.keys(mx.reverse_dns).length > 0 && (
                <div className="mb-2">
                  <div className="text-sm font-medium text-gray-700">Reverse DNS:</div>
                  {Object.entries(mx.reverse_dns).map(([ip, ptr]) => (
                    <div key={ip} className="text-xs text-gray-600 mt-1">
                      {ip} → {ptr}
                    </div>
                  ))}
                </div>
              )}

              <div className="mt-3 pt-3 border-t border-gray-200">
                <div className="flex items-center text-sm">
                  {mx.supports_tls ? (
                    <>
                      <CheckCircle className="h-4 w-4 text-green-600 mr-2" />
                      <span className="text-green-700">TLS/STARTTLS Supported</span>
                    </>
                  ) : (
                    <>
                      <XCircle className="h-4 w-4 text-red-600 mr-2" />
                      <span className="text-red-700">TLS/STARTTLS Not Detected</span>
                    </>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {result && !result.error && !result.has_mx && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <h3 className="font-semibold text-red-900 mb-2 flex items-center">
            <XCircle className="h-5 w-5 mr-2" />
            No MX Records Found
          </h3>
          <p className="text-red-800">This domain cannot receive email</p>
        </div>
      )}

      {result?.error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <p className="text-red-800">{result.error}</p>
        </div>
      )}
    </div>
  )
}

// Email Header Analyzer
function HeaderAnalyzer({ onSubmit, loading, result }) {
  const [headers, setHeaders] = useState('')

  const handleAnalyze = (e) => {
    e.preventDefault()
    onSubmit('analyze-headers', { headers })
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">Email Header Analyzer</h2>
      <p className="text-gray-600 mb-6">Paste full email headers to analyze authentication and routing</p>

      <form onSubmit={handleAnalyze} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Email Headers</label>
          <textarea
            value={headers}
            onChange={(e) => setHeaders(e.target.value)}
            placeholder="Paste email headers here..."
            rows={12}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 font-mono text-sm"
            required
          />
        </div>

        <button type="submit" disabled={loading} className="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
          {loading ? 'Analyzing...' : 'Analyze Headers'}
        </button>
      </form>

      {result && !result.error && (
        <div className="mt-6 space-y-4">
          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-semibold text-gray-900 mb-3">Authentication Results</h4>
            <div className="grid grid-cols-3 gap-4">
              <div className={`p-3 rounded-lg ${
                result.authentication.spf === 'pass' ? 'bg-green-50 border border-green-200' :
                result.authentication.spf === 'fail' ? 'bg-red-50 border border-red-200' :
                'bg-gray-50 border border-gray-200'
              }`}>
                <div className="text-sm font-medium">SPF</div>
                <div className="text-lg font-bold capitalize">{result.authentication.spf || 'Not checked'}</div>
              </div>
              <div className={`p-3 rounded-lg ${
                result.authentication.dkim === 'pass' ? 'bg-green-50 border border-green-200' :
                result.authentication.dkim === 'fail' ? 'bg-red-50 border border-red-200' :
                'bg-gray-50 border border-gray-200'
              }`}>
                <div className="text-sm font-medium">DKIM</div>
                <div className="text-lg font-bold capitalize">{result.authentication.dkim || 'Not checked'}</div>
              </div>
              <div className={`p-3 rounded-lg ${
                result.authentication.dmarc === 'pass' ? 'bg-green-50 border border-green-200' :
                result.authentication.dmarc === 'fail' ? 'bg-red-50 border border-red-200' :
                'bg-gray-50 border border-gray-200'
              }`}>
                <div className="text-sm font-medium">DMARC</div>
                <div className="text-lg font-bold capitalize">{result.authentication.dmarc || 'Not checked'}</div>
              </div>
            </div>
          </div>

          {result.metadata && (
            <div className="p-4 border border-gray-200 rounded-lg">
              <h4 className="font-semibold text-gray-900 mb-3">Message Metadata</h4>
              <div className="space-y-2 text-sm">
                <div><span className="font-medium">From:</span> {result.metadata.from || 'N/A'}</div>
                <div><span className="font-medium">To:</span> {result.metadata.to || 'N/A'}</div>
                <div><span className="font-medium">Subject:</span> {result.metadata.subject || 'N/A'}</div>
                <div><span className="font-medium">Date:</span> {result.metadata.date || 'N/A'}</div>
                <div><span className="font-medium">Message-ID:</span> {result.metadata.message_id || 'N/A'}</div>
              </div>
            </div>
          )}

          {result.routing && result.routing.length > 0 && (
            <div className="p-4 border border-gray-200 rounded-lg">
              <h4 className="font-semibold text-gray-900 mb-3">Email Route ({result.hops} hops)</h4>
              <div className="space-y-2">
                {result.routing.map((hop, idx) => (
                  <div key={idx} className="p-2 bg-gray-50 rounded text-xs font-mono">
                    {hop.substring(0, 200)}{hop.length > 200 ? '...' : ''}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {result?.error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <p className="text-red-800">{result.error}</p>
        </div>
      )}
    </div>
  )
}

// Blacklist Checker
function BlacklistChecker({ onSubmit, loading, result }) {
  const [query, setQuery] = useState('')

  const handleCheck = (e) => {
    e.preventDefault()
    onSubmit('check-blacklists', { query })
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">Email Blacklist Checker</h2>
      <p className="text-gray-600 mb-6">Check if domain or IP address is listed on major email blacklists (RBLs)</p>

      <form onSubmit={handleCheck} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Domain or IP Address</label>
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="example.com or 192.168.1.1"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            required
          />
        </div>

        <button type="submit" disabled={loading} className="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
          {loading ? 'Checking...' : 'Check Blacklists'}
        </button>
      </form>

      {result && !result.error && (
        <div className="mt-6 space-y-4">
          <div className={`p-4 rounded-lg ${
            result.is_blacklisted ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'
          }`}>
            <h3 className={`font-semibold mb-2 flex items-center ${
              result.is_blacklisted ? 'text-red-900' : 'text-green-900'
            }`}>
              {result.is_blacklisted ? (
                <><AlertTriangle className="h-5 w-5 mr-2" />Listed on {result.blacklists_listed} Blacklist(s)</>
              ) : (
                <><CheckCircle className="h-5 w-5 mr-2" />Not Blacklisted</>
              )}
            </h3>
            <div className="space-y-1 text-sm">
              <div><span className="font-medium">Query:</span> {result.query} ({result.type})</div>
              <div><span className="font-medium">Blacklists Checked:</span> {result.blacklists_checked}</div>
              {result.is_blacklisted && (
                <div><span className="font-medium">Severity:</span> <span className="capitalize">{result.severity}</span></div>
              )}
            </div>
          </div>

          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-semibold text-gray-900 mb-3">Blacklist Results</h4>
            <div className="space-y-2">
              {result.results.map((rbl, idx) => (
                <div key={idx} className="flex items-center justify-between p-3 bg-gray-50 rounded">
                  <span className="text-sm font-medium">{rbl.rbl}</span>
                  <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                    rbl.listed ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
                  }`}>
                    {rbl.listed ? 'Listed' : 'Clean'}
                  </span>
                </div>
              ))}
            </div>
          </div>

          <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p className="text-blue-800">{result.recommendation}</p>
          </div>
        </div>
      )}

      {result?.error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <p className="text-red-800">{result.error}</p>
        </div>
      )}
    </div>
  )
}

// Deliverability Score
function DeliverabilityScore({ onSubmit, loading, result }) {
  const [domain, setDomain] = useState('')

  const handleAnalyze = (e) => {
    e.preventDefault()
    onSubmit('deliverability-score', { domain })
  }

  const getGradeColor = (grade) => {
    if (grade === 'A') return 'text-green-600'
    if (grade === 'B') return 'text-blue-600'
    if (grade === 'C') return 'text-yellow-600'
    if (grade === 'D') return 'text-orange-600'
    return 'text-red-600'
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">Email Deliverability Score</h2>
      <p className="text-gray-600 mb-6">Comprehensive analysis of email configuration and deliverability</p>

      <form onSubmit={handleAnalyze} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Domain Name</label>
          <input
            type="text"
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            placeholder="example.com"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            required
          />
        </div>

        <button type="submit" disabled={loading} className="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
          {loading ? 'Analyzing...' : 'Calculate Score'}
        </button>
      </form>

      {result && !result.error && (
        <div className="mt-6 space-y-4">
          <div className="p-6 bg-gradient-to-br from-primary-50 to-primary-100 border border-primary-200 rounded-lg text-center">
            <div className="text-sm font-medium text-primary-600 mb-2">Overall Score</div>
            <div className={`text-6xl font-bold mb-2 ${getGradeColor(result.grade)}`}>
              {result.grade}
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {result.score}/{result.max_score} points
            </div>
            <div className="text-lg text-gray-600">
              {result.percentage}%
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {result.factors.map((factor, idx) => (
              <div key={idx} className={`p-4 rounded-lg border ${
                factor.status === 'pass' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'
              }`}>
                <div className="flex items-center justify-between mb-2">
                  <span className="font-medium text-gray-900">{factor.name}</span>
                  {factor.status === 'pass' ? (
                    <CheckCircle className="h-5 w-5 text-green-600" />
                  ) : (
                    <XCircle className="h-5 w-5 text-red-600" />
                  )}
                </div>
                <div className="text-sm text-gray-700">
                  Score: {factor.score}/{factor.max} points
                </div>
              </div>
            ))}
          </div>

          <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <h4 className="font-semibold text-blue-900 mb-3 flex items-center">
              <Info className="h-5 w-5 mr-2" />
              Recommendations
            </h4>
            <ul className="list-disc list-inside space-y-1 text-blue-800">
              {result.summary.map((rec, idx) => <li key={idx}>{rec}</li>)}
            </ul>
          </div>
        </div>
      )}

      {result?.error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <p className="text-red-800">{result.error}</p>
        </div>
      )}
    </div>
  )
}

// SPF Wizard Component (continued in next message due to size)
function SPFWizard() {
  const [config, setConfig] = useState({
    mailServers: [],
    ipv4Addresses: [],
    ipv6Addresses: [],
    includes: [],
    allMechanism: '-all'
  })
  const [generatedSPF, setGeneratedSPF] = useState('')

  const generateSPF = () => {
    let spf = 'v=spf1'

    config.ipv4Addresses.forEach(ip => {
      spf += ` ip4:${ip}`
    })

    config.ipv6Addresses.forEach(ip => {
      spf += ` ip6:${ip}`
    })

    if (config.mailServers.includes('mx')) {
      spf += ' mx'
    }

    if (config.mailServers.includes('a')) {
      spf += ' a'
    }

    config.includes.forEach(include => {
      spf += ` include:${include}`
    })

    spf += ` ${config.allMechanism}`

    setGeneratedSPF(spf)
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">SPF Record Wizard</h2>
      <p className="text-gray-600 mb-6">Create a custom SPF record for your domain</p>

      <div className="space-y-6">
        <div className="p-4 border border-gray-200 rounded-lg">
          <h3 className="font-semibold text-gray-900 mb-3">Mail Servers</h3>
          <div className="space-y-2">
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={config.mailServers.includes('mx')}
                onChange={(e) => {
                  setConfig(prev => ({
                    ...prev,
                    mailServers: e.target.checked
                      ? [...prev.mailServers, 'mx']
                      : prev.mailServers.filter(s => s !== 'mx')
                  }))
                }}
                className="mr-2"
              />
              <span>Use domain's MX records</span>
            </label>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={config.mailServers.includes('a')}
                onChange={(e) => {
                  setConfig(prev => ({
                    ...prev,
                    mailServers: e.target.checked
                      ? [...prev.mailServers, 'a']
                      : prev.mailServers.filter(s => s !== 'a')
                  }))
                }}
                className="mr-2"
              />
              <span>Use domain's A record</span>
            </label>
          </div>
        </div>

        <div className="p-4 border border-gray-200 rounded-lg">
          <h3 className="font-semibold text-gray-900 mb-3">All Mechanism</h3>
          <select
            value={config.allMechanism}
            onChange={(e) => setConfig(prev => ({ ...prev, allMechanism: e.target.value }))}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg"
          >
            <option value="-all">-all (Fail - Recommended)</option>
            <option value="~all">~all (SoftFail)</option>
            <option value="?all">?all (Neutral)</option>
            <option value="+all">+all (Pass - Not Recommended)</option>
          </select>
        </div>

        <button
          onClick={generateSPF}
          className="w-full px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium"
        >
          Generate SPF Record
        </button>

        {generatedSPF && (
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
            <h3 className="font-semibold text-green-900 mb-2">Generated SPF Record</h3>
            <div className="bg-gray-900 text-green-400 p-3 rounded font-mono text-sm overflow-x-auto mb-3">
              {generatedSPF}
            </div>
            <div className="text-sm text-green-800">
              Add this as a TXT record to your domain's DNS configuration
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

// DMARC Wizard Component
function DMARCWizard() {
  const [config, setConfig] = useState({
    policy: 'none',
    subdomainPolicy: '',
    percentage: '100',
    dkimAlignment: 'r',
    spfAlignment: 'r',
    reportEmail: '',
    forensicEmail: ''
  })
  const [generatedDMARC, setGeneratedDMARC] = useState('')

  const generateDMARC = () => {
    let dmarc = 'v=DMARC1'
    dmarc += `; p=${config.policy}`

    if (config.subdomainPolicy) {
      dmarc += `; sp=${config.subdomainPolicy}`
    }

    if (config.percentage !== '100') {
      dmarc += `; pct=${config.percentage}`
    }

    dmarc += `; adkim=${config.dkimAlignment}`
    dmarc += `; aspf=${config.spfAlignment}`

    if (config.reportEmail) {
      dmarc += `; rua=mailto:${config.reportEmail}`
    }

    if (config.forensicEmail) {
      dmarc += `; ruf=mailto:${config.forensicEmail}`
    }

    setGeneratedDMARC(dmarc)
  }

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-4">DMARC Record Wizard</h2>
      <p className="text-gray-600 mb-6">Create a custom DMARC policy for your domain</p>

      <div className="space-y-6">
        <div className="p-4 border border-gray-200 rounded-lg">
          <label className="block text-sm font-medium text-gray-700 mb-2">Policy</label>
          <select
            value={config.policy}
            onChange={(e) => setConfig(prev => ({ ...prev, policy: e.target.value }))}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg"
          >
            <option value="none">none (Monitor only)</option>
            <option value="quarantine">quarantine (Mark as spam)</option>
            <option value="reject">reject (Reject email)</option>
          </select>
        </div>

        <div className="p-4 border border-gray-200 rounded-lg">
          <label className="block text-sm font-medium text-gray-700 mb-2">Percentage</label>
          <input
            type="number"
            min="0"
            max="100"
            value={config.percentage}
            onChange={(e) => setConfig(prev => ({ ...prev, percentage: e.target.value }))}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg"
          />
          <p className="mt-1 text-sm text-gray-500">Percentage of emails to apply policy to</p>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="p-4 border border-gray-200 rounded-lg">
            <label className="block text-sm font-medium text-gray-700 mb-2">DKIM Alignment</label>
            <select
              value={config.dkimAlignment}
              onChange={(e) => setConfig(prev => ({ ...prev, dkimAlignment: e.target.value }))}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg"
            >
              <option value="r">Relaxed</option>
              <option value="s">Strict</option>
            </select>
          </div>

          <div className="p-4 border border-gray-200 rounded-lg">
            <label className="block text-sm font-medium text-gray-700 mb-2">SPF Alignment</label>
            <select
              value={config.spfAlignment}
              onChange={(e) => setConfig(prev => ({ ...prev, spfAlignment: e.target.value }))}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg"
            >
              <option value="r">Relaxed</option>
              <option value="s">Strict</option>
            </select>
          </div>
        </div>

        <div className="p-4 border border-gray-200 rounded-lg">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Aggregate Report Email (RUA)
          </label>
          <input
            type="email"
            value={config.reportEmail}
            onChange={(e) => setConfig(prev => ({ ...prev, reportEmail: e.target.value }))}
            placeholder="dmarc-reports@example.com"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg"
          />
        </div>

        <button
          onClick={generateDMARC}
          className="w-full px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium"
        >
          Generate DMARC Record
        </button>

        {generatedDMARC && (
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
            <h3 className="font-semibold text-green-900 mb-2">Generated DMARC Record</h3>
            <div className="bg-gray-900 text-green-400 p-3 rounded font-mono text-sm overflow-x-auto mb-3">
              {generatedDMARC}
            </div>
            <div className="text-sm text-green-800">
              Add this as a TXT record at _dmarc.yourdomain.com
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
