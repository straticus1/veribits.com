'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';

export default function DashboardPage() {
  const [activeTab, setActiveTab] = useState('overview');
  const [user, setUser] = useState(null);
  const [scans, setScans] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Check authentication
    const token = localStorage.getItem('access_token');
    if (!token) {
      window.location.href = '/login';
      return;
    }

    // Fetch user data
    fetchUserData(token);
    fetchScanHistory(token);
  }, []);

  const fetchUserData = async (token) => {
    try {
      const response = await fetch('/api/v1/auth/profile', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      if (response.ok) {
        const data = await response.json();
        setUser(data);
      } else {
        localStorage.removeItem('access_token');
        window.location.href = '/login';
      }
    } catch (error) {
      console.error('Error fetching user data:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchScanHistory = async (token) => {
    try {
      const response = await fetch('/api/v1/history', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      if (response.ok) {
        const data = await response.json();
        setScans(data.scans || []);
      }
    } catch (error) {
      console.error('Error fetching scan history:', error);
    }
  };

  const handleLogout = () => {
    localStorage.removeItem('access_token');
    window.location.href = '/login';
  };

  const getScanTypeIcon = (type) => {
    const icons = {
      'malware_scan': '🛡️',
      'archive_inspection': '📦',
      'dns_check': '🌐',
      'ssl_website_check': '🔐',
      'ssl_certificate_check': '📜',
      'ssl_key_match': '🔑',
      'id_verification': '🪪',
      'file': '📄',
      'email': '📧',
      'transaction': '💰'
    };
    return icons[type] || '✓';
  };

  const getScanTypeName = (type) => {
    const names = {
      'malware_scan': 'Malware Scan',
      'archive_inspection': 'Archive Inspection',
      'dns_check': 'DNS Check',
      'ssl_website_check': 'SSL Website',
      'ssl_certificate_check': 'SSL Certificate',
      'ssl_key_match': 'SSL Key Match',
      'id_verification': 'ID Verification',
      'file': 'File Verification',
      'email': 'Email Verification',
      'transaction': 'Transaction Verification'
    };
    return names[type] || type;
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-dark-900 flex items-center justify-center">
        <div className="text-center">
          <div className="text-brand-green text-4xl mb-4">⟳</div>
          <p className="text-gray-400">Loading...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-dark-900">
      {/* Top Navigation */}
      <nav className="bg-gradient-brand border-b border-dark-700">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            <div className="flex items-center">
              <h1 className="text-2xl font-bold text-white">VeriBits</h1>
            </div>
            <div className="flex items-center gap-4">
              <span className="text-gray-200">
                {user?.email}
              </span>
              <button
                onClick={handleLogout}
                className="btn-secondary text-sm"
              >
                Logout
              </button>
            </div>
          </div>
        </div>
      </nav>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Tabs */}
        <div className="border-b border-dark-700 mb-8">
          <nav className="flex gap-8">
            {['overview', 'scans', 'profile', 'billing'].map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`pb-4 px-1 border-b-2 font-medium text-sm capitalize transition-colors ${
                  activeTab === tab
                    ? 'border-brand-green text-brand-green'
                    : 'border-transparent text-gray-400 hover:text-gray-300'
                }`}
              >
                {tab}
              </button>
            ))}
          </nav>
        </div>

        {/* Overview Tab */}
        {activeTab === 'overview' && (
          <div className="space-y-6">
            <div>
              <h2 className="text-2xl font-bold text-white mb-6">Welcome back, {user?.firstName || 'User'}!</h2>
            </div>

            {/* Stats */}
            <div className="grid md:grid-cols-4 gap-6">
              <div className="card">
                <div className="text-gray-400 text-sm mb-1">Total Scans</div>
                <div className="text-3xl font-bold text-white">{scans.length}</div>
              </div>
              <div className="card">
                <div className="text-gray-400 text-sm mb-1">This Month</div>
                <div className="text-3xl font-bold text-white">
                  {scans.filter(s => new Date(s.created_at) > new Date(Date.now() - 30*24*60*60*1000)).length}
                </div>
              </div>
              <div className="card">
                <div className="text-gray-400 text-sm mb-1">API Quota</div>
                <div className="text-3xl font-bold text-brand-green">75%</div>
              </div>
              <div className="card">
                <div className="text-gray-400 text-sm mb-1">Plan</div>
                <div className="text-2xl font-bold text-brand-amber">Free</div>
              </div>
            </div>

            {/* Recent Scans */}
            <div>
              <h3 className="text-xl font-semibold text-white mb-4">Recent Scans</h3>
              <div className="card">
                {scans.length === 0 ? (
                  <p className="text-gray-400 text-center py-8">
                    No scans yet. Start verifying to see your history here.
                  </p>
                ) : (
                  <div className="space-y-3">
                    {scans.slice(0, 5).map((scan, index) => (
                      <div key={index} className="flex items-center justify-between py-3 border-b border-dark-700 last:border-0">
                        <div className="flex items-center gap-3">
                          <span className="text-2xl">{getScanTypeIcon(scan.type)}</span>
                          <div>
                            <div className="text-white font-medium">{getScanTypeName(scan.type)}</div>
                            <div className="text-sm text-gray-400">{new Date(scan.created_at).toLocaleString()}</div>
                          </div>
                        </div>
                        <div className="badge-success">Completed</div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Scans Tab */}
        {activeTab === 'scans' && (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <h2 className="text-2xl font-bold text-white">Scan History</h2>
              <Link href="/api" className="btn-primary">
                New Scan
              </Link>
            </div>

            <div className="card">
              {scans.length === 0 ? (
                <p className="text-gray-400 text-center py-12">
                  No scans found. Use our API to start verifying files, emails, domains, and more.
                </p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead>
                      <tr className="border-b border-dark-700">
                        <th className="text-left py-3 px-4 text-gray-400 font-medium">Type</th>
                        <th className="text-left py-3 px-4 text-gray-400 font-medium">Details</th>
                        <th className="text-left py-3 px-4 text-gray-400 font-medium">Status</th>
                        <th className="text-left py-3 px-4 text-gray-400 font-medium">Date</th>
                        <th className="text-left py-3 px-4 text-gray-400 font-medium">Badge</th>
                      </tr>
                    </thead>
                    <tbody>
                      {scans.map((scan, index) => (
                        <tr key={index} className="border-b border-dark-700 last:border-0 hover:bg-dark-800">
                          <td className="py-3 px-4">
                            <div className="flex items-center gap-2">
                              <span className="text-xl">{getScanTypeIcon(scan.type)}</span>
                              <span className="text-white">{getScanTypeName(scan.type)}</span>
                            </div>
                          </td>
                          <td className="py-3 px-4 text-gray-400">
                            {scan.domain || scan.file_hash || scan.email || 'N/A'}
                          </td>
                          <td className="py-3 px-4">
                            <span className="badge-success">Verified</span>
                          </td>
                          <td className="py-3 px-4 text-gray-400">
                            {new Date(scan.created_at).toLocaleDateString()}
                          </td>
                          <td className="py-3 px-4">
                            {scan.badge_id && (
                              <Link href={`/api/v1/badge/${scan.badge_id}`} className="link-subtle text-xs">
                                View Badge
                              </Link>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Profile Tab */}
        {activeTab === 'profile' && (
          <div className="space-y-6">
            <h2 className="text-2xl font-bold text-white">Personal Information</h2>

            <div className="card">
              <div className="space-y-6">
                <div className="grid md:grid-cols-2 gap-6">
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">First Name</label>
                    <input type="text" className="input" value={user?.firstName || ''} readOnly />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
                    <input type="text" className="input" value={user?.lastName || ''} readOnly />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                  <input type="email" className="input" value={user?.email || ''} readOnly />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Company</label>
                  <input type="text" className="input" value={user?.company || 'Not specified'} readOnly />
                </div>

                <div className="pt-4 border-t border-dark-700">
                  <button className="btn-secondary">
                    Edit Profile
                  </button>
                </div>
              </div>
            </div>

            {/* API Key Section */}
            <div className="card">
              <h3 className="text-lg font-semibold text-white mb-4">API Access</h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">API Key</label>
                  <div className="flex gap-2">
                    <input
                      type="password"
                      className="input flex-1"
                      value="sk_live_••••••••••••••••"
                      readOnly
                    />
                    <button className="btn-secondary">
                      Reveal
                    </button>
                  </div>
                </div>
                <p className="text-sm text-gray-400">
                  Use this API key to authenticate your requests. Keep it secure!
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Billing Tab */}
        {activeTab === 'billing' && (
          <div className="space-y-6">
            <h2 className="text-2xl font-bold text-white">Billing & Payments</h2>

            {/* Current Plan */}
            <div className="card">
              <div className="flex items-center justify-between mb-6">
                <div>
                  <h3 className="text-lg font-semibold text-white">Current Plan</h3>
                  <p className="text-3xl font-bold text-brand-amber mt-2">Free Tier</p>
                </div>
                <button className="btn-primary">
                  Upgrade Plan
                </button>
              </div>

              <div className="grid md:grid-cols-3 gap-4 pt-6 border-t border-dark-700">
                <div>
                  <div className="text-gray-400 text-sm">Monthly Quota</div>
                  <div className="text-xl font-semibold text-white mt-1">1,000 scans</div>
                </div>
                <div>
                  <div className="text-gray-400 text-sm">Used This Month</div>
                  <div className="text-xl font-semibold text-white mt-1">750 scans</div>
                </div>
                <div>
                  <div className="text-gray-400 text-sm">Resets On</div>
                  <div className="text-xl font-semibold text-white mt-1">Dec 1, 2025</div>
                </div>
              </div>
            </div>

            {/* Payment Method */}
            <div className="card">
              <h3 className="text-lg font-semibold text-white mb-4">Payment Method</h3>
              <p className="text-gray-400 mb-4">No payment method on file</p>
              <button className="btn-secondary">
                Add Payment Method
              </button>
            </div>

            {/* Billing History */}
            <div className="card">
              <h3 className="text-lg font-semibold text-white mb-4">Billing History</h3>
              <p className="text-gray-400 text-center py-8">
                No invoices yet
              </p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
