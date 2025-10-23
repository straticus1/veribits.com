/**
 * File Signature Validation Utilities
 * Handles PGP/GPG signature verification on the frontend
 */

/**
 * Detect the type of signature file
 */
export function detectSignatureType(signatureContent) {
  if (signatureContent.includes('-----BEGIN PGP SIGNATURE-----')) {
    return {
      type: 'pgp_detached',
      name: 'PGP Detached Signature',
      description: 'Separate .sig or .asc file for verifying another file'
    }
  }

  if (signatureContent.includes('-----BEGIN PGP SIGNED MESSAGE-----')) {
    return {
      type: 'pgp_cleartext',
      name: 'PGP Cleartext Signature',
      description: 'Message with embedded signature'
    }
  }

  if (signatureContent.includes('-----BEGIN PGP MESSAGE-----')) {
    return {
      type: 'pgp_encrypted',
      name: 'PGP Encrypted Message',
      description: 'Encrypted and possibly signed message'
    }
  }

  if (/^[0-9a-fA-F\s]+$/.test(signatureContent.trim())) {
    return {
      type: 'hex_signature',
      name: 'Hexadecimal Signature',
      description: 'Raw signature in hexadecimal format'
    }
  }

  return {
    type: 'unknown',
    name: 'Unknown Signature Type',
    description: 'Unable to detect signature format'
  }
}

/**
 * Validate PGP public key format
 */
export function validatePublicKey(keyContent) {
  if (!keyContent || typeof keyContent !== 'string') {
    return { valid: false, error: 'Public key is required' }
  }

  keyContent = keyContent.trim()

  if (!keyContent.includes('-----BEGIN PGP PUBLIC KEY BLOCK-----')) {
    return { valid: false, error: 'Not a valid PGP public key (missing header)' }
  }

  if (!keyContent.includes('-----END PGP PUBLIC KEY BLOCK-----')) {
    return { valid: false, error: 'Not a valid PGP public key (missing footer)' }
  }

  // Extract base64 content
  const lines = keyContent.split('\n')
  let base64Content = ''
  let inKey = false

  for (const line of lines) {
    if (line.includes('-----BEGIN')) {
      inKey = true
      continue
    }
    if (line.includes('-----END')) {
      inKey = false
      continue
    }
    if (inKey && !line.startsWith('=') && !line.startsWith('Version:') && !line.startsWith('Comment:')) {
      base64Content += line.trim()
    }
  }

  if (base64Content.length === 0) {
    return { valid: false, error: 'No key data found' }
  }

  // Validate base64
  const base64Regex = /^[A-Za-z0-9+/=]+$/
  if (!base64Regex.test(base64Content)) {
    return { valid: false, error: 'Invalid base64 encoding in key' }
  }

  return { valid: true }
}

/**
 * Parse PGP signature headers
 */
export function parseSignatureHeaders(signatureContent) {
  const headers = {}
  const lines = signatureContent.split('\n')

  for (const line of lines) {
    if (line.startsWith('Version:')) {
      headers.version = line.substring(8).trim()
    } else if (line.startsWith('Comment:')) {
      headers.comment = line.substring(8).trim()
    } else if (line.startsWith('Hash:')) {
      headers.hash = line.substring(5).trim()
    }
  }

  return headers
}

/**
 * Format verification result for display
 */
export function formatVerificationResult(result) {
  return {
    ...result,
    statusText: result.is_valid ? 'Valid Signature' : 'Invalid Signature',
    statusColor: result.is_valid ? 'green' : 'red',
    trustLevel: getTrustLevel(result),
    warnings: generateWarnings(result)
  }
}

/**
 * Determine trust level based on verification result
 */
function getTrustLevel(result) {
  if (!result.is_valid) {
    return {
      level: 'untrusted',
      text: 'Untrusted',
      description: 'Signature could not be verified'
    }
  }

  if (result.signer_info?.validity === 'Valid') {
    return {
      level: 'trusted',
      text: 'Trusted',
      description: 'Signature is valid and key is trusted'
    }
  }

  return {
    level: 'valid',
    text: 'Valid',
    description: 'Signature is cryptographically valid but key trust not established'
  }
}

/**
 * Generate warnings based on verification details
 */
function generateWarnings(result) {
  const warnings = []

  if (result.signer_info?.validity === 'Key expired') {
    warnings.push({
      level: 'warning',
      message: 'The signing key has expired'
    })
  }

  if (result.signer_info?.validity === 'Signature expired') {
    warnings.push({
      level: 'warning',
      message: 'The signature has expired'
    })
  }

  if (result.signer_info?.validity === 'Key revoked') {
    warnings.push({
      level: 'error',
      message: 'The signing key has been revoked'
    })
  }

  if (result.details?.includes('not cryptographically verified')) {
    warnings.push({
      level: 'info',
      message: 'Signature was parsed but not cryptographically verified due to server limitations'
    })
  }

  return warnings
}

/**
 * Verify file signature via API
 */
export async function verifyFileSignature(file, signatureFile, publicKey) {
  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080'
  const token = localStorage.getItem('auth_token')

  const formData = new FormData()
  formData.append('file', file)
  formData.append('signature', signatureFile)
  formData.append('public_key', publicKey)

  const response = await fetch(`${apiUrl}/api/v1/verify/file-signature`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
    },
    body: formData
  })

  const data = await response.json()

  if (!response.ok) {
    throw new Error(data.error?.message || 'Verification failed')
  }

  return formatVerificationResult(data.data)
}

/**
 * Common signature file extensions
 */
export const SIGNATURE_EXTENSIONS = [
  '.sig',
  '.asc',
  '.pgp',
  '.gpg',
  '.sign',
  '.signature'
]

/**
 * Check if file has signature extension
 */
export function hasSignatureExtension(filename) {
  const lower = filename.toLowerCase()
  return SIGNATURE_EXTENSIONS.some(ext => lower.endsWith(ext))
}

/**
 * Suggest signature filename for a file
 */
export function suggestSignatureFilename(filename) {
  return `${filename}.sig`
}
