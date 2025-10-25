<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Validator - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">DNS Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Validate DNS records, check DNSSEC, and analyze domain configurations
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Enter Domain or DNS Records</h2>

                <div class="form-group">
                    <label for="domain">Domain Name</label>
                    <input type="text" id="domain" placeholder="example.com"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <div class="form-group">
                    <label for="record-type">Record Type</label>
                    <select id="record-type"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                        <option value="A">A - IPv4 Address</option>
                        <option value="AAAA">AAAA - IPv6 Address</option>
                        <option value="MX">MX - Mail Exchange</option>
                        <option value="TXT">TXT - Text Record</option>
                        <option value="CNAME">CNAME - Canonical Name</option>
                        <option value="NS">NS - Name Server</option>
                        <option value="SOA">SOA - Start of Authority</option>
                        <option value="PTR">PTR - Pointer</option>
                        <option value="SRV">SRV - Service</option>
                        <option value="CAA">CAA - Certificate Authority Authorization</option>
                    </select>
                </div>

                <button class="btn btn-primary" id="validate-button" style="margin-top: 1rem; width: 100%;">
                    Validate DNS
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Validation Results</h2>
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
        const validateButton = document.getElementById('validate-button');
        const resultsSection = document.getElementById('results-section');

        validateButton.addEventListener('click', async () => {
            const domain = document.getElementById('domain').value.trim();
            const recordType = document.getElementById('record-type').value;

            if (!domain) {
                showAlert('Please enter a domain name', 'error');
                return;
            }

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            try {
                const response = await fetch('/api/v1/tools/dns-validate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ domain, record_type: recordType })
                });

                const data = await response.json();

                if (!response.ok) {
                    // Handle error object properly
                    const errorMsg = data.error?.message || data.error || data.message || 'Validation failed';
                    throw new Error(errorMsg);
                }

                displayResults(data.data);
            } catch (error) {
                const errorMessage = error.message || 'An unknown error occurred';
                document.getElementById('results-content').innerHTML =
                    `<div class="alert alert-error">${errorMessage}</div>`;
            }
        });

        function displayResults(data) {
            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">DNS Records Found</h3>
            `;

            if (data.records && data.records.length > 0) {
                data.records.forEach(record => {
                    html += `
                        <div style="margin-bottom: 1rem; padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Type:</strong> ${record.type}</p>
                            <p><strong>Value:</strong> <code style="color: var(--accent-color);">${record.value}</code></p>
                            ${record.ttl ? `<p><strong>TTL:</strong> ${record.ttl}</p>` : ''}
                            ${record.priority ? `<p><strong>Priority:</strong> ${record.priority}</p>` : ''}
                        </div>
                    `;
                });
            } else {
                html += '<p>No records found</p>';
            }

            html += `</div>`;

            if (data.dnssec) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">DNSSEC Status</h3>
                        <p><strong>Enabled:</strong> ${data.dnssec.enabled ? '✓ Yes' : '✗ No'}</p>
                    </div>
                `;
            }

            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Validate Another Domain</button>
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
