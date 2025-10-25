<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Relay Validator - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.html">Tools</a></li>
                <li><a href="/pricing.html">Pricing</a></li>
                <li><a href="/about.html">About</a></li>
                <li><a href="/login.html">Login</a></li>
                <li><a href="/signup.html" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 900px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">SMTP Relay Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Check if an SMTP server is an open relay (security risk)
            </p>

            <div class="alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--error-color); color: var(--error-color); margin-bottom: 2rem;">
                <strong>⚠️ Important:</strong> An open SMTP relay is a serious security vulnerability. Your mail server should NEVER be an open relay as it can be exploited for spam and malicious purposes.
            </div>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Enter Mail Server Details</h2>

                <div class="form-group">
                    <label for="target">Email Address or Domain</label>
                    <input type="text" id="target" placeholder="user@example.com or example.com"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.5rem;">
                        We'll automatically find the MX records for the domain
                    </p>
                </div>

                <button class="btn btn-primary" id="check-button" style="margin-top: 1rem; width: 100%;">
                    Check SMTP Relay Status
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">SMTP Relay Check Results</h2>
                <div id="results-content"></div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits by After Dark Systems. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script>
        const checkButton = document.getElementById('check-button');
        const resultsSection = document.getElementById('results-section');

        checkButton.addEventListener('click', async () => {
            const target = document.getElementById('target').value.trim();

            if (!target) {
                showAlert('Please enter an email address or domain', 'error');
                return;
            }

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            try {
                const response = await fetch('/api/v1/tools/smtp-relay-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ target })
                });

                const data = await response.json();

                if (!response.ok) {
                    const errorMsg = data.error?.message || data.error || data.message || 'SMTP relay check failed';
                    throw new Error(errorMsg);
                }

                displayResults(data.data);
            } catch (error) {
                document.getElementById('results-content').innerHTML =
                    `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        function displayResults(data) {
            const isOpenRelay = data.is_open_relay || data.open_relay;
            const statusColor = isOpenRelay ? 'var(--error-color)' : 'var(--success-color)';
            const statusIcon = isOpenRelay ? '⚠️' : '✓';

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: ${statusColor}; margin-bottom: 1rem;">
                        ${statusIcon} ${isOpenRelay ? 'Open Relay Detected (CRITICAL)' : 'Server is Secured'}
                    </h3>
                    <div style="display: grid; gap: 1rem;">
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Mail Server:</strong> <code style="color: var(--accent-color);">${data.server || data.mx_record || 'N/A'}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Status:</strong> <span style="color: ${statusColor}; font-weight: bold;">${isOpenRelay ? 'OPEN RELAY' : 'CLOSED (SECURE)'}</span></p>
                        </div>
            `;

            if (data.mx_records && data.mx_records.length > 0) {
                html += `
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>MX Records Found:</strong> ${data.mx_records.length}</p>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;">${data.mx_records.join(', ')}</p>
                        </div>
                `;
            }

            html += `
                    </div>
                </div>
            `;

            if (isOpenRelay) {
                html += `
                    <div class="alert alert-error" style="margin-bottom: 1rem;">
                        <strong>🚨 SECURITY ALERT:</strong> This server is configured as an open relay and can be exploited to send spam. This is a critical security vulnerability that must be fixed immediately.
                    </div>
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--error-color); margin-bottom: 1rem;">Recommended Actions</h3>
                        <ul style="list-style: none; padding: 0;">
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">✓ Configure SMTP authentication (SASL)</li>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">✓ Restrict relay access to authorized networks only</li>
                            <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">✓ Implement proper access controls and firewall rules</li>
                            <li style="padding: 0.5rem 0;">✓ Review and update your mail server configuration immediately</li>
                        </ul>
                    </div>
                `;
            } else {
                html += `
                    <div class="alert alert-success" style="margin-bottom: 1rem;">
                        <strong>✓ Good Configuration:</strong> Your mail server is properly secured and does not allow unauthorized relay.
                    </div>
                `;
            }

            if (data.tests_performed && data.tests_performed.length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Tests Performed</h3>
                        ${data.tests_performed.map(test => `
                            <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px; margin-bottom: 0.5rem;">
                                <p><strong>${test.test}:</strong> <span style="color: ${test.passed ? 'var(--success-color)' : 'var(--error-color)'};">${test.result}</span></p>
                                ${test.details ? `<p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">${test.details}</p>` : ''}
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Check Another Server</button>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }
    </script>
</body>
</html>
