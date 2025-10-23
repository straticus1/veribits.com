/**
 * PGP/GPG Key Validation Utilities
 * Requires: openpgp library (install with: npm install openpgp)
 */

/**
 * Parse PGP public key and extract metadata
 * @param {string} armoredKey - ASCII-armored PGP public key
 * @returns {Promise<object>} Key metadata
 */
export async function parsePGPKey(armoredKey) {
  try {
    // Dynamic import to handle if openpgp is not installed
    const openpgp = await import('openpgp')

    // Read the armored key
    const publicKey = await openpgp.readKey({ armoredKey })

    // Extract primary key
    const primaryKey = publicKey.getKeys()[0]
    const keyPacket = primaryKey.keyPacket

    // Get user IDs
    const userIds = publicKey.users.map(user => ({
      name: user.userID?.name || 'Unknown',
      email: user.userID?.email || '',
      comment: user.userID?.comment || '',
      userID: user.userID?.userID || '',
      isPrimary: user.isPrimaryUser || false,
      verified: user.selfCertifications && user.selfCertifications.length > 0
    }))

    // Get subkeys
    const subkeys = publicKey.subkeys.map(subkey => {
      const subkeyPacket = subkey.keyPacket
      return {
        keyID: subkeyPacket.getKeyID().toHex().toUpperCase(),
        fingerprint: subkeyPacket.getFingerprint().toUpperCase(),
        algorithm: getAlgorithmName(subkeyPacket.algorithm),
        bits: subkeyPacket.getBitSize(),
        created: subkeyPacket.created,
        expires: getExpirationDate(subkey),
        capabilities: getKeyCapabilities(subkey)
      }
    })

    // Get key capabilities
    const capabilities = getKeyCapabilities(primaryKey)

    // Get expiration
    const expirationTime = await publicKey.getExpirationTime()

    return {
      valid: true,
      version: keyPacket.version,
      keyID: keyPacket.getKeyID().toHex().toUpperCase(),
      fingerprint: keyPacket.getFingerprint().toUpperCase(),
      algorithm: getAlgorithmName(keyPacket.algorithm),
      bits: keyPacket.getBitSize(),
      created: keyPacket.created,
      expires: expirationTime,
      isExpired: expirationTime && new Date() > expirationTime,
      userIds,
      subkeys,
      capabilities,
      curve: keyPacket.curve || null,
      keyStrength: assessKeyStrength(keyPacket),
      warnings: generateWarnings(keyPacket, expirationTime, capabilities)
    }
  } catch (error) {
    throw new Error(`Failed to parse PGP key: ${error.message}`)
  }
}

/**
 * Get algorithm name from algorithm ID
 * @param {string} algorithm - Algorithm identifier
 * @returns {string} Algorithm name
 */
function getAlgorithmName(algorithm) {
  const algorithms = {
    rsa_encrypt_sign: 'RSA (Encrypt or Sign)',
    rsa_encrypt: 'RSA (Encrypt only)',
    rsa_sign: 'RSA (Sign only)',
    elgamal: 'ElGamal (Encrypt only)',
    dsa: 'DSA (Sign only)',
    ecdh: 'ECDH (Encrypt)',
    ecdsa: 'ECDSA (Sign)',
    eddsa: 'EdDSA (Sign)',
    aedh: 'AEDH',
    aedsa: 'AEDSA'
  }

  return algorithms[algorithm] || algorithm || 'Unknown'
}

/**
 * Get key capabilities
 * @param {object} key - Key object
 * @returns {object} Capabilities
 */
function getKeyCapabilities(key) {
  const capabilities = {
    certify: false,
    sign: false,
    encrypt: false,
    authenticate: false
  }

  try {
    // Try to get capabilities from key flags
    if (key.getKeyFlags) {
      const flags = key.getKeyFlags()
      if (flags) {
        capabilities.certify = flags.certifyKeys || false
        capabilities.sign = flags.signData || false
        capabilities.encrypt = flags.encryptCommunication || flags.encryptStorage || false
        capabilities.authenticate = flags.authenticate || false
      }
    }

    // Fallback: infer from algorithm
    if (!capabilities.sign && !capabilities.encrypt && !capabilities.certify) {
      const algo = key.keyPacket?.algorithm
      if (algo?.includes('rsa') || algo === 'rsa_encrypt_sign') {
        capabilities.sign = true
        capabilities.encrypt = true
        capabilities.certify = true
      } else if (algo?.includes('dsa') || algo?.includes('eddsa') || algo?.includes('ecdsa')) {
        capabilities.sign = true
      } else if (algo?.includes('elgamal') || algo?.includes('ecdh')) {
        capabilities.encrypt = true
      }
    }
  } catch (error) {
    console.warn('Failed to determine key capabilities:', error)
  }

  return capabilities
}

/**
 * Get expiration date for a key
 * @param {object} key - Key object
 * @returns {Date|null} Expiration date or null
 */
function getExpirationDate(key) {
  try {
    if (key.getExpirationTime) {
      return key.getExpirationTime()
    }
    return null
  } catch (error) {
    return null
  }
}

