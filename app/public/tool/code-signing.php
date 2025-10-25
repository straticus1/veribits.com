<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code Signing - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.html">Tools</a></li>
                <li><a href="/cli.html">CLI</a></li>
                <li><a href="/pricing.html">Pricing</a></li>
                <li><a href="/about.html">About</a></li>
                <li><a href="/login.html">Login</a></li>
                <li><a href="/signup.html" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">✍️ Code Signing</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Sign your executables, DLLs, JARs, and applications with test certificates
            </p>

            <div id="alert-container"></div>

            <!-- Quota Info -->
            <div class="feature-card" style="margin-bottom: 2rem; background: rgba(251, 191, 36, 0.1); border-left: 4px solid var(--primary-color);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; color: var(--primary-color);">Your Quota</h3>
                        <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary);" id="quota-text">Loading...</p>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);" id="quota-remaining">-</div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">signings remaining</div>
                    </div>
                </div>
            </div>

            <div class="features-grid">
                <!-- Upload Section -->
                <div class="feature-card" style="grid-column: span 2;">
                    <h2 style="color: var(--primary-color); margin-bottom: 1.5rem;">Upload File to Sign</h2>

                    <div style="border: 2px dashed var(--border-color); border-radius: 8px; padding: 3rem; text-align: center; background: var(--darker-bg); cursor: pointer;" id="drop-zone">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📄</div>
                        <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Drag & drop your file here or click to browse</p>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Supported: .exe, .dll, .msi, .jar (max 100MB)</p>
                        <input type="file" id="file-input" accept=".exe,.dll,.msi,.jar" style="display: none;">
                    </div>

                    <div id="file-info" style="margin-top: 1.5rem; display: none;">
                        <div style="background: var(--darker-bg); padding: 1rem; border-radius: 4px; border-left: 3px solid var(--accent-color);">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong>Selected File:</strong> <span id="file-name"></span><br>
                                    <span style="color: var(--text-secondary); font-size: 0.9rem;">
                                        <span id="file-size"></span> • <span id="file-type"></span>
                                    </span>
                                </div>
                                <button onclick="clearFile()" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Clear</button>
                            </div>
                        </div>
                    </div>

                    <button id="sign-btn" onclick="signFile()" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem; padding: 1rem; font-size: 1.1rem;" disabled>
                        Sign File with Test Certificate
                    </button>

                    <div style="margin-top: 1rem; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; border-radius: 4px;">
                        <strong>ℹ️ Test Certificate:</strong> Files are signed with a self-signed test certificate for evaluation purposes. For production use, upgrade to get CA-issued certificates.
                    </div>
                </div>

                <!-- Info Section -->
                <div class="feature-card">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">What is Code Signing?</h3>
                    <p style="color: var(--text-secondary); line-height: 1.6;">
                        Code signing adds a digital signature to your software, verifying its authenticity and integrity. This helps users trust that your software hasn't been tampered with.
                    </p>

                    <h4 style="margin-top: 1.5rem; margin-bottom: 0.75rem;">Supported Files:</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">✓ .exe - Windows Executables</li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">✓ .dll - Windows Libraries</li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">✓ .msi - Windows Installers</li>
                        <li style="padding: 0.5rem 0;">✓ .jar - Java Archives</li>
                    </ul>

                    <h4 style="margin-top: 1.5rem; margin-bottom: 0.75rem;">Tier Limits:</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">Free: 1 signing/month</li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">Monthly: 500 signings/month</li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">Annual: 2,500 signings/year</li>
                        <li style="padding: 0.5rem 0;">Enterprise: 10,000 signings/month</li>
                    </ul>

                    <a href="/pricing.html" class="btn btn-secondary" style="width: 100%; margin-top: 1rem;">View Pricing</a>
                </div>
            </div>

            <!-- Results Section -->
            <div id="results" style="display: none; margin-top: 2rem;">
                <div class="feature-card">
                    <h2 style="color: var(--primary-color); margin-bottom: 1.5rem;">Signing Results</h2>
                    <div id="results-content"></div>
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
        let selectedFile = null;

        // Load quota on page load
        async function loadQuota() {
            try {
                const response = await fetch('/api/v1/code-signing/quota');
                const result = await response.json();

                if (result.success) {
                    const quota = result.data.quota;
                    document.getElementById('quota-remaining').textContent = quota.remaining;
                    document.getElementById('quota-text').textContent = `${quota.plan_type.charAt(0).toUpperCase() + quota.plan_type.slice(1)} Plan: ${quota.used}/${quota.allowance} used`;
                }
            } catch (error) {
                console.error('Failed to load quota:', error);
            }
        }

        // Drag and drop
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = 'var(--primary-color)';
            dropZone.style.background = 'rgba(251, 191, 36, 0.1)';
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.style.borderColor = 'var(--border-color)';
            dropZone.style.background = 'var(--darker-bg)';
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = 'var(--border-color)';
            dropZone.style.background = 'var(--darker-bg)';

            if (e.dataTransfer.files.length > 0) {
                handleFile(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        });

        function handleFile(file) {
            const validExtensions = ['.exe', '.dll', '.msi', '.jar'];
            const fileExt = '.' + file.name.split('.').pop().toLowerCase();

            if (!validExtensions.includes(fileExt)) {
                alert('Invalid file type. Supported: .exe, .dll, .msi, .jar');
                return;
            }

            if (file.size > 100 * 1024 * 1024) {
                alert('File too large. Maximum size is 100MB');
                return;
            }

            selectedFile = file;
            document.getElementById('file-name').textContent = file.name;
            document.getElementById('file-size').textContent = formatFileSize(file.size);
            document.getElementById('file-type').textContent = fileExt.toUpperCase();
            document.getElementById('file-info').style.display = 'block';
            document.getElementById('sign-btn').disabled = false;
            document.getElementById('results').style.display = 'none';
        }

        function clearFile() {
            selectedFile = null;
            fileInput.value = '';
            document.getElementById('file-info').style.display = 'none';
            document.getElementById('sign-btn').disabled = true;
        }

        async function signFile() {
            if (!selectedFile) {
                alert('Please select a file first');
                return;
            }

            const signBtn = document.getElementById('sign-btn');
            signBtn.disabled = true;
            signBtn.textContent = 'Signing...';

            try {
                const formData = new FormData();
                formData.append('file', selectedFile);

                const response = await fetch('/api/v1/code-signing/sign', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    displayResults(result.data);
                    loadQuota(); // Refresh quota
                } else {
                    const errorMsg = result.error?.message || result.error || result.message || 'Code signing failed';
                    showAlert(errorMsg, 'error');
                }
            } catch (error) {
                showAlert(error.message, 'error');
            } finally {
                signBtn.disabled = false;
                signBtn.textContent = 'Sign File with Test Certificate';
            }
        }

        function displayResults(data) {
            const html = `
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                    <h3 style="color: var(--accent-color); margin-bottom: 0.5rem;">File Signed Successfully!</h3>
                    <p style="color: var(--text-secondary);">Your file has been signed with a test certificate</p>
                </div>

                <div style="background: var(--darker-bg); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem;">File Information</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 0.75rem 0;">Original Filename:</td>
                            <td style="text-align: right; color: var(--primary-color);">${data.original_filename}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 0.75rem 0;">Signed Filename:</td>
                            <td style="text-align: right; color: var(--primary-color);">${data.signed_filename}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 0.75rem 0;">File Type:</td>
                            <td style="text-align: right;">${data.file_type.toUpperCase()}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 0.75rem 0;">File Size:</td>
                            <td style="text-align: right;">${formatFileSize(data.file_size)}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 0.75rem 0;">Signature Verified:</td>
                            <td style="text-align: right;">${data.signature_verified ? '✅ Yes' : '❌ No'}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem 0;">Badge ID:</td>
                            <td style="text-align: right; font-family: monospace; font-size: 0.9rem;">${data.badge_id}</td>
                        </tr>
                    </table>
                </div>

                <div style="background: var(--darker-bg); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem;">Certificate Information</h4>
                    <table style="width: 100%;">
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 0.75rem 0;">Type:</td>
                            <td style="text-align: right;">Test Certificate (Self-Signed)</td>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 0.75rem 0;">Subject:</td>
                            <td style="text-align: right; font-size: 0.9rem;">${data.certificate_info.subject}</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem 0;">Issuer:</td>
                            <td style="text-align: right;">${data.certificate_info.issuer}</td>
                        </tr>
                    </table>
                    <div style="margin-top: 1rem; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; border-radius: 4px;">
                        <strong>ℹ️ Note:</strong> ${data.certificate_info.note}
                    </div>
                </div>

                <button onclick="downloadSignedFile('${data.signed_file}', '${data.signed_filename}')" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                    📥 Download Signed File
                </button>
            `;

            document.getElementById('results-content').innerHTML = html;
            document.getElementById('results').style.display = 'block';
            document.getElementById('results').scrollIntoView({ behavior: 'smooth' });
        }

        function downloadSignedFile(base64Data, filename) {
            const binaryData = atob(base64Data);
            const bytes = new Uint8Array(binaryData.length);
            for (let i = 0; i < binaryData.length; i++) {
                bytes[i] = binaryData.charCodeAt(i);
            }
            const blob = new Blob([bytes]);
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        // Load quota on page load
        loadQuota();
    </script>
</body>
</html>
