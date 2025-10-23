'use client'

import { useState } from 'react'
import { useAuth } from '@/app/components/AuthProvider'
import {
  Globe,
  Shield,
  Mail,
  Server,
  AlertCircle,
  CheckCircle,
  XCircle,
  Info,
  Copy,
  RefreshCw,
  ExternalLink
} from 'lucide-react'

export default function DNSValidator() {
  const { user } = useAuth()
  const [domain, setDomain] = useState('')
  const [checkType, setCheckType] = useState('full')
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [copied, setCopied] = useState(false)

  const checkTypes = [
    { value: 'full', label: 'Full Check', icon: Globe },
    { value: 'records', label: 'DNS Records', icon: Server },
    { value: 'ns', label: 'Nameservers', icon: Server },
    { value: 'security', label: 'DNSSEC', icon: Shield },
    { value: 'email', label: 'Email Config', icon: Mail },
    { value: 'propagation', label: 'Propagation', icon: RefreshCw },
    { value: 'blacklist', label: 'Blacklist', icon: AlertCircle }
  ]

  const handleCheck = async () => {
    setError('')
    setResult(null)

    if (!domain.trim()) {
      setError('Please enter a domain name')
      return
    }

    setLoading(true)

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
      const token = localStorage.getItem('auth_token')

      const response = await fetch(`${apiUrl}/api/v1/dns/check`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          domain: domain.trim(),
          check_type: checkType
        })
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error?.message || 'DNS check failed')
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

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">DNS Validation Suite</h1>
        <p className="text-gray-600 mt-2">
          Comprehensive DNS analysis including records, email configuration, security, and propagation checks.
        </p>
      </div>

      {/* Input Section */}
      <div className="card max-w-4xl mb-8">
        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">Domain Name</label>
          <input
            type="text"
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && handleCheck()}
            placeholder="e.g., example.com"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-3">Check Type</label>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {checkTypes.map(({ value, label, icon: Icon }) => (
              <button
                key={value}
                onClick={() => setCheckType(value)}
                className={`p-3 rounded-lg border-2 transition-all ${
                  checkType === value
                    ? 'border-primary-500 bg-primary-50 text-primary-700'
                    : 'border-gray-200 hover:border-gray-300 text-gray-700'
                }`}
              >
                <Icon className="h-5 w-5 mx-auto mb-1" />
                <div className="text-xs font-medium">{label}</div>
              </button>
            ))}
          </div>
        </div>

        <button onClick={handleCheck} disabled={loading} className="btn-primary w-full">
          {loading ? (
            <>
              <RefreshCw className="h-5 w-5 mr-2 animate-spin" />
              Checking DNS...
            </>
          ) : (
            <>
              <Globe className="h-5 w-5 mr-2" />
              Run DNS Check
            </>
          )}
        </button>
      </div>

      {/* Error Message */}
      {error && (
        <div className="max-w-4xl mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
          <AlertCircle className="h-5 w-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" />
          <div className="text-sm text-red-800">{error}</div>
        </div>
      )}

      {/* Results */}
      {result && (
        <div className="space-y-6">
          {/* Health Score */}
          <div className="card max-w-4xl">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold text-gray-900">Health Score</h2>
              <div className="flex items-center space-x-4">
                <div className={`text-4xl font-bold ${getScoreColor(result.health_score)}`}>
                  {result.health_grade}
                </div>
                <div className="text-3xl font-semibold text-gray-900">{result.health_score}/100</div>
              </div>
            </div>

            {result.issues_found && result.issues_found.length > 0 && (
              <div className="mt-4">
                <h3 className="text-sm font-semibold text-gray-900 mb-2">Issues Found</h3>
                <ul className="space-y-2">
                  {result.issues_found.map((issue, idx) => (
                    <li key={idx} className="flex items-start text-sm text-red-700">
                      <AlertCircle className="h-4 w-4 mr-2 flex-shrink-0 mt-0.5" />
                      {issue}
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {result.badge_url && (
              <div className="mt-4 pt-4 border-t border-gray-200">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-600">Shareable Badge:</span>
                  <button
                    onClick={() => copyToClipboard(window.location.origin + result.badge_url)}
                    className="text-sm text-primary-600 hover:text-primary-700 flex items-center"
                  >
                    {copied ? <CheckCircle className="h-4 w-4 mr-1" /> : <Copy className="h-4 w-4 mr-1" />}
                    {copied ? 'Copied!' : 'Copy Badge URL'}
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* DNS Records */}
          {result.results?.dns_records && (
            <DNSRecordsSection records={result.results.dns_records} />
          )}

          {/* Nameserver Verification */}
          {result.results?.ns_verification && (
            <NameserverSection data={result.results.ns_verification} />
          )}

          {/* Email Configuration */}
          {result.results?.email_config && (
            <EmailConfigSection config={result.results.email_config} copyToClipboard={copyToClipboard} />
          )}

          {/* DNSSEC Status */}
          {result.results?.dnssec_status && (
            <DNSSECSection status={result.results.dnssec_status} />
          )}

          {/* Propagation Check */}
          {result.results?.propagation && (
            <PropagationSection data={result.results.propagation} />
          )}

          {/* Blacklist Status */}
          {result.results?.blacklist_status && (
            <BlacklistSection data={result.results.blacklist_status} />
          )}
        </div>
      )}

      {/* Info Section */}
      <div className="mt-8 max-w-4xl">
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 className="text-sm font-semibold text-blue-900 mb-2 flex items-center">
            <Info className="h-4 w-4 mr-2" />
            About DNS Validation
          </h3>
          <p className="text-sm text-blue-800">
            This tool performs comprehensive DNS checks including record verification, email security configuration (SPF,
            DMARC, DKIM), DNSSEC validation, propagation analysis across global DNS servers, and blacklist monitoring. A
            healthy DNS configuration is essential for email deliverability, security, and overall domain reputation.
          </p>
        </div>
      </div>
    </div>
  )
}

// DNS Records Section
function DNSRecordsSection({ records }) {
  const [expandedType, setExpandedType] = useState(null)

  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <Server className="h-5 w-5 mr-2 text-primary-600" />
        DNS Records
      </h2>

      <div className="space-y-2">
        {Object.entries(records).map(([type, typeRecords]) => (
          <div key={type} className="border border-gray-200 rounded-lg">
            <button
              onClick={() => setExpandedType(expandedType === type ? null : type)}
              className="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-50"
            >
              <div className="flex items-center">
                <span className="font-mono font-semibold text-sm text-gray-900">{type}</span>
                <span className="ml-3 text-sm text-gray-600">({typeRecords.length} record{typeRecords.length !== 1 ? 's' : ''})</span>
              </div>
              <span className="text-gray-400">{expandedType === type ? '−' : '+'}</span>
            </button>

            {expandedType === type && (
              <div className="px-4 py-3 border-t border-gray-200 bg-gray-50">
                {typeRecords.map((record, idx) => (
                  <div key={idx} className="mb-3 last:mb-0">
                    <RecordDisplay type={type} record={record} />
                  </div>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}

function RecordDisplay({ type, record }) {
  const displayFields = {
    A: ['ip', 'ttl'],
    AAAA: ['ipv6', 'ttl'],
    MX: ['target', 'pri', 'ttl'],
    NS: ['target', 'ttl'],
    TXT: ['txt', 'ttl'],
    CNAME: ['target', 'ttl'],
    SOA: ['mname', 'rname', 'serial', 'refresh', 'retry', 'expire', 'minimum-ttl'],
    PTR: ['target', 'ttl']
  }

  const fields = displayFields[type] || Object.keys(record).filter(k => !['host', 'class', 'type'].includes(k))

  return (
    <div className="font-mono text-xs space-y-1">
      {fields.map(field => (
        record[field] && (
          <div key={field} className="flex">
            <span className="text-gray-600 w-24">{field}:</span>
            <span className="text-gray-900">{record[field]}</span>
          </div>
        )
      ))}
    </div>
  )
}

// Nameserver Section
function NameserverSection({ data }) {
  const statusColor = data.status === 'healthy' ? 'text-green-600' : data.status === 'warning' ? 'text-yellow-600' : 'text-red-600'
  const StatusIcon = data.status === 'healthy' ? CheckCircle : data.status === 'warning' ? AlertCircle : XCircle

  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <Server className="h-5 w-5 mr-2 text-primary-600" />
        Nameserver Verification
      </h2>

      <div className={`flex items-center mb-4 ${statusColor}`}>
        <StatusIcon className="h-5 w-5 mr-2" />
        <span className="font-medium capitalize">{data.status}</span>
        <span className="ml-3 text-gray-600">({data.nameserver_count} nameservers)</span>
      </div>

      {data.nameservers && data.nameservers.length > 0 && (
        <div className="space-y-3">
          {data.nameservers.map((ns, idx) => (
            <div key={idx} className="p-3 bg-gray-50 rounded-lg">
              <div className="flex items-center justify-between mb-2">
                <span className="font-mono text-sm font-semibold text-gray-900">{ns.hostname}</span>
                {ns.responsive ? (
                  <span className="text-xs text-green-600 flex items-center">
                    <CheckCircle className="h-3 w-3 mr-1" />
                    Responsive
                  </span>
                ) : (
                  <span className="text-xs text-red-600 flex items-center">
                    <XCircle className="h-3 w-3 mr-1" />
                    Not Responsive
                  </span>
                )}
              </div>
              {ns.ip_addresses && ns.ip_addresses.length > 0 && (
                <div className="text-xs text-gray-600">
                  IPs: {ns.ip_addresses.join(', ')}
                </div>
              )}
              {ns.response_time_ms && (
                <div className="text-xs text-gray-600">
                  Response time: {ns.response_time_ms}ms
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {data.issues && data.issues.length > 0 && (
        <div className="mt-4 pt-4 border-t border-gray-200">
          <h3 className="text-sm font-semibold text-gray-900 mb-2">Issues</h3>
          <ul className="space-y-1">
            {data.issues.map((issue, idx) => (
              <li key={idx} className="text-sm text-yellow-700 flex items-start">
                <AlertCircle className="h-4 w-4 mr-2 flex-shrink-0 mt-0.5" />
                {issue}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}

// Email Configuration Section
function EmailConfigSection({ config, copyToClipboard }) {
  const [expandedRecord, setExpandedRecord] = useState(null)

  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <Mail className="h-5 w-5 mr-2 text-primary-600" />
        Email Configuration
      </h2>

      <div className="space-y-4">
        {/* MX Records */}
        {config.mx_records && config.mx_records.length > 0 && (
          <div>
            <h3 className="text-sm font-semibold text-gray-900 mb-2 flex items-center">
              <CheckCircle className="h-4 w-4 mr-2 text-green-600" />
              MX Records ({config.mx_records.length})
            </h3>
            <div className="space-y-2">
              {config.mx_records.map((mx, idx) => (
                <div key={idx} className="flex items-center justify-between p-2 bg-gray-50 rounded">
                  <span className="font-mono text-sm">{mx.host}</span>
                  <span className="text-xs text-gray-600">Priority: {mx.priority}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* SPF Record */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-sm font-semibold text-gray-900 flex items-center">
              {config.spf_record ? (
                <CheckCircle className="h-4 w-4 mr-2 text-green-600" />
              ) : (
                <XCircle className="h-4 w-4 mr-2 text-red-600" />
              )}
              SPF Record
            </h3>
            {config.spf_record && (
              <button
                onClick={() => setExpandedRecord(expandedRecord === 'spf' ? null : 'spf')}
                className="text-xs text-primary-600 hover:text-primary-700"
              >
                {expandedRecord === 'spf' ? 'Hide' : 'Show'} Details
              </button>
            )}
          </div>
          {config.spf_record ? (
            <>
              <div className="p-3 bg-gray-50 rounded font-mono text-xs break-all">
                {config.spf_record}
              </div>
              {config.spf_valid !== undefined && (
                <div className="mt-2 text-sm">
                  {config.spf_valid ? (
                    <span className="text-green-600">✓ Valid SPF syntax</span>
                  ) : (
                    <span className="text-red-600">✗ Invalid SPF syntax</span>
                  )}
                </div>
              )}
            </>
          ) : (
            <div className="text-sm text-gray-600">No SPF record found</div>
          )}
        </div>

        {/* DMARC Record */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-sm font-semibold text-gray-900 flex items-center">
              {config.dmarc_record ? (
                <CheckCircle className="h-4 w-4 mr-2 text-green-600" />
              ) : (
                <XCircle className="h-4 w-4 mr-2 text-red-600" />
              )}
              DMARC Record
            </h3>
            {config.dmarc_record && (
              <button
                onClick={() => setExpandedRecord(expandedRecord === 'dmarc' ? null : 'dmarc')}
                className="text-xs text-primary-600 hover:text-primary-700"
              >
                {expandedRecord === 'dmarc' ? 'Hide' : 'Show'} Details
              </button>
            )}
          </div>
          {config.dmarc_record ? (
            <>
              <div className="p-3 bg-gray-50 rounded font-mono text-xs break-all">
                {config.dmarc_record}
              </div>
              {config.dmarc_policy && (
                <div className="mt-2 text-sm">
                  <span className="text-gray-700">Policy: </span>
                  <span className={`font-semibold ${
                    config.dmarc_policy === 'reject' ? 'text-green-600' :
                    config.dmarc_policy === 'quarantine' ? 'text-yellow-600' :
                    'text-gray-600'
                  }`}>
                    {config.dmarc_policy}
                  </span>
                </div>
              )}
            </>
          ) : (
            <div className="text-sm text-gray-600">No DMARC record found</div>
          )}
        </div>

        {/* Status Summary */}
        <div className="pt-4 border-t border-gray-200">
          <div className="text-sm">
            <span className="text-gray-700">Configuration Status: </span>
            <span className={`font-semibold ${
              config.status === 'fully_configured' ? 'text-green-600' :
              config.status === 'partial' ? 'text-yellow-600' :
              'text-red-600'
            }`}>
              {config.status.replace('_', ' ').toUpperCase()}
            </span>
          </div>
        </div>
      </div>
    </div>
  )
}

// DNSSEC Section
function DNSSECSection({ status }) {
  const isEnabled = status === 'enabled'

  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <Shield className="h-5 w-5 mr-2 text-primary-600" />
        DNSSEC Status
      </h2>

      <div className="flex items-center">
        {isEnabled ? (
          <CheckCircle className="h-6 w-6 text-green-600 mr-3" />
        ) : (
          <XCircle className="h-6 w-6 text-gray-400 mr-3" />
        )}
        <div>
          <div className={`font-semibold ${isEnabled ? 'text-green-600' : 'text-gray-600'}`}>
            DNSSEC {isEnabled ? 'Enabled' : 'Not Enabled'}
          </div>
          <div className="text-sm text-gray-600">
            {isEnabled
              ? 'Your domain is protected with DNSSEC'
              : 'Consider enabling DNSSEC for additional security'}
          </div>
        </div>
      </div>
    </div>
  )
}

// Propagation Section
function PropagationSection({ data }) {
  const isConsistent = data.consistent

  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <RefreshCw className="h-5 w-5 mr-2 text-primary-600" />
        DNS Propagation
      </h2>

      <div className={`flex items-center mb-4 ${isConsistent ? 'text-green-600' : 'text-yellow-600'}`}>
        {isConsistent ? (
          <CheckCircle className="h-5 w-5 mr-2" />
        ) : (
          <AlertCircle className="h-5 w-5 mr-2" />
        )}
        <span className="font-medium">
          {isConsistent ? 'Fully Propagated' : 'Propagation Incomplete'}
        </span>
      </div>

      {data.servers && (
        <div className="space-y-2">
          {Object.entries(data.servers).map(([name, server]) => (
            <div key={name} className="flex items-center justify-between p-3 bg-gray-50 rounded">
              <div className="flex-1">
                <div className="text-sm font-medium text-gray-900">{name}</div>
                <div className="text-xs text-gray-600 font-mono">{server.server}</div>
              </div>
              <div className="text-right">
                {server.responsive ? (
                  <div className="text-xs text-green-600">
                    ✓ {server.response_time_ms}ms
                  </div>
                ) : (
                  <div className="text-xs text-red-600">✗ No response</div>
                )}
                {server.records && server.records.length > 0 && (
                  <div className="text-xs text-gray-600 font-mono">
                    {server.records.join(', ')}
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

// Blacklist Section
function BlacklistSection({ data }) {
  const isClean = data.status === 'clean'

  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <Shield className="h-5 w-5 mr-2 text-primary-600" />
        Blacklist Status
      </h2>

      <div className={`flex items-center mb-4 ${isClean ? 'text-green-600' : 'text-red-600'}`}>
        {isClean ? (
          <CheckCircle className="h-5 w-5 mr-2" />
        ) : (
          <XCircle className="h-5 w-5 mr-2" />
        )}
        <span className="font-medium">
          {isClean ? 'No Blacklisting Detected' : 'Blacklisted!'}
        </span>
      </div>

      <div className="text-sm text-gray-600 mb-3">
        Checked {data.rbls_checked} blacklist{data.rbls_checked !== 1 ? 's' : ''} for{' '}
        {data.ips_checked && data.ips_checked.length} IP address{data.ips_checked && data.ips_checked.length !== 1 ? 'es' : ''}
      </div>

      {data.listings && data.listings.length > 0 && (
        <div className="mt-4 space-y-2">
          <h3 className="text-sm font-semibold text-gray-900">Listings Found:</h3>
          {data.listings.map((listing, idx) => (
            <div key={idx} className="p-3 bg-red-50 border border-red-200 rounded">
              <div className="font-mono text-sm text-red-900">{listing.ip}</div>
              <div className="text-xs text-red-700">Listed on: {listing.rbl}</div>
            </div>
          ))}
        </div>
      )}

      {data.ips_checked && data.ips_checked.length > 0 && (
        <div className="mt-3 text-xs text-gray-600">
          IPs checked: {data.ips_checked.join(', ')}
        </div>
      )}
    </div>
  )
}

// Helper function for score color
function getScoreColor(score) {
  if (score >= 90) return 'text-green-600'
  if (score >= 80) return 'text-blue-600'
  if (score >= 70) return 'text-yellow-600'
  if (score >= 60) return 'text-orange-600'
  return 'text-red-600'
}
