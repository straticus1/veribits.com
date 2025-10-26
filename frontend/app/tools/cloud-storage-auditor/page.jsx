'use client'

import { useState } from 'react'
import { useAuth } from '@/app/components/AuthProvider'
import {
  Cloud,
  Search,
  Shield,
  FileText,
  Database,
  Lock,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Loader,
  Download,
  Eye,
  Settings,
  ChevronDown,
  ChevronUp,
  Info
} from 'lucide-react'

const PROVIDERS = [
  { id: 'aws', name: 'AWS S3', icon: Cloud, color: 'text-orange-600' },
  { id: 'gcs', name: 'Google Cloud Storage', icon: Cloud, color: 'text-blue-600' },
  { id: 'azure', name: 'Azure Blob Storage', icon: Cloud, color: 'text-cyan-600' },
  { id: 'digitalocean', name: 'Digital Ocean Spaces', icon: Cloud, color: 'text-indigo-600' }
]

export default function CloudStorageAuditor() {
  const { user } = useAuth()
  const [activeTab, setActiveTab] = useState('search') // search, security, buckets
  const [selectedProviders, setSelectedProviders] = useState(['all'])
  const [searchType, setSearchType] = useState('filename')
  const [searchQuery, setSearchQuery] = useState('')
  const [credentials, setCredentials] = useState({})
  const [loading, setLoading] = useState(false)
  const [results, setResults] = useState(null)
  const [error, setError] = useState('')
  const [showCredentials, setShowCredentials] = useState({})

  const handleProviderToggle = (providerId) => {
    if (providerId === 'all') {
      setSelectedProviders(['all'])
    } else {
      const newSelection = selectedProviders.filter(p => p !== 'all')
      if (newSelection.includes(providerId)) {
        const updated = newSelection.filter(p => p !== providerId)
        setSelectedProviders(updated.length > 0 ? updated : ['all'])
      } else {
        setSelectedProviders([...newSelection, providerId])
      }
    }
  }

  const handleCredentialChange = (provider, field, value) => {
    setCredentials(prev => ({
      ...prev,
      [provider]: {
        ...(prev[provider] || {}),
        [field]: value
      }
    }))
  }

  const handleSearch = async () => {
    setError('')
    setResults(null)

    if (!searchQuery.trim()) {
      setError('Please enter a search query')
      return
    }

    const providersToSearch = selectedProviders.includes('all')
      ? PROVIDERS.map(p => p.id)
      : selectedProviders

    // Validate credentials
    const missingCreds = providersToSearch.filter(p => !credentials[p])
    if (missingCreds.length > 0) {
      setError(`Missing credentials for: ${missingCreds.join(', ')}`)
      return
    }

    setLoading(true)

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
      const token = localStorage.getItem('auth_token')

      const response = await fetch(`${apiUrl}/api/v1/tools/cloud-storage/search`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(token && { 'Authorization': `Bearer ${token}` })
        },
        body: JSON.stringify({
          providers: selectedProviders,
          search_type: searchType,
          query: searchQuery.trim(),
          credentials,
          max_results: 1000
        })
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error?.message || 'Search failed')
      }

      setResults(data.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleSecurityAnalysis = async () => {
    setError('')
    setResults(null)

    const provider = selectedProviders[0]
    if (selectedProviders.includes('all') || selectedProviders.length > 1) {
      setError('Please select a single provider for security analysis')
      return
    }

    if (!credentials[provider]) {
      setError(`Missing credentials for ${provider}`)
      return
    }

    setLoading(true)

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
      const token = localStorage.getItem('auth_token')

      // First, list buckets
      const listResponse = await fetch(`${apiUrl}/api/v1/tools/cloud-storage/list-buckets`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(token && { 'Authorization': `Bearer ${token}` })
        },
        body: JSON.stringify({
          provider,
          credentials: credentials[provider]
        })
      })

      const listData = await listResponse.json()
      if (!listResponse.ok) {
        throw new Error(listData.error?.message || 'Failed to list buckets')
      }

      const bucketNames = listData.data.buckets.map(b => b.name)

      // Then analyze security
      const analyzeResponse = await fetch(`${apiUrl}/api/v1/tools/cloud-storage/analyze-security`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          provider,
          credentials: credentials[provider],
          buckets: bucketNames
        })
      })

      const analyzeData = await analyzeResponse.json()
      if (!analyzeResponse.ok) {
        throw new Error(analyzeData.error?.message || 'Security analysis failed')
      }

      setResults(analyzeData.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 flex items-center">
          <Shield className="h-8 w-8 mr-3 text-primary-600" />
          Cloud Storage Security Auditor
        </h1>
        <p className="text-gray-600 mt-2">
          Enterprise multi-cloud blob storage security scanner. Search for files and sensitive data across AWS S3,
          Google Cloud Storage, Azure Blob Storage, and Digital Ocean Spaces. Analyze security posture and identify misconfigurations.
        </p>
      </div>

      {/* Tabs */}
      <div className="flex space-x-4 mb-6 border-b border-gray-200">
        <button
          onClick={() => setActiveTab('search')}
          className={`px-4 py-2 font-medium border-b-2 transition-colors ${
            activeTab === 'search'
              ? 'border-primary-600 text-primary-600'
              : 'border-transparent text-gray-600 hover:text-gray-900'
          }`}
        >
          <Search className="h-5 w-5 inline mr-2" />
          Search
        </button>
        <button
          onClick={() => setActiveTab('security')}
          className={`px-4 py-2 font-medium border-b-2 transition-colors ${
            activeTab === 'security'
              ? 'border-primary-600 text-primary-600'
              : 'border-transparent text-gray-600 hover:text-gray-900'
          }`}
        >
          <Shield className="h-5 w-5 inline mr-2" />
          Security Analysis
        </button>
      </div>

      {/* Provider Selection */}
      <div className="card mb-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Select Cloud Providers</h2>
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          <button
            onClick={() => handleProviderToggle('all')}
            className={`p-4 rounded-lg border-2 transition-all ${
              selectedProviders.includes('all')
                ? 'border-primary-600 bg-primary-50 text-primary-700'
                : 'border-gray-200 hover:border-gray-300 text-gray-700'
            }`}
          >
            <Cloud className="h-6 w-6 mx-auto mb-2" />
            <div className="text-sm font-medium">All Providers</div>
          </button>
          {PROVIDERS.map(provider => (
            <button
              key={provider.id}
              onClick={() => handleProviderToggle(provider.id)}
              className={`p-4 rounded-lg border-2 transition-all ${
                selectedProviders.includes(provider.id) || selectedProviders.includes('all')
                  ? 'border-primary-600 bg-primary-50 text-primary-700'
                  : 'border-gray-200 hover:border-gray-300 text-gray-700'
              }`}
            >
              <provider.icon className={`h-6 w-6 mx-auto mb-2 ${provider.color}`} />
              <div className="text-xs font-medium">{provider.name}</div>
            </button>
          ))}
        </div>
      </div>

      {/* Credentials */}
      <CredentialsSection
        providers={PROVIDERS}
        selectedProviders={selectedProviders}
        credentials={credentials}
        showCredentials={showCredentials}
        setShowCredentials={setShowCredentials}
        onCredentialChange={handleCredentialChange}
      />

      {/* Search Tab */}
      {activeTab === 'search' && (
        <div className="card mb-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Search Configuration</h2>

          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-2">Search Type</label>
            <div className="grid grid-cols-2 gap-3">
              <button
                onClick={() => setSearchType('filename')}
                className={`p-3 rounded-lg border-2 transition-all ${
                  searchType === 'filename'
                    ? 'border-primary-600 bg-primary-50 text-primary-700'
                    : 'border-gray-200 hover:border-gray-300 text-gray-700'
                }`}
              >
                <FileText className="h-5 w-5 mx-auto mb-1" />
                <div className="text-sm font-medium">Search by Filename</div>
              </button>
              <button
                onClick={() => setSearchType('content')}
                className={`p-3 rounded-lg border-2 transition-all ${
                  searchType === 'content'
                    ? 'border-primary-600 bg-primary-50 text-primary-700'
                    : 'border-gray-200 hover:border-gray-300 text-gray-700'
                }`}
              >
                <Search className="h-5 w-5 mx-auto mb-1" />
                <div className="text-sm font-medium">Search by Content</div>
              </button>
            </div>
          </div>

          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-2">Search Query</label>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
              placeholder={searchType === 'filename' ? 'e.g., config.json, *.env, credentials' : 'e.g., api_key, password, secret'}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            />
          </div>

          <button onClick={handleSearch} disabled={loading} className="btn-primary w-full">
            {loading ? (
              <>
                <Loader className="h-5 w-5 mr-2 animate-spin" />
                Searching...
              </>
            ) : (
              <>
                <Search className="h-5 w-5 mr-2" />
                Search Cloud Storage
              </>
            )}
          </button>
        </div>
      )}

      {/* Security Analysis Tab */}
      {activeTab === 'security' && (
        <div className="card mb-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Security Analysis</h2>
          <p className="text-sm text-gray-600 mb-4">
            Analyze bucket security configurations including public access, encryption, versioning, and more.
          </p>

          <button onClick={handleSecurityAnalysis} disabled={loading} className="btn-primary w-full">
            {loading ? (
              <>
                <Loader className="h-5 w-5 mr-2 animate-spin" />
                Analyzing...
              </>
            ) : (
              <>
                <Shield className="h-5 w-5 mr-2" />
                Run Security Analysis
              </>
            )}
          </button>
        </div>
      )}

      {/* Error Message */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
          <AlertTriangle className="h-5 w-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" />
          <div className="text-sm text-red-800">{error}</div>
        </div>
      )}

      {/* Results */}
      {results && activeTab === 'search' && (
        <SearchResults results={results} searchType={searchType} />
      )}

      {results && activeTab === 'security' && (
        <SecurityResults results={results} />
      )}

      {/* Info Section */}
      <div className="mt-8">
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 className="text-sm font-semibold text-blue-900 mb-2 flex items-center">
            <Info className="h-4 w-4 mr-2" />
            Enterprise Cloud Storage Security
          </h3>
          <p className="text-sm text-blue-800 mb-2">
            This tool helps enterprises audit and secure their cloud storage infrastructure. Features include:
          </p>
          <ul className="text-sm text-blue-800 space-y-1 ml-4">
            <li>• Multi-cloud support (AWS, GCP, Azure, Digital Ocean)</li>
            <li>• File name and content search across all buckets</li>
            <li>• Security posture analysis and misconfiguration detection</li>
            <li>• Results cached for 24 hours for faster subsequent searches</li>
            <li>• Enterprise-grade authentication and access control</li>
          </ul>
          <p className="text-sm text-blue-800 mt-2">
            <strong>Note:</strong> Your credentials are never stored and are only used for the duration of the search.
          </p>
        </div>
      </div>
    </div>
  )
}

