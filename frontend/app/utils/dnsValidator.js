/**
 * DNS Validation Utilities
 * Note: Client-side DNS lookups require a backend API or DNS-over-HTTPS service
 */

/**
 * Validate domain name format
 * @param {string} domain - Domain name to validate
 * @returns {boolean} True if valid
 */
export function isValidDomain(domain) {
  const domainRegex = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i
  return domainRegex.test(domain)
}

/**
 * Validate IP address format (IPv4 or IPv6)
 * @param {string} ip - IP address to validate
 * @returns {boolean} True if valid
 */
export function isValidIP(ip) {
  // IPv4
  const ipv4Regex = /^(\d{1,3}\.){3}\d{1,3}$/
  if (ipv4Regex.test(ip)) {
    const parts = ip.split('.').map(Number)
    return parts.every(part => part >= 0 && part <= 255)
  }

  // IPv6
  const ipv6Regex = /^([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}$/
  return ipv6Regex.test(ip)
}

/**
 * DNS record types supported
 */
export const DNS_RECORD_TYPES = {
  A: { name: 'A', description: 'IPv4 Address' },
  AAAA: { name: 'AAAA', description: 'IPv6 Address' },
  MX: { name: 'MX', description: 'Mail Exchange' },
  NS: { name: 'NS', description: 'Name Server' },
  TXT: { name: 'TXT', description: 'Text Record' },
  CNAME: { name: 'CNAME', description: 'Canonical Name' },
  PTR: { name: 'PTR', description: 'Pointer Record' },
  SOA: { name: 'SOA', description: 'Start of Authority' },
  SRV: { name: 'SRV', description: 'Service Record' },
  CAA: { name: 'CAA', description: 'Certification Authority Authorization' },
  DNSKEY: { name: 'DNSKEY', description: 'DNS Public Key' },
  DS: { name: 'DS', description: 'Delegation Signer' }
}

/**
 * Perform DNS lookup using DNS-over-HTTPS (Cloudflare)
 * @param {string} domain - Domain to lookup
 * @param {string} type - DNS record type
 * @returns {Promise<object>} DNS lookup result
 */
export async function dnsLookup(domain, type = 'A') {
  const url = `https://cloudflare-dns.com/dns-query?name=${encodeURIComponent(domain)}&type=${type}`

  try {
    const response = await fetch(url, {
      headers: {
        'Accept': 'application/dns-json'
      }
    })

    if (!response.ok) {
      throw new Error(`DNS lookup failed: ${response.statusText}`)
    }

    return await response.json()
  } catch (error) {
    throw new Error(`DNS lookup error: ${error.message}`)
  }
}

/**
 * Parse SPF record
 * @param {string} spfRecord - SPF record value
 * @returns {object} Parsed SPF information
 */
export function parseSPF(spfRecord) {
  if (!spfRecord || !spfRecord.startsWith('v=spf1')) {
    return { valid: false, error: 'Invalid SPF record format' }
  }

  const parts = spfRecord.split(/\s+/)
  const mechanisms = []
  const modifiers = {}
  let allMechanism = null

  for (const part of parts.slice(1)) {
    if (part.startsWith('redirect=') || part.startsWith('exp=')) {
      const [key, value] = part.split('=')
      modifiers[key] = value
    } else if (part.startsWith('all') || part.startsWith('+all') || part.startsWith('-all') || part.startsWith('~all') || part.startsWith('?all')) {
      allMechanism = part
    } else {
      mechanisms.push(part)
    }
  }

  const strictness = allMechanism === '-all' ? 'Strict (Hard Fail)' :
                     allMechanism === '~all' ? 'Moderate (Soft Fail)' :
                     allMechanism === '+all' ? 'Permissive (Pass)' :
                     allMechanism === '?all' ? 'Neutral' : 'Unknown'

  return {
    valid: true,
    version: 'SPF v1',
    mechanisms,
    modifiers,
    allMechanism,
    strictness,
    warnings: checkSPFWarnings(spfRecord, mechanisms)
  }
}

/**
 * Check SPF record for common issues
 * @param {string} spfRecord - SPF record
 * @param {Array} mechanisms - Parsed mechanisms
 * @returns {Array} List of warnings
 */
function checkSPFWarnings(spfRecord, mechanisms) {
  const warnings = []

  // Check for too many DNS lookups (10 lookup limit)
  const lookupMechanisms = mechanisms.filter(m =>
    m.startsWith('include:') || m.startsWith('a:') || m.startsWith('mx:') || m.startsWith('exists:')
  )
  if (lookupMechanisms.length > 10) {
    warnings.push('Exceeds 10 DNS lookup limit (may cause validation failures)')
  }

  // Check record length (512 character UDP limit)
  if (spfRecord.length > 450) {
    warnings.push('Record approaching 512 character UDP limit')
  }

  // Check for deprecated mechanisms
  if (spfRecord.includes('ptr:')) {
    warnings.push('PTR mechanism is deprecated and should be avoided')
  }

  return warnings
}

/**
 * Parse DMARC record
 * @param {string} dmarcRecord - DMARC record value
 * @returns {object} Parsed DMARC information
 */
export function parseDMARC(dmarcRecord) {
  if (!dmarcRecord || !dmarcRecord.startsWith('v=DMARC1')) {
    return { valid: false, error: 'Invalid DMARC record format' }
  }

  const tags = {}
  const parts = dmarcRecord.split(';').map(p => p.trim()).filter(p => p)

  for (const part of parts) {
    const [key, value] = part.split('=').map(s => s.trim())
    if (key && value) {
      tags[key] = value
    }
  }

  const policy = tags.p || 'none'
  const subdomainPolicy = tags.sp || tags.p || 'none'
  const percentage = tags.pct || '100'
  const alignment = {
    dkim: tags.adkim || 'r',
    spf: tags.aspf || 'r'
  }

  return {
    valid: true,
    version: 'DMARC v1',
    policy: policy,
    subdomainPolicy: subdomainPolicy,
    percentage: percentage + '%',
    alignment,
    reportingEmails: {
      aggregate: tags.rua || 'Not configured',
      forensic: tags.ruf || 'Not configured'
    },
    tags,
    warnings: checkDMARCWarnings(tags)
  }
}

/**
 * Check DMARC record for common issues
 * @param {object} tags - Parsed DMARC tags
 * @returns {Array} List of warnings
 */
function checkDMARCWarnings(tags) {
  const warnings = []

  if (!tags.rua && !tags.ruf) {
    warnings.push('No reporting email addresses configured')
  }

  if (tags.p === 'none') {
    warnings.push('Policy set to "none" - emails will not be rejected or quarantined')
  }

  if (tags.pct && parseInt(tags.pct) < 100) {
    warnings.push(`Only ${tags.pct}% of emails are subject to DMARC policy`)
  }

  if (!tags.sp) {
    warnings.push('No subdomain policy specified (will inherit main domain policy)')
  }

  return warnings
}

/**
 * Parse DKIM selector record
 * @param {string} dkimRecord - DKIM record value
 * @returns {object} Parsed DKIM information
 */
export function parseDKIM(dkimRecord) {
  if (!dkimRecord || !dkimRecord.includes('p=')) {
    return { valid: false, error: 'Invalid DKIM record format' }
  }

  const tags = {}
  const parts = dkimRecord.split(';').map(p => p.trim()).filter(p => p)

  for (const part of parts) {
    const [key, value] = part.split('=').map(s => s.trim())
    if (key && value !== undefined) {
      tags[key] = value
    }
  }

  const publicKey = tags.p || ''
  const keyType = tags.k || 'rsa'
  const version = tags.v || 'DKIM1'

  return {
    valid: true,
    version,
    keyType,
    publicKey: publicKey.substring(0, 50) + (publicKey.length > 50 ? '...' : ''),
    publicKeyLength: publicKey.length,
    flags: tags.t || 'No flags',
    serviceType: tags.s || 'email',
    notes: tags.n || 'None',
    tags,
    warnings: checkDKIMWarnings(tags, publicKey)
  }
}

/**
 * Check DKIM record for common issues
 * @param {object} tags - Parsed DKIM tags
 * @param {string} publicKey - Public key value
 * @returns {Array} List of warnings
 */
function checkDKIMWarnings(tags, publicKey) {
  const warnings = []

  if (!publicKey) {
    warnings.push('Public key is missing or empty')
  }

  if (publicKey && publicKey.length < 200) {
    warnings.push('Public key seems short - verify it is complete')
  }

  if (tags.t && tags.t.includes('y')) {
    warnings.push('Testing mode enabled (t=y) - should be removed in production')
  }

  if (tags.k && tags.k !== 'rsa' && tags.k !== 'ed25519') {
    warnings.push(`Unusual key type: ${tags.k}`)
  }

  return warnings
}

/**
 * Get DKIM selector for a domain (common selectors to try)
 * @returns {Array} Common DKIM selectors
 */
export function getCommonDKIMSelectors() {
  return [
    'default',
    'selector1',
    'selector2',
    'google',
    'k1',
    's1',
    's2',
    'dkim',
    'mail',
    'email',
    'mx'
  ]
}

/**
 * Reverse IP address for PTR lookup
 * @param {string} ip - IPv4 address
 * @returns {string} Reversed IP for in-addr.arpa lookup
 */
export function reverseIPv4(ip) {
  if (!isValidIP(ip) || ip.includes(':')) {
    throw new Error('Invalid IPv4 address')
  }
  return ip.split('.').reverse().join('.') + '.in-addr.arpa'
}

/**
 * Format DNS response for display
 * @param {object} response - DNS response from dnsLookup
 * @returns {Array} Formatted records
 */
export function formatDNSResponse(response) {
  if (!response || !response.Answer) {
    return []
  }

  return response.Answer.map(record => ({
    name: record.name,
    type: record.type,
    ttl: record.TTL,
    data: record.data,
    priority: record.priority // For MX records
  }))
}

/**
 * Check if domain has DNSSEC enabled
 * @param {object} response - DNS response
 * @returns {boolean} True if DNSSEC is enabled
 */
export function hasDNSSEC(response) {
  return response && (response.AD === true || (response.Answer && response.Answer.some(r => r.type === 43 || r.type === 48)))
}
