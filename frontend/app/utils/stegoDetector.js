/**
 * Steganography Detection Utilities
 * Analyzes images for hidden data using various detection techniques
 */

/**
 * Analyze image for potential steganography
 * @param {File} imageFile - Image file to analyze
 * @returns {Promise<object>} Analysis results
 */
export async function analyzeImage(imageFile) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()

    reader.onload = async (e) => {
      try {
        const img = new Image()
        img.onload = async () => {
          const canvas = document.createElement('canvas')
          canvas.width = img.width
          canvas.height = img.height
          const ctx = canvas.getContext('2d')
          ctx.drawImage(img, 0, 0)

          const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height)
          const pixels = imageData.data

          // Perform various analyses
          const results = {
            fileInfo: await getFileInfo(imageFile),
            lsbAnalysis: analyzeLSB(pixels, canvas.width, canvas.height),
            statisticalAnalysis: analyzeStatistics(pixels),
            entropyAnalysis: analyzeEntropy(pixels),
            colorAnalysis: analyzeColors(pixels),
            metadata: await extractMetadata(imageFile),
            suspicionScore: 0,
            indicators: [],
            techniques: []
          }

          // Calculate suspicion score
          results.suspicionScore = calculateSuspicionScore(results)
          results.indicators = generateIndicators(results)
          results.techniques = detectTechniques(results)

          resolve(results)
        }

        img.onerror = () => reject(new Error('Failed to load image'))
        img.src = e.target.result
      } catch (error) {
        reject(error)
      }
    }

    reader.onerror = () => reject(new Error('Failed to read file'))
    reader.readAsDataURL(imageFile)
  })
}

/**
 * Get file information
 * @param {File} file - Image file
 * @returns {Promise<object>} File info
 */
async function getFileInfo(file) {
  return {
    name: file.name,
    size: file.size,
    type: file.type,
    lastModified: new Date(file.lastModified)
  }
}

/**
 * Analyze Least Significant Bits (LSB)
 * @param {Uint8ClampedArray} pixels - Pixel data
 * @param {number} width - Image width
 * @param {number} height - Image height
 * @returns {object} LSB analysis results
 */
function analyzeLSB(pixels, width, height) {
  const lsbCounts = { r: 0, g: 0, b: 0 }
  const totalPixels = width * height

  for (let i = 0; i < pixels.length; i += 4) {
    // Count LSBs that are set
    if (pixels[i] & 1) lsbCounts.r++         // Red channel
    if (pixels[i + 1] & 1) lsbCounts.g++     // Green channel
    if (pixels[i + 2] & 1) lsbCounts.b++     // Blue channel
  }

  const rRatio = lsbCounts.r / totalPixels
  const gRatio = lsbCounts.g / totalPixels
  const bRatio = lsbCounts.b / totalPixels

  // In natural images, LSB distribution should be close to 50%
  // Significant deviation may indicate LSB steganography
  const deviationThreshold = 0.05 // 5% deviation from 50%
  const suspicious =
    Math.abs(rRatio - 0.5) > deviationThreshold ||
    Math.abs(gRatio - 0.5) > deviationThreshold ||
    Math.abs(bRatio - 0.5) > deviationThreshold

  return {
    redLSB: (rRatio * 100).toFixed(2) + '%',
    greenLSB: (gRatio * 100).toFixed(2) + '%',
    blueLSB: (bRatio * 100).toFixed(2) + '%',
    suspicious,
    deviation: {
      red: Math.abs(rRatio - 0.5),
      green: Math.abs(gRatio - 0.5),
      blue: Math.abs(bRatio - 0.5)
    }
  }
}

/**
 * Perform statistical analysis on pixel data
 * @param {Uint8ClampedArray} pixels - Pixel data
 * @returns {object} Statistical analysis results
 */
function analyzeStatistics(pixels) {
  const channelData = { r: [], g: [], b: [] }

  for (let i = 0; i < pixels.length; i += 4) {
    channelData.r.push(pixels[i])
    channelData.g.push(pixels[i + 1])
    channelData.b.push(pixels[i + 2])
  }

  const stats = {
    red: calculateChannelStats(channelData.r),
    green: calculateChannelStats(channelData.g),
    blue: calculateChannelStats(channelData.b)
  }

  // Check for unusual patterns
  const avgStdDev = (stats.red.stdDev + stats.green.stdDev + stats.blue.stdDev) / 3
  const lowVariance = avgStdDev < 20 // Very uniform images might be suspicious

  return {
    ...stats,
    averageStdDev: avgStdDev.toFixed(2),
    lowVariance,
    suspicious: lowVariance
  }
}

/**
 * Calculate statistics for a color channel
 * @param {Array} data - Channel data
 * @returns {object} Channel statistics
 */
