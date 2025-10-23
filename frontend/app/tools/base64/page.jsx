'use client'

import { useState } from 'react'
import { encodeBase64, decodeBase64, encodeFileToBase64, isValidBase64 } from '@/app/utils/base64'
import { Copy, FileText, AlertCircle, CheckCircle } from 'lucide-react'

export default function Base64Tool() {
  const [mode, setMode] = useState('encode')
  const [input, setInput] = useState('')
  const [output, setOutput] = useState('')
  const [error, setError] = useState('')
  const [copied, setCopied] = useState(false)

  const handleEncode = () => {
    setError('')
    try {
      const encoded = encodeBase64(input)
      setOutput(encoded)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleDecode = () => {
    setError('')
    try {
      if (!isValidBase64(input)) {
        setError('Invalid Base64 string')
        return
      }
      const decoded = decodeBase64(input)
      setOutput(decoded)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleFileEncode = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    setError('')
    try {
      const encoded = await encodeFileToBase64(file)
      setOutput(encoded)
      setInput(`File: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(output)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch (err) {
      setError('Failed to copy to clipboard')
    }
  }

  const handleProcess = () => {
    if (mode === 'encode') {
      handleEncode()
    } else {
      handleDecode()
    }
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Base64 Encoder/Decoder</h1>
        <p className="text-gray-600 mt-2">
          Encode and decode text or files using Base64 encoding.
        </p>
      </div>

      <div className="card max-w-4xl">
        {/* Mode Selection */}
        <div className="flex space-x-4 mb-6">
          <button
            onClick={() => {
              setMode('encode')
              setInput('')
              setOutput('')
              setError('')
            }}
            className={`px-4 py-2 rounded-lg font-medium transition-colors ${
              mode === 'encode'
                ? 'bg-primary-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Encode
          </button>
          <button
            onClick={() => {
              setMode('decode')
              setInput('')
              setOutput('')
              setError('')
            }}
            className={`px-4 py-2 rounded-lg font-medium transition-colors ${
              mode === 'decode'
                ? 'bg-primary-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Decode
          </button>
        </div>

        {/* Input Section */}
        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            {mode === 'encode' ? 'Text to Encode' : 'Base64 to Decode'}
          </label>
          <textarea
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder={
              mode === 'encode'
                ? 'Enter text to encode...'
                : 'Enter Base64 string to decode...'
            }
            className="w-full h-40 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent font-mono text-sm"
          />
        </div>

        {/* File Upload (Encode only) */}
        {mode === 'encode' && (
          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Or Upload a File
            </label>
            <div className="flex items-center space-x-4">
              <label className="flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                <FileText className="h-5 w-5 text-gray-400 mr-2" />
                <span className="text-sm text-gray-700">Choose File</span>
                <input
                  type="file"
                  onChange={handleFileEncode}
                  className="hidden"
                />
              </label>
            </div>
          </div>
        )}

        {/* Process Button */}
        <button
          onClick={handleProcess}
          disabled={!input}
          className="btn-primary w-full mb-6 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {mode === 'encode' ? 'Encode to Base64' : 'Decode from Base64'}
        </button>

        {/* Error Message */}
        {error && (
          <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start">
            <AlertCircle className="h-5 w-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" />
            <div className="text-sm text-red-800">{error}</div>
          </div>
        )}

        {/* Output Section */}
        {output && (
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="block text-sm font-medium text-gray-700">
                {mode === 'encode' ? 'Base64 Output' : 'Decoded Text'}
              </label>
              <button
                onClick={handleCopy}
                className="flex items-center text-sm text-primary-600 hover:text-primary-700"
              >
                {copied ? (
                  <>
                    <CheckCircle className="h-4 w-4 mr-1" />
                    Copied!
                  </>
                ) : (
                  <>
                    <Copy className="h-4 w-4 mr-1" />
                    Copy
                  </>
                )}
              </button>
            </div>
            <textarea
              value={output}
              readOnly
              className="w-full h-40 px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 font-mono text-sm"
            />
            <div className="mt-2 text-sm text-gray-500">
              {mode === 'encode' ? 'Encoded' : 'Decoded'} length: {output.length} characters
            </div>
          </div>
        )}
      </div>

      {/* Info Section */}
      <div className="mt-8 max-w-4xl">
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 className="text-sm font-semibold text-blue-900 mb-2">About Base64 Encoding</h3>
          <p className="text-sm text-blue-800">
            Base64 is a binary-to-text encoding scheme that represents binary data in ASCII string format.
            It's commonly used for encoding data in emails, URLs, and storing complex data in text-based formats.
            This tool supports Unicode characters and file uploads.
          </p>
        </div>
      </div>
    </div>
  )
}
