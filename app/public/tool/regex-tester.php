<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regex Tester - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Regex Tester</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">Test and debug regular expressions with real-time matching</p>

            <div class="feature-card" style="margin-bottom: 2rem;">
                <div class="form-group">
                    <label for="regex-pattern">Regular Expression</label>
                    <input type="text" id="regex-pattern" placeholder="[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}">
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                    <label><input type="checkbox" id="flag-g" checked> Global (g)</label>
                    <label><input type="checkbox" id="flag-i"> Ignore Case (i)</label>
                    <label><input type="checkbox" id="flag-m"> Multiline (m)</label>
                </div>

                <div class="form-group">
                    <label for="test-string">Test String</label>
                    <textarea id="test-string" rows="8" placeholder="Enter text to test..."></textarea>
                </div>

                <button class="btn btn-primary" onclick="testRegex()" style="width: 100%;">Test Regex</button>
            </div>

            <div class="feature-card" id="regex-results" style="display: none;">
                <h2 style="margin-bottom: 1rem;">Results</h2>
                <div id="regex-output"></div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">A service from <a href="https://www.afterdarksys.com/" target="_blank" rel="noopener">After Dark Systems, LLC</a></p>
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script>
        async function testRegex() {
            const pattern = document.getElementById('regex-pattern').value;
            const text = document.getElementById('test-string').value;
            let flags = '';
            if (document.getElementById('flag-g').checked) flags += 'g';
            if (document.getElementById('flag-i').checked) flags += 'i';
            if (document.getElementById('flag-m').checked) flags += 'm';

            if (!pattern) {
                showAlert('Please enter a regex pattern', 'error');
                return;
            }

            try {
                const data = await apiRequest('/api/v1/tools/regex-test', {
                    method: 'POST',
                    body: JSON.stringify({ pattern, text, flags })
                });

                const result = data.data;
                const resultsDiv = document.getElementById('regex-results');
                const outputDiv = document.getElementById('regex-output');

                resultsDiv.style.display = 'block';

                let html = `
                    <div style="padding: 1rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <p><strong>Pattern:</strong> <code style="color: var(--primary-color);">${result.pattern}</code></p>
                        <p><strong>Matches Found:</strong> ${result.match_count}</p>
                    </div>
                `;

                if (result.matches && result.matches.length > 0) {
                    html += '<h3 style="margin-bottom: 1rem;">Matches:</h3>';
                    result.matches.forEach((match, i) => {
                        html += `
                            <div style="background: var(--darker-bg); padding: 0.75rem; border-radius: 4px; margin-bottom: 0.5rem; border-left: 3px solid var(--success-color);">
                                <strong>Match ${i + 1}:</strong> <code style="color: var(--accent-color);">${match.match}</code>
                                <span style="color: var(--text-secondary); margin-left: 1rem;">@ position ${match.position}</span>
                            </div>
                        `;
                    });
                } else {
                    html += '<p style="color: var(--text-secondary);">No matches found</p>';
                }

                outputDiv.innerHTML = html;
            } catch (error) {
                document.getElementById('regex-output').innerHTML = `<div class="alert alert-error">${error.message}</div>`;
                document.getElementById('regex-results').style.display = 'block';
            }
        }
    </script>
</body>
</html>
