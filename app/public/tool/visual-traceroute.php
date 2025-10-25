<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Traceroute - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map {
            height: 500px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .hop-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .hop-table th,
        .hop-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .hop-table th {
            background: var(--darker-bg);
            font-weight: 600;
        }
        .hop-table tr:hover {
            background: var(--darker-bg);
        }
        .timeout {
            color: var(--text-secondary);
            font-style: italic;
        }
        .latency-graph {
            height: 200px;
            margin-top: 2rem;
            background: var(--darker-bg);
            border-radius: 8px;
            padding: 1rem;
            position: relative;
        }
        .latency-bar {
            display: inline-block;
            background: var(--primary-color);
            margin: 0 2px;
            vertical-align: bottom;
            width: 20px;
            border-radius: 4px 4px 0 0;
        }
        .spinner {
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .progress-indicator {
            text-align: center;
            color: var(--text-secondary);
            margin-top: 1rem;
        }
    </style>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Visual Traceroute</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Trace the network path to any destination with geographic visualization
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Traceroute Configuration</h2>

                <div class="form-group">
                    <label for="target">Target Hostname or IP Address</label>
                    <input type="text" id="target" placeholder="google.com or 8.8.8.8"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <div class="form-group">
                    <label for="max-hops">Maximum Hops (1-64)</label>
                    <input type="number" id="max-hops" value="30" min="1" max="64"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <button class="btn btn-primary" id="trace-button" style="margin-top: 1rem; width: 100%;">
                    Start Traceroute
                </button>
            </div>

            <!-- Map Section -->
            <div class="feature-card" id="map-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Geographic Route Visualization</h2>
                <div id="map"></div>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Hop Details</h2>
                <div id="results-content"></div>
            </div>

            <!-- Latency Graph -->
            <div class="feature-card" id="graph-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Latency Analysis</h2>
                <div id="latency-graph" class="latency-graph"></div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits by After Dark Systems. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map = null;
        let polyline = null;
        let markers = [];

        const traceButton = document.getElementById('trace-button');
        const mapSection = document.getElementById('map-section');
        const resultsSection = document.getElementById('results-section');
        const graphSection = document.getElementById('graph-section');

        traceButton.addEventListener('click', async () => {
            const target = document.getElementById('target').value.trim();
            const maxHops = parseInt(document.getElementById('max-hops').value);

            if (!target) {
                showAlert('Please enter a target hostname or IP address', 'error');
                return;
            }

            if (maxHops < 1 || maxHops > 64) {
                showAlert('Maximum hops must be between 1 and 64', 'error');
                return;
            }

            // Show loading state
            traceButton.disabled = true;
            traceButton.textContent = 'Running traceroute...';

            mapSection.style.display = 'block';
            resultsSection.style.display = 'block';
            graphSection.style.display = 'block';

            document.getElementById('results-content').innerHTML = '<div class="spinner"></div><div class="progress-indicator">Tracing route to ' + target + '... This may take 30-60 seconds.</div>';

            try {
                const response = await fetch('/api/v1/tools/traceroute', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ target, max_hops: maxHops })
                });

                const data = await response.json();

                if (!response.ok) {
                    const errorMsg = data.error?.message || data.error || data.message || 'Traceroute failed';
                    throw new Error(errorMsg);
                }

                displayResults(data.data);
            } catch (error) {
                const errorMessage = error.message || 'An unknown error occurred';
                document.getElementById('results-content').innerHTML =
                    `<div class="alert alert-error">${errorMessage}</div>`;
                mapSection.style.display = 'none';
                graphSection.style.display = 'none';
            } finally {
                traceButton.disabled = false;
                traceButton.textContent = 'Start Traceroute';
            }
        });

        function initMap() {
            if (!map) {
                map = L.map('map').setView([20, 0], 2);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);
            } else {
                // Clear existing markers and lines
                markers.forEach(marker => map.removeLayer(marker));
                markers = [];
                if (polyline) {
                    map.removeLayer(polyline);
                }
            }
        }

        function displayResults(data) {
            // Initialize map
            initMap();

            const hops = data.hops || [];
            const coordinates = [];

            // Build table
            let tableHtml = `
                <table class="hop-table">
                    <thead>
                        <tr>
                            <th>Hop</th>
                            <th>IP Address</th>
                            <th>Hostname</th>
                            <th>Location</th>
                            <th>Latency (ms)</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            hops.forEach(hop => {
                if (hop.timeout) {
                    tableHtml += `
                        <tr>
                            <td>${hop.hop}</td>
                            <td colspan="4" class="timeout">* Request timed out *</td>
                        </tr>
                    `;
                } else {
                    const avgLatency = hop.latencies.length > 0
                        ? (hop.latencies.reduce((a, b) => a + b, 0) / hop.latencies.length).toFixed(2)
                        : 'N/A';

                    const location = hop.location
                        ? `${hop.location.city || ''}, ${hop.location.country || ''}`.replace(', ,', ',').trim()
                        : 'Unknown';

                    tableHtml += `
                        <tr>
                            <td><strong>${hop.hop}</strong></td>
                            <td><code>${hop.ip || 'N/A'}</code></td>
                            <td>${hop.hostname || '-'}</td>
                            <td>${location}</td>
                            <td>${avgLatency}</td>
                        </tr>
                    `;

                    // Add to map if location available
                    if (hop.location && hop.location.latitude && hop.location.longitude) {
                        coordinates.push([hop.location.latitude, hop.location.longitude]);

                        const marker = L.marker([hop.location.latitude, hop.location.longitude])
                            .addTo(map)
                            .bindPopup(`
                                <strong>Hop ${hop.hop}</strong><br>
                                IP: ${hop.ip || 'N/A'}<br>
                                ${hop.hostname ? 'Host: ' + hop.hostname + '<br>' : ''}
                                Location: ${location}<br>
                                ${hop.location.isp ? 'ISP: ' + hop.location.isp + '<br>' : ''}
                                Latency: ${avgLatency} ms
                            `);
                        markers.push(marker);
                    }
                }
            });

            tableHtml += `
                    </tbody>
                </table>
            `;

            document.getElementById('results-content').innerHTML = tableHtml;

            // Draw path on map
            if (coordinates.length > 1) {
                polyline = L.polyline(coordinates, {
                    color: '#00d4ff',
                    weight: 3,
                    opacity: 0.7
                }).addTo(map);

                map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
            } else if (coordinates.length === 1) {
                map.setView(coordinates[0], 6);
            }

            // Display latency graph
            displayLatencyGraph(hops);

            // Add retry button
            const retryHtml = `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Run Another Traceroute</button>
                </div>
            `;
            document.getElementById('results-content').innerHTML += retryHtml;
        }

        function displayLatencyGraph(hops) {
            const graphContainer = document.getElementById('latency-graph');
            let graphHtml = '<div style="display: flex; align-items: flex-end; height: 150px; gap: 4px;">';

            const maxLatency = Math.max(...hops.map(h =>
                h.latencies.length > 0 ? Math.max(...h.latencies) : 0
            ));

            hops.forEach(hop => {
                if (hop.timeout || hop.latencies.length === 0) {
                    graphHtml += '<div style="width: 20px; text-align: center; font-size: 0.7rem; color: var(--text-secondary);">-</div>';
                } else {
                    const avgLatency = hop.latencies.reduce((a, b) => a + b, 0) / hop.latencies.length;
                    const height = (avgLatency / maxLatency) * 100;
                    graphHtml += `
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <div class="latency-bar" style="height: ${height}%; min-height: 4px;"
                                 title="Hop ${hop.hop}: ${avgLatency.toFixed(2)} ms"></div>
                            <div style="font-size: 0.7rem; margin-top: 4px; color: var(--text-secondary);">${hop.hop}</div>
                        </div>
                    `;
                }
            });

            graphHtml += '</div>';
            graphHtml += '<div style="margin-top: 1rem; text-align: center; color: var(--text-secondary); font-size: 0.9rem;">Hop Number</div>';

            graphContainer.innerHTML = graphHtml;
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
