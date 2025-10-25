<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Calculator - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">IP Calculator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Calculate IP subnets, CIDR notation, and network ranges
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Enter IP Address</h2>

                <div class="form-group">
                    <label for="ip-address">IP Address / CIDR</label>
                    <input type="text" id="ip-address" placeholder="192.168.1.0/24 or 192.168.1.1"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <div class="form-group">
                    <label for="subnet-mask">Subnet Mask (optional if using CIDR)</label>
                    <input type="text" id="subnet-mask" placeholder="255.255.255.0"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <button class="btn btn-primary" id="calculate-button" style="margin-top: 1rem; width: 100%;">
                    Calculate
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Calculation Results</h2>
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
        const calculateButton = document.getElementById('calculate-button');
        const resultsSection = document.getElementById('results-section');

        calculateButton.addEventListener('click', async () => {
            const ipAddress = document.getElementById('ip-address').value.trim();
            const subnetMask = document.getElementById('subnet-mask').value.trim();

            if (!ipAddress) {
                showAlert('Please enter an IP address', 'error');
                return;
            }

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            try {
                const response = await fetch('/api/v1/tools/ip-calculate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({
                        ip: ipAddress,
                        subnet_mask: subnetMask
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    // Handle error object properly
                    const errorMsg = data.error?.message || data.error || data.message || 'Calculation failed';
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
            const html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Network Information</h3>
                    <div style="display: grid; gap: 1rem;">
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>IP Address:</strong> <code style="color: var(--accent-color);">${data.ip_address}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Network Address:</strong> <code style="color: var(--accent-color);">${data.network_address}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Broadcast Address:</strong> <code style="color: var(--accent-color);">${data.broadcast_address}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Subnet Mask:</strong> <code style="color: var(--accent-color);">${data.subnet_mask}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>CIDR Notation:</strong> <code style="color: var(--accent-color);">${data.cidr}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Wildcard Mask:</strong> <code style="color: var(--accent-color);">${data.wildcard_mask}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>First Usable IP:</strong> <code style="color: var(--accent-color);">${data.first_usable}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Last Usable IP:</strong> <code style="color: var(--accent-color);">${data.last_usable}</code></p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Total Hosts:</strong> ${data.total_hosts}</p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Usable Hosts:</strong> ${data.usable_hosts}</p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>IP Class:</strong> ${data.ip_class}</p>
                        </div>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>IP Type:</strong> ${data.ip_type}</p>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Calculate Another</button>
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
