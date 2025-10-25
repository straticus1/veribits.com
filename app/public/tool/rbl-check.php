<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RBL Check - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">RBL Check</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Check if an IP address is listed on email blacklists (RBLs)
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Enter IP Address</h2>

                <div class="form-group">
                    <label for="ip-address">IP Address</label>
                    <input type="text" id="ip-address" placeholder="192.168.1.1 or 2001:db8::1"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <button class="btn btn-primary" id="check-button" style="margin-top: 1rem; width: 100%;">
                    Check RBL Status
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">RBL Check Results</h2>
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
            const ipAddress = document.getElementById('ip-address').value.trim();

            if (!ipAddress) {
                showAlert('Please enter an IP address', 'error');
                return;
            }

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            try {
                const response = await fetch('/api/v1/tools/rbl-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ ip: ipAddress })
                });

                const data = await response.json();

                if (!response.ok) {
                    const errorMsg = data.error?.message || data.error || data.message || 'RBL check failed';
                    throw new Error(errorMsg);
                }

                displayResults(data.data);
            } catch (error) {
                document.getElementById('results-content').innerHTML =
                    `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        function displayResults(data) {
            const isListed = data.listed || (data.blacklists_found && data.blacklists_found > 0);
            const blacklistsChecked = data.blacklists_checked || data.total_checked || 0;
            const blacklistsFound = data.blacklists_found || 0;

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: ${isListed ? 'var(--error-color)' : 'var(--success-color)'}; margin-bottom: 1rem;">
                        ${isListed ? '⚠️ IP Address is Listed' : '✓ IP Address is Clean'}
                    </h3>
                    <div style="display: grid; gap: 1rem;">
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>IP Address:</strong> <code style="color: var(--accent-color);">${data.ip_address}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Blacklists Checked:</strong> ${blacklistsChecked}</p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Blacklists Found:</strong> <span style="color: ${blacklistsFound > 0 ? 'var(--error-color)' : 'var(--success-color)'}; font-weight: bold;">${blacklistsFound}</span></p>
                        </div>
                    </div>
                </div>
            `;

            if (data.listings && data.listings.length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--error-color); margin-bottom: 1rem;">Blacklist Details</h3>
                        ${data.listings.map(listing => `
                            <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px; margin-bottom: 0.5rem; border-left: 3px solid var(--error-color);">
                                <p><strong>RBL:</strong> ${listing.rbl || listing.name}</p>
                                ${listing.reason ? `<p><strong>Reason:</strong> ${listing.reason}</p>` : ''}
                                ${listing.listed_at ? `<p><strong>Listed At:</strong> ${listing.listed_at}</p>` : ''}
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            if (data.checked_rbls && data.checked_rbls.length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">RBLs Checked</h3>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">${data.checked_rbls.join(', ')}</p>
                        </div>
                    </div>
                `;
            }

            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Check Another IP</button>
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
