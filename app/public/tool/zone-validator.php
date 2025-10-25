<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Zone File Validator - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">DNS Zone File Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Validate BIND zone files and named.conf/nsd.conf syntax
            </p>

            <div id="alert-container"></div>

            <!-- Upload Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Upload Configuration Files</h2>

                <div class="form-group">
                    <label for="config-type">Configuration Type</label>
                    <select id="config-type"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                        <option value="bind-zone">BIND Zone File</option>
                        <option value="named-conf">named.conf</option>
                        <option value="nsd-conf">nsd.conf</option>
                    </select>
                </div>

                <div class="upload-area" id="upload-area" style="margin-top: 1.5rem;">
                    <div class="upload-icon">📄</div>
                    <p>Drag & drop your configuration file here or click to browse</p>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;">
                        Supports: .conf, .zone, .txt files
                    </p>
                    <input type="file" id="file-input" accept=".conf,.zone,.txt" style="display: none;">
                </div>

                <div id="file-info" style="display: none; margin-top: 1.5rem;">
                    <p><strong>Selected file:</strong> <span id="filename"></span></p>
                    <p><strong>Size:</strong> <span id="filesize"></span></p>
                    <button class="btn btn-primary" id="validate-button" style="margin-top: 1rem;">Validate Syntax</button>
                </div>
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
        let selectedFile = null;

        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');
        const fileInfo = document.getElementById('file-info');
        const validateButton = document.getElementById('validate-button');
        const resultsSection = document.getElementById('results-section');

        uploadArea.addEventListener('click', () => fileInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            handleFile(e.dataTransfer.files[0]);
        });

        fileInput.addEventListener('change', (e) => {
            handleFile(e.target.files[0]);
        });

        function handleFile(file) {
            if (!file) return;

            selectedFile = file;
            document.getElementById('filename').textContent = file.name;
            document.getElementById('filesize').textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
        }

        validateButton.addEventListener('click', async () => {
            if (!selectedFile) return;

            const configType = document.getElementById('config-type').value;

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            const formData = new FormData();
            formData.append('file', selectedFile);
            formData.append('type', configType);

            try {
                const data = await uploadFile('/zone-validate', formData);
                displayResults(data);
            } catch (error) {
                document.getElementById('results-content').innerHTML =
                    `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        function displayResults(data) {
            const results = data.data || data;
            const isValid = results.valid || results.status === 'valid';

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: ${isValid ? 'var(--success-color)' : 'var(--error-color)'}; margin-bottom: 1rem;">
                        ${isValid ? '✓ Configuration Valid' : '✗ Configuration Invalid'}
                    </h3>
                    <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                        <p><strong>Status:</strong> ${results.status || (isValid ? 'Valid' : 'Invalid')}</p>
                    </div>
                </div>
            `;

            if (results.errors && results.errors.length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--error-color); margin-bottom: 1rem;">Errors Found</h3>
                        ${results.errors.map(error => `
                            <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px; margin-bottom: 0.5rem; border-left: 3px solid var(--error-color);">
                                <p style="color: var(--error-color);">${error}</p>
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            if (results.warnings && results.warnings.length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--warning-color); margin-bottom: 1rem;">Warnings</h3>
                        ${results.warnings.map(warning => `
                            <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px; margin-bottom: 0.5rem; border-left: 3px solid var(--warning-color);">
                                <p style="color: var(--warning-color);">${warning}</p>
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            if (results.info) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Information</h3>
                        <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            ${Object.entries(results.info).map(([key, value]) => `
                                <p><strong>${key.replace(/_/g, ' ').toUpperCase()}:</strong> ${value}</p>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Validate Another File</button>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }
    </script>
</body>
</html>