function calculateChannelStats(data) {
  const mean = data.reduce((a, b) => a + b, 0) / data.length
  const variance = data.reduce((sum, val) => sum + Math.pow(val - mean, 2), 0) / data.length
  const stdDev = Math.sqrt(variance)

  return {
    mean: mean.toFixed(2),
    stdDev: stdDev.toFixed(2),
    min: Math.min(...data),
    max: Math.max(...data)
  }
}

/**
 * Analyze entropy of image data
 * @param {Uint8ClampedArray} pixels - Pixel data
 * @returns {object} Entropy analysis
 */
function analyzeEntropy(pixels) {
  const histogram = new Array(256).fill(0)

  // Count frequency of each byte value (using red channel as sample)
  for (let i = 0; i < pixels.length; i += 4) {
    histogram[pixels[i]]++
  }

  // Calculate Shannon entropy
  let entropy = 0
  const total = pixels.length / 4

  for (let count of histogram) {
    if (count > 0) {
      const probability = count / total
      entropy -= probability * Math.log2(probability)
    }
  }

  // High entropy (close to 8) may indicate encrypted or compressed data
  // Low entropy (close to 0) indicates very uniform data
  const highEntropy = entropy > 7.5
  const normalRange = entropy >= 5 && entropy <= 7.5

  return {
    entropy: entropy.toFixed(3),
    maxEntropy: '8.000',
    percentage: ((entropy / 8) * 100).toFixed(1) + '%',
    highEntropy,
    normalRange,
    interpretation: highEntropy
      ? 'High (may indicate compressed/encrypted data)'
      : normalRange
      ? 'Normal range'
      : 'Low (uniform data)'
  }
}

/**
 * Analyze color distribution
 * @param {Uint8ClampedArray} pixels - Pixel data
 * @returns {object} Color analysis
 */
function analyzeColors(pixels) {
  let uniqueColors = new Set()
  const pairCounts = new Map()

  for (let i = 0; i < pixels.length; i += 4) {
    const color = `${pixels[i]},${pixels[i + 1]},${pixels[i + 2]}`
    uniqueColors.add(color)

    // Check for pairs of values (PoVs) - common in LSB steganography
    const pair = Math.floor(pixels[i] / 2)
    pairCounts.set(pair, (pairCounts.get(pair) || 0) + 1)
  }

  // Calculate Pairs of Values analysis
  let suspiciousPairs = 0
  pairCounts.forEach((count, pair) => {
    const expected = (pixels.length / 4) / 128 // Expected if uniform
    const deviation = Math.abs(count - expected) / expected
    if (deviation > 0.3) suspiciousPairs++
  })

  const totalPixels = pixels.length / 4
  const colorRatio = uniqueColors.size / totalPixels

  return {
    uniqueColors: uniqueColors.size,
    totalPixels,
    ratio: (colorRatio * 100).toFixed(2) + '%',
    suspiciousPairs,
    highDiversity: colorRatio > 0.5
  }
}

/**
 * Extract metadata from image file
 * @param {File} file - Image file
 * @returns {Promise<object>} Metadata
 */
async function extractMetadata(file) {
  // Basic metadata extraction
  // For advanced EXIF parsing, would need exif-js library
  return new Promise((resolve) => {
    const reader = new FileReader()

    reader.onload = (e) => {
      const arr = new Uint8Array(e.target.result)
      const metadata = {
        hasExif: false,
        hasPngChunks: false,
        hasJpegComment: false,
        suspiciousMarkers: []
      }

      // Check for EXIF (JPEG)
      if (file.type === 'image/jpeg') {
        const view = new DataView(arr.buffer)
        if (view.getUint16(0) === 0xFFD8) { // JPEG SOI marker
          metadata.hasExif = checkForExif(arr)
          metadata.hasJpegComment = checkForJpegComment(arr)
        }
      }

      // Check for PNG chunks
      if (file.type === 'image/png') {
        metadata.hasPngChunks = checkPngChunks(arr)
      }

      resolve(metadata)
    }

    reader.readAsArrayBuffer(file)
  })
}

/**
 * Check for EXIF data in JPEG
 * @param {Uint8Array} data - Image data
 * @returns {boolean} Has EXIF
 */
function checkForExif(data) {
  // Look for EXIF marker (0xFFE1) followed by "Exif"
  for (let i = 0; i < data.length - 6; i++) {
    if (data[i] === 0xFF && data[i + 1] === 0xE1) {
      const marker = String.fromCharCode(data[i + 4], data[i + 5], data[i + 6], data[i + 7])
      if (marker === 'Exif') return true
    }
  }
  return false
}

/**
 * Check for JPEG comments
 * @param {Uint8Array} data - Image data
 * @returns {boolean} Has comments
 */
function checkForJpegComment(data) {
  // Look for comment marker (0xFFFE)
  for (let i = 0; i < data.length - 2; i++) {
    if (data[i] === 0xFF && data[i + 1] === 0xFE) {
      return true
    }
  }
  return false
}

