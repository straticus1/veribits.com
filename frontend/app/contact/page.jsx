'use client';

import { useState } from 'react';

export default function ContactPage() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    subject: '',
    message: ''
  });
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = (e) => {
    e.preventDefault();
    // TODO: Implement actual form submission
    console.log('Form submitted:', formData);
    setSubmitted(true);
    setTimeout(() => setSubmitted(false), 5000);
  };

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  return (
    <div className="min-h-screen bg-dark-900">
      {/* Header Section */}
      <div className="bg-gradient-brand py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h1 className="text-5xl font-bold text-white mb-6">
            Contact Us
          </h1>
          <p className="text-xl text-gray-200 max-w-3xl">
            Have questions? Need enterprise support? We're here to help.
          </p>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid lg:grid-cols-2 gap-12">
          {/* Contact Form */}
          <div>
            <h2 className="text-3xl font-bold text-white mb-6">Send us a message</h2>

            {submitted && (
              <div className="mb-6 p-4 bg-success-600 bg-opacity-20 border border-success-600 rounded-lg">
                <p className="text-success-500 font-semibold">
                  Thank you! We'll get back to you soon.
                </p>
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-6">
              <div>
                <label htmlFor="name" className="block text-sm font-medium text-gray-300 mb-2">
                  Name *
                </label>
                <input
                  type="text"
                  id="name"
                  name="name"
                  required
                  value={formData.name}
                  onChange={handleChange}
                  className="input"
                  placeholder="Your name"
                />
              </div>

              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-300 mb-2">
                  Email *
                </label>
                <input
                  type="email"
                  id="email"
                  name="email"
                  required
                  value={formData.email}
                  onChange={handleChange}
                  className="input"
                  placeholder="your.email@example.com"
                />
              </div>

              <div>
                <label htmlFor="subject" className="block text-sm font-medium text-gray-300 mb-2">
                  Subject *
                </label>
                <select
                  id="subject"
                  name="subject"
                  required
                  value={formData.subject}
                  onChange={handleChange}
                  className="input"
                >
                  <option value="">Select a subject</option>
                  <option value="general">General Inquiry</option>
                  <option value="technical">Technical Support</option>
                  <option value="enterprise">Enterprise Solutions</option>
                  <option value="billing">Billing & Payments</option>
                  <option value="api">API Integration</option>
                  <option value="security">Security Concerns</option>
                </select>
              </div>

              <div>
                <label htmlFor="message" className="block text-sm font-medium text-gray-300 mb-2">
                  Message *
                </label>
                <textarea
                  id="message"
                  name="message"
                  required
                  rows={6}
                  value={formData.message}
                  onChange={handleChange}
                  className="input resize-none"
                  placeholder="Tell us how we can help..."
                />
              </div>

              <button type="submit" className="btn-primary w-full">
                Send Message
              </button>
            </form>
          </div>

          {/* Contact Information */}
          <div className="space-y-8">
            <div>
              <h2 className="text-3xl font-bold text-white mb-6">Get in touch</h2>
              <p className="text-gray-400 text-lg mb-8">
                Whether you're a developer, enterprise client, or just curious about our services,
                we'd love to hear from you.
              </p>
            </div>

            {/* Contact Methods */}
            <div className="space-y-6">
              <div className="card">
                <div className="flex items-start">
                  <div className="text-brand-green text-2xl mr-4">📧</div>
                  <div>
                    <h3 className="text-lg font-semibold text-white mb-1">Email</h3>
                    <a href="mailto:support@veribits.com" className="link-subtle">
                      support@veribits.com
                    </a>
                  </div>
                </div>
              </div>

              <div className="card">
                <div className="flex items-start">
                  <div className="text-brand-green text-2xl mr-4">💼</div>
                  <div>
                    <h3 className="text-lg font-semibold text-white mb-1">Enterprise Sales</h3>
                    <a href="mailto:enterprise@veribits.com" className="link-subtle">
                      enterprise@veribits.com
                    </a>
                  </div>
                </div>
              </div>

              <div className="card">
                <div className="flex items-start">
                  <div className="text-brand-green text-2xl mr-4">📚</div>
                  <div>
                    <h3 className="text-lg font-semibold text-white mb-1">Documentation</h3>
                    <a href="/docs" className="link-subtle">
                      API Documentation & Guides
                    </a>
                  </div>
                </div>
              </div>

              <div className="card">
                <div className="flex items-start">
                  <div className="text-brand-green text-2xl mr-4">🔧</div>
                  <div>
                    <h3 className="text-lg font-semibold text-white mb-1">Technical Support</h3>
                    <p className="text-gray-400 text-sm mb-2">
                      For existing customers
                    </p>
                    <a href="mailto:tech@veribits.com" className="link-subtle">
                      tech@veribits.com
                    </a>
                  </div>
                </div>
              </div>
            </div>

            {/* Business Hours */}
            <div className="card">
              <h3 className="text-lg font-semibold text-white mb-3">Business Hours</h3>
              <div className="space-y-2 text-gray-400">
                <p>Monday - Friday: 9:00 AM - 6:00 PM EST</p>
                <p>Saturday - Sunday: Closed</p>
                <p className="text-sm text-gray-500 mt-4">
                  * Emergency support available 24/7 for Enterprise customers
                </p>
              </div>
            </div>

            {/* After Dark Systems */}
            <div className="card bg-gradient-brand">
              <h3 className="text-lg font-semibold text-white mb-2">
                Part of After Dark Systems, LLC
              </h3>
              <p className="text-gray-200 text-sm mb-4">
                VeriBits is a product of After Dark Systems, providing enterprise-grade
                verification and security solutions.
              </p>
              <a
                href="https://www.aeims.app"
                target="_blank"
                rel="noopener noreferrer"
                className="text-brand-amber hover:text-brand-amber-light font-semibold"
              >
                Learn more about After Dark Systems →
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
