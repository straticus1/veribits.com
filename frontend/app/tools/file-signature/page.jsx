'use client'

import { useState } from 'react'
import { useAuth } from '@/app/components/AuthProvider'
import {
  Shield,
  Upload,
  Key,
  FileText,
  AlertCircle,
  CheckCircle,
  Info,
  Copy,
  ShieldCheck,
  ShieldAlert,
  FileSignature
} from 'lucide-react'
import {
  detectSignatureType,
  validatePublicKey,
  verifyFileSignature,
  hasSignatureExtension
} from '@/app/utils/fileSignatureValidator'

export default function FileSignatureValidator() {
  const { user } = useAuth()
  const [selectedFile, setSelectedFile] = useState(null)
  const [signatureFile, setSignatureFile] = useState(null)
  const [publicKey, setPublicKey] = useState('')
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [copied, setCopied] = useState(null)

  const handleFileSelect = (e, type) => {
    const file = e.target.files?.[0]
    if (!file) return

    if (type === 'file') {
      setSelectedFile(file)
    } else if (type === 'signature') {
      setSignatureFile(file)
      // Read signature file content for type detection
      const reader = new FileReader()
      reader.onload = (event) => {
        const content = event.target.result
        const sigType = detectSignatureType(content)
        console.log('Detected signature type:', sigType)
      }
      reader.readAsText(file)
    }

    setError('')
    setResult(null)
  }

  const handlePublicKeyChange = (e) => {
    setPublicKey(e.target.value)
    setError('')
  }

  const handleVerify = async () => {
    if (!selectedFile) {
      setError('Please select a file to verify')
      return
    }

    if (!signatureFile) {
      setError('Please select a signature file')
      return
    }

    if (!publicKey.trim()) {
      setError('Please paste the public key')
      return
    }

    // Validate public key format
    const keyValidation = validatePublicKey(publicKey)
    if (!keyValidation.valid) {
      setError(keyValidation.error)
      return
    }

    setLoading(true)
    setError('')

    try {
      const verificationResult = await verifyFileSignature(selectedFile, signatureFile, publicKey)
      setResult(verificationResult)
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

  const handleDrop = (e, type) => {
    e.preventDefault()
    const file = e.dataTransfer.files?.[0]
    if (file) {
      handleFileSelect({ target: { files: [file] } }, type)
    }
  }

  const handleDragOver = (e) => {
    e.preventDefault()
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">File Signature Validator</h1>
        <p className="text-gray-600 mt-2">
          Verify cryptographic signatures on files using PGP/GPG. Upload your file, signature, and public key to verify authenticity.
        </p>
      </div>

      {/* Upload Section */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        {/* File Upload */}
        <div className="card">
          <h2 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <FileText className="h-5 w-5 mr-2 text-primary-600" />
            File to Verify
          </h2>
          <div
            className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-primary-400 transition-colors cursor-pointer"
            onDrop={(e) => handleDrop(e, 'file')}
            onDragOver={handleDragOver}
          >
            <input
              type="file"
              onChange={(e) => handleFileSelect(e, 'file')}
              className="hidden"
              id="file-upload"
            />
            <label htmlFor="file-upload" className="cursor-pointer">
              {selectedFile ? (
                <div className="space-y-2">
                  <FileText className="h-12 w-12 mx-auto text-primary-600" />
                  <div className="font-semibold text-gray-900">{selectedFile.name}</div>
                  <div className="text-sm text-gray-600">
                    {(selectedFile.size / 1024 / 1024).toFixed(2)} MB
                  </div>
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault()
                      document.getElementById('file-upload').click()
                    }}
                    className="text-primary-600 hover:text-primary-700 text-sm"
                  >
                    Choose different file
                  </button>
                </div>
              ) : (
                <div className="space-y-2">
                  <Upload className="h-12 w-12 mx-auto text-gray-400" />
                  <p className="text-gray-700">Drop file here or click to browse</p>
                  <p className="text-sm text-gray-500">Any file up to 100MB</p>
                </div>
              )}
            </label>
          </div>
        </div>

        {/* Signature Upload */}
        <div className="card">
          <h2 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <FileSignature className="h-5 w-5 mr-2 text-primary-600" />
            Signature File
          </h2>
          <div
            className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-primary-400 transition-colors cursor-pointer"
            onDrop={(e) => handleDrop(e, 'signature')}
            onDragOver={handleDragOver}
          >
            <input
              type="file"
              onChange={(e) => handleFileSelect(e, 'signature')}
              className="hidden"
              id="signature-upload"
              accept=".sig,.asc,.pgp,.gpg"
            />
            <label htmlFor="signature-upload" className="cursor-pointer">
              {signatureFile ? (
                <div className="space-y-2">
                  <FileSignature className="h-12 w-12 mx-auto text-primary-600" />
                  <div className="font-semibold text-gray-900">{signatureFile.name}</div>
                  <div className="text-sm text-gray-600">
                    {(signatureFile.size / 1024).toFixed(2)} KB
                  </div>
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault()
                      document.getElementById('signature-upload').click()
                    }}
                    className="text-primary-600 hover:text-primary-700 text-sm"
                  >
                    Choose different signature
                  </button>
                </div>
              ) : (
                <div className="space-y-2">
                  <Upload className="h-12 w-12 mx-auto text-gray-400" />
                  <p className="text-gray-700">Drop signature here or click to browse</p>
                  <p className="text-sm text-gray-500">.sig, .asc, .pgp, .gpg files</p>
                </div>
              )}
            </label>
          </div>
        </div>
      </div>

      {/* Public Key Section */}
      <div className="card mb-8">
        <h2 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <Key className="h-5 w-5 mr-2 text-primary-600" />
          Public Key
        </h2>
        <textarea
          value={publicKey}
          onChange={handlePublicKeyChange}
          placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----&#10;&#10;Paste the PGP public key here...&#10;&#10;-----END PGP PUBLIC KEY BLOCK-----"
          className="w-full h-48 px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-y"
        />
        <p className="text-sm text-gray-600 mt-2">
          Paste the ASCII-armored PGP public key used to create the signature
        </p>
      </div>

      {/* Verify Button */}
      {selectedFile && signatureFile && (
        <button
          onClick={handleVerify}
          disabled={loading}
          className="btn-primary w-full mb-8"
        >
          {loading ? (
            <>
              <div className="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full mr-2"></div>
              Verifying Signature...
            </>
          ) : (
            <>
              <Shield className="h-5 w-5 mr-2" />
              Verify Signature
            </>
          )}
        </button>
      )}

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
          {/* Verification Status */}
          <div className="card">
            <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
              <Shield className="h-5 w-5 mr-2 text-primary-600" />
              Verification Result
            </h2>

            <div className="flex items-center mb-6">
              {result.is_valid ? (
                <ShieldCheck className="h-12 w-12 text-green-600 mr-4" />
              ) : (
                <ShieldAlert className="h-12 w-12 text-red-600 mr-4" />
              )}
              <div>
                <div className={`text-2xl font-bold ${result.is_valid ? 'text-green-900' : 'text-red-900'}`}>
                  {result.statusText}
                </div>
                <div className="text-sm text-gray-600 mt-1">
                  Trust Level: <span className={`font-semibold ${
                    result.trustLevel.level === 'trusted' ? 'text-green-600' :
                    result.trustLevel.level === 'valid' ? 'text-blue-600' : 'text-red-600'
                  }`}>
                    {result.trustLevel.text}
                  </span>
                </div>
                <div className="text-xs text-gray-500 mt-1">{result.trustLevel.description}</div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <InfoField label="Signature Type" value={result.signature_type} icon={FileSignature} />
              <InfoField label="Filename" value={result.filename} icon={FileText} />
            </div>
          </div>

          {/* Signer Information */}
          {result.signer_info && Object.keys(result.signer_info).length > 0 && (
            <div className="card">
              <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <Key className="h-5 w-5 mr-2 text-primary-600" />
                Signer Information
              </h2>

              <div className="space-y-4">
                {result.signer_info.user_id && (
                  <div className="p-3 bg-gray-50 rounded-lg">
                    <div className="text-xs text-gray-600 mb-1">User ID</div>
                    <div className="text-sm font-medium text-gray-900">{result.signer_info.user_id}</div>
                  </div>
                )}

                {result.signer_info.fingerprint && (
                  <CopyableField
                    label="Key Fingerprint"
                    value={result.signer_info.fingerprint}
                    onCopy={() => copyToClipboard(result.signer_info.fingerprint, 'fingerprint')}
                    copied={copied === 'fingerprint'}
                  />
                )}

                {result.signer_info.timestamp && (
                  <div className="p-3 bg-gray-50 rounded-lg">
                    <div className="text-xs text-gray-600 mb-1">Signature Created</div>
                    <div className="text-sm font-medium text-gray-900">{result.signer_info.timestamp}</div>
                  </div>
                )}

                {result.signer_info.validity && (
                  <div className="p-3 bg-gray-50 rounded-lg">
                    <div className="text-xs text-gray-600 mb-1">Validity Status</div>
                    <div className="text-sm font-medium text-gray-900">{result.signer_info.validity}</div>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* File Hash */}
          <div className="card">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">File Hash</h2>
            <CopyableField
              label="SHA-256"
              value={result.file_hash}
              onCopy={() => copyToClipboard(result.file_hash, 'hash')}
              copied={copied === 'hash'}
            />
          </div>

          {/* Warnings */}
          {result.warnings && result.warnings.length > 0 && (
            <div className="card">
              <h2 className="text-xl font-semibold text-gray-900 mb-4">Warnings</h2>
              <div className="space-y-2">
                {result.warnings.map((warning, index) => (
                  <div
                    key={index}
                    className={`p-3 rounded-lg flex items-start ${
                      warning.level === 'error' ? 'bg-red-50 border border-red-200' :
                      warning.level === 'warning' ? 'bg-yellow-50 border border-yellow-200' :
                      'bg-blue-50 border border-blue-200'
                    }`}
                  >
                    <AlertCircle className={`h-5 w-5 mr-2 flex-shrink-0 mt-0.5 ${
                      warning.level === 'error' ? 'text-red-600' :
                      warning.level === 'warning' ? 'text-yellow-600' :
                      'text-blue-600'
                    }`} />
                    <div className={`text-sm ${
                      warning.level === 'error' ? 'text-red-800' :
                      warning.level === 'warning' ? 'text-yellow-800' :
                      'text-blue-800'
                    }`}>
                      {warning.message}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Badge */}
          {result.badge_url && (
            <div className="card">
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
      <div className="mt-8">
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 className="text-sm font-semibold text-blue-900 mb-2 flex items-center">
            <Info className="h-4 w-4 mr-2" />
            About Digital Signatures
          </h3>
          <p className="text-sm text-blue-800 mb-2">
            Digital signatures use public-key cryptography to verify that a file hasn't been tampered with and
            comes from the claimed source. PGP/GPG signatures are commonly used for software distributions,
            documents, and communications.
          </p>
          <p className="text-sm text-blue-800">
            To verify a signature, you need three things: the original file, the signature file (usually .sig or .asc),
            and the signer's public key. The verification process confirms that the file matches the signature and
            was signed with the private key corresponding to the public key.
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