/**
 * Check PNG chunks
 * @param {Uint8Array} data - Image data
 * @returns {boolean} Has custom chunks
 */
function checkPngChunks(data) {
  // PNG signature: 89 50 4E 47 0D 0A 1A 0A
  if (data[0] !== 0x89 || data[1] !== 0x50) return false

  let hasCustomChunks = false
  let offset = 8 // Skip PNG signature

  while (offset < data.length - 8) {
    const length = new DataView(data.buffer).getUint32(offset)
    const type = String.fromCharCode(data[offset + 4], data[offset + 5], data[offset + 6], data[offset + 7])

    // Check for custom/private chunks (lowercase first letter)
    if (type[0] === type[0].toLowerCase() && type !== 'tEXt' && type !== 'zTXt' && type !== 'iTXt') {
      hasCustomChunks = true
    }

    offset += 12 + length // length + type + data + CRC
  }

  return hasCustomChunks
}

/**
 * Calculate overall suspicion score
 * @param {object} results - Analysis results
 * @returns {number} Suspicion score (0-100)
 */
function calculateSuspicionScore(results) {
  let score = 0

  // LSB analysis (0-30 points)
  if (results.lsbAnalysis.suspicious) {
    const maxDeviation = Math.max(
      results.lsbAnalysis.deviation.red,
      results.lsbAnalysis.deviation.green,
      results.lsbAnalysis.deviation.blue
    )
    score += Math.min(30, maxDeviation * 300)
  }

  // Entropy analysis (0-25 points)
  if (results.entropyAnalysis.highEntropy) {
    score += 25
  }

  // Statistical analysis (0-15 points)
  if (results.statisticalAnalysis.suspicious) {
    score += 15
  }

  // Color analysis (0-15 points)
  if (results.colorAnalysis.suspiciousPairs > 10) {
    score += Math.min(15, results.colorAnalysis.suspiciousPairs)
  }

  // Metadata (0-15 points)
  if (results.metadata.hasPngChunks || results.metadata.hasJpegComment) {
    score += 15
  }

  return Math.min(100, Math.round(score))
}

/**
 * Generate list of indicators found
 * @param {object} results - Analysis results
 * @returns {Array} List of indicators
 */
function generateIndicators(results) {
  const indicators = []

  if (results.lsbAnalysis.suspicious) {
    indicators.push({
      type: 'LSB Pattern',
      severity: 'medium',
      description: 'Unusual least significant bit distribution detected'
    })
  }

  if (results.entropyAnalysis.highEntropy) {
    indicators.push({
      type: 'High Entropy',
      severity: 'medium',
      description: 'Image data has high entropy, may contain compressed/encrypted data'
    })
  }

  if (results.statisticalAnalysis.suspicious) {
    indicators.push({
      type: 'Low Variance',
      severity: 'low',
      description: 'Unusually uniform pixel distribution'
    })
  }

  if (results.metadata.hasPngChunks) {
    indicators.push({
      type: 'Custom PNG Chunks',
      severity: 'high',
      description: 'Private/custom PNG chunks detected'
    })
  }

  if (results.metadata.hasJpegComment) {
    indicators.push({
      type: 'JPEG Comment',
      severity: 'low',
      description: 'JPEG comment marker found'
    })
  }

  if (results.colorAnalysis.suspiciousPairs > 10) {
    indicators.push({
      type: 'Pairs of Values',
      severity: 'medium',
      description: 'Suspicious color pair distribution'
    })
  }

  return indicators
}

/**
 * Detect likely steganography techniques
 * @param {object} results - Analysis results
 * @returns {Array} Detected techniques
 */
function detectTechniques(results) {
  const techniques = []

  if (results.lsbAnalysis.suspicious) {
    techniques.push('LSB (Least Significant Bit) Replacement')
  }

  if (results.entropyAnalysis.highEntropy && results.lsbAnalysis.suspicious) {
    techniques.push('Encrypted LSB Steganography')
  }

  if (results.metadata.hasPngChunks) {
    techniques.push('PNG Chunk Embedding')
  }

  if (results.metadata.hasJpegComment) {
    techniques.push('JPEG Comment Field')
  }

  if (results.metadata.hasExif) {
    techniques.push('Possible EXIF Metadata Embedding')
  }

  return techniques
}

/**
 * Get risk level from suspicion score
 * @param {number} score - Suspicion score
 * @returns {object} Risk level info
 */
export function getRiskLevel(score) {
  if (score >= 70) {
    return { level: 'High', color: 'red', description: 'Strong indicators of hidden data' }
  } else if (score >= 40) {
    return { level: 'Medium', color: 'yellow', description: 'Moderate indicators present' }
  } else if (score >= 20) {
    return { level: 'Low', color: 'blue', description: 'Minor anomalies detected' }
  } else {
    return { level: 'Minimal', color: 'green', description: 'No significant indicators' }
  }
}
