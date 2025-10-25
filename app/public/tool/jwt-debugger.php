<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JWT Debugger - VeriBits</title>
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
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">JWT Debugger</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Decode, verify, and generate JSON Web Tokens
            </p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                <button class="btn btn-primary" id="decode-tab" onclick="switchTab('decode')">Decode & Verify</button>
                <button class="btn btn-secondary" id="generate-tab" onclick="switchTab('generate')">Generate Token</button>
            </div>

            <!-- Decode Mode -->
            <div id="decode-mode">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div class="feature-card">
                        <h2 style="margin-bottom: 1rem;">Encoded Token</h2>
                        <textarea id="jwt-input" placeholder="Paste your JWT token here..." style="width: 100%; height: 300px; padding: 1rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>

                        <div class="form-group" style="margin-top: 1rem;">
                            <label>
                                <input type="checkbox" id="verify-signature"> Verify Signature
                            </label>
                        </div>

                        <div id="secret-input-group" style="display: none; margin-top: 1rem;">
                            <label for="secret">Secret Key</label>
                            <input type="text" id="secret" placeholder="your-256-bit-secret" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                        </div>

                        <button class="btn btn-primary" onclick="decodeJWT()" style="width: 100%; margin-top: 1rem;">Decode JWT</button>
                    </div>

                    <div class="feature-card">
                        <h2 style="margin-bottom: 1rem;">Decoded</h2>
                        <div id="decoded-output">
                            <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">Enter a JWT token to decode it</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Generate Mode -->
            <div id="generate-mode" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div class="feature-card">
                        <h2 style="margin-bottom: 1rem;">Payload & Settings</h2>

                        <div class="form-group">
                            <label for="gen-secret">Secret Key *</label>
                            <input type="text" id="gen-secret" placeholder="your-256-bit-secret" required>
                        </div>

                        <div class="form-group">
                            <label for="gen-payload">Payload (JSON)</label>
                            <textarea id="gen-payload" rows="10" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace;">{\n  "sub": "1234567890",\n  "name": "John Doe",\n  "iat": 1516239022\n}</textarea>
                        </div>

                        <div class="form-group">
                            <label for="gen-expires">Expires In (seconds)</label>
                            <select id="gen-expires">
                                <option value="3600">1 hour</option>
                                <option value="86400">24 hours</option>
                                <option value="604800">7 days</option>
                                <option value="2592000">30 days</option>
                                <option value="0">Never</option>
                            </select>
                        </div>

                        <button class="btn btn-primary" onclick="generateJWT()" style="width: 100%;">Generate JWT</button>
                    </div>

                    <div class="feature-card">
                        <h2 style="margin-bottom: 1rem;">Generated Token</h2>
                        <div id="generated-output">
                            <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">Configure and generate a JWT token</p>
                        </div>
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
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script>
        function switchTab(tab) {
            if (tab === 'decode') {
                document.getElementById('decode-tab').classList.remove('btn-secondary');
                document.getElementById('decode-tab').classList.add('btn-primary');
                document.getElementById('generate-tab').classList.remove('btn-primary');
                document.getElementById('generate-tab').classList.add('btn-secondary');
                document.getElementById('decode-mode').style.display = 'block';
                document.getElementById('generate-mode').style.display = 'none';
            } else {
                document.getElementById('generate-tab').classList.remove('btn-secondary');
                document.getElementById('generate-tab').classList.add('btn-primary');
                document.getElementById('decode-tab').classList.remove('btn-primary');
                document.getElementById('decode-tab').classList.add('btn-secondary');
                document.getElementById('decode-mode').style.display = 'none';
                document.getElementById('generate-mode').style.display = 'block';
            }
        }

        document.getElementById('verify-signature').addEventListener('change', (e) => {
            document.getElementById('secret-input-group').style.display = e.target.checked ? 'block' : 'none';
        });

        async function decodeJWT() {
            const token = document.getElementById('jwt-input').value.trim();
            const verifySignature = document.getElementById('verify-signature').checked;
            const secret = document.getElementById('secret').value;

            if (!token) {
                showAlert('Please enter a JWT token', 'error');
                return;
            }

            try {
                const data = await apiRequest('/api/v1/jwt/decode', {
                    method: 'POST',
                    body: JSON.stringify({ token, secret, verify_signature: verifySignature })
                });

                displayDecoded(data.data);
            } catch (error) {
                document.getElementById('decoded-output').innerHTML =
                    `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayDecoded(data) {
            let html = '<div style="font-family: monospace;">';

            // Header
            html += '<h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">Header</h3>';
            html += `<pre style="background: var(--darker-bg); padding: 1rem; border-radius: 4px; overflow-x: auto;">${JSON.stringify(data.header, null, 2)}</pre>`;

            // Payload
            html += '<h3 style="color: var(--primary-color); margin-top: 1.5rem; margin-bottom: 0.5rem;">Payload</h3>';
            html += `<pre style="background: var(--darker-bg); padding: 1rem; border-radius: 4px; overflow-x: auto;">${JSON.stringify(data.payload, null, 2)}</pre>`;

            // Claims
            if (data.claims && Object.keys(data.claims).length > 0) {
                html += '<h3 style="color: var(--primary-color); margin-top: 1.5rem; margin-bottom: 0.5rem;">Claims</h3>';
                html += '<div style="background: var(--darker-bg); padding: 1rem; border-radius: 4px;">';
                for (const [key, value] of Object.entries(data.claims)) {
                    if (key === 'expired' || key === 'not_yet_valid') {
                        const color = value ? 'var(--error-color)' : 'var(--success-color)';
                        html += `<p style="color: ${color};"><strong>${key}:</strong> ${value ? 'Yes ❌' : 'No ✅'}</p>`;
                    } else {
                        html += `<p><strong>${key}:</strong> ${value}</p>`;
                    }
                }
                html += '</div>';
            }

            // Signature verification
            if (data.signature_verified !== undefined) {
                const color = data.signature_verified ? 'var(--success-color)' : 'var(--error-color)';
                const icon = data.signature_verified ? '✅' : '❌';
                html += `<div style="margin-top: 1.5rem; padding: 1rem; background: var(--darker-bg); border-radius: 4px; border-left: 4px solid ${color};">`;
                html += `<p style="color: ${color};"><strong>Signature: ${icon} ${data.signature_verified ? 'Valid' : 'Invalid'}</strong></p>`;
                if (data.signature_error) {
                    html += `<p style="color: var(--error-color); margin-top: 0.5rem;">${data.signature_error}</p>`;
                }
                html += '</div>';
            }

            html += '</div>';
            document.getElementById('decoded-output').innerHTML = html;
        }

        async function generateJWT() {
            const secret = document.getElementById('gen-secret').value.trim();
            const payloadText = document.getElementById('gen-payload').value;
            const expiresIn = parseInt(document.getElementById('gen-expires').value);

            if (!secret) {
                showAlert('Secret key is required', 'error');
                return;
            }

            try {
                const payload = JSON.parse(payloadText);

                const data = await apiRequest('/api/v1/jwt/sign', {
                    method: 'POST',
                    body: JSON.stringify({ secret, payload, expires_in: expiresIn })
                });

                displayGenerated(data.data);
            } catch (error) {
                if (error.message.includes('JSON')) {
                    showAlert('Invalid JSON in payload', 'error');
                } else {
                    document.getElementById('generated-output').innerHTML =
                        `<div class="alert alert-error">${error.message}</div>`;
                }
            }
        }

        function displayGenerated(data) {
            let html = `
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="color: var(--success-color); margin-bottom: 0.5rem;">✅ Token Generated</h3>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">JWT Token:</label>
                    <textarea readonly style="width: 100%; height: 150px; padding: 1rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;">${data.token}</textarea>
                    <button class="btn btn-secondary" onclick="copyToClipboard('${data.token}')" style="margin-top: 0.5rem;">Copy Token</button>
                </div>

                <div>
                    <p><strong>Algorithm:</strong> ${data.algorithm}</p>
                    <p><strong>Expires In:</strong> ${data.expires_in} seconds</p>
                    ${data.expires_at ? `<p><strong>Expires At:</strong> ${data.expires_at}</p>` : ''}
                </div>
            `;

            document.getElementById('generated-output').innerHTML = html;
        }
    </script>
</body>
</html>
