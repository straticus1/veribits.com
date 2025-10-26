<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/cli.php">CLI</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li><a href="/login.php">Login</a></li>
                <li><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 1000px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Support Center</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem; font-size: 1.2rem;">
                We're here to help! Get answers to your questions or contact our support team.
            </p>

            <!-- Contact Options -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 4rem;">
                <div class="feature-card" style="text-align: center;">
                    <div class="feature-icon">📧</div>
                    <h3 style="margin-bottom: 1rem;">Email Support</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">Get help via email. We typically respond within 24 hours.</p>
                    <a href="mailto:support@veribits.com" class="btn btn-primary">support@veribits.com</a>
                </div>

                <div class="feature-card" style="text-align: center;">
                    <div class="feature-icon">📚</div>
                    <h3 style="margin-bottom: 1rem;">Documentation</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">Browse our comprehensive guides and API documentation.</p>
                    <a href="/docs/api-docs.php" class="btn btn-secondary">View Docs</a>
                </div>

                <div class="feature-card" style="text-align: center;">
                    <div class="feature-icon">💬</div>
                    <h3 style="margin-bottom: 1rem;">Enterprise Support</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">Dedicated support for enterprise customers with SLA guarantees.</p>
                    <a href="mailto:enterprise@veribits.com" class="btn btn-secondary">Contact Sales</a>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="feature-card" style="max-width: 700px; margin: 0 auto 4rem;">
                <h2 style="color: var(--primary-color); margin-bottom: 1.5rem; text-align: center;">Send Us a Message</h2>
                <form id="support-form" style="margin-top: 2rem;">
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <select id="subject" name="subject" required>
                            <option value="">Select a topic...</option>
                            <option value="technical">Technical Issue</option>
                            <option value="billing">Billing & Subscriptions</option>
                            <option value="api">API Support</option>
                            <option value="feature">Feature Request</option>
                            <option value="security">Security Concern</option>
                            <option value="general">General Inquiry</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" rows="6" required placeholder="Please describe your issue or question in detail..."></textarea>
                    </div>

                    <div id="form-message" style="display: none; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;"></div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Send Message</button>
                </form>
            </div>

            <!-- FAQ Section -->
            <div class="feature-card">
                <h2 style="color: var(--primary-color); margin-bottom: 2rem; text-align: center;">Frequently Asked Questions</h2>

                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">How do I get started with VeriBits?</h3>
                    <p style="color: var(--text-secondary);">Simply <a href="/signup.php" style="color: var(--accent-color);">sign up for a free account</a> to get 5 free scans. No credit card required. You can start using our tools immediately after registration.</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">What file types can I verify?</h3>
                    <p style="color: var(--text-secondary);">VeriBits supports over 40 file types including executables, archives, documents, images, and more. Our magic number detection automatically identifies file types regardless of extension.</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">How long are my files stored?</h3>
                    <p style="color: var(--text-secondary);">Uploaded files are automatically deleted within 24 hours. We only retain metadata and scan results based on your subscription tier (up to 90 days for paid plans).</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">How do I access the API?</h3>
                    <p style="color: var(--text-secondary);">API access is available with Monthly, Annual, and Enterprise plans. After subscribing, you can generate API keys from your <a href="/dashboard.php" style="color: var(--accent-color);">dashboard</a>. View our <a href="/docs/api-docs.php" style="color: var(--accent-color);">API documentation</a> for integration guides.</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">Can I cancel my subscription anytime?</h3>
                    <p style="color: var(--text-secondary);">Yes! You can cancel your subscription at any time from your account settings. Your access will continue until the end of your billing period. We also offer a 30-day money-back guarantee.</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">What payment methods do you accept?</h3>
                    <p style="color: var(--text-secondary);">We accept all major credit cards (Visa, MasterCard, American Express, Discover) and PayPal. Enterprise customers can arrange invoice payments.</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">Is my data secure?</h3>
                    <p style="color: var(--text-secondary);">Yes. All data is transmitted over TLS/SSL encryption. We use AWS infrastructure with enterprise-grade security. Files are processed in isolated environments and automatically deleted. See our <a href="/privacy.php" style="color: var(--accent-color);">Privacy Policy</a> for details.</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">Do you offer enterprise plans?</h3>
                    <p style="color: var(--text-secondary);">Yes! Enterprise plans include unlimited scans, custom file limits, dedicated support, SLA guarantees, and optional on-premise deployment. Contact <a href="mailto:sales@veribits.com" style="color: var(--accent-color);">sales@veribits.com</a> for a custom quote.</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">How do I use the CLI?</h3>
                    <p style="color: var(--text-secondary);">Visit our <a href="/cli.php" style="color: var(--accent-color);">CLI page</a> for installation instructions and usage examples. The CLI provides command-line access to all VeriBits verification tools.</p>
                </div>

                <div>
                    <h3 style="color: var(--text-primary); margin-bottom: 0.75rem;">I found a bug or security issue. How do I report it?</h3>
                    <p style="color: var(--text-secondary);">For security vulnerabilities, please email <a href="mailto:security@veribits.com" style="color: var(--accent-color);">security@veribits.com</a>. For general bugs, use our support form above or email <a href="mailto:support@veribits.com" style="color: var(--accent-color);">support@veribits.com</a>.</p>
                </div>
            </div>

            <!-- Response Time Notice -->
            <div style="margin-top: 3rem; text-align: center; padding: 2rem; background: rgba(30, 64, 175, 0.1); border-radius: 12px; border: 1px solid var(--border-color);">
                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Support Response Times</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                    <div>
                        <div style="font-size: 2rem; color: var(--accent-color); font-weight: bold;">24h</div>
                        <div style="color: var(--text-secondary); margin-top: 0.5rem;">Free & Monthly</div>
                    </div>
                    <div>
                        <div style="font-size: 2rem; color: var(--accent-color); font-weight: bold;">12h</div>
                        <div style="color: var(--text-secondary); margin-top: 0.5rem;">Annual Plans</div>
                    </div>
                    <div>
                        <div style="font-size: 2rem; color: var(--accent-color); font-weight: bold;">4h</div>
                        <div style="color: var(--text-secondary); margin-top: 0.5rem;">Enterprise (24/7)</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">
                A service from <a href="https://www.afterdarksys.com/" target="_blank" rel="noopener">After Dark Systems, LLC</a>
            </p>
            <p style="margin-top: 1rem;">
                <a href="/privacy.php" style="color: var(--text-secondary); margin: 0 1rem;">Privacy</a>
                <a href="/terms.php" style="color: var(--text-secondary); margin: 0 1rem;">Terms</a>
                <a href="/support.php" style="color: var(--text-secondary); margin: 0 1rem;">Support</a>
            </p>
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script>
        document.getElementById('support-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formMessage = document.getElementById('form-message');
            const submitButton = e.target.querySelector('button[type="submit"]');

            // Disable submit button
            submitButton.disabled = true;
            submitButton.textContent = 'Sending...';

            const formData = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                subject: document.getElementById('subject').value,
                message: document.getElementById('message').value
            };

            try {
                const response = await fetch('/api/v1/support/contact', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    formMessage.style.display = 'block';
                    formMessage.style.background = 'rgba(5, 150, 105, 0.1)';
                    formMessage.style.border = '1px solid var(--success-color)';
                    formMessage.style.color = 'var(--success-color)';
                    formMessage.textContent = '✓ Message sent successfully! We\'ll get back to you within 24 hours.';

                    // Reset form
                    e.target.reset();
                } else {
                    throw new Error(result.error?.message || 'Failed to send message');
                }
            } catch (error) {
                formMessage.style.display = 'block';
                formMessage.style.background = 'rgba(239, 68, 68, 0.1)';
                formMessage.style.border = '1px solid var(--error-color)';
                formMessage.style.color = 'var(--error-color)';
                formMessage.textContent = '✗ Error: ' + error.message + '. Please email us directly at support@veribits.com';
            } finally {
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.textContent = 'Send Message';
            }
        });
    </script>
</body>
</html>
