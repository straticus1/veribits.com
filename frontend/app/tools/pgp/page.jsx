'use client'

import { useState } from 'react'
import {
  parsePGPKey,
  looksLikePGPKey,
  extractPGPKey,
  formatFingerprint,
  getShortKeyID,
  formatKeyDate
} from '@/app/utils/pgpValidator'
import {
  Key,
  Upload,
  AlertCircle,
  CheckCircle,
  XCircle,
  Shield,
  User,
  Calendar,
  Hash,
  Lock,
  Copy,
  Info
} from 'lucide-react'

export default function PGPValidator() {
  const [input, setInput] = useState('')
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [copied, setCopied] = useState(null)

  const handleFileUpload = (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = (event) => {
      const content = event.target?.result
      if (typeof content === 'string') {
        setInput(content)
      }
    }
    reader.onerror = () => {
      setError('Failed to read file')
    }
    reader.readAsText(file)
  }

  const handleValidate = async () => {
    setError('')
    setResult(null)

    if (!input.trim()) {
      setError('Please paste or upload a PGP public key')
      return
    }

    // Check if it looks like a PGP key
    let keyText = input.trim()
    if (!looksLikePGPKey(keyText)) {
      // Try to extract if embedded
      const extracted = extractPGPKey(keyText)
      if (extracted) {
        keyText = extracted
      } else {
        setError('Invalid PGP key format. Key must be ASCII-armored (begins with "-----BEGIN PGP PUBLIC KEY BLOCK-----")')
        return
      }
    }

    setLoading(true)

    try {
      const metadata = await parsePGPKey(keyText)
      setResult(metadata)
    } catch (err) {
      // Check if openpgp is not installed
      if (err.message.includes('Cannot find module')) {
        setError('OpenPGP library not installed. Please run: npm install openpgp')
      } else {
        setError(err.message)
      }
    } finally {
      setLoading(false)
    }
  }

  const copyToClipboard = async (text, field) => {
    try {
      await navigator.clipboard.writeText(text)
      setCopied(field)
      setTimeout(() => setCopied(null), 2000)
    } catch (err) {
      console.error('Failed to copy:', err)
    }
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">PGP/GPG Key Validator</h1>
        <p className="text-gray-600 mt-2">
          Upload or paste a PGP/GPG public key to view its metadata, verify validity, and check security.
        </p>
      </div>

      {/* Input Section */}
      <div className="card max-w-4xl mb-8">
        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">PGP Public Key</label>
          <textarea
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----&#10;&#10;Paste your PGP public key here...&#10;&#10;-----END PGP PUBLIC KEY BLOCK-----"
            className="w-full h-48 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent font-mono text-sm"
          />
        </div>

        {/* File Upload */}
        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">Or Upload Key File</label>
          <label className="flex items-center justify-center px-4 py-3 bg-white border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 hover:bg-gray-50 transition-colors">
            <Upload className="h-5 w-5 text-gray-400 mr-2" />
            <span className="text-sm text-gray-700">Choose .asc, .pgp, or .txt file</span>
            <input
              type="file"
              accept=".asc,.pgp,.gpg,.txt"
              onChange={handleFileUpload}
              className="hidden"
            />
          </label>
        </div>

        <button onClick={handleValidate} disabled={loading} className="btn-primary w-full">
          {loading ? (
            <>
              <div className="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full mr-2"></div>
              Validating Key...
            </>
          ) : (
            <>
              <Key className="h-5 w-5 mr-2" />
              Validate PGP Key
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
          {/* Validation Status */}
          <div className="card max-w-4xl">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold text-gray-900">Validation Status</h2>
              <div className="flex items-center">
                {result.isExpired ? (
                  <>
                    <XCircle className="h-6 w-6 text-red-600 mr-2" />
                    <span className="text-red-600 font-semibold">Expired</span>
                  </>
                ) : (
                  <>
                    <CheckCircle className="h-6 w-6 text-green-600 mr-2" />
                    <span className="text-green-600 font-semibold">Valid</span>
                  </>
                )}
              </div>
            </div>

            {/* Key Strength */}
            {result.keyStrength && (
              <div className="mb-4">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-sm font-medium text-gray-700">Key Strength</span>
                  <span className={`text-sm font-semibold ${
                    result.keyStrength.score >= 80 ? 'text-green-600' :
                    result.keyStrength.score >= 60 ? 'text-yellow-600' :
                    'text-red-600'
                  }`}>
                    {result.keyStrength.strength}
                  </span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div
                    className={`h-2 rounded-full ${
                      result.keyStrength.score >= 80 ? 'bg-green-600' :
                      result.keyStrength.score >= 60 ? 'bg-yellow-600' :
                      'bg-red-600'
                    }`}
                    style={{ width: `${result.keyStrength.score}%` }}
                  ></div>
                </div>
              </div>
            )}

            {/* Warnings */}
            {result.warnings && result.warnings.length > 0 && (
              <div className="mt-4">
                <h3 className="text-sm font-semibold text-gray-900 mb-2">Warnings</h3>
                <ul className="space-y-2">
                  {result.warnings.map((warning, idx) => (
                    <li key={idx} className="flex items-start text-sm text-yellow-700">
                      <AlertCircle className="h-4 w-4 mr-2 flex-shrink-0 mt-0.5" />
                      {warning}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          {/* Key Information */}
          <div className="card max-w-4xl">
            <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
              <Key className="h-5 w-5 mr-2 text-primary-600" />
              Key Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <InfoField
                label="Fingerprint"
                value={formatFingerprint(result.fingerprint)}
                icon={Hash}
                copyable
                onCopy={() => copyToClipboard(result.fingerprint, 'fingerprint')}
                copied={copied === 'fingerprint'}
              />
              <InfoField
                label="Key ID"
                value={`0x${result.keyID}`}
                icon={Hash}
                copyable
                onCopy={() => copyToClipboard(result.keyID, 'keyID')}
                copied={copied === 'keyID'}
              />
              <InfoField
                label="Short Key ID"
                value={`0x${getShortKeyID(result.keyID)}`}
                icon={Hash}
              />
              <InfoField
                label="Algorithm"
                value={result.algorithm}
                icon={Lock}
              />
              <InfoField
                label="Key Size"
                value={`${result.bits} bits`}
                icon={Shield}
              />
              {result.curve && (
                <InfoField
                  label="Curve"
                  value={result.curve}
                  icon={Lock}
                />
              )}
              <InfoField
                label="Created"
                value={formatKeyDate(result.created)}
                icon={Calendar}
              />
              <InfoField
                label="Expires"
                value={result.expires ? formatKeyDate(result.expires) : 'Never'}
                icon={Calendar}
                warning={!result.expires || result.isExpired}
              />
              <InfoField
                label="Version"
                value={`v${result.version}`}
                icon={Info}
              />
            </div>

            {/* Capabilities */}
            {result.capabilities && (
              <div className="mt-6 pt-6 border-t border-gray-200">
                <h3 className="text-sm font-semibold text-gray-900 mb-3">Key Capabilities</h3>
                <div className="flex flex-wrap gap-2">
                  <Capability label="Certify" enabled={result.capabilities.certify} />
                  <Capability label="Sign" enabled={result.capabilities.sign} />
                  <Capability label="Encrypt" enabled={result.capabilities.encrypt} />
                  <Capability label="Authenticate" enabled={result.capabilities.authenticate} />
                </div>
              </div>
            )}
          </div>

          {/* User IDs */}
          {result.userIds && result.userIds.length > 0 && (
            <div className="card max-w-4xl">
              <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <User className="h-5 w-5 mr-2 text-primary-600" />
                User IDs ({result.userIds.length})
              </h2>

              <div className="space-y-3">
                {result.userIds.map((userId, idx) => (
                  <div key={idx} className="p-4 bg-gray-50 rounded-lg">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="font-semibold text-gray-900">{userId.name}</div>
                        {userId.email && (
                          <div className="text-sm text-gray-600 font-mono">{userId.email}</div>
                        )}
                        {userId.comment && (
                          <div className="text-sm text-gray-500 italic">({userId.comment})</div>
                        )}
                      </div>
                      <div className="flex items-center space-x-2">
                        {userId.isPrimary && (
                          <span className="px-2 py-1 text-xs font-medium bg-primary-100 text-primary-700 rounded">
                            Primary
                          </span>
                        )}
                        {userId.verified ? (
                          <CheckCircle className="h-4 w-4 text-green-600" title="Self-certified" />
                        ) : (
                          <XCircle className="h-4 w-4 text-gray-400" title="Not certified" />
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Subkeys */}
          {result.subkeys && result.subkeys.length > 0 && (
            <div className="card max-w-4xl">
              <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <Key className="h-5 w-5 mr-2 text-primary-600" />
                Subkeys ({result.subkeys.length})
              </h2>

              <div className="space-y-4">
                {result.subkeys.map((subkey, idx) => (
                  <div key={idx} className="p-4 bg-gray-50 rounded-lg">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <div className="text-xs text-gray-600 mb-1">Key ID</div>
                        <div className="font-mono text-sm text-gray-900">0x{subkey.keyID}</div>
                      </div>
                      <div>
                        <div className="text-xs text-gray-600 mb-1">Algorithm</div>
                        <div className="text-sm text-gray-900">{subkey.algorithm}</div>
                      </div>
                      <div>
                        <div className="text-xs text-gray-600 mb-1">Size</div>
                        <div className="text-sm text-gray-900">{subkey.bits} bits</div>
                      </div>
                      <div>
                        <div className="text-xs text-gray-600 mb-1">Created</div>
                        <div className="text-sm text-gray-900">{formatKeyDate(subkey.created)}</div>
                      </div>
                    </div>
                    {subkey.capabilities && (
                      <div className="mt-3 pt-3 border-t border-gray-200">
                        <div className="text-xs text-gray-600 mb-2">Capabilities</div>
                        <div className="flex flex-wrap gap-2">
                          <Capability label="Sign" enabled={subkey.capabilities.sign} small />
                          <Capability label="Encrypt" enabled={subkey.capabilities.encrypt} small />
                          <Capability label="Authenticate" enabled={subkey.capabilities.authenticate} small />
                        </div>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Info Section */}
      <div className="mt-8 max-w-4xl">
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 className="text-sm font-semibold text-blue-900 mb-2 flex items-center">
            <Info className="h-4 w-4 mr-2" />
            About PGP/GPG Keys
          </h3>
          <p className="text-sm text-blue-800">
            PGP (Pretty Good Privacy) and GPG (GNU Privacy Guard) use public-key cryptography for secure communication.
            This tool validates public keys only and extracts metadata including fingerprints, key IDs, algorithms,
            user identities, and expiration dates. For security, only upload public keys, never private keys.
          </p>
        </div>
      </div>
    </div>
  )
}

// Info Field Component
function InfoField({ label, value, icon: Icon, copyable, onCopy, copied, warning }) {
  return (
    <div className={`p-3 rounded-lg ${warning ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50'}`}>
      <div className="flex items-center justify-between mb-1">
        <div className="flex items-center text-xs text-gray-600">
          <Icon className="h-3 w-3 mr-1" />
          {label}
        </div>
        {copyable && (
          <button
            onClick={onCopy}
            className="text-primary-600 hover:text-primary-700"
            title="Copy to clipboard"
          >
            {copied ? <CheckCircle className="h-3 w-3" /> : <Copy className="h-3 w-3" />}
          </button>
        )}
      </div>
      <div className={`font-mono text-sm break-all ${warning ? 'text-yellow-900 font-semibold' : 'text-gray-900'}`}>
        {value}
      </div>
    </div>
  )
}

// Capability Badge Component
function Capability({ label, enabled, small = false }) {
  return (
    <span
      className={`inline-flex items-center ${
        small ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'
      } rounded-full font-medium ${
        enabled
          ? 'bg-green-100 text-green-800'
          : 'bg-gray-100 text-gray-500'
      }`}
    >
      {enabled ? <CheckCircle className={`${small ? 'h-3 w-3' : 'h-4 w-4'} mr-1`} /> : <XCircle className={`${small ? 'h-3 w-3' : 'h-4 w-4'} mr-1`} />}
      {label}
    </span>
  )
}
