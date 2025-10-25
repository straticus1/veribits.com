<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cryptocurrency Validator - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Cryptocurrency Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Validate Bitcoin and Ethereum addresses and transaction IDs
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Select Cryptocurrency</h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <button class="btn btn-primary crypto-btn" data-crypto="bitcoin" onclick="selectCrypto('bitcoin')" style="width: 100%;">
                        ₿ Bitcoin
                    </button>
                    <button class="btn btn-secondary crypto-btn" data-crypto="ethereum" onclick="selectCrypto('ethereum')" style="width: 100%;">
                        Ξ Ethereum
                    </button>
                </div>

                <div id="validation-form" style="display: none;">
                    <div class="form-group">
                        <label for="validation-type">Validation Type</label>
                        <select id="validation-type" class="form-control">
                            <option value="address">Address</option>
                            <option value="transaction">Transaction ID/Hash</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="value-input" id="value-label">Address or Transaction ID</label>
                        <textarea id="value-input" rows="3" placeholder="Enter address or transaction ID..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <button class="btn btn-primary" id="validate-button" onclick="validateCrypto()" style="width: 100%;">
                        Validate
                    </button>
                </div>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Validation Results</h2>
                <div id="results-content"></div>
            </div>

            <!-- Examples Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Examples</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Bitcoin</h3>
                <p style="margin-bottom: 0.5rem;"><strong>Legacy (P2PKH):</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;">1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa</code>

                <p style="margin-bottom: 0.5rem;"><strong>SegWit (Bech32):</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;">bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq</code>

                <p style="margin-bottom: 0.5rem;"><strong>Transaction ID:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;">a1075db55d416d3ca199f55b6084e2115b9345e16c5cf302fc80e9d5fbf5d48d</code>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Ethereum</h3>
                <p style="margin-bottom: 0.5rem;"><strong>Address (with checksum):</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;">0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb</code>

                <p style="margin-bottom: 0.5rem;"><strong>Transaction Hash:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px;">0x5c504ed432cb51138bcf09aa5e8a410dd4a1e204ef84bfed1be16dfba1b22060</code>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">
                A service from <a href="https://www.afterdarksys.com/" target="_blank" rel="noopener">After Dark Systems, LLC</a>
            </p>
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script>
        let selectedCrypto = null;

        function selectCrypto(crypto) {
            selectedCrypto = crypto;

            // Update button styles
            document.querySelectorAll('.crypto-btn').forEach(btn => {
                if (btn.dataset.crypto === crypto) {
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary');
                }
            });

            // Show validation form
            document.getElementById('validation-form').style.display = 'block';

            // Update label
            updateLabel();
        }

        function updateLabel() {
            const validationType = document.getElementById('validation-type').value;
            const label = document.getElementById('value-label');

            if (validationType === 'address') {
                label.textContent = `${selectedCrypto === 'bitcoin' ? 'Bitcoin' : 'Ethereum'} Address`;
            } else {
                label.textContent = selectedCrypto === 'bitcoin' ? 'Bitcoin Transaction ID' : 'Ethereum Transaction Hash';
            }
        }

        document.getElementById('validation-type').addEventListener('change', updateLabel);

        async function validateCrypto() {
            if (!selectedCrypto) {
                showAlert('Please select a cryptocurrency first', 'error');
                return;
            }

            const value = document.getElementById('value-input').value.trim();
            const type = document.getElementById('validation-type').value;

            if (!value) {
                showAlert('Please enter a value to validate', 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div class="spinner"></div>';

            try {
                const endpoint = selectedCrypto === 'bitcoin'
                    ? '/api/v1/crypto/validate/bitcoin'
                    : '/api/v1/crypto/validate/ethereum';

                const data = await apiRequest(endpoint, {
                    method: 'POST',
                    body: JSON.stringify({ value, type })
                });

                displayResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayResults(result) {
            const statusClass = result.is_valid ? 'success-color' : 'error-color';
            const statusIcon = result.is_valid ? '✅' : '❌';
            const statusText = result.is_valid ? 'Valid' : 'Invalid';

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--${statusClass});">
                    <h3 style="color: var(--${statusClass}); margin-bottom: 1rem;">${statusIcon} ${statusText}</h3>
                    <p><strong>Value:</strong></p>
                    <code style="display: block; background: var(--dark-bg); padding: 1rem; border-radius: 4px; overflow-x: auto; word-break: break-all; margin-bottom: 1rem;">${result.value}</code>
                    <p><strong>Type:</strong> ${result.type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>
            `;

            if (result.format) {
                html += `<p><strong>Format:</strong> ${result.format}</p>`;
            }

            if (result.network) {
                html += `<p><strong>Network:</strong> ${result.network}</p>`;
            }

            if (result.checksum_valid !== undefined) {
                const checksumIcon = result.checksum_valid ? '✅' : '⚠️';
                html += `<p><strong>Checksum:</strong> ${checksumIcon} ${result.checksum_valid ? 'Valid' : 'Invalid or Missing'}</p>`;
            }

            html += `</div>`;

            // Details section
            if (result.details && Object.keys(result.details).length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Details</h3>
                `;

                for (const [key, value] of Object.entries(result.details)) {
                    if (key === 'error') {
                        html += `<p style="color: var(--error-color);"><strong>Error:</strong> ${value}</p>`;
                    } else if (key === 'warning') {
                        html += `<p style="color: var(--warning-color);"><strong>Warning:</strong> ${value}</p>`;
                    } else if (key === 'checksum_address') {
                        html += `<p><strong>Checksum Address:</strong></p>`;
                        html += `<code style="display: block; background: var(--dark-bg); padding: 0.75rem; border-radius: 4px; overflow-x: auto; word-break: break-all; margin-top: 0.5rem;">${value}</code>`;
                    } else {
                        html += `<p><strong>${key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}:</strong> ${value}</p>`;
                    }
                }

                html += `</div>`;
            }

            // Add validate another button
            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Validate Another</button>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }
    </script>
</body>
</html>
