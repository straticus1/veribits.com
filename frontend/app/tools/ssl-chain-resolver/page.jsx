'use client'

import { useState } from 'react'
import { useAuth } from '@/app/components/AuthProvider'
import {
  Shield,
  Upload,
  Link2,
  FileText,
  AlertCircle,
  CheckCircle,
  Info,
  Copy,
  Download,
  Lock,
  AlertTriangle,
  ChevronRight,
  Globe,
  Key,
  XCircle
} from 'lucide-react'

export default function SSLChainResolver() {
  const { user } = useAuth()
  const [mode, setMode] = useState('chain-resolver') // 'chain-resolver' or 'key-verification'
  const [inputType, setInputType] = useState('url')
  const [url, setUrl] = useState('')
  const [port, setPort] = useState('443')
  const [selectedFile, setSelectedFile] = useState(null)
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [copied, setCopied] = useState(null)
  const [fetchingMissing, setFetchingMissing] = useState(false)

  // Key verification state
  const [certFile, setCertFile] = useState(null)
  const [keyFile, setKeyFile] = useState(null)
  const [keyVerifyResult, setKeyVerifyResult] = useState(null)

  const inputTypes = [
    { value: 'url', label: 'URL', icon: Globe, description: 'Fetch from live website' },
    { value: 'pem', label: 'PEM Certificate', icon: FileText, description: 'Upload PEM file' },
    { value: 'pkcs12', label: 'PKCS12 (.pfx/.p12)', icon: Lock, description: 'Upload with password' },
    { value: 'pkcs7', label: 'PKCS7 (.p7b)', icon: Shield, description: 'Certificate bundle' }
  ]

  const handleFileSelect = (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    if (file.size > 10 * 1024 * 1024) {
      setError('File too large (max 10MB)')
      return
    }

    setSelectedFile(file)
    setError('')
    setResult(null)
  }

  const handleDrop = (e) => {
    e.preventDefault()
    const file = e.dataTransfer.files?.[0]
    if (file) {
      handleFileSelect({ target: { files: [file] } })
    }
  }

  const handleDragOver = (e) => {
    e.preventDefault()
  }

  const handleResolveChain = async () => {
    setError('')
    setResult(null)

    // Validation
    if (inputType === 'url') {
      if (!url.trim()) {
        setError('Please enter a URL or domain')
        return
      }
    } else {
      if (!selectedFile) {
        setError('Please select a certificate file')
        return
      }
      if (inputType === 'pkcs12' && !password.trim()) {
        setError('Password is required for PKCS12 files')
        return
      }
    }

    setLoading(true)

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
      const token = localStorage.getItem('auth_token')

      const formData = new FormData()
      formData.append('input_type', inputType)

      if (inputType === 'url') {
        formData.append('url', url.trim())
        formData.append('port', port)
      } else {
        formData.append('certificate', selectedFile)
        if (inputType === 'pkcs12' && password) {
          formData.append('password', password)
        }
      }

      const headers = {}
      if (token) {
        headers['Authorization'] = `Bearer ${token}`
      }

      const response = await fetch(`${apiUrl}/api/v1/ssl/resolve-chain`, {
        method: 'POST',
        headers,
        body: formData
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error?.message || 'Chain resolution failed')
      }

      setResult(data.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleFetchMissing = async (certificate) => {
    setFetchingMissing(true)

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
      const token = localStorage.getItem('auth_token')

      const response = await fetch(`${apiUrl}/api/v1/ssl/fetch-missing`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': token ? `Bearer ${token}` : ''
        },
        body: JSON.stringify({
          certificate: certificate
        })
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error?.message || 'Failed to fetch certificate')
      }

      // Add the fetched certificate to the chain
      if (data.data.certificate) {
        // Re-resolve the chain with the new certificate
        const updatedChain = [...result.chain]
        updatedChain.push(data.data.info)

        setResult({
          ...result,
          chain: updatedChain,
          missing_count: Math.max(0, result.missing_count - 1)
        })
      }
    } catch (err) {
      setError('Failed to fetch missing certificate: ' + err.message)
    } finally {
      setFetchingMissing(false)
    }
  }

  const handleDownloadBundle = async (format) => {
    if (!result || !result.chain || result.chain.length === 0) {
      setError('No certificates available to download')
      return
    }

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
      const token = localStorage.getItem('auth_token')

      // For PKCS12, we need password and private key
      let bundlePassword = null
      let privateKey = null

      if (format === 'pkcs12') {
        if (!result.has_private_key) {
          setError('Private key is required for PKCS12 format')
          return
        }

        bundlePassword = prompt('Enter password for PKCS12 bundle:')
        if (!bundlePassword) {
          return
        }

        // Note: In a real implementation, we'd need to get the private key from the original upload
        // For now, we'll show an error if user didn't upload a PKCS12 file
        if (inputType !== 'pkcs12') {
          setError('PKCS12 download is only available when uploading a PKCS12 file')
          return
        }
      }

      const certificates = result.chain.map(cert => cert.pem)

      const response = await fetch(`${apiUrl}/api/v1/ssl/build-bundle`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': token ? `Bearer ${token}` : ''
        },
        body: JSON.stringify({
          certificates,
          format,
          password: bundlePassword,
          private_key: privateKey
        })
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error?.message || 'Failed to build bundle')
      }

      // Download the file
      const blob = new Blob([atob(data.data.content)], { type: data.data.mime_type })
      const downloadUrl = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.download = data.data.filename
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(downloadUrl)

    } catch (err) {
      setError('Failed to download bundle: ' + err.message)
    }
  }

  const downloadCertificate = (cert, index) => {
    const blob = new Blob([cert.pem], { type: 'application/x-pem-file' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    const cn = cert.subject?.CN || `certificate-${index}`
    link.download = `${cn.replace(/[^a-zA-Z0-9]/g, '_')}.pem`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
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

  const formatDN = (dn) => {
    if (!dn) return 'N/A'
    const parts = []
    if (dn.CN) parts.push(`CN=${dn.CN}`)
    if (dn.O) parts.push(`O=${dn.O}`)
    if (dn.OU) parts.push(`OU=${dn.OU}`)
    if (dn.C) parts.push(`C=${dn.C}`)
    return parts.join(', ') || 'N/A'
  }

  const getCertificateTypeLabel = (cert, index, total) => {
    if (index === 0) return 'Leaf Certificate'
    if (index === total - 1 && cert.is_ca) return 'Root CA'
    if (cert.is_ca) return 'Intermediate CA'
    return 'Certificate'
  }

  const getCertificateIcon = (cert, index, total) => {
    if (index === 0) return Globe
    if (index === total - 1 && cert.is_ca) return Shield
    return Key
  }

  const handleVerifyKeyPair = async () => {
    setError('')
    setKeyVerifyResult(null)

    if (!certFile) {
      setError('Please select a certificate file')
      return
    }

    if (!keyFile) {
      setError('Please select a private key file')
      return
    }

    setLoading(true)

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
      const token = localStorage.getItem('auth_token')

      const formData = new FormData()
      formData.append('certificate', certFile)
      formData.append('private_key', keyFile)

      const response = await fetch(`${apiUrl}/api/v1/ssl/verify-key-pair`, {
        method: 'POST',
        headers: {
          'Authorization': token ? `Bearer ${token}` : ''
        },
        body: formData
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error?.message || 'Verification failed')
      }

      setKeyVerifyResult(data.data)
    } catch (err) {
      setError(err.message || 'Failed to verify key pair')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">SSL Certificate Tools</h1>
        <p className="text-gray-600 mt-2">
          Build complete SSL certificate chains and verify private key matches with certificates.
        </p>
      </div>

      {/* Mode Selection */}
      <div className="mb-6">
        <div className="flex space-x-2 border-b border-gray-200">
          <button
            onClick={() => {
              setMode('chain-resolver')
              setError('')
              setResult(null)
              setKeyVerifyResult(null)
            }}
            className={`px-6 py-3 font-medium text-sm transition-all ${
              mode === 'chain-resolver'
                ? 'border-b-2 border-primary-600 text-primary-600'
                : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            <Shield className="h-4 w-4 inline-block mr-2" />
            Chain Resolver
          </button>
          <button
            onClick={() => {
              setMode('key-verification')
              setError('')
              setResult(null)
              setKeyVerifyResult(null)
            }}
            className={`px-6 py-3 font-medium text-sm transition-all ${
              mode === 'key-verification'
                ? 'border-b-2 border-primary-600 text-primary-600'
                : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            <Key className="h-4 w-4 inline-block mr-2" />
            Key Verification
          </button>
        </div>
      </div>

      {/* Chain Resolver Mode */}
      {mode === 'chain-resolver' && (
        <>
          {/* Input Type Selection */}
          <div className="card mb-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Input Method</h2>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {inputTypes.map((type) => {
            const Icon = type.icon
            return (
              <button
                key={type.value}
                onClick={() => {
                  setInputType(type.value)
                  setError('')
                  setResult(null)
                }}
                className={`p-4 rounded-lg border-2 transition-all text-left ${
                  inputType === type.value
                    ? 'border-primary-500 bg-primary-50'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <Icon className={`h-6 w-6 mb-2 ${
                  inputType === type.value ? 'text-primary-600' : 'text-gray-400'
                }`} />
                <div className="font-semibold text-gray-900 text-sm">{type.label}</div>
                <div className="text-xs text-gray-600 mt-1">{type.description}</div>
              </button>
            )
          })}
        </div>
      </div>

      {/* Input Section */}
      <div className="card mb-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Input</h2>

        {inputType === 'url' ? (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                URL or Domain
              </label>
              <input
                type="text"
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                placeholder="example.com or https://example.com"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>
            <div className="w-32">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Port
              </label>
              <input
                type="number"
                value={port}
                onChange={(e) => setPort(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <div
              className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary-400 transition-colors cursor-pointer"
              onDrop={handleDrop}
              onDragOver={handleDragOver}
            >
              <input
                type="file"
                onChange={handleFileSelect}
                className="hidden"
                id="cert-upload"
                accept=".pem,.crt,.cer,.pfx,.p12,.p7b,.p7c"
              />
              <label htmlFor="cert-upload" className="cursor-pointer">
                {selectedFile ? (
                  <div className="space-y-2">
                    <FileText className="h-12 w-12 mx-auto text-primary-600" />
                    <div className="font-semibold text-gray-900">{selectedFile.name}</div>
                    <div className="text-sm text-gray-600">
                      {(selectedFile.size / 1024).toFixed(2)} KB
                    </div>
                    <button
                      type="button"
                      onClick={(e) => {
                        e.preventDefault()
                        document.getElementById('cert-upload').click()
                      }}
                      className="text-primary-600 hover:text-primary-700 text-sm"
                    >
                      Choose different file
                    </button>
                  </div>
                ) : (
                  <div className="space-y-2">
                    <Upload className="h-12 w-12 mx-auto text-gray-400" />
                    <p className="text-gray-700">Drop certificate file here or click to browse</p>
                    <p className="text-sm text-gray-500">
                      Supports PEM, PKCS12 (.pfx, .p12), PKCS7 (.p7b) - Max 10MB
                    </p>
                  </div>
                )}
              </label>
            </div>

            {inputType === 'pkcs12' && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Password
                </label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter PKCS12 password"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                />
              </div>
            )}
          </div>
        )}
      </div>

      {/* Resolve Button */}
      <button
        onClick={handleResolveChain}
        disabled={loading}
        className="btn-primary w-full mb-8"
      >
        {loading ? (
          <>
            <div className="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full mr-2"></div>
            Analyzing Chain...
          </>
        ) : (
          <>
            <Shield className="h-5 w-5 mr-2" />
            Resolve Certificate Chain
          </>
        )}
      </button>

      {/* Error Message */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
          <AlertCircle className="h-5 w-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" />
          <div className="text-sm text-red-800">{error}</div>
        </div>
      )}

      {/* Results */}
      {result && (
        <div className="space-y-6">
          {/* Chain Status */}
          <div className="card">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-semibold text-gray-900 flex items-center">
                <Shield className="h-5 w-5 mr-2 text-primary-600" />
                Chain Analysis
              </h2>
              <div className={`px-4 py-2 rounded-full text-sm font-semibold ${
                result.complete
                  ? 'bg-green-100 text-green-800'
                  : 'bg-yellow-100 text-yellow-800'
              }`}>
                {result.complete ? (
                  <span className="flex items-center">
                    <CheckCircle className="h-4 w-4 mr-1" />
                    Complete Chain
                  </span>
                ) : (
                  <span className="flex items-center">
                    <AlertTriangle className="h-4 w-4 mr-1" />
                    Incomplete Chain
                  </span>
                )}
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-sm text-gray-600 mb-1">Total Certificates</div>
                <div className="text-2xl font-bold text-gray-900">{result.total_certificates}</div>
              </div>
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-sm text-gray-600 mb-1">Missing Certificates</div>
                <div className="text-2xl font-bold text-gray-900">{result.missing_count}</div>
              </div>
              {result.domain && (
                <div className="p-4 bg-gray-50 rounded-lg">
                  <div className="text-sm text-gray-600 mb-1">Domain</div>
                  <div className="text-lg font-semibold text-gray-900 truncate">{result.domain}</div>
                </div>
              )}
            </div>

            {/* Certificate Chain Visualization */}
            <div className="space-y-3">
              {result.chain && result.chain.map((cert, index) => {
                const Icon = getCertificateIcon(cert, index, result.chain.length)
                const typeLabel = getCertificateTypeLabel(cert, index, result.chain.length)

                return (
                  <div key={index} className="relative">
                    {index > 0 && (
                      <div className="absolute left-6 -top-3 bottom-full w-0.5 bg-gray-300 h-3"></div>
                    )}
                    <div className={`p-4 rounded-lg border-2 ${
                      cert.validity?.is_valid
                        ? 'border-green-200 bg-green-50'
                        : 'border-red-200 bg-red-50'
                    }`}>
                      <div className="flex items-start justify-between">
                        <div className="flex items-start space-x-3 flex-1">
                          <Icon className={`h-6 w-6 flex-shrink-0 mt-1 ${
                            cert.validity?.is_valid ? 'text-green-600' : 'text-red-600'
                          }`} />
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center space-x-2 mb-1">
                              <span className="text-xs font-semibold text-gray-600 uppercase">
                                {typeLabel}
                              </span>
                              {cert.validity?.is_valid ? (
                                <CheckCircle className="h-4 w-4 text-green-600" />
                              ) : (
                                <XCircle className="h-4 w-4 text-red-600" />
                              )}
                            </div>
                            <div className="font-semibold text-gray-900 mb-1">
                              {cert.subject?.CN || 'Unknown'}
                            </div>
                            <div className="text-sm text-gray-600 space-y-1">
                              <div>Issuer: {formatDN(cert.issuer)}</div>
                              {cert.validity && (
                                <div className="flex items-center space-x-4">
                                  <span>Valid: {new Date(cert.validity.valid_from).toLocaleDateString()}</span>
                                  <span>Expires: {new Date(cert.validity.valid_to).toLocaleDateString()}</span>
                                  {cert.validity.days_until_expiry > 0 && (
                                    <span className={
                                      cert.validity.days_until_expiry < 30 ? 'text-red-600 font-semibold' :
                                      cert.validity.days_until_expiry < 60 ? 'text-yellow-600' :
                                      'text-green-600'
                                    }>
                                      ({cert.validity.days_until_expiry} days)
                                    </span>
                                  )}
                                </div>
                              )}
                              {cert.fingerprints && (
                                <div className="font-mono text-xs">
                                  SHA256: {cert.fingerprints.sha256.substring(0, 32)}...
                                </div>
                              )}
                            </div>
                          </div>
                        </div>
                        <button
                          onClick={() => downloadCertificate(cert, index)}
                          className="ml-4 p-2 text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                          title="Download certificate"
                        >
                          <Download className="h-5 w-5" />
                        </button>
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>

          {/* Missing Certificates */}
          {result.missing && result.missing.length > 0 && (
            <div className="card">
              <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <AlertTriangle className="h-5 w-5 mr-2 text-yellow-600" />
                Missing Certificates
              </h2>
              <div className="space-y-4">
                {result.missing.map((missing, index) => (
                  <div key={index} className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="font-semibold text-gray-900 mb-2">
                          Missing {missing.type}: {missing.for_certificate}
                        </div>
                        <div className="text-sm text-gray-600 space-y-1">
                          <div>Required Issuer: {formatDN(missing.issuer_dn)}</div>
                          {missing.aia_urls && missing.aia_urls.length > 0 && (
                            <div>
                              AIA URLs:
                              {missing.aia_urls.map((url, i) => (
                                <div key={i} className="ml-4 font-mono text-xs">{url}</div>
                              ))}
                            </div>
                          )}
                        </div>
                      </div>
                      {missing.aia_urls && missing.aia_urls.length > 0 && (
                        <button
                          onClick={() => handleFetchMissing(result.chain[result.chain.length - 1].pem)}
                          disabled={fetchingMissing}
                          className="ml-4 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 text-sm"
                        >
                          {fetchingMissing ? 'Fetching...' : 'Fetch Now'}
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Download Options */}
          <div className="card">
            <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
              <Download className="h-5 w-5 mr-2 text-primary-600" />
              Download Options
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <button
                onClick={() => handleDownloadBundle('pem')}
                className="p-4 border-2 border-gray-200 rounded-lg hover:border-primary-500 hover:bg-primary-50 transition-all text-left"
              >
                <FileText className="h-6 w-6 text-primary-600 mb-2" />
                <div className="font-semibold text-gray-900">PEM Bundle</div>
                <div className="text-sm text-gray-600 mt-1">
                  All certificates in one PEM file
                </div>
              </button>

              <button
                onClick={() => handleDownloadBundle('pkcs7')}
                className="p-4 border-2 border-gray-200 rounded-lg hover:border-primary-500 hover:bg-primary-50 transition-all text-left"
              >
                <Shield className="h-6 w-6 text-primary-600 mb-2" />
                <div className="font-semibold text-gray-900">PKCS7 Bundle</div>
                <div className="text-sm text-gray-600 mt-1">
                  Certificate chain in .p7b format
                </div>
              </button>

              <button
                onClick={() => handleDownloadBundle('pkcs12')}
                disabled={!result.has_private_key || inputType !== 'pkcs12'}
                className="p-4 border-2 border-gray-200 rounded-lg hover:border-primary-500 hover:bg-primary-50 transition-all text-left disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <Lock className="h-6 w-6 text-primary-600 mb-2" />
                <div className="font-semibold text-gray-900">PKCS12 Bundle</div>
                <div className="text-sm text-gray-600 mt-1">
                  {result.has_private_key && inputType === 'pkcs12'
                    ? 'Certificate + private key in .pfx'
                    : 'Requires private key from PKCS12 input'}
                </div>
              </button>
            </div>
          </div>
        </div>
      )}
        </>
      )}

      {/* Key Verification Mode */}
      {mode === 'key-verification' && (
        <>
          <div className="card mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Verify Private Key Matches Certificate</h2>
            <p className="text-sm text-gray-600 mb-6">
              Upload a certificate and private key to verify they form a valid pair. This tool compares the modulus
              and public key to confirm they match.
            </p>

            {/* Error Display */}
            {error && (
              <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
                <AlertCircle className="h-5 w-5 text-red-600 mr-3 mt-0.5 flex-shrink-0" />
                <div className="text-sm text-red-800">{error}</div>
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Certificate Upload */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Certificate File (.pem, .crt, .cer)
                </label>
                <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-primary-400 transition-colors cursor-pointer">
                  <input
                    type="file"
                    onChange={(e) => setCertFile(e.target.files?.[0])}
                    accept=".pem,.crt,.cer,.cert"
                    className="hidden"
                    id="cert-file-input"
                  />
                  <label htmlFor="cert-file-input" className="cursor-pointer">
                    <FileText className="h-12 w-12 text-gray-400 mx-auto mb-3" />
                    {certFile ? (
                      <div>
                        <div className="text-sm font-medium text-gray-900">{certFile.name}</div>
                        <div className="text-xs text-gray-500 mt-1">
                          {(certFile.size / 1024).toFixed(2)} KB
                        </div>
                      </div>
                    ) : (
                      <div>
                        <div className="text-sm text-gray-600">Click to upload certificate</div>
                        <div className="text-xs text-gray-500 mt-1">PEM, CRT, or CER format</div>
                      </div>
                    )}
                  </label>
                </div>
              </div>

              {/* Private Key Upload */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Private Key File (.key, .pem)
                </label>
                <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-primary-400 transition-colors cursor-pointer">
                  <input
                    type="file"
                    onChange={(e) => setKeyFile(e.target.files?.[0])}
                    accept=".key,.pem"
                    className="hidden"
                    id="key-file-input"
                  />
                  <label htmlFor="key-file-input" className="cursor-pointer">
                    <Key className="h-12 w-12 text-gray-400 mx-auto mb-3" />
                    {keyFile ? (
                      <div>
                        <div className="text-sm font-medium text-gray-900">{keyFile.name}</div>
                        <div className="text-xs text-gray-500 mt-1">
                          {(keyFile.size / 1024).toFixed(2)} KB
                        </div>
                      </div>
                    ) : (
                      <div>
                        <div className="text-sm text-gray-600">Click to upload private key</div>
                        <div className="text-xs text-gray-500 mt-1">KEY or PEM format</div>
                      </div>
                    )}
                  </label>
                </div>
              </div>
            </div>

            <button
              onClick={handleVerifyKeyPair}
              disabled={loading || !certFile || !keyFile}
              className="mt-6 w-full bg-primary-600 text-white py-3 px-6 rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
            >
              {loading ? (
                <>
                  <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                  Verifying...
                </>
              ) : (
                <>
                  <Shield className="h-5 w-5 mr-2" />
                  Verify Key Pair
                </>
              )}
            </button>
          </div>

          {/* Verification Results */}
          {keyVerifyResult && (
            <div className="card">
              <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                {keyVerifyResult.match ? (
                  <>
                    <CheckCircle className="h-6 w-6 text-green-600 mr-2" />
                    <span className="text-green-600">Key Pair Match Verified</span>
                  </>
                ) : (
                  <>
                    <XCircle className="h-6 w-6 text-red-600 mr-2" />
                    <span className="text-red-600">Key Pair Does Not Match</span>
                  </>
                )}
              </h2>

              <div className="space-y-4">
                {/* Verification Details */}
                <div className={`p-4 rounded-lg ${keyVerifyResult.match ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
                  <h3 className="font-medium mb-2 flex items-center">
                    <Info className={`h-4 w-4 mr-2 ${keyVerifyResult.match ? 'text-green-600' : 'text-red-600'}`} />
                    Verification Method: {keyVerifyResult.verification_method}
                  </h3>
                  <div className="text-sm space-y-2">
                    {keyVerifyResult.details.modulus_match !== undefined && (
                      <div className="flex items-center">
                        {keyVerifyResult.details.modulus_match ? (
                          <CheckCircle className="h-4 w-4 text-green-600 mr-2" />
                        ) : (
                          <XCircle className="h-4 w-4 text-red-600 mr-2" />
                        )}
                        <span>Modulus Match: {keyVerifyResult.details.modulus_match ? 'Yes' : 'No'}</span>
                      </div>
                    )}
                    {keyVerifyResult.details.public_key_match !== undefined && (
                      <div className="flex items-center">
                        {keyVerifyResult.details.public_key_match ? (
                          <CheckCircle className="h-4 w-4 text-green-600 mr-2" />
                        ) : (
                          <XCircle className="h-4 w-4 text-red-600 mr-2" />
                        )}
                        <span>Public Key Match: {keyVerifyResult.details.public_key_match ? 'Yes' : 'No'}</span>
                      </div>
                    )}
                    {keyVerifyResult.details.certificate_modulus && (
                      <div className="mt-3">
                        <span className="font-medium">Certificate Modulus:</span>
                        <div className="font-mono text-xs mt-1 text-gray-600 break-all">
                          {keyVerifyResult.details.certificate_modulus}
                        </div>
                      </div>
                    )}
                    {keyVerifyResult.details.key_modulus && (
                      <div className="mt-3">
                        <span className="font-medium">Key Modulus:</span>
                        <div className="font-mono text-xs mt-1 text-gray-600 break-all">
                          {keyVerifyResult.details.key_modulus}
                        </div>
                      </div>
                    )}
                  </div>
                </div>

                {/* Certificate Information */}
                {keyVerifyResult.certificate_info && (
                  <div className="bg-gray-50 rounded-lg p-4">
                    <h3 className="font-medium mb-3">Certificate Information</h3>
                    <div className="space-y-2 text-sm">
                      <div>
                        <span className="font-medium">Subject:</span> {formatDN(keyVerifyResult.certificate_info.subject)}
                      </div>
                      <div>
                        <span className="font-medium">Issuer:</span> {formatDN(keyVerifyResult.certificate_info.issuer)}
                      </div>
                      {keyVerifyResult.certificate_info.validity && (
                        <>
                          <div>
                            <span className="font-medium">Valid From:</span> {new Date(keyVerifyResult.certificate_info.validity.valid_from).toLocaleString()}
                          </div>
                          <div>
                            <span className="font-medium">Valid To:</span> {new Date(keyVerifyResult.certificate_info.validity.valid_to).toLocaleString()}
                          </div>
                          <div>
                            <span className={`font-medium ${keyVerifyResult.certificate_info.validity.is_valid ? 'text-green-600' : 'text-red-600'}`}>
                              Status: {keyVerifyResult.certificate_info.validity.is_valid ? 'Valid' : 'Expired/Not Yet Valid'}
                            </span>
                          </div>
                        </>
                      )}
                      {keyVerifyResult.certificate_info.fingerprints && (
                        <div className="mt-2">
                          <span className="font-medium">SHA-256 Fingerprint:</span>
                          <div className="font-mono text-xs mt-1 text-gray-600 break-all">
                            {keyVerifyResult.certificate_info.fingerprints.sha256}
                          </div>
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Info Section for Key Verification */}
          <div className="mt-6">
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h3 className="text-sm font-semibold text-blue-900 mb-2 flex items-center">
                <Info className="h-4 w-4 mr-2" />
                About Key Verification
              </h3>
              <p className="text-sm text-blue-800 mb-2">
                Verifying that a private key matches a certificate is crucial before deploying SSL/TLS certificates
                to production servers. A mismatch will cause connection failures.
              </p>
              <p className="text-sm text-blue-800 mb-2">
                This tool verifies the key pair by:
              </p>
              <ul className="text-sm text-blue-800 list-disc list-inside space-y-1 ml-2">
                <li>Comparing RSA modulus values from both certificate and private key</li>
                <li>Verifying public key derivation from the private key</li>
                <li>Checking Subject Key Identifier (SKI) and Authority Key Identifier (AKI)</li>
                <li>Supporting both RSA and EC (Elliptic Curve) key types</li>
              </ul>
            </div>
          </div>
        </>
      )}

      {/* Error Display (global) */}
      {error && !mode && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
          <AlertCircle className="h-5 w-5 text-red-600 mr-3 mt-0.5 flex-shrink-0" />
          <div className="text-sm text-red-800">{error}</div>
        </div>
      )}
    </div>
  )
}
