'use client'

import { useState, useEffect } from 'react'
import { useAuth } from './AuthProvider'
import axios from 'axios'
import { Webhook, Plus, Edit, Trash2, TestTube, Check, X, Clock } from 'lucide-react'

export default function WebhookManager() {
  const { apiBaseUrl } = useAuth()
  const [webhooks, setWebhooks] = useState([])
  const [loading, setLoading] = useState(true)
  const [showCreateForm, setShowCreateForm] = useState(false)
  const [editingWebhook, setEditingWebhook] = useState(null)
  const [formData, setFormData] = useState({
    url: '',
    events: ['*']
  })
  const [error, setError] = useState('')

  const availableEvents = [
    { id: '*', name: 'All Events', description: 'Receive all webhook events' },
    { id: 'verification.completed', name: 'Verification Completed', description: 'When a verification is completed' },
    { id: 'quota.warning', name: 'Quota Warning', description: 'When quota usage reaches 80%' },
    { id: 'quota.exceeded', name: 'Quota Exceeded', description: 'When quota is fully used' }
  ]

  useEffect(() => {
    loadWebhooks()
  }, [])

  const loadWebhooks = async () => {
    try {
      // Mock data for now
      const mockWebhooks = [
        {
          id: '1',
          url: 'https://api.example.com/webhooks/veribits',
          events: ['*'],
          active: true,
          created_at: '2024-01-10T12:00:00Z',
          stats: {
            total_events: 145,
            delivered_events: 142,
            avg_response_time: 250,
            last_delivery: '2024-01-15T10:30:00Z'
          }
        },
        {
          id: '2',
          url: 'https://app.myservice.com/veribits-webhook',
          events: ['verification.completed'],
          active: false,
          created_at: '2024-01-05T09:15:00Z',
          stats: {
            total_events: 23,
            delivered_events: 20,
            avg_response_time: 180,
            last_delivery: '2024-01-12T14:22:00Z'
          }
        }
      ]

      setTimeout(() => {
        setWebhooks(mockWebhooks)
        setLoading(false)
      }, 1000)
    } catch (err) {
      setError('Failed to load webhooks')
      setLoading(false)
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    try {
      if (editingWebhook) {
        // Update webhook
        console.log('Updating webhook:', editingWebhook.id, formData)
        // await axios.put(`${apiBaseUrl}/api/v1/webhooks/${editingWebhook.id}`, formData)
      } else {
        // Create webhook
        console.log('Creating webhook:', formData)
        // await axios.post(`${apiBaseUrl}/api/v1/webhooks`, formData)
      }

      setShowCreateForm(false)
      setEditingWebhook(null)
      setFormData({ url: '', events: ['*'] })
      await loadWebhooks()
    } catch (err) {
      setError(err.response?.data?.error?.message || 'Failed to save webhook')
    }
  }

  const handleDelete = async (webhookId) => {
    if (!confirm('Are you sure you want to delete this webhook?')) return

    try {
      console.log('Deleting webhook:', webhookId)
      // await axios.delete(`${apiBaseUrl}/api/v1/webhooks/${webhookId}`)
      await loadWebhooks()
    } catch (err) {
      setError('Failed to delete webhook')
    }
  }

  const handleTest = async (webhookId) => {
    try {
      console.log('Testing webhook:', webhookId)
      // await axios.post(`${apiBaseUrl}/api/v1/webhooks/${webhookId}/test`)
      alert('Test webhook sent successfully!')
    } catch (err) {
      setError('Failed to send test webhook')
    }
  }

  const toggleWebhook = async (webhookId, active) => {
    try {
      console.log('Toggling webhook:', webhookId, active)
      // await axios.put(`${apiBaseUrl}/api/v1/webhooks/${webhookId}`, { active })
      await loadWebhooks()
    } catch (err) {
      setError('Failed to update webhook status')
    }
  }

  const startEdit = (webhook) => {
    setEditingWebhook(webhook)
    setFormData({
      url: webhook.url,
      events: webhook.events
    })
    setShowCreateForm(true)
  }

  const cancelForm = () => {
    setShowCreateForm(false)
    setEditingWebhook(null)
    setFormData({ url: '', events: ['*'] })
    setError('')
  }

  const handleEventChange = (eventId) => {
    if (eventId === '*') {
      setFormData({ ...formData, events: ['*'] })
    } else {
      const currentEvents = formData.events.filter(e => e !== '*')
      if (currentEvents.includes(eventId)) {
        setFormData({
          ...formData,
          events: currentEvents.filter(e => e !== eventId)
        })
      } else {
        setFormData({
          ...formData,
          events: [...currentEvents, eventId]
        })
      }
    }
  }

  if (loading) {
    return (
      <div className="card">
        <div className="animate-pulse space-y-4">
          {[1, 2].map((i) => (
            <div key={i} className="p-4 border rounded-lg">
              <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
              <div className="h-3 bg-gray-200 rounded w-1/2"></div>
            </div>
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h3 className="text-lg font-medium text-gray-900">Webhooks</h3>
          <p className="text-sm text-gray-500">Receive real-time notifications about verifications</p>
        </div>
        <button
          onClick={() => setShowCreateForm(true)}
          className="btn-primary"
        >
          <Plus className="h-4 w-4 mr-2" />
          Add Webhook
        </button>
      </div>

      {error && (
        <div className="p-3 bg-danger-50 border border-danger-200 rounded-md">
          <p className="text-danger-600 text-sm">{error}</p>
        </div>
      )}

      {/* Create/Edit Form */}
      {showCreateForm && (
        <div className="card">
          <h4 className="text-lg font-medium text-gray-900 mb-4">
            {editingWebhook ? 'Edit Webhook' : 'Create Webhook'}
          </h4>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label htmlFor="webhook-url" className="block text-sm font-medium text-gray-700 mb-1">
                Webhook URL
              </label>
              <input
                id="webhook-url"
                type="url"
                required
                value={formData.url}
                onChange={(e) => setFormData({ ...formData, url: e.target.value })}
                className="input"
                placeholder="https://your-app.com/webhooks/veribits"
              />
              <p className="text-xs text-gray-500 mt-1">
                URL where webhook events will be sent via HTTP POST
              </p>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Events to Subscribe
              </label>
              <div className="space-y-2">
                {availableEvents.map((event) => (
                  <label key={event.id} className="flex items-start">
                    <input
                      type="checkbox"
                      checked={formData.events.includes(event.id)}
                      onChange={() => handleEventChange(event.id)}
                      className="mt-0.5 mr-3"
                    />
                    <div>
                      <div className="text-sm font-medium text-gray-900">{event.name}</div>
                      <div className="text-xs text-gray-500">{event.description}</div>
                    </div>
                  </label>
                ))}
              </div>
            </div>

            <div className="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                onClick={cancelForm}
                className="btn-secondary"
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn-primary"
              >
                {editingWebhook ? 'Update' : 'Create'} Webhook
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Webhooks List */}
      <div className="space-y-4">
        {webhooks.length === 0 ? (
          <div className="card">
            <div className="text-center py-8">
              <Webhook className="h-12 w-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-2">No webhooks configured</h3>
              <p className="text-gray-500 mb-4">
                Set up webhooks to receive real-time notifications about your verifications.
              </p>
              <button
                onClick={() => setShowCreateForm(true)}
                className="btn-primary"
              >
                Create Your First Webhook
              </button>
            </div>
          </div>
        ) : (
          webhooks.map((webhook) => (
            <div key={webhook.id} className="card">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center space-x-2 mb-2">
                    <h4 className="text-lg font-medium text-gray-900">{webhook.url}</h4>
                    <span className={`badge ${webhook.active ? 'badge-success' : 'badge-gray'}`}>
                      {webhook.active ? 'Active' : 'Inactive'}
                    </span>
                  </div>

                  <div className="text-sm text-gray-600 mb-3">
                    Events: {webhook.events.join(', ')}
                  </div>

                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                      <div className="text-gray-500">Total Events</div>
                      <div className="font-medium">{webhook.stats.total_events}</div>
                    </div>
                    <div>
                      <div className="text-gray-500">Success Rate</div>
                      <div className="font-medium">
                        {Math.round((webhook.stats.delivered_events / webhook.stats.total_events) * 100)}%
                      </div>
                    </div>
                    <div>
                      <div className="text-gray-500">Avg Response</div>
                      <div className="font-medium">{webhook.stats.avg_response_time}ms</div>
                    </div>
                    <div>
                      <div className="text-gray-500">Last Delivery</div>
                      <div className="font-medium">
                        {new Date(webhook.stats.last_delivery).toLocaleDateString()}
                      </div>
                    </div>
                  </div>
                </div>

                <div className="flex items-center space-x-2 ml-4">
                  <button
                    onClick={() => handleTest(webhook.id)}
                    className="p-2 text-gray-400 hover:text-gray-600"
                    title="Test webhook"
                  >
                    <TestTube className="h-4 w-4" />
                  </button>
                  <button
                    onClick={() => startEdit(webhook)}
                    className="p-2 text-gray-400 hover:text-gray-600"
                    title="Edit webhook"
                  >
                    <Edit className="h-4 w-4" />
                  </button>
                  <button
                    onClick={() => toggleWebhook(webhook.id, !webhook.active)}
                    className="p-2 text-gray-400 hover:text-gray-600"
                    title={webhook.active ? 'Disable webhook' : 'Enable webhook'}
                  >
                    {webhook.active ? <X className="h-4 w-4" /> : <Check className="h-4 w-4" />}
                  </button>
                  <button
                    onClick={() => handleDelete(webhook.id)}
                    className="p-2 text-gray-400 hover:text-danger-600"
                    title="Delete webhook"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  )
}