function CredentialsSection({ providers, selectedProviders, credentials, showCredentials, setShowCredentials, onCredentialChange }) {
  const activeProviders = selectedProviders.includes('all')
    ? providers
    : providers.filter(p => selectedProviders.includes(p.id))

  return (
    <div className="card mb-6">
      <h2 className="text-lg font-semibold text-gray-900 mb-4">Cloud Credentials</h2>
      <p className="text-sm text-gray-600 mb-4">
        Enter your cloud provider credentials. Credentials are transmitted securely and never stored.
      </p>

      {activeProviders.map(provider => (
        <div key={provider.id} className="mb-6 last:mb-0">
          <div
            className="flex items-center justify-between cursor-pointer p-3 bg-gray-50 rounded-lg"
            onClick={() => setShowCredentials(prev => ({ ...prev, [provider.id]: !prev[provider.id] }))}
          >
            <div className="flex items-center">
              <provider.icon className={`h-5 w-5 mr-2 ${provider.color}`} />
              <span className="font-medium text-gray-900">{provider.name}</span>
            </div>
            {showCredentials[provider.id] ? (
              <ChevronUp className="h-5 w-5 text-gray-400" />
            ) : (
              <ChevronDown className="h-5 w-5 text-gray-400" />
            )}
          </div>

          {showCredentials[provider.id] && (
            <div className="mt-3 p-4 border border-gray-200 rounded-lg space-y-3">
              {provider.id === 'aws' && (
                <>
                  <input
                    type="text"
                    placeholder="AWS Access Key ID"
                    value={credentials[provider.id]?.access_key || ''}
                    onChange={(e) => onCredentialChange(provider.id, 'access_key', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                  />
                  <input
                    type="password"
                    placeholder="AWS Secret Access Key"
                    value={credentials[provider.id]?.secret_key || ''}
                    onChange={(e) => onCredentialChange(provider.id, 'secret_key', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                  />
                  <input
                    type="text"
                    placeholder="Region (e.g., us-east-1)"
                    value={credentials[provider.id]?.region || 'us-east-1'}
                    onChange={(e) => onCredentialChange(provider.id, 'region', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                  />
                </>
              )}

              {provider.id === 'gcs' && (
                <>
                  <input
                    type="text"
                    placeholder="GCP Project ID"
                    value={credentials[provider.id]?.project_id || ''}
                    onChange={(e) => onCredentialChange(provider.id, 'project_id', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                  />
                  <textarea
                    placeholder="Service Account JSON (paste entire JSON)"
                    value={credentials[provider.id]?.service_account_json || ''}
                    onChange={(e) => onCredentialChange(provider.id, 'service_account_json', e.target.value)}
                    rows={4}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm font-mono"
                  />
                </>
              )}

              {provider.id === 'azure' && (
                <>
                  <input
                    type="text"
                    placeholder="Storage Account Name"
                    value={credentials[provider.id]?.account_name || ''}
                    onChange={(e) => onCredentialChange(provider.id, 'account_name', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                  />
                  <input
                    type="password"
                    placeholder="Account Key"
                    value={credentials[provider.id]?.account_key || ''}
                    onChange={(e) => onCredentialChange(provider.id, 'account_key', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                  />
                </>
              )}

              {provider.id === 'digitalocean' && (
                <>
                  <input
                    type="text"
                    placeholder="Access Key"
                    value={credentials[provider.id]?.access_key || ''}
                    onChange={(e) => onCredentialChange(provider.id, 'access_key', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                  />
                  <input
                    type="password"
                    placeholder="Secret Key"
                    value={credentials[provider.id]?.secret_key || ''}
                    onChange={(e) => onCredentialChange(provider.id, 'secret_key', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                  />
                  <input
                    type="text"
                    placeholder="Region (e.g., nyc3)"
                    value={credentials[provider.id]?.region || 'nyc3'}
                    onChange={(e) => onCredentialChange(provider.id, 'region', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                  />
                </>
              )}
            </div>
          )}
        </div>
      ))}
    </div>
  )
}

function SearchResults({ results, searchType }) {
  const [expandedProvider, setExpandedProvider] = useState(null)

  return (
    <div className="space-y-6">
      {/* Summary */}
      <div className="card">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">Search Results</h2>
        <div className="grid grid-cols-3 gap-4">
          <div className="text-center p-4 bg-blue-50 rounded-lg">
            <div className="text-2xl font-bold text-blue-600">{results.summary?.total_providers_searched || 0}</div>
            <div className="text-sm text-gray-600">Providers Searched</div>
          </div>
          <div className="text-center p-4 bg-green-50 rounded-lg">
            <div className="text-2xl font-bold text-green-600">{results.summary?.total_buckets_searched || 0}</div>
            <div className="text-sm text-gray-600">Buckets Scanned</div>
          </div>
          <div className="text-center p-4 bg-purple-50 rounded-lg">
            <div className="text-2xl font-bold text-purple-600">{results.summary?.total_matches || 0}</div>
            <div className="text-sm text-gray-600">Matches Found</div>
          </div>
        </div>
        {results.cached && (
          <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
            <Info className="h-4 w-4 inline mr-2" />
            Results retrieved from cache (24hr TTL)
          </div>
        )}
      </div>

      {/* Provider Results */}
      {Object.entries(results.results || {}).map(([provider, data]) => (
        <div key={provider} className="card">
          <div
            className="flex items-center justify-between cursor-pointer"
            onClick={() => setExpandedProvider(expandedProvider === provider ? null : provider)}
          >
            <div className="flex items-center">
              <Cloud className="h-6 w-6 mr-3 text-primary-600" />
              <div>
                <h3 className="text-lg font-semibold text-gray-900 capitalize">{provider}</h3>
                <p className="text-sm text-gray-600">
                  {data.total_matches} matches in {data.buckets_searched} buckets
                </p>
              </div>
            </div>
            {expandedProvider === provider ? (
              <ChevronUp className="h-5 w-5 text-gray-400" />
            ) : (
              <ChevronDown className="h-5 w-5 text-gray-400" />
            )}
          </div>

          {expandedProvider === provider && data.results && data.results.length > 0 && (
            <div className="mt-4 space-y-3">
              {data.results.map((bucket, idx) => (
                <div key={idx} className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center justify-between mb-3">
                    <span className="font-semibold text-gray-900">{bucket.bucket || bucket.container || bucket.space}</span>
                    <span className="text-sm text-gray-600">{bucket.count} files</span>
                  </div>
                  <div className="space-y-2">
                    {bucket.matches.slice(0, 10).map((match, midx) => (
                      <div key={midx} className="p-2 bg-gray-50 rounded text-sm">
                        <div className="flex items-center justify-between">
                          <span className="font-mono text-gray-900">{match.key}</span>
                          {match.size_human && (
                            <span className="text-xs text-gray-600">{match.size_human}</span>
                          )}
                        </div>
                        {match.content_match && (
                          <span className="text-xs text-green-600 mt-1 block">
                            <CheckCircle className="h-3 w-3 inline mr-1" />
                            Content match found
                          </span>
                        )}
                      </div>
                    ))}
                    {bucket.matches.length > 10 && (
                      <div className="text-sm text-gray-600 text-center pt-2">
                        ... and {bucket.matches.length - 10} more files
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ))}
    </div>
  )
}

function SecurityResults({ results }) {
  const summary = results.summary || {}

  const getRiskColor = (level) => {
    switch (level) {
      case 'low': return 'text-green-600 bg-green-50'
      case 'medium': return 'text-yellow-600 bg-yellow-50'
      case 'high': return 'text-red-600 bg-red-50'
      default: return 'text-gray-600 bg-gray-50'
    }
  }

  return (
    <div className="space-y-6">
      {/* Security Score */}
      <div className="card">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">Security Posture</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="text-center p-4 bg-blue-50 rounded-lg">
            <div className="text-3xl font-bold text-blue-600">{summary.security_score || 0}</div>
            <div className="text-sm text-gray-600">Security Score</div>
          </div>
          <div className={`text-center p-4 rounded-lg ${getRiskColor(summary.risk_level)}`}>
            <div className="text-lg font-bold uppercase">{summary.risk_level || 'unknown'}</div>
            <div className="text-sm">Risk Level</div>
          </div>
          <div className="text-center p-4 bg-red-50 rounded-lg">
            <div className="text-2xl font-bold text-red-600">{summary.public_buckets || 0}</div>
            <div className="text-sm text-gray-600">Public Buckets</div>
          </div>
          <div className="text-center p-4 bg-orange-50 rounded-lg">
            <div className="text-2xl font-bold text-orange-600">{summary.unencrypted_buckets || 0}</div>
            <div className="text-sm text-gray-600">Unencrypted</div>
          </div>
        </div>
      </div>

      {/* Bucket Analysis */}
      {Object.entries(results.analysis || {}).map(([bucket, analysis]) => (
        <div key={bucket} className="card">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-semibold text-gray-900">{bucket}</h3>
            <div className="flex items-center space-x-2">
              {analysis.public_access && (
                <span className="px-2 py-1 bg-red-100 text-red-700 text-xs rounded">Public</span>
              )}
              {analysis.encryption_enabled && (
                <span className="px-2 py-1 bg-green-100 text-green-700 text-xs rounded">Encrypted</span>
              )}
              {analysis.versioning_enabled && (
                <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded">Versioned</span>
              )}
            </div>
          </div>

          {analysis.issues && analysis.issues.length > 0 && (
            <div className="mb-4">
              <h4 className="text-sm font-semibold text-gray-900 mb-2">Issues Found</h4>
              <ul className="space-y-1">
                {analysis.issues.map((issue, idx) => (
                  <li key={idx} className="flex items-start text-sm text-red-700">
                    <XCircle className="h-4 w-4 mr-2 flex-shrink-0 mt-0.5" />
                    {issue}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {analysis.recommendations && analysis.recommendations.length > 0 && (
            <div>
              <h4 className="text-sm font-semibold text-gray-900 mb-2">Recommendations</h4>
              <ul className="space-y-1">
                {analysis.recommendations.map((rec, idx) => (
                  <li key={idx} className="flex items-start text-sm text-blue-700">
                    <Info className="h-4 w-4 mr-2 flex-shrink-0 mt-0.5" />
                    {rec}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      ))}
    </div>
  )
}