/**
 * Assess key strength
 * @param {object} keyPacket - Key packet
 * @returns {object} Strength assessment
 */
function assessKeyStrength(keyPacket) {
  const algorithm = keyPacket.algorithm
  const bits = keyPacket.getBitSize()

  let strength = 'Unknown'
  let score = 0

  if (algorithm?.includes('rsa')) {
    if (bits >= 4096) {
      strength = 'Very Strong'
      score = 100
    } else if (bits >= 2048) {
      strength = 'Strong'
      score = 80
    } else if (bits >= 1024) {
      strength = 'Weak'
      score = 40
    } else {
      strength = 'Very Weak'
      score = 20
    }
  } else if (algorithm === 'eddsa' || algorithm === 'ecdsa') {
    if (bits >= 256) {
      strength = 'Very Strong'
      score = 100
    } else if (bits >= 224) {
      strength = 'Strong'
      score = 80
    } else {
      strength = 'Weak'
      score = 50
    }
  } else if (algorithm === 'dsa') {
    if (bits >= 3072) {
      strength = 'Strong'
      score = 80
    } else if (bits >= 2048) {
      strength = 'Moderate'
      score = 60
    } else {
      strength = 'Weak'
      score = 40
    }
  }

  return { strength, score }
}

/**
 * Generate warnings for the key
 * @param {object} keyPacket - Key packet
 * @param {Date|null} expirationTime - Expiration date
 * @param {object} capabilities - Key capabilities
 * @returns {Array} List of warnings
 */
function generateWarnings(keyPacket, expirationTime, capabilities) {
  const warnings = []
  const algorithm = keyPacket.algorithm
  const bits = keyPacket.getBitSize()
  const created = keyPacket.created

  // Check key age
  const ageYears = (new Date() - created) / (1000 * 60 * 60 * 24 * 365)
  if (ageYears > 5) {
    warnings.push(`Key is ${Math.floor(ageYears)} years old - consider generating a new key`)
  }

  // Check expiration
  if (expirationTime) {
    const daysUntilExpiry = (expirationTime - new Date()) / (1000 * 60 * 60 * 24)
    if (daysUntilExpiry < 0) {
      warnings.push('Key has expired')
    } else if (daysUntilExpiry < 30) {
      warnings.push(`Key expires in ${Math.floor(daysUntilExpiry)} days`)
    }
  } else {
    warnings.push('Key has no expiration date - best practice is to set an expiration')
  }

  // Check algorithm and key size
  if (algorithm?.includes('rsa') && bits < 2048) {
    warnings.push('RSA key size less than 2048 bits is considered weak')
  }

  if (algorithm === 'dsa') {
    warnings.push('DSA is deprecated - consider using RSA or EdDSA')
  }

  if (algorithm === 'elgamal') {
    warnings.push('ElGamal is less commonly used - consider RSA or ECC')
  }

  // Check if key has no capabilities
  if (!capabilities.sign && !capabilities.encrypt && !capabilities.certify && !capabilities.authenticate) {
    warnings.push('Key capabilities could not be determined')
  }

  return warnings
}

/**
 * Validate that a string looks like a PGP key
 * @param {string} text - Text to validate
 * @returns {boolean} True if it looks like a PGP key
 */
export function looksLikePGPKey(text) {
  return text.includes('-----BEGIN PGP PUBLIC KEY BLOCK-----') &&
         text.includes('-----END PGP PUBLIC KEY BLOCK-----')
}

/**
 * Extract PGP key from text (if embedded)
 * @param {string} text - Text that may contain a PGP key
 * @returns {string|null} Extracted PGP key or null
 */
export function extractPGPKey(text) {
  const startMarker = '-----BEGIN PGP PUBLIC KEY BLOCK-----'
  const endMarker = '-----END PGP PUBLIC KEY BLOCK-----'

  const startIdx = text.indexOf(startMarker)
  const endIdx = text.indexOf(endMarker)

  if (startIdx === -1 || endIdx === -1) {
    return null
  }

  return text.substring(startIdx, endIdx + endMarker.length)
}

/**
 * Format fingerprint with spaces for readability
 * @param {string} fingerprint - Raw fingerprint
 * @returns {string} Formatted fingerprint
 */
export function formatFingerprint(fingerprint) {
  if (!fingerprint) return ''

  // Remove existing spaces
  const clean = fingerprint.replace(/\s/g, '')

  // Add space every 4 characters
  return clean.match(/.{1,4}/g)?.join(' ') || clean
}

/**
 * Get short key ID from full key ID
 * @param {string} keyID - Full key ID
 * @returns {string} Short key ID (last 8 characters)
 */
export function getShortKeyID(keyID) {
  if (!keyID) return ''
  return keyID.slice(-8)
}

/**
 * Format date for display
 * @param {Date} date - Date object
 * @returns {string} Formatted date string
 */
export function formatKeyDate(date) {
  if (!date) return 'Never'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
