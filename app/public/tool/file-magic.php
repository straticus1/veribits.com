<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Magic Detector - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">File Magic Detector</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Analyze file headers and magic numbers to identify true file types
            </p>

            <div id="alert-container"></div>

            <!-- Upload Section -->
            <div class="feature-card" id="upload-section">
                <h2 style="margin-bottom: 1.5rem;">Upload File</h2>

                <div class="upload-area" id="upload-area">
                    <div class="upload-icon">📁</div>
                    <p>Drag & drop your file here or click to browse</p>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;">
                        Maximum file size: 50MB (anonymous) / 200MB (authenticated)
                    </p>
                    <input type="file" id="file-input" style="display: none;">
                </div>

                <div id="file-info" style="display: none; margin-top: 1.5rem;">
                    <p><strong>Selected file:</strong> <span id="filename"></span></p>
                    <p><strong>Size:</strong> <span id="filesize"></span></p>
                    <button class="btn btn-primary" id="scan-button" style="margin-top: 1rem;">Scan File</button>
                </div>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Scan Results</h2>

                <div id="results-content">
                    <div class="spinner"></div>
                </div>
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
        const scanButton = document.getElementById('scan-button');
        const resultsSection = document.getElementById('results-section');

        // Upload area click
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // Drag and drop
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

        // File input change
        fileInput.addEventListener('change', (e) => {
            handleFile(e.target.files[0]);
        });

        // Handle file selection
        function handleFile(file) {
            if (!file) return;

            selectedFile = file;
            document.getElementById('filename').textContent = file.name;
            document.getElementById('filesize').textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
        }

        // Scan button click
        scanButton.addEventListener('click', async () => {
            if (!selectedFile) return;

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            const formData = new FormData();
            formData.append('file', selectedFile);

            try {
                const data = await uploadFile('/file-magic', formData);

                displayResults(data);
            } catch (error) {
                document.getElementById('results-content').innerHTML =
                    `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        // Display results
        function displayResults(data) {
            const results = data.data || data;

            const html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">File Type Detected</h3>
                    <p><strong>Type:</strong> ${results.detected_type || 'Unknown'}</p>
                    <p><strong>Extension:</strong> ${results.detected_extension || 'N/A'}</p>
                    <p><strong>MIME Type:</strong> ${results.detected_mime || 'N/A'}</p>
                    <p><strong>File Hash:</strong> <code style="color: var(--accent-color);">${results.file_hash || 'N/A'}</code></p>
                </div>

                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Magic Number</h3>
                    <pre style="background: var(--dark-bg); padding: 1rem; border-radius: 4px; overflow-x: auto;">${results.magic_number_hex || 'N/A'}</pre>
                </div>

                ${results.badge_url ? `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <p style="margin-bottom: 0.5rem;">Share this verification:</p>
                    <input type="text" value="${results.badge_url}" readonly
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    <button class="btn btn-secondary" style="margin-top: 0.5rem;" onclick="copyToClipboard('${results.badge_url}')">
                        Copy Badge URL
                    </button>
                </div>
                ` : ''}

                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Scan Another File</button>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }
    </script>
</body>
</html>
