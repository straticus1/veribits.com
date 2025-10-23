/**
 * IP Address Calculator Utilities
 * Supports IPv4 CIDR calculations
 */

/**
 * Convert IP address string to 32-bit integer
 * @param {string} ip - IP address (e.g., "192.168.1.1")
 * @returns {number} 32-bit integer representation
 */
export function ipToInt(ip) {
  const parts = ip.split('.').map(Number)
  if (parts.length !== 4 || parts.some(p => isNaN(p) || p < 0 || p > 255)) {
    throw new Error('Invalid IP address')
  }
  return ((parts[0] << 24) | (parts[1] << 16) | (parts[2] << 8) | parts[3]) >>> 0
}

/**
 * Convert 32-bit integer to IP address string
 * @param {number} int - 32-bit integer
 * @returns {string} IP address string
 */
export function intToIp(int) {
  return [
    (int >>> 24) & 0xFF,
    (int >>> 16) & 0xFF,
    (int >>> 8) & 0xFF,
    int & 0xFF
  ].join('.')
}

/**
 * Validate IP address format
 * @param {string} ip - IP address to validate
 * @returns {boolean} True if valid
 */
export function isValidIp(ip) {
  const parts = ip.split('.')
  if (parts.length !== 4) return false
  return parts.every(part => {
    const num = Number(part)
    return !isNaN(num) && num >= 0 && num <= 255 && part === String(num)
  })
}

/**
 * Validate CIDR notation
 * @param {string} cidr - CIDR notation (e.g., "192.168.1.0/24")
 * @returns {boolean} True if valid
 */
export function isValidCidr(cidr) {
  const [ip, prefix] = cidr.split('/')
  const prefixNum = Number(prefix)
  return isValidIp(ip) && !isNaN(prefixNum) && prefixNum >= 0 && prefixNum <= 32
}

/**
 * Calculate network information from CIDR
 * @param {string} cidr - CIDR notation (e.g., "192.168.1.0/24")
 * @returns {object} Network information
 */
export function calculateNetwork(cidr) {
  const [ip, prefix] = cidr.split('/')
  const prefixNum = Number(prefix)

  if (!isValidIp(ip) || isNaN(prefixNum) || prefixNum < 0 || prefixNum > 32) {
    throw new Error('Invalid CIDR notation')
  }

  const ipInt = ipToInt(ip)
  const mask = (0xFFFFFFFF << (32 - prefixNum)) >>> 0
  const networkInt = (ipInt & mask) >>> 0
  const broadcastInt = (networkInt | ~mask) >>> 0
  const firstHostInt = prefixNum === 31 ? networkInt : networkInt + 1
  const lastHostInt = prefixNum === 31 ? broadcastInt : broadcastInt - 1
  const totalHosts = Math.pow(2, 32 - prefixNum)
  const usableHosts = prefixNum === 31 ? 2 : prefixNum === 32 ? 1 : totalHosts - 2

  return {
    ip: ip,
    cidr: cidr,
    prefix: prefixNum,
    netmask: intToIp(mask),
    wildcardMask: intToIp(~mask >>> 0),
    networkAddress: intToIp(networkInt),
    broadcastAddress: intToIp(broadcastInt),
    firstHost: prefixNum === 32 ? ip : intToIp(firstHostInt),
    lastHost: prefixNum === 32 ? ip : intToIp(lastHostInt),
    totalHosts: totalHosts,
    usableHosts: usableHosts,
    ipClass: getIpClass(ip),
    isPrivate: isPrivateIp(ip),
    binary: {
      ip: ipToBinary(ip),
      netmask: ipToBinary(intToIp(mask)),
      network: ipToBinary(intToIp(networkInt))
    }
  }
}

/**
 * Get IP address class (A, B, C, D, E)
 * @param {string} ip - IP address
 * @returns {string} IP class
 */
export function getIpClass(ip) {
  const firstOctet = Number(ip.split('.')[0])
  if (firstOctet >= 1 && firstOctet <= 126) return 'A'
  if (firstOctet >= 128 && firstOctet <= 191) return 'B'
  if (firstOctet >= 192 && firstOctet <= 223) return 'C'
  if (firstOctet >= 224 && firstOctet <= 239) return 'D (Multicast)'
  if (firstOctet >= 240 && firstOctet <= 255) return 'E (Reserved)'
  return 'Unknown'
}

/**
 * Check if IP is private (RFC 1918)
 * @param {string} ip - IP address
 * @returns {boolean} True if private
 */
