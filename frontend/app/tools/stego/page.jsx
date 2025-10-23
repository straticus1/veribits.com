'use client'

import { useState } from 'react'
import { analyzeImage, getRiskLevel } from '@/app/utils/stegoDetector'
import {
  Image as ImageIcon,
  Upload,
  AlertCircle,
  Shield,
  Eye,
  BarChart3,
  FileText,
  Info,
  CheckCircle,
  XCircle
} from 'lucide-react'

export default function StegoDetector() {
  const [selectedFile, setSelectedFile] = useState(null)
  const [preview, setPreview] = useState(null)
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  const handleFileSelect = (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    // Validate file type
    if (!file.type.startsWith('image/')) {
      setError('Please select a valid image file')
      return
    }

    setSelectedFile(file)
    setError('')
    setResult(null)

    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      setPreview(e.target.result)
    }
    reader.readAsDataURL(file)
  }

  const handleAnalyze = async () => {
    if (!selectedFile) {
      setError('Please select an image file first')
      return
    }

    setLoading(true)
    setError('')

    try {
      const analysis = await analyzeImage(selectedFile)
      setResult(analysis)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
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
        <h1 className="text-3xl font-bold text-gray-900">Steganography Detector</h1>
        <p className="text-gray-600 mt-2">
          Upload an image to analyze for potential hidden data using LSB analysis, entropy detection, and statistical patterns.
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
            accept="image/*"
            onChange={handleFileSelect}
            className="hidden"
            id="image-upload"
          />
          <label htmlFor="image-upload" className="cursor-pointer">
            {preview ? (
              <div className="space-y-4">
                <img
                  src={preview}
                  alt="Preview"
                  className="max-w-full max-h-96 mx-auto rounded-lg shadow-lg"
                />
                <div className="text-sm text-gray-600">
                  {selectedFile?.name} ({(selectedFile?.size / 1024).toFixed(2)} KB)
                </div>
                <button
                  type="button"
                  onClick={(e) => {
                    e.preventDefault()
                    document.getElementById('image-upload').click()
                  }}
                  className="text-primary-600 hover:text-primary-700 text-sm"
                >
                  Choose a different image
                </button>
              </div>
            ) : (
              <div className="space-y-4">
                <Upload className="h-16 w-16 mx-auto text-gray-400" />
                <div>
                  <p className="text-lg text-gray-700">Drop an image here or click to browse</p>
                  <p className="text-sm text-gray-500 mt-1">Supports PNG, JPEG, GIF, WebP</p>
                </div>
              </div>
            )}
          </label>
        </div>

        {preview && (
          <button
            onClick={handleAnalyze}
            disabled={loading}
            className="btn-primary w-full mt-6"
          >
            {loading ? (
              <>
                <div className="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                Analyzing Image...
              </>
            ) : (
              <>
                <Eye className="h-5 w-5 mr-2" />
                Analyze for Hidden Data
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
          {/* Risk Assessment */}
          <RiskAssessment score={result.suspicionScore} indicators={result.indicators} />

          {/* File Information */}
          <FileInformation fileInfo={result.fileInfo} />

          {/* LSB Analysis */}
          <LSBAnalysis data={result.lsbAnalysis} />

          {/* Entropy Analysis */}
          <EntropyAnalysis data={result.entropyAnalysis} />

          {/* Statistical Analysis */}
          <StatisticalAnalysis data={result.statisticalAnalysis} />

          {/* Color Analysis */}
          <ColorAnalysis data={result.colorAnalysis} />

          {/* Metadata Analysis */}
          <MetadataAnalysis data={result.metadata} />

          {/* Detected Techniques */}
          {result.techniques.length > 0 && <DetectedTechniques techniques={result.techniques} />}
        </div>
      )}

      {/* Info Section */}
      <div className="mt-8 max-w-4xl">
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 className="text-sm font-semibold text-blue-900 mb-2 flex items-center">
            <Info className="h-4 w-4 mr-2" />
            About Steganography Detection
          </h3>
          <p className="text-sm text-blue-800">
            Steganography is the practice of hiding data within images. This tool analyzes images using multiple techniques:
            LSB (Least Significant Bit) analysis, entropy measurement, statistical pattern detection, and metadata examination.
            A high suspicion score doesn't guarantee hidden data, but indicates anomalies worth investigating. This tool is
            for educational and security analysis purposes only.
          </p>
        </div>
      </div>
    </div>
  )
}

// Risk Assessment Component
function RiskAssessment({ score, indicators }) {
  const riskLevel = getRiskLevel(score)

  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <Shield className="h-5 w-5 mr-2 text-primary-600" />
        Risk Assessment
      </h2>

      <div className="mb-6">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm font-medium text-gray-700">Suspicion Score</span>
          <span className={`text-2xl font-bold text-${riskLevel.color}-600`}>
            {score}/100
          </span>
        </div>
        <div className="w-full bg-gray-200 rounded-full h-3">
          <div
            className={`h-3 rounded-full bg-${riskLevel.color}-600 transition-all duration-500`}
            style={{ width: `${score}%` }}
          ></div>
        </div>
        <div className="mt-2 flex items-center justify-between">
          <span className={`text-sm font-semibold text-${riskLevel.color}-700`}>
            {riskLevel.level} Risk
          </span>
          <span className="text-sm text-gray-600">{riskLevel.description}</span>
        </div>
      </div>

      {indicators.length > 0 && (
        <div>
          <h3 className="text-sm font-semibold text-gray-900 mb-3">Indicators Found ({indicators.length})</h3>
          <div className="space-y-2">
            {indicators.map((indicator, idx) => (
              <div key={idx} className="flex items-start p-3 bg-gray-50 rounded-lg">
                <AlertCircle className={`h-5 w-5 mr-3 flex-shrink-0 ${
                  indicator.severity === 'high' ? 'text-red-600' :
                  indicator.severity === 'medium' ? 'text-yellow-600' :
                  'text-blue-600'
                }`} />
                <div className="flex-1">
                  <div className="font-medium text-gray-900">{indicator.type}</div>
                  <div className="text-sm text-gray-600">{indicator.description}</div>
                </div>
                <span className={`text-xs font-semibold px-2 py-1 rounded ${
                  indicator.severity === 'high' ? 'bg-red-100 text-red-700' :
                  indicator.severity === 'medium' ? 'bg-yellow-100 text-yellow-700' :
                  'bg-blue-100 text-blue-700'
                }`}>
                  {indicator.severity.toUpperCase()}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

// File Information Component
function FileInformation({ fileInfo }) {
  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <FileText className="h-5 w-5 mr-2 text-primary-600" />
        File Information
      </h2>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <InfoField label="Filename" value={fileInfo.name} />
        <InfoField label="File Type" value={fileInfo.type} />
        <InfoField label="File Size" value={`${(fileInfo.size / 1024).toFixed(2)} KB`} />
        <InfoField label="Last Modified" value={fileInfo.lastModified.toLocaleString()} />
      </div>
    </div>
  )
}

// LSB Analysis Component
function LSBAnalysis({ data }) {
  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <BarChart3 className="h-5 w-5 mr-2 text-primary-600" />
        LSB (Least Significant Bit) Analysis
      </h2>

      <div className="mb-4">
        <div className="flex items-center mb-2">
          {data.suspicious ? (
            <XCircle className="h-5 w-5 text-yellow-600 mr-2" />
          ) : (
            <CheckCircle className="h-5 w-5 text-green-600 mr-2" />
          )}
          <span className={`font-semibold ${data.suspicious ? 'text-yellow-700' : 'text-green-700'}`}>
            {data.suspicious ? 'Suspicious LSB Pattern Detected' : 'Normal LSB Distribution'}
          </span>
        </div>
        <p className="text-sm text-gray-600">
          LSB distribution should be close to 50% in natural images. Significant deviation may indicate LSB steganography.
        </p>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="p-3 bg-red-50 rounded-lg">
          <div className="text-xs text-red-600 font-medium mb-1">Red Channel LSB</div>
          <div className="text-2xl font-bold text-red-700">{data.redLSB}</div>
          <div className="text-xs text-red-600">Deviation: {(data.deviation.red * 100).toFixed(2)}%</div>
        </div>
        <div className="p-3 bg-green-50 rounded-lg">
          <div className="text-xs text-green-600 font-medium mb-1">Green Channel LSB</div>
          <div className="text-2xl font-bold text-green-700">{data.greenLSB}</div>
          <div className="text-xs text-green-600">Deviation: {(data.deviation.green * 100).toFixed(2)}%</div>
        </div>
        <div className="p-3 bg-blue-50 rounded-lg">
          <div className="text-xs text-blue-600 font-medium mb-1">Blue Channel LSB</div>
          <div className="text-2xl font-bold text-blue-700">{data.blueLSB}</div>
          <div className="text-xs text-blue-600">Deviation: {(data.deviation.blue * 100).toFixed(2)}%</div>
        </div>
      </div>
    </div>
  )
}

// Entropy Analysis Component
function EntropyAnalysis({ data }) {
  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <BarChart3 className="h-5 w-5 mr-2 text-primary-600" />
        Entropy Analysis
      </h2>

      <div className="mb-4">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm font-medium text-gray-700">Shannon Entropy</span>
          <span className="text-2xl font-bold text-gray-900">{data.entropy}</span>
        </div>
        <div className="w-full bg-gray-200 rounded-full h-2">
          <div
            className={`h-2 rounded-full ${data.highEntropy ? 'bg-yellow-600' : 'bg-green-600'}`}
            style={{ width: data.percentage }}
          ></div>
        </div>
        <div className="mt-2 text-sm text-gray-600">
          {data.interpretation}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <InfoField label="Entropy Value" value={data.entropy} />
        <InfoField label="Maximum Possible" value={data.maxEntropy} />
      </div>
    </div>
  )
}

// Statistical Analysis Component
function StatisticalAnalysis({ data }) {
  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <BarChart3 className="h-5 w-5 mr-2 text-primary-600" />
        Statistical Analysis
      </h2>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <ChannelStats label="Red Channel" stats={data.red} />
        <ChannelStats label="Green Channel" stats={data.green} />
        <ChannelStats label="Blue Channel" stats={data.blue} />
      </div>

      <div className="mt-4 p-3 bg-gray-50 rounded-lg">
        <div className="flex items-center justify-between">
          <span className="text-sm text-gray-700">Average Standard Deviation</span>
          <span className="font-semibold text-gray-900">{data.averageStdDev}</span>
        </div>
        {data.lowVariance && (
          <div className="mt-2 text-sm text-yellow-700 flex items-center">
            <AlertCircle className="h-4 w-4 mr-1" />
            Unusually low variance detected
          </div>
        )}
      </div>
    </div>
  )
}

// Channel Stats Component
function ChannelStats({ label, stats }) {
  return (
    <div className="p-3 bg-gray-50 rounded-lg">
      <div className="text-sm font-medium text-gray-700 mb-2">{label}</div>
      <div className="space-y-1 text-xs">
        <div className="flex justify-between">
          <span className="text-gray-600">Mean:</span>
          <span className="font-mono text-gray-900">{stats.mean}</span>
        </div>
        <div className="flex justify-between">
          <span className="text-gray-600">Std Dev:</span>
          <span className="font-mono text-gray-900">{stats.stdDev}</span>
        </div>
        <div className="flex justify-between">
          <span className="text-gray-600">Range:</span>
          <span className="font-mono text-gray-900">{stats.min}-{stats.max}</span>
        </div>
      </div>
    </div>
  )
}

// Color Analysis Component
function ColorAnalysis({ data }) {
  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <ImageIcon className="h-5 w-5 mr-2 text-primary-600" />
        Color Analysis
      </h2>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <InfoField label="Unique Colors" value={data.uniqueColors.toLocaleString()} />
        <InfoField label="Total Pixels" value={data.totalPixels.toLocaleString()} />
        <InfoField label="Color Diversity" value={data.ratio} />
        <InfoField
          label="Suspicious Pairs"
          value={data.suspiciousPairs}
          warning={data.suspiciousPairs > 10}
        />
      </div>
    </div>
  )
}

// Metadata Analysis Component
function MetadataAnalysis({ data }) {
  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <FileText className="h-5 w-5 mr-2 text-primary-600" />
        Metadata Analysis
      </h2>

      <div className="space-y-2">
        <MetadataItem label="EXIF Data" present={data.hasExif} />
        <MetadataItem label="PNG Custom Chunks" present={data.hasPngChunks} warning />
        <MetadataItem label="JPEG Comment" present={data.hasJpegComment} />
      </div>
    </div>
  )
}

// Metadata Item Component
function MetadataItem({ label, present, warning }) {
  return (
    <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
      <span className="text-sm text-gray-700">{label}</span>
      {present ? (
        <div className="flex items-center">
          <CheckCircle className={`h-4 w-4 mr-1 ${warning ? 'text-yellow-600' : 'text-blue-600'}`} />
          <span className={`text-sm font-semibold ${warning ? 'text-yellow-700' : 'text-blue-700'}`}>
            Present
          </span>
        </div>
      ) : (
        <div className="flex items-center">
          <XCircle className="h-4 w-4 text-gray-400 mr-1" />
          <span className="text-sm text-gray-500">Not Found</span>
        </div>
      )}
    </div>
  )
}

// Detected Techniques Component
function DetectedTechniques({ techniques }) {
  return (
    <div className="card max-w-4xl">
      <h2 className="text-xl font-semibold text-gray-900 mb-4 flex items-center">
        <Shield className="h-5 w-5 mr-2 text-primary-600" />
        Potential Steganography Techniques
      </h2>

      <div className="space-y-2">
        {techniques.map((technique, idx) => (
          <div key={idx} className="p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-center">
            <AlertCircle className="h-5 w-5 text-yellow-600 mr-3 flex-shrink-0" />
            <span className="text-sm text-yellow-900 font-medium">{technique}</span>
          </div>
        ))}
      </div>
    </div>
  )
}

// Info Field Component
function InfoField({ label, value, warning }) {
  return (
    <div className={`p-3 rounded-lg ${warning ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50'}`}>
      <div className="text-xs text-gray-600 mb-1">{label}</div>
      <div className={`text-sm font-semibold ${warning ? 'text-yellow-900' : 'text-gray-900'}`}>
        {value}
      </div>
    </div>
  )
}
