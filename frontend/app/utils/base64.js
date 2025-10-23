/**
 * Base64 encoding and decoding utilities
 */

/**
 * Encode a string to Base64
 * @param {string} str - The string to encode
 * @returns {string} Base64 encoded string
 */
export function encodeBase64(str) {
  try {
    // Handle Unicode characters properly
    const utf8Bytes = new TextEncoder().encode(str)
    const binaryString = Array.from(utf8Bytes, byte => String.fromCharCode(byte)).join('')
    return btoa(binaryString)
  } catch (error) {
    throw new Error(`Base64 encoding failed: ${error.message}`)
  }
}

/**
 * Decode a Base64 string
 * @param {string} base64 - The Base64 string to decode
 * @returns {string} Decoded string
 */
export function decodeBase64(base64) {
  try {
    const binaryString = atob(base64)
    const bytes = Uint8Array.from(binaryString, char => char.charCodeAt(0))
    return new TextDecoder().decode(bytes)
  } catch (error) {
    throw new Error(`Base64 decoding failed: ${error.message}`)
  }
}

/**
 * Encode a file to Base64
 * @param {File} file - The file to encode
 * @returns {Promise<string>} Base64 encoded file
 */
export function encodeFileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => {
      const base64 = reader.result.split(',')[1] // Remove data URL prefix
      resolve(base64)
    }
    reader.onerror = () => reject(new Error('File reading failed'))
    reader.readAsDataURL(file)
  })
}

/**
 * Validate if a string is valid Base64
 * @param {string} str - The string to validate
 * @returns {boolean} True if valid Base64
 */
export function isValidBase64(str) {
  try {
    return btoa(atob(str)) === str
  } catch (error) {
    return false
  }
}
