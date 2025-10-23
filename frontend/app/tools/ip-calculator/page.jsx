'use client'

import { useState } from 'react'
import {
  calculateNetwork,
  isValidCidr,
  prefixToNetmask,
  netmaskToPrefix,
  isValidIp,
  convertIpFormats,
  calculateHostsInRange,
  hexToIp,
  decimalToIp
} from '@/app/utils/ipCalculator'
import { Network, AlertCircle, Info, Repeat, Hash, Globe, Copy, CheckCircle } from 'lucide-react'

export default function IpCalculator() {
  const [mode, setMode] = useState('cidr') // 'cidr', 'converter', 'formats', 'range', 'whois'
  const [error, setError] = useState('')
  const [copied, setCopied] = useState(false)

  const copyToClipboard = async (text) => {
    try {
      await navigator.clipboard.writeText(text)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch (err) {
      setError('Failed to copy to clipboard')
    }
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">IP Address Calculator</h1>
        <p className="text-gray-600 mt-2">
          Comprehensive IP address tools for networking and subnet calculations.
        </p>
      </div>

      {/* Mode Selection */}
      <div className="flex flex-wrap gap-2 mb-6">
        {[
          { id: 'cidr', name: 'CIDR Calculator', icon: Network },
          { id: 'converter', name: 'Netmask Converter', icon: Repeat },
          { id: 'formats', name: 'Format Converter', icon: Hash },
          { id: 'range', name: 'Host Range', icon: Network },
          { id: 'whois', name: 'WHOIS Lookup', icon: Globe }
        ].map(({ id, name, icon: Icon }) => (
          <button
            key={id}
            onClick={() => {
              setMode(id)
              setError('')
            }}
            className={`px-4 py-2 rounded-lg font-medium transition-colors flex items-center ${
              mode === id
                ? 'bg-primary-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            <Icon className="h-4 w-4 mr-2" />
            {name}
          </button>
        ))}
      </div>

      {/* Error Message */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start max-w-4xl">
          <AlertCircle className="h-5 w-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" />
          <div className="text-sm text-red-800">{error}</div>
        </div>
      )}

      {/* Content */}
      {mode === 'cidr' && <CidrCalculator setError={setError} copyToClipboard={copyToClipboard} />}
      {mode === 'converter' && <NetmaskConverter setError={setError} />}
      {mode === 'formats' && <FormatConverter setError={setError} copyToClipboard={copyToClipboard} />}
      {mode === 'range' && <RangeCalculator setError={setError} />}
      {mode === 'whois' && <WhoisLookup setError={setError} />}

      {/* Info Section */}
      <div className="mt-8 max-w-4xl">
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 className="text-sm font-semibold text-blue-900 mb-2 flex items-center">
            <Info className="h-4 w-4 mr-2" />
            {mode === 'cidr' && 'About CIDR and Subnetting'}
            {mode === 'converter' && 'About Subnet Masks'}
            {mode === 'formats' && 'About IP Address Formats'}
            {mode === 'range' && 'About IP Address Ranges'}
            {mode === 'whois' && 'About WHOIS Lookup'}
          </h3>
          <p className="text-sm text-blue-800">
            {mode === 'cidr' &&
              'CIDR (Classless Inter-Domain Routing) notation specifies IP addresses and their routing prefix. The prefix length indicates how many bits are used for the network portion.'}
            {mode === 'converter' &&
              'Subnet masks define the network and host portions of an IP address. They can be represented as dotted decimal or CIDR prefix notation.'}
            {mode === 'formats' &&
              'IP addresses can be represented in multiple formats: dotted decimal (192.168.1.1), hexadecimal (0xC0A80101), decimal (3232235777), or binary.'}
            {mode === 'range' &&
              'Calculate the number of IP addresses between two IPs or determine how many hosts fit in a network block.'}
            {mode === 'whois' &&
              'WHOIS lookup provides registration and ownership information for IP addresses and domain names from registries like ARIN, RIPE, APNIC, and domain registrars.'}
          </p>
        </div>
      </div>
    </div>
  )
}

// CIDR Calculator Component
function CidrCalculator({ setError, copyToClipboard }) {
  const [input, setInput] = useState('')
  const [result, setResult] = useState(null)

  const handleCalculate = () => {
    setError('')
    setResult(null)

    try {
      if (!input.trim()) {
        setError('Please enter a CIDR notation (e.g., 192.168.1.0/24)')
        return
      }

      if (!isValidCidr(input.trim())) {
        setError('Invalid CIDR notation. Format: IP/prefix (e.g., 192.168.1.0/24)')
        return
      }

      const networkInfo = calculateNetwork(input.trim())
      setResult(networkInfo)
    } catch (err) {
      setError(err.message)
    }
  }

  return (
    <div className="card max-w-4xl mb-8">
      <div className="mb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">CIDR Notation</label>
        <div className="flex space-x-4">
          <input
            type="text"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && handleCalculate()}
            placeholder="e.g., 192.168.1.0/24"
            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
          <button onClick={handleCalculate} className="btn-primary px-8">
            Calculate
          </button>
        </div>
      </div>

      {result && (
        <div>
          <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <Network className="h-5 w-5 mr-2 text-primary-600" />
            Network Information
          </h3>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <InfoRow label="IP Address" value={result.ip} />
            <InfoRow label="CIDR Notation" value={result.cidr} />
            <InfoRow label="Subnet Mask" value={result.netmask} />
            <InfoRow label="Wildcard Mask" value={result.wildcardMask} />
            <InfoRow label="Network Address" value={result.networkAddress} highlight />
            <InfoRow label="Broadcast Address" value={result.broadcastAddress} highlight />
            <InfoRow label="First Usable Host" value={result.firstHost} />
            <InfoRow label="Last Usable Host" value={result.lastHost} />
            <InfoRow label="Total Hosts" value={result.totalHosts.toLocaleString()} />
            <InfoRow label="Usable Hosts" value={result.usableHosts.toLocaleString()} />
            <InfoRow label="IP Class" value={result.ipClass} />
            <InfoRow label="Address Type" value={result.isPrivate ? 'Private (RFC 1918)' : 'Public'} />
          </div>

          {/* Subnet Utilization */}
          <div className="mt-6 pt-6 border-t border-gray-200">
            <h4 className="text-sm font-semibold text-gray-900 mb-3">Subnet Utilization</h4>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="p-3 bg-blue-50 rounded-lg">
                <div className="text-xs text-blue-600 mb-1">IPs Remaining (if none used)</div>
                <div className="font-mono text-lg text-blue-900 font-semibold">
                  {result.usableHosts.toLocaleString()}
                </div>
              </div>
              <div className="p-3 bg-green-50 rounded-lg">
                <div className="text-xs text-green-600 mb-1">Possible /30 Subnets</div>
                <div className="font-mono text-lg text-green-900 font-semibold">
                  {Math.floor(result.totalHosts / 4).toLocaleString()}
                </div>
              </div>
              <div className="p-3 bg-purple-50 rounded-lg">
                <div className="text-xs text-purple-600 mb-1">Possible /29 Subnets</div>
                <div className="font-mono text-lg text-purple-900 font-semibold">
                  {Math.floor(result.totalHosts / 8).toLocaleString()}
                </div>
              </div>
            </div>
          </div>

          {/* Binary Representation */}
          <div className="mt-6 pt-6 border-t border-gray-200">
            <h4 className="text-sm font-semibold text-gray-900 mb-3">Binary Representation</h4>
            <div className="space-y-2 font-mono text-xs">
              <div className="flex">
                <span className="w-32 text-gray-600">IP Address:</span>
                <span className="text-gray-900">{result.binary.ip}</span>
              </div>
              <div className="flex">
                <span className="w-32 text-gray-600">Subnet Mask:</span>
                <span className="text-gray-900">{result.binary.netmask}</span>
              </div>
              <div className="flex">
                <span className="w-32 text-gray-600">Network:</span>
                <span className="text-gray-900">{result.binary.network}</span>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// Netmask Converter Component
function NetmaskConverter({ setError }) {
  const [converterInput, setConverterInput] = useState('')
  const [converterType, setConverterType] = useState('prefix')
  const [converterResult, setConverterResult] = useState('')

  const handleConvert = () => {
    setError('')
    try {
      if (converterType === 'prefix') {
        const prefix = Number(converterInput)
        if (isNaN(prefix) || prefix < 0 || prefix > 32) {
          setError('Prefix must be between 0 and 32')
          return
        }
        setConverterResult(prefixToNetmask(prefix))
      } else {
        if (!isValidIp(converterInput)) {
          setError('Invalid subnet mask format')
          return
        }
        const prefix = netmaskToPrefix(converterInput)
        setConverterResult(`/${prefix}`)
      }
    } catch (err) {
      setError(err.message)
    }
  }

  return (
    <div className="card max-w-4xl mb-8">
      <div className="mb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">Conversion Type</label>
        <div className="flex space-x-4 mb-4">
          <label className="flex items-center">
            <input
              type="radio"
              value="prefix"
              checked={converterType === 'prefix'}
              onChange={(e) => setConverterType(e.target.value)}
              className="mr-2"
            />
            <span className="text-sm">Prefix to Netmask</span>
          </label>
          <label className="flex items-center">
            <input
              type="radio"
              value="netmask"
              checked={converterType === 'netmask'}
              onChange={(e) => setConverterType(e.target.value)}
              className="mr-2"
            />
            <span className="text-sm">Netmask to Prefix</span>
          </label>
        </div>

        <div className="flex space-x-4">
          <input
            type="text"
            value={converterInput}
            onChange={(e) => setConverterInput(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && handleConvert()}
            placeholder={converterType === 'prefix' ? 'e.g., 24' : 'e.g., 255.255.255.0'}
            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
          <button onClick={handleConvert} className="btn-primary px-8">
            Convert
          </button>
        </div>
      </div>

      {converterResult && (
        <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium text-gray-700">Result:</span>
            <span className="text-lg font-mono font-semibold text-green-900">{converterResult}</span>
          </div>
        </div>
      )}

      <div className="mt-6 pt-6 border-t border-gray-200">
        <h4 className="text-sm font-semibold text-gray-900 mb-3">Common Subnet Masks</h4>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {[
            { prefix: 8, mask: '255.0.0.0', hosts: '16.7M' },
            { prefix: 16, mask: '255.255.0.0', hosts: '65K' },
            { prefix: 24, mask: '255.255.255.0', hosts: '254' },
            { prefix: 25, mask: '255.255.255.128', hosts: '126' },
            { prefix: 26, mask: '255.255.255.192', hosts: '62' },
            { prefix: 27, mask: '255.255.255.224', hosts: '30' },
            { prefix: 28, mask: '255.255.255.240', hosts: '14' },
            { prefix: 30, mask: '255.255.255.252', hosts: '2' }
          ].map(({ prefix, mask, hosts }) => (
            <div key={prefix} className="text-xs p-2 bg-gray-50 rounded">
              <div className="font-semibold text-gray-900">/{prefix}</div>
              <div className="text-gray-600">{mask}</div>
              <div className="text-gray-500">{hosts} hosts</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

// Format Converter Component
function FormatConverter({ setError, copyToClipboard }) {
  const [input, setInput] = useState('')
  const [inputType, setInputType] = useState('ip')
  const [result, setResult] = useState(null)

  const handleConvert = () => {
    setError('')
    setResult(null)

    try {
      if (!input.trim()) {
        setError('Please enter an IP address or value to convert')
        return
      }

      if (inputType === 'ip') {
        if (!isValidIp(input.trim())) {
          setError('Invalid IP address format')
          return
        }
        setResult(convertIpFormats(input.trim()))
      } else if (inputType === 'hex') {
        const ip = hexToIp(input.trim())
        setResult(convertIpFormats(ip))
      } else if (inputType === 'decimal') {
        const ip = decimalToIp(Number(input.trim()))
        setResult(convertIpFormats(ip))
      }
    } catch (err) {
      setError(err.message)
    }
  }

  return (
    <div className="card max-w-4xl mb-8">
      <div className="mb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">Input Type</label>
        <div className="flex space-x-4 mb-4">
          {[
            { value: 'ip', label: 'IP Address' },
            { value: 'hex', label: 'Hexadecimal' },
            { value: 'decimal', label: 'Decimal' }
          ].map(({ value, label }) => (
            <label key={value} className="flex items-center">
              <input
                type="radio"
                value={value}
                checked={inputType === value}
                onChange={(e) => setInputType(e.target.value)}
                className="mr-2"
              />
              <span className="text-sm">{label}</span>
            </label>
          ))}
        </div>

        <div className="flex space-x-4">
          <input
            type="text"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && handleConvert()}
            placeholder={
              inputType === 'ip'
                ? 'e.g., 192.168.1.1'
                : inputType === 'hex'
                ? 'e.g., 0xC0A80101'
                : 'e.g., 3232235777'
            }
            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
          <button onClick={handleConvert} className="btn-primary px-8">
            Convert
          </button>
        </div>
      </div>

      {result && (
        <div>
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Format Conversions</h3>
          <div className="space-y-3">
            <FormatRow label="Dotted Decimal" value={result.dotted} onCopy={() => copyToClipboard(result.dotted)} />
            <FormatRow
              label="Decimal"
              value={result.decimal.toString()}
              onCopy={() => copyToClipboard(result.decimal.toString())}
            />
            <FormatRow label="Hexadecimal" value={result.hexadecimal} onCopy={() => copyToClipboard(result.hexadecimal)} />
            <FormatRow label="Binary" value={result.binary} onCopy={() => copyToClipboard(result.binary)} />
            <FormatRow
              label="Octal (per octet)"
              value={result.octalArray.join('.')}
              onCopy={() => copyToClipboard(result.octalArray.join('.'))}
            />
          </div>
        </div>
      )}
    </div>
  )
}

// Range Calculator Component
function RangeCalculator({ setError }) {
  const [startIp, setStartIp] = useState('')
  const [endIp, setEndIp] = useState('')
  const [result, setResult] = useState(null)

  const handleCalculate = () => {
    setError('')
    setResult(null)

    try {
      if (!startIp.trim() || !endIp.trim()) {
        setError('Please enter both start and end IP addresses')
        return
      }

      const rangeInfo = calculateHostsInRange(startIp.trim(), endIp.trim())
      setResult(rangeInfo)
    } catch (err) {
      setError(err.message)
    }
  }

  return (
    <div className="card max-w-4xl mb-8">
      <div className="mb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">IP Address Range</label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <input
            type="text"
            value={startIp}
            onChange={(e) => setStartIp(e.target.value)}
            placeholder="Start IP (e.g., 192.168.1.1)"
            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
          <input
            type="text"
            value={endIp}
            onChange={(e) => setEndIp(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && handleCalculate()}
            placeholder="End IP (e.g., 192.168.1.254)"
            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
        </div>
        <button onClick={handleCalculate} className="btn-primary w-full">
          Calculate Range
        </button>
      </div>

      {result && (
        <div>
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Range Information</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <InfoRow label="Start IP" value={result.startIp} />
            <InfoRow label="End IP" value={result.endIp} />
            <InfoRow label="Total Addresses" value={result.totalAddresses.toLocaleString()} highlight />
            <InfoRow label="Suggested CIDR" value={result.suggestedCidr} />
          </div>
        </div>
      )}
    </div>
  )
}

// WHOIS Lookup Component
function WhoisLookup({ setError }) {
  const [query, setQuery] = useState('')
  const [queryType, setQueryType] = useState('ip')
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState(null)

  const handleLookup = async () => {
    setError('')
    setResult(null)

    if (!query.trim()) {
      setError('Please enter an IP address or domain name')
      return
    }

    setLoading(true)

    try {
      // Note: This requires a backend API to perform WHOIS lookups
      // For now, providing information about what would be returned
      setResult({
        query: query.trim(),
        type: queryType,
        message: 'WHOIS lookup requires backend API integration. This would query registries like ARIN, RIPE, APNIC, LACNIC, or AFRINIC for IP ownership information, or domain registrars for domain information.'
      })
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="card max-w-4xl mb-8">
      <div className="mb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">Lookup Type</label>
        <div className="flex space-x-4 mb-4">
          <label className="flex items-center">
            <input
              type="radio"
              value="ip"
              checked={queryType === 'ip'}
              onChange={(e) => setQueryType(e.target.value)}
              className="mr-2"
            />
            <span className="text-sm">IP Address</span>
          </label>
          <label className="flex items-center">
            <input
              type="radio"
              value="domain"
              checked={queryType === 'domain'}
              onChange={(e) => setQueryType(e.target.value)}
              className="mr-2"
            />
            <span className="text-sm">Domain Name</span>
          </label>
        </div>

        <div className="flex space-x-4">
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && handleLookup()}
            placeholder={queryType === 'ip' ? 'e.g., 8.8.8.8' : 'e.g., example.com'}
            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
          <button onClick={handleLookup} disabled={loading} className="btn-primary px-8">
            {loading ? 'Looking up...' : 'Lookup'}
          </button>
        </div>
      </div>

      {result && (
        <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <div className="flex items-start">
            <Info className="h-5 w-5 text-yellow-600 mr-2 flex-shrink-0 mt-0.5" />
            <div>
              <div className="text-sm font-semibold text-yellow-900 mb-2">Backend Integration Required</div>
              <div className="text-sm text-yellow-800">{result.message}</div>
              <div className="mt-3 text-xs text-yellow-700">
                <strong>Query:</strong> {result.query} ({result.type})
                <br />
                <strong>Would query:</strong>{' '}
                {result.type === 'ip' ? 'ARIN, RIPE, APNIC, LACNIC, or AFRINIC' : 'Domain registrars via WHOIS protocol'}
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="mt-6 pt-6 border-t border-gray-200">
        <h4 className="text-sm font-semibold text-gray-900 mb-3">Regional Internet Registries</h4>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          {[
            { name: 'ARIN', region: 'North America', url: 'whois.arin.net' },
            { name: 'RIPE NCC', region: 'Europe, Middle East', url: 'whois.ripe.net' },
            { name: 'APNIC', region: 'Asia Pacific', url: 'whois.apnic.net' },
            { name: 'LACNIC', region: 'Latin America', url: 'whois.lacnic.net' },
            { name: 'AFRINIC', region: 'Africa', url: 'whois.afrinic.net' }
          ].map(({ name, region, url }) => (
            <div key={name} className="text-xs p-3 bg-gray-50 rounded">
              <div className="font-semibold text-gray-900">{name}</div>
              <div className="text-gray-600">{region}</div>
              <div className="text-gray-500 font-mono">{url}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

// Helper Components
function InfoRow({ label, value, highlight = false }) {
  return (
    <div className={`p-3 rounded-lg ${highlight ? 'bg-primary-50 border border-primary-200' : 'bg-gray-50'}`}>
      <div className="text-xs text-gray-600 mb-1">{label}</div>
      <div className={`font-mono text-sm ${highlight ? 'text-primary-900 font-semibold' : 'text-gray-900'}`}>{value}</div>
    </div>
  )
}

function FormatRow({ label, value, onCopy }) {
  const [copied, setCopied] = useState(false)

  const handleCopy = async () => {
    await onCopy()
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
      <div className="flex-1">
        <div className="text-xs text-gray-600 mb-1">{label}</div>
        <div className="font-mono text-sm text-gray-900">{value}</div>
      </div>
      <button onClick={handleCopy} className="ml-4 text-primary-600 hover:text-primary-700">
        {copied ? <CheckCircle className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
      </button>
    </div>
  )
}