export function isPrivateIp(ip) {
  const ipInt = ipToInt(ip)
  const ranges = [
    { start: ipToInt('10.0.0.0'), end: ipToInt('10.255.255.255') },
    { start: ipToInt('172.16.0.0'), end: ipToInt('172.31.255.255') },
    { start: ipToInt('192.168.0.0'), end: ipToInt('192.168.255.255') }
  ]
  return ranges.some(range => ipInt >= range.start && ipInt <= range.end)
}

/**
 * Convert IP to binary representation
 * @param {string} ip - IP address
 * @returns {string} Binary representation with dots
 */
export function ipToBinary(ip) {
  return ip.split('.')
    .map(octet => Number(octet).toString(2).padStart(8, '0'))
    .join('.')
}

/**
 * Calculate subnet mask from prefix length
 * @param {number} prefix - Prefix length (0-32)
 * @returns {string} Subnet mask
 */
export function prefixToNetmask(prefix) {
  if (prefix < 0 || prefix > 32) {
    throw new Error('Prefix must be between 0 and 32')
  }
  const mask = (0xFFFFFFFF << (32 - prefix)) >>> 0
  return intToIp(mask)
}

/**
 * Calculate prefix length from subnet mask
 * @param {string} netmask - Subnet mask
 * @returns {number} Prefix length
 */
export function netmaskToPrefix(netmask) {
  const maskInt = ipToInt(netmask)
  return 32 - Math.log2((~maskInt >>> 0) + 1)
}

/**
 * Convert IP address to hexadecimal
 * @param {string} ip - IP address
 * @returns {string} Hexadecimal representation
 */
export function ipToHex(ip) {
  const ipInt = ipToInt(ip)
  return '0x' + ipInt.toString(16).toUpperCase().padStart(8, '0')
}

/**
 * Convert IP address to decimal
 * @param {string} ip - IP address
 * @returns {number} Decimal representation
 */
export function ipToDecimal(ip) {
  return ipToInt(ip)
}

/**
 * Convert hexadecimal to IP address
 * @param {string} hex - Hexadecimal value (with or without 0x prefix)
 * @returns {string} IP address
 */
export function hexToIp(hex) {
  const cleanHex = hex.replace('0x', '')
  const int = parseInt(cleanHex, 16)
  if (isNaN(int) || int < 0 || int > 0xFFFFFFFF) {
    throw new Error('Invalid hexadecimal value')
  }
  return intToIp(int)
}

/**
 * Convert decimal to IP address
 * @param {number} decimal - Decimal value
 * @returns {string} IP address
 */
export function decimalToIp(decimal) {
  if (decimal < 0 || decimal > 0xFFFFFFFF) {
    throw new Error('Invalid decimal value')
  }
  return intToIp(decimal)
}

/**
 * Calculate hosts in an address range
 * @param {string} startIp - Starting IP address
 * @param {string} endIp - Ending IP address
 * @returns {object} Host count information
 */
export function calculateHostsInRange(startIp, endIp) {
  if (!isValidIp(startIp) || !isValidIp(endIp)) {
    throw new Error('Invalid IP address')
  }

  const startInt = ipToInt(startIp)
  const endInt = ipToInt(endIp)

  if (startInt > endInt) {
    throw new Error('Start IP must be less than or equal to end IP')
  }

  const totalAddresses = endInt - startInt + 1

  return {
    startIp,
    endIp,
    totalAddresses,
    suggestedCidr: calculateCidrFromRange(startInt, endInt)
  }
}

/**
 * Calculate CIDR notation from IP range
 * @param {number} startInt - Start IP as integer
 * @param {number} endInt - End IP as integer
 * @returns {string} Suggested CIDR notation
 */
function calculateCidrFromRange(startInt, endInt) {
  const size = endInt - startInt + 1
  const prefix = 32 - Math.ceil(Math.log2(size))
  const networkInt = (startInt & ((0xFFFFFFFF << (32 - prefix)) >>> 0)) >>> 0
  return `${intToIp(networkInt)}/${prefix}`
}

/**
 * Get all format conversions for an IP address
 * @param {string} ip - IP address
 * @returns {object} All format conversions
 */
export function convertIpFormats(ip) {
  if (!isValidIp(ip)) {
    throw new Error('Invalid IP address')
  }

  return {
    dotted: ip,
    decimal: ipToDecimal(ip),
    hexadecimal: ipToHex(ip),
    binary: ipToBinary(ip),
    octalArray: ip.split('.').map(octet => '0' + Number(octet).toString(8))
  }
}
