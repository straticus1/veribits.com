<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Converter - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Certificate Converter</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Convert PEM certificates and keys to PKCS12 or JKS format
            </p>

            <div class="alert" style="background: rgba(102, 126, 234, 0.1); border: 1px solid var(--primary-color); color: var(--primary-color); margin-bottom: 2rem;">
                <strong>💡 Tip:</strong> For the best experience and security, use the <a href="/cli.html" style="color: var(--primary-color); text-decoration: underline;">VeriBits CLI</a> to perform certificate conversions locally on your machine.
            </div>

            <div id="alert-container"></div>

            <!-- Upload Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Upload Certificate & Key</h2>

                <div class="form-group">
                    <label for="output-format">Output Format</label>
                    <select id="output-format"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                        <option value="pkcs12">PKCS12 (.p12, .pfx)</option>
                        <option value="jks">Java KeyStore (JKS)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cert-file">Certificate File (PEM)</label>
                    <div class="upload-area" id="cert-upload-area" style="padding: 1.5rem;">
                        <p>Click to upload certificate (.pem, .crt)</p>
                        <input type="file" id="cert-file" accept=".pem,.crt,.cer" style="display: none;">
                    </div>
                    <div id="cert-info" style="display: none; margin-top: 0.5rem; color: var(--success-color);">
                        <p>✓ <span id="cert-filename"></span></p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="key-file">Private Key File (PEM)</label>
                    <div class="upload-area" id="key-upload-area" style="padding: 1.5rem;">
                        <p>Click to upload private key (.pem, .key)</p>
                        <input type="file" id="key-file" accept=".pem,.key" style="display: none;">
                    </div>
                    <div id="key-info" style="display: none; margin-top: 0.5rem; color: var(--success-color);">
                        <p>✓ <span id="key-filename"></span></p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Export Password (optional but recommended)</label>
                    <input type="password" id="password" placeholder="Enter a password to protect the keystore"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <div class="form-group">
                    <label for="alias">Certificate Alias (optional)</label>
                    <input type="text" id="alias" placeholder="mycert"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <button class="btn btn-primary" id="convert-button" style="margin-top: 1rem; width: 100%;" disabled>
                    Convert Certificate
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Conversion Results</h2>
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
        let certFile = null;
        let keyFile = null;

        const certUploadArea = document.getElementById('cert-upload-area');
        const certFileInput = document.getElementById('cert-file');
        const certInfo = document.getElementById('cert-info');

        const keyUploadArea = document.getElementById('key-upload-area');
        const keyFileInput = document.getElementById('key-file');
        const keyInfo = document.getElementById('key-info');

        const convertButton = document.getElementById('convert-button');
        const resultsSection = document.getElementById('results-section');

        // Certificate upload
        certUploadArea.addEventListener('click', () => certFileInput.click());
        certFileInput.addEventListener('change', (e) => {
            certFile = e.target.files[0];
            if (certFile) {
                document.getElementById('cert-filename').textContent = certFile.name;
                certInfo.style.display = 'block';
                checkFilesUploaded();
            }
        });

        // Key upload
        keyUploadArea.addEventListener('click', () => keyFileInput.click());
        keyFileInput.addEventListener('change', (e) => {
            keyFile = e.target.files[0];
            if (keyFile) {
                document.getElementById('key-filename').textContent = keyFile.name;
                keyInfo.style.display = 'block';
                checkFilesUploaded();
            }
        });

        function checkFilesUploaded() {
            convertButton.disabled = !(certFile && keyFile);
        }

        convertButton.addEventListener('click', async () => {
            if (!certFile || !keyFile) return;

            const outputFormat = document.getElementById('output-format').value;
            const password = document.getElementById('password').value;
            const alias = document.getElementById('alias').value;

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            const formData = new FormData();
            formData.append('certificate', certFile);
            formData.append('private_key', keyFile);
            formData.append('format', outputFormat);
            if (password) formData.append('password', password);
            if (alias) formData.append('alias', alias);

            try {
                const response = await fetch('/api/v1/tools/cert-convert', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: formData
                });

                if (!response.ok) {
                    const data = await response.json();
                    const errorMsg = data.error?.message || data.error || data.message || 'Conversion failed';
                    throw new Error(errorMsg);
                }

                // Check if response is JSON (error) or binary (success)
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const data = await response.json();
                    if (data.error) {
                        const errorMsg = data.error?.message || data.error || data.message || 'Conversion failed';
                        throw new Error(errorMsg);
                    }
                } else {
                    // Download the file
                    const blob = await response.blob();
                    const filename = response.headers.get('content-disposition')?.split('filename=')[1]?.replace(/"/g, '') || `certificate.${outputFormat === 'pkcs12' ? 'p12' : 'jks'}`;

                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);

                    displayResults({ success: true, filename, format: outputFormat });
                }
            } catch (error) {
                document.getElementById('results-content').innerHTML =
                    `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        function displayResults(data) {
            const html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--success-color); margin-bottom: 1rem;">✓ Conversion Successful</h3>
                    <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px; margin-bottom: 1rem;">
                        <p><strong>Output Format:</strong> ${data.format.toUpperCase()}</p>
                    </div>
                    <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px; margin-bottom: 1rem;">
                        <p><strong>Downloaded File:</strong> <code style="color: var(--accent-color);">${data.filename}</code></p>
                    </div>
                    <div class="alert" style="background: rgba(102, 126, 234, 0.1); border: 1px solid var(--primary-color); color: var(--primary-color);">
                        <strong>⚠️ Security Note:</strong> The converted file has been downloaded to your computer. Please store it securely and delete it from the server immediately if processing was done remotely.
                    </div>
                </div>

                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Convert Another Certificate</button>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }
    </script>
</body>
</html>
