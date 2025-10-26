'use client'

import { useState, useEffect, Suspense } from 'react'
import { useSearchParams } from 'next/navigation'
import Link from 'next/link'
import { Search, Wrench, ExternalLink, Terminal, ChevronRight } from 'lucide-react'

function SearchResults() {
  const searchParams = useSearchParams()
  const query = searchParams.get('q') || ''
  const category = searchParams.get('category') || ''

  const [results, setResults] = useState([])
  const [categories, setCategories] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    const fetchResults = async () => {
      setLoading(true)
      setError(null)

      try {
        const params = new URLSearchParams()
        if (query) params.append('q', query)
        if (category) params.append('category', category)

        const response = await fetch(`/api/v1/tools/search?${params.toString()}`)
        const data = await response.json()

        if (data.success) {
          setResults(data.data.tools || [])
          setCategories(data.data.categories || [])
        } else {
          setError('Failed to load search results')
        }
      } catch (err) {
        setError('Error connecting to server')
        console.error(err)
      } finally {
        setLoading(false)
      }
    }

    fetchResults()
  }, [query, category])

  const getCategoryIcon = (category) => {
    switch (category) {
      case 'Network': return '🌐'
      case 'Security': return '🔒'
      case 'Cryptography': return '🔐'
      case 'Files': return '📁'
      case 'Developer': return '💻'
      default: return '🔧'
    }
  }

  const getCategoryColor = (category) => {
    switch (category) {
      case 'Network': return 'bg-blue-100 text-blue-800'
      case 'Security': return 'bg-red-100 text-red-800'
      case 'Cryptography': return 'bg-purple-100 text-purple-800'
      case 'Files': return 'bg-green-100 text-green-800'
      case 'Developer': return 'bg-yellow-100 text-yellow-800'
      default: return 'bg-gray-100 text-gray-800'
    }
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Search header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">
            Tool Search
          </h1>
          {query && (
            <p className="text-gray-600">
              Search results for: <span className="font-semibold">"{query}"</span>
            </p>
          )}
          {category && (
            <p className="text-gray-600">
              Category: <span className="font-semibold">{category}</span>
            </p>
          )}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
          {/* Sidebar - Categories */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">
                Categories
              </h2>
              <div className="space-y-2">
                <Link
                  href="/search"
                  className={`block px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                    !category
                      ? 'bg-primary-50 text-primary-700'
                      : 'text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  All Tools {!loading && `(${results.length})`}
                </Link>
                {categories.map((cat) => (
                  <Link
                    key={cat.name}
                    href={`/search?${query ? `q=${encodeURIComponent(query)}&` : ''}category=${encodeURIComponent(cat.name)}`}
                    className={`block px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                      category === cat.name
                        ? 'bg-primary-50 text-primary-700'
                        : 'text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    {getCategoryIcon(cat.name)} {cat.name} ({cat.count})
                  </Link>
                ))}
              </div>
            </div>
          </div>

          {/* Results */}
          <div className="lg:col-span-3">
            {loading ? (
              <div className="text-center py-12">
                <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                <p className="mt-4 text-gray-600">Loading tools...</p>
              </div>
            ) : error ? (
              <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <p className="text-red-800">{error}</p>
              </div>
            ) : results.length === 0 ? (
              <div className="bg-white border border-gray-200 rounded-lg p-12 text-center">
                <Search className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                <h3 className="text-lg font-medium text-gray-900 mb-2">
                  No tools found
                </h3>
                <p className="text-gray-600">
                  Try adjusting your search query or browse all categories
                </p>
              </div>
            ) : (
              <div className="space-y-4">
                <p className="text-sm text-gray-600 mb-4">
                  Found {results.length} tool{results.length !== 1 ? 's' : ''}
                </p>
                {results.map((tool) => (
                  <div
                    key={tool.id}
                    className="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow"
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                          <Wrench className="h-5 w-5 text-primary-600" />
                          <h3 className="text-lg font-semibold text-gray-900">
                            {tool.name}
                          </h3>
                          <span className={`px-2 py-1 rounded-full text-xs font-medium ${getCategoryColor(tool.category)}`}>
                            {tool.category}
                          </span>
                        </div>
                        <p className="text-gray-600 mb-4">
                          {tool.description}
                        </p>
                        <div className="flex flex-wrap gap-4 text-sm">
                          <div className="flex items-center text-gray-500">
                            <Terminal className="h-4 w-4 mr-1" />
                            <code className="bg-gray-100 px-2 py-1 rounded text-xs">
                              {tool.cli_command}
                            </code>
                          </div>
                        </div>
                      </div>
                      {tool.url && (
                        <Link
                          href={tool.url}
                          className="ml-4 flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium"
                        >
                          Use Tool
                          <ChevronRight className="h-4 w-4 ml-1" />
                        </Link>
                      )}
                    </div>

                    {/* Keywords */}
                    {tool.keywords && tool.keywords.length > 0 && (
                      <div className="mt-4 pt-4 border-t border-gray-100">
                        <div className="flex flex-wrap gap-2">
                          {tool.keywords.slice(0, 8).map((keyword, idx) => (
                            <span
                              key={idx}
                              className="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded"
                            >
                              {keyword}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

export default function SearchPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
          <p className="mt-4 text-gray-600">Loading...</p>
        </div>
      </div>
    }>
      <SearchResults />
    </Suspense>
  )
}
