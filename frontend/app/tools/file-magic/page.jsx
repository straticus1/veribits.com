'use client'

import { useState } from 'react'
import { useAuth } from '@/app/components/AuthProvider'
import {
  FileType,
  Upload,
  AlertCircle,
  CheckCircle,
  Info,
  Copy,
  Hash,
  FileText
} from 'lucide-react'

export default function FileMagicAnalyzer() {
  const { user } = useAuth()
  const [selectedFile, setSelectedFile] = useState(null)
  const [preview, setPreview] = useState(null)
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [copied, setCopied] = useState(null)

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

    // Create preview for images
    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onload = (e) => setPreview(e.target.result)
      reader.readAsDataURL(file)
    } else {
      setPreview(null)
    }
  }

  const handleAnalyze = async () => {
    if (!selectedFile) {
      setError('Please select a file first')
      return
    }

    setLoading(true)
    setError('')

    try {
      const formData = new FormData()
      formData.append('file', selectedFile)

      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
      const token = localStorage.getItem('auth_token')

      const response = await fetch(`${apiUrl}/api/v1/file-magic`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: formData
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error?.message || 'Analysis failed')
      }

      setResult(data.data)
    } catch (err) {
      setError(err.message)
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

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">File Magic Number Analyzer</h1>
        <p className="text-gray-600 mt-2">
          Upload any file to detect its type by analyzing the magic number (file signature) in the file header.
        </p>
      </div>

      {/* Upload Section */}
      <div className="card max-w-4xl mb-8">
        <div
          className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary-400 transition-colors cursor-pointer"
          onDrop={handleDrop}
          onDragOver={handleDragOver}
        >
          <input
            type="file"
            onChange={handleFileSelect}
            className="hidden"
            id="file-upload"
          />
          <label htmlFor="file-upload" className="cursor-pointer">
            {selectedFile ? (
              <div className="space-y-4">
                {preview && (
                  <img
                    src={preview}
                    alt="Preview"
                    className="max-w-full max-h-64 mx-auto rounded-lg shadow-lg"
                  />
                )}
                <div className="flex items-center justify-center space-x-2">
                  <FileType className="h-8 w-8 text-primary-600" />
                  <div className="text-left">
                    <div className="font-semibold text-gray-900">{selectedFile.name}</div>
                    <div className="text-sm text-gray-600">
                      {(selectedFile.size / 1024).toFixed(2)} KB
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={(e) => {
                    e.preventDefault()
                    document.getElementById('file-upload').click()
                  }}
                  className="text-primary-600 hover:text-primary-700 text-sm"
                >
                  Choose a different file
                </button>
              </div>
            ) : (
              <div className="space-y-4">
                <Upload className="h-16 w-16 mx-auto text-gray-400" />
                <div>
                  <p className="text-lg text-gray-700">Drop a file here or click to browse</p>
                  <p className="text-sm text-gray-500 mt-1">Maximum file size: 10MB</p>
                </div>
              </div>
            )}
          </label>
        </div>

        {selectedFile && (
          <button
            onClick={handleAnalyze}
            disabled={loading}
            className="btn-primary w-full mt-6"
          >
            {loading ? (
              <>
                <div className="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                Analyzing File...
              </>
            ) : (
              <>
                <FileType className="h-5 w-5 mr-2" />
                Analyze Magic Number
              </>
            )}
          </button>
        )}
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
          {/* Detection Result */}
          <div className="card max-w-4xl">
            <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
              <FileType className="h-5 w-5 mr-2 text-primary-600" />
              Detection Result
            </h2>

            <div className="flex items-center mb-6">
              <CheckCircle className="h-8 w-8 text-green-600 mr-3" />
              <div>
                <div className="text-2xl font-bold text-gray-900">{result.detected_type}</div>
                <div className="text-sm text-gray-600">
                  Confidence: <span className={`font-semibold ${
                    result.match_confidence === 'high' ? 'text-green-600' : 'text-yellow-600'
                  }`}>
                    {result.match_confidence.toUpperCase()}
                  </span>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <InfoField
                label="Detected Extension"
                value={`.${result.detected_extension}`}
                icon={FileText}
              />
              <InfoField
                label="MIME Type"
                value={result.detected_mime}
                icon={FileType}
              />
            </div>
          </div>

          {/* Magic Number Details */}
          <div className="card max-w-4xl">
            <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
              <Hash className="h-5 w-5 mr-2 text-primary-600" />
              Magic Number Details
            </h2>

            <div className="space-y-4">
              <CopyableField
                label="Magic Number (Hex)"
                value={result.magic_number_hex}
                onCopy={() => copyToClipboard(result.magic_number_hex, 'magic_hex')}
                copied={copied === 'magic_hex'}
              />

              <CopyableField
                label="File Hash (SHA-256)"
                value={result.file_hash}
                onCopy={() => copyToClipboard(result.file_hash, 'file_hash')}
                copied={copied === 'file_hash'}
              />

              <div className="p-3 bg-gray-50 rounded-lg">
                <div className="text-xs text-gray-600 mb-1">File Size</div>
                <div className="text-sm font-mono text-gray-900">
                  {result.file_size.toLocaleString()} bytes ({(result.file_size / 1024).toFixed(2)} KB)
                </div>
              </div>
            </div>
          </div>

          {/* Additional Information */}
          {result.additional_info && (
            <div className="card max-w-4xl">
              <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <Info className="h-5 w-5 mr-2 text-primary-600" />
                Additional Information
              </h2>

              <div className="space-y-4">
                <div className="p-3 bg-gray-50 rounded-lg">
                  <div className="text-xs text-gray-600 mb-1">First 16 Bytes (Hex)</div>
                  <div className="text-sm font-mono text-gray-900 break-all">
                    {result.additional_info.first_16_bytes}
                  </div>
                </div>

                <div className="p-3 bg-gray-50 rounded-lg">
                  <div className="text-xs text-gray-600 mb-1">First 32 Bytes (Hex)</div>
                  <div className="text-sm font-mono text-gray-900 break-all">
                    {result.additional_info.first_32_bytes}
                  </div>
                </div>

                {result.additional_info.printable_header && (
                  <div className="p-3 bg-gray-50 rounded-lg">
                    <div className="text-xs text-gray-600 mb-1">Printable Header</div>
                    <div className="text-sm font-mono text-gray-900 break-all">
                      {result.additional_info.printable_header}
                    </div>
                  </div>
                )}

                {result.additional_info.extension_from_name && (
                  <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                      <div className="text-xs text-gray-600 mb-1">Filename Extension</div>
                      <div className="text-sm font-mono text-gray-900">
                        .{result.additional_info.extension_from_name}
                      </div>
                    </div>
                    <div className="flex items-center">
                      {result.additional_info.matches_extension ? (
                        <>
                          <CheckCircle className="h-5 w-5 text-green-600 mr-2" />
                          <span className="text-sm font-semibold text-green-700">Matches Detection</span>
                        </>
                      ) : (
                        <>
                          <AlertCircle className="h-5 w-5 text-yellow-600 mr-2" />
                          <span className="text-sm font-semibold text-yellow-700">Mismatch Warning</span>
                        </>
                      )}
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Badge */}
          {result.badge_url && (
            <div className="card max-w-4xl">
              <h2 className="text-xl font-semibold text-gray-900 mb-4">Shareable Badge</h2>
              <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span className="text-sm text-gray-600">Badge URL:</span>
                <button
                  onClick={() => copyToClipboard(window.location.origin + result.badge_url, 'badge')}
                  className="text-sm text-primary-600 hover:text-primary-700 flex items-center"
                >
                  {copied === 'badge' ? (
                    <>
                      <CheckCircle className="h-4 w-4 mr-1" />
                      Copied!
                    </>
                  ) : (
                    <>
                      <Copy className="h-4 w-4 mr-1" />
                      Copy Badge URL
                    </>
                  )}
                </button>
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
            About Magic Numbers
          </h3>
          <p className="text-sm text-blue-800 mb-2">
            Magic numbers (file signatures) are unique byte sequences at the beginning of files that identify the file
            type, regardless of the file extension. This is more reliable than relying on file extensions, which can be
            easily changed or spoofed.
          </p>
          <p className="text-sm text-blue-800">
            For example, JPEG files always start with <code className="bg-blue-100 px-1">FF D8 FF</code>, PNG files with{' '}
            <code className="bg-blue-100 px-1">89 50 4E 47</code>, and PDF files with{' '}
            <code className="bg-blue-100 px-1">25 50 44 46</code> (%PDF in ASCII).
          </p>
        </div>
      </div>
    </div>
  )
}

// Helper Components
function InfoField({ label, value, icon: Icon }) {
  return (
    <div className="p-3 bg-gray-50 rounded-lg">
      <div className="flex items-center text-xs text-gray-600 mb-1">
        <Icon className="h-3 w-3 mr-1" />
        {label}
      </div>
      <div className="text-sm font-semibold text-gray-900">{value}</div>
    </div>
  )
}

function CopyableField({ label, value, onCopy, copied }) {
  return (
    <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
      <div className="flex-1 mr-4">
        <div className="text-xs text-gray-600 mb-1">{label}</div>
        <div className="text-sm font-mono text-gray-900 break-all">{value}</div>
      </div>
      <button
        onClick={onCopy}
        className="flex-shrink-0 text-primary-600 hover:text-primary-700"
        title="Copy to clipboard"
      >
        {copied ? <CheckCircle className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
      </button>
    </div>
  )
}
