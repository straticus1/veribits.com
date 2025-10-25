<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL CSR Generator & Validator - VeriBits</title>
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
        <div class="container" style="max-width: 1000px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">SSL CSR Generator & Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Generate Certificate Signing Requests (CSR) and Private Keys, or validate existing CSRs
            </p>

            <div id="alert-container"></div>

            <!-- Mode Selection -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                <button class="btn btn-primary" id="generate-btn" onclick="switchMode('generate')">
                    Generate CSR & Key
                </button>
                <button class="btn btn-secondary" id="validate-btn" onclick="switchMode('validate')">
                    Validate CSR
                </button>
            </div>

            <!-- Generate Mode -->
            <div id="generate-mode">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Generate SSL Certificate Signing Request</h2>

                    <form id="csr-form">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="common_name">Common Name (Domain) *</label>
                                <input type="text" id="common_name" name="common_name" placeholder="example.com" required>
                            </div>

                            <div class="form-group">
                                <label for="organization">Organization *</label>
                                <input type="text" id="organization" name="organization" placeholder="Company Name" required>
                            </div>

                            <div class="form-group">
                                <label for="organizational_unit">Organizational Unit</label>
                                <input type="text" id="organizational_unit" name="organizational_unit" placeholder="IT Department">
                            </div>

                            <div class="form-group">
                                <label for="city">City/Locality *</label>
                                <input type="text" id="city" name="city" placeholder="San Francisco" required>
                            </div>

                            <div class="form-group">
                                <label for="state">State/Province *</label>
                                <input type="text" id="state" name="state" placeholder="California" required>
                            </div>

                            <div class="form-group">
                                <label for="country">Country Code (2 letters) *</label>
                                <input type="text" id="country" name="country" maxlength="2" placeholder="US" required pattern="[A-Z]{2}">
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="admin@example.com">
                            </div>

                            <div class="form-group">
                                <label for="key_size">Key Size</label>
                                <select id="key_size" name="key_size">
                                    <option value="2048">2048 bits (Standard)</option>
                                    <option value="3072">3072 bits</option>
                                    <option value="4096" selected>4096 bits (Recommended)</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                            Generate CSR & Private Key
                        </button>
                    </form>
                </div>

                <!-- Generation Results -->
                <div class="feature-card" id="generate-results" style="display: none; margin-top: 2rem;">
                    <h2 style="margin-bottom: 1.5rem;">Generated Files</h2>
                    <div id="generate-results-content"></div>
                </div>
            </div>

            <!-- Validate Mode -->
            <div id="validate-mode" style="display: none;">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Validate Certificate Signing Request</h2>

                    <div class="upload-area" id="csr-upload-area">
                        <div class="upload-icon">📄</div>
                        <p>Drag & drop your CSR file here or click to browse</p>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;">
                            Accepted formats: .csr, .pem, .txt
                        </p>
                        <input type="file" id="csr-file-input" accept=".csr,.pem,.txt" style="display: none;">
                    </div>

                    <div id="csr-file-info" style="display: none; margin-top: 1.5rem;">
                        <p><strong>Selected file:</strong> <span id="csr-filename"></span></p>
                        <button class="btn btn-primary" id="validate-csr-button" style="margin-top: 1rem;">Validate CSR</button>
                    </div>
                </div>

                <!-- Validation Results -->
                <div class="feature-card" id="validate-results" style="display: none; margin-top: 2rem;">
                    <h2 style="margin-bottom: 1.5rem;">CSR Information</h2>
                    <div id="validate-results-content"></div>
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
        let currentMode = 'generate';
        let selectedCSRFile = null;

        function switchMode(mode) {
            currentMode = mode;

            if (mode === 'generate') {
                document.getElementById('generate-btn').classList.remove('btn-secondary');
                document.getElementById('generate-btn').classList.add('btn-primary');
                document.getElementById('validate-btn').classList.remove('btn-primary');
                document.getElementById('validate-btn').classList.add('btn-secondary');
                document.getElementById('generate-mode').style.display = 'block';
                document.getElementById('validate-mode').style.display = 'none';
            } else {
                document.getElementById('validate-btn').classList.remove('btn-secondary');
                document.getElementById('validate-btn').classList.add('btn-primary');
                document.getElementById('generate-btn').classList.remove('btn-primary');
                document.getElementById('generate-btn').classList.add('btn-secondary');
                document.getElementById('generate-mode').style.display = 'none';
                document.getElementById('validate-mode').style.display = 'block';
            }
        }

        // CSR Generation Form
        document.getElementById('csr-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            // Convert country to uppercase
            data.country = data.country.toUpperCase();

            const resultsSection = document.getElementById('generate-results');
            const resultsContent = document.getElementById('generate-results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div class="spinner"></div>';

            try {
                const result = await apiRequest('/api/v1/ssl/generate-csr', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });

                displayGenerateResults(result.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        function displayGenerateResults(data) {
            const html = `
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="color: var(--success-color); margin-bottom: 0.5rem;">✅ Success!</h3>
                    <p style="color: var(--text-secondary);">Your CSR and Private Key have been generated. Download both files and keep your private key secure.</p>
                </div>

                <div style="padding: 1rem; background: var(--darker-bg); border-radius: 8px; border-left: 4px solid var(--warning-color); margin-bottom: 1.5rem;">
                    <p style="color: var(--warning-color); font-weight: bold;">⚠️ Important Security Notice</p>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem;">Never share your private key with anyone. Store it securely and back it up.</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="margin-bottom: 1rem;">Certificate Information</h3>
                    <p><strong>Common Name:</strong> ${data.subject.CN || 'N/A'}</p>
                    <p><strong>Organization:</strong> ${data.subject.O || 'N/A'}</p>
                    <p><strong>Country:</strong> ${data.subject.C || 'N/A'}</p>
                    <p><strong>Key Size:</strong> ${data.key_size} bits</p>
                    <p><strong>Algorithm:</strong> ${data.algorithm}</p>
                    <p><strong>Signature:</strong> ${data.signature_algorithm}</p>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="margin-bottom: 1rem;">Certificate Signing Request (CSR)</h3>
                    <textarea readonly style="width: 100%; height: 200px; padding: 1rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;">${data.csr}</textarea>
                    <button class="btn btn-secondary" onclick="downloadFile('${data.files.csr}', \`${data.csr.replace(/\n/g, '\\n')}\`)" style="margin-top: 0.5rem;">
                        Download CSR
                    </button>
                    <button class="btn btn-secondary" onclick="copyToClipboard(\`${data.csr.replace(/\n/g, '\\n')}\`)" style="margin-top: 0.5rem; margin-left: 0.5rem;">
                        Copy CSR
                    </button>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="margin-bottom: 1rem;">Private Key</h3>
                    <textarea readonly style="width: 100%; height: 200px; padding: 1rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;">${data.private_key}</textarea>
                    <button class="btn btn-secondary" onclick="downloadFile('${data.files.key}', \`${data.private_key.replace(/\n/g, '\\n')}\`)" style="margin-top: 0.5rem;">
                        Download Private Key
                    </button>
                    <button class="btn btn-secondary" onclick="copyToClipboard(\`${data.private_key.replace(/\n/g, '\\n')}\`)" style="margin-top: 0.5rem; margin-left: 0.5rem;">
                        Copy Private Key
                    </button>
                </div>

                <div style="text-align: center;">
                    <button class="btn btn-primary" onclick="downloadBoth('${data.files.csr}', '${data.files.key}', \`${data.csr.replace(/\n/g, '\\n')}\`, \`${data.private_key.replace(/\n/g, '\\n')}\`)">
                        📦 Download Both Files
                    </button>
                </div>
            `;

            document.getElementById('generate-results-content').innerHTML = html;
        }

        function downloadFile(filename, content) {
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        }

        function downloadBoth(csrFile, keyFile, csrContent, keyContent) {
            downloadFile(csrFile, csrContent);
            setTimeout(() => {
                downloadFile(keyFile, keyContent);
            }, 500);
        }

        // CSR Upload
        const csrUploadArea = document.getElementById('csr-upload-area');
        const csrFileInput = document.getElementById('csr-file-input');
        const csrFileInfo = document.getElementById('csr-file-info');

        csrUploadArea.addEventListener('click', () => {
            csrFileInput.click();
        });

        csrUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            csrUploadArea.classList.add('drag-over');
        });

        csrUploadArea.addEventListener('dragleave', () => {
            csrUploadArea.classList.remove('drag-over');
        });

        csrUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            csrUploadArea.classList.remove('drag-over');
            handleCSRFile(e.dataTransfer.files[0]);
        });

        csrFileInput.addEventListener('change', (e) => {
            handleCSRFile(e.target.files[0]);
        });

        function handleCSRFile(file) {
            if (!file) return;

            selectedCSRFile = file;
            document.getElementById('csr-filename').textContent = file.name;
            csrFileInfo.style.display = 'block';
        }

        document.getElementById('validate-csr-button').addEventListener('click', async () => {
            if (!selectedCSRFile) return;

            const resultsSection = document.getElementById('validate-results');
            const resultsContent = document.getElementById('validate-results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div class="spinner"></div>';

            const formData = new FormData();
            formData.append('csr_file', selectedCSRFile);

            try {
                const data = await uploadFile('/api/v1/ssl/validate-csr', formData);
                displayValidateResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        function displayValidateResults(data) {
            const statusIcon = data.is_valid ? '✅' : '❌';
            const statusColor = data.is_valid ? 'success-color' : 'error-color';

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; border-left: 4px solid var(--${statusColor}); margin-bottom: 1.5rem;">
                    <h3 style="color: var(--${statusColor}); margin-bottom: 1rem;">${statusIcon} ${data.is_valid ? 'Valid CSR' : 'Invalid CSR'}</h3>
                </div>
            `;

            if (data.is_valid) {
                html += `
                    <div style="margin-bottom: 1.5rem;">
                        <h3 style="margin-bottom: 1rem;">Subject Information</h3>
                        ${data.common_name ? `<p><strong>Common Name:</strong> ${data.common_name}</p>` : ''}
                        ${data.organization ? `<p><strong>Organization:</strong> ${data.organization}</p>` : ''}
                        ${data.organizational_unit ? `<p><strong>Organizational Unit:</strong> ${data.organizational_unit}</p>` : ''}
                        ${data.city ? `<p><strong>City:</strong> ${data.city}</p>` : ''}
                        ${data.state ? `<p><strong>State:</strong> ${data.state}</p>` : ''}
                        ${data.country ? `<p><strong>Country:</strong> ${data.country}</p>` : ''}
                        ${data.email ? `<p><strong>Email:</strong> ${data.email}</p>` : ''}
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <h3 style="margin-bottom: 1rem;">Public Key Information</h3>
                        <p><strong>Algorithm:</strong> ${data.public_key.algorithm}</p>
                        <p><strong>Key Size:</strong> ${data.public_key.bits} bits</p>
                    </div>
                `;
            }

            html += `
                <div style="text-align: center; margin-top: 1.5rem;">
                    <button class="btn btn-primary" onclick="location.reload()">Validate Another CSR</button>
                </div>
            `;

            document.getElementById('validate-results-content').innerHTML = html;
        }
    </script>
</body>
</html>
