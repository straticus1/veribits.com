<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BGP Intelligence Portal - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        .tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1rem;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .tab:hover {
            color: var(--text-primary);
        }
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .info-card {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }
        .info-card h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        .info-card p {
            margin: 0;
            font-size: 1.1rem;
        }
        .peer-list, .prefix-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 1rem;
        }
        .peer-item, .prefix-item {
            padding: 1rem;
            background: var(--darker-bg);
            border-radius: 4px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-success {
            background: rgba(0, 255, 136, 0.2);
            color: #00ff88;
        }
        .badge-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        .badge-error {
            background: rgba(255, 59, 48, 0.2);
            color: #ff3b30;
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
        .search-result {
            padding: 1rem;
            background: var(--darker-bg);
            border-radius: 4px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .search-result:hover {
            background: var(--dark-bg);
            border-left: 3px solid var(--primary-color);
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">BGP Intelligence Portal</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Analyze BGP routing data, AS relationships, and global Internet infrastructure
            </p>

            <div id="alert-container"></div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" data-tab="prefix">Prefix Lookup</button>
                <button class="tab" data-tab="asn">AS Lookup</button>
                <button class="tab" data-tab="prefixes">AS Prefixes</button>
                <button class="tab" data-tab="peers">AS Peers</button>
                <button class="tab" data-tab="upstreams">AS Upstreams</button>
                <button class="tab" data-tab="downstreams">AS Downstreams</button>
                <button class="tab" data-tab="search">Search</button>
            </div>

            <!-- Prefix Lookup Tab -->
            <div class="tab-content active" id="prefix-tab">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">BGP Prefix Lookup</h2>
                    <div class="form-group">
                        <label for="prefix-input">IP Address or CIDR Prefix</label>
                        <input type="text" id="prefix-input" placeholder="8.8.8.8 or 8.8.8.0/24"
                            style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    </div>
                    <button class="btn btn-primary" id="prefix-lookup-btn" style="margin-top: 1rem; width: 100%;">
                        Lookup Prefix
                    </button>
                </div>
                <div id="prefix-results" style="margin-top: 2rem;"></div>
            </div>

            <!-- AS Lookup Tab -->
            <div class="tab-content" id="asn-tab">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS (Autonomous System) Lookup</h2>
                    <div class="form-group">
                        <label for="asn-input">ASN (AS Number)</label>
                        <input type="text" id="asn-input" placeholder="AS15169 or 15169"
                            style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    </div>
                    <button class="btn btn-primary" id="asn-lookup-btn" style="margin-top: 1rem; width: 100%;">
                        Lookup AS
                    </button>
                </div>
                <div id="asn-results" style="margin-top: 2rem;"></div>
            </div>

            <!-- AS Prefixes Tab -->
            <div class="tab-content" id="prefixes-tab">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS Announced Prefixes</h2>
                    <div class="form-group">
                        <label for="prefixes-asn-input">ASN</label>
                        <input type="text" id="prefixes-asn-input" placeholder="AS15169 or 15169"
                            style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    </div>
                    <button class="btn btn-primary" id="prefixes-lookup-btn" style="margin-top: 1rem; width: 100%;">
                        Get Prefixes
                    </button>
                </div>
                <div id="prefixes-results" style="margin-top: 2rem;"></div>
            </div>

            <!-- AS Peers Tab -->
            <div class="tab-content" id="peers-tab">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS Peering Relationships</h2>
                    <div class="form-group">
                        <label for="peers-asn-input">ASN</label>
                        <input type="text" id="peers-asn-input" placeholder="AS15169 or 15169"
                            style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    </div>
                    <button class="btn btn-primary" id="peers-lookup-btn" style="margin-top: 1rem; width: 100%;">
                        Get Peers
                    </button>
                </div>
                <div id="peers-results" style="margin-top: 2rem;"></div>
            </div>

            <!-- AS Upstreams Tab -->
            <div class="tab-content" id="upstreams-tab">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS Transit Providers (Upstreams)</h2>
                    <div class="form-group">
                        <label for="upstreams-asn-input">ASN</label>
                        <input type="text" id="upstreams-asn-input" placeholder="AS15169 or 15169"
                            style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    </div>
                    <button class="btn btn-primary" id="upstreams-lookup-btn" style="margin-top: 1rem; width: 100%;">
                        Get Upstreams
                    </button>
                </div>
                <div id="upstreams-results" style="margin-top: 2rem;"></div>
            </div>

            <!-- AS Downstreams Tab -->
            <div class="tab-content" id="downstreams-tab">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS Customers (Downstreams)</h2>
                    <div class="form-group">
                        <label for="downstreams-asn-input">ASN</label>
                        <input type="text" id="downstreams-asn-input" placeholder="AS15169 or 15169"
                            style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    </div>
                    <button class="btn btn-primary" id="downstreams-lookup-btn" style="margin-top: 1rem; width: 100%;">
                        Get Downstreams
                    </button>
                </div>
                <div id="downstreams-results" style="margin-top: 2rem;"></div>
            </div>

            <!-- Search Tab -->
            <div class="tab-content" id="search-tab">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Search AS by Name or Description</h2>
                    <div class="form-group">
                        <label for="search-input">Search Query</label>
                        <input type="text" id="search-input" placeholder="Google, Cloudflare, Amazon..."
                            style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    </div>
                    <button class="btn btn-primary" id="search-btn" style="margin-top: 1rem; width: 100%;">
                        Search
                    </button>
                </div>
                <div id="search-results" style="margin-top: 2rem;"></div>
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
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                tab.classList.add('active');
                document.getElementById(targetTab + '-tab').classList.add('active');
            });
        });

        // Prefix Lookup
        document.getElementById('prefix-lookup-btn').addEventListener('click', async () => {
            const query = document.getElementById('prefix-input').value.trim();
            if (!query) {
                showAlert('Please enter an IP address or CIDR prefix', 'error');
                return;
            }

            const resultsDiv = document.getElementById('prefix-results');
            resultsDiv.innerHTML = '<div class="feature-card"><div class="spinner"></div></div>';

            try {
                const response = await fetch('/api/v1/bgp/prefix', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ query })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.error?.message || data.error || 'Lookup failed');

                displayPrefixResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="feature-card"><div class="alert alert-error">${error.message}</div></div>`;
            }
        });

        // ASN Lookup
        document.getElementById('asn-lookup-btn').addEventListener('click', async () => {
            const asn = document.getElementById('asn-input').value.trim();
            if (!asn) {
                showAlert('Please enter an ASN', 'error');
                return;
            }

            const resultsDiv = document.getElementById('asn-results');
            resultsDiv.innerHTML = '<div class="feature-card"><div class="spinner"></div></div>';

            try {
                const response = await fetch('/api/v1/bgp/asn', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ asn })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.error?.message || data.error || 'Lookup failed');

                displayASNResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="feature-card"><div class="alert alert-error">${error.message}</div></div>`;
            }
        });

        // AS Prefixes
        document.getElementById('prefixes-lookup-btn').addEventListener('click', async () => {
            const asn = document.getElementById('prefixes-asn-input').value.trim();
            if (!asn) {
                showAlert('Please enter an ASN', 'error');
                return;
            }

            const resultsDiv = document.getElementById('prefixes-results');
            resultsDiv.innerHTML = '<div class="feature-card"><div class="spinner"></div></div>';

            try {
                const response = await fetch('/api/v1/bgp/asn/prefixes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ asn })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.error?.message || data.error || 'Lookup failed');

                displayPrefixesResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="feature-card"><div class="alert alert-error">${error.message}</div></div>`;
            }
        });

        // AS Peers
        document.getElementById('peers-lookup-btn').addEventListener('click', async () => {
            const asn = document.getElementById('peers-asn-input').value.trim();
            if (!asn) {
                showAlert('Please enter an ASN', 'error');
                return;
            }

            const resultsDiv = document.getElementById('peers-results');
            resultsDiv.innerHTML = '<div class="feature-card"><div class="spinner"></div></div>';

            try {
                const response = await fetch('/api/v1/bgp/asn/peers', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ asn })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.error?.message || data.error || 'Lookup failed');

                displayPeersResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="feature-card"><div class="alert alert-error">${error.message}</div></div>`;
            }
        });

        // AS Upstreams
        document.getElementById('upstreams-lookup-btn').addEventListener('click', async () => {
            const asn = document.getElementById('upstreams-asn-input').value.trim();
            if (!asn) {
                showAlert('Please enter an ASN', 'error');
                return;
            }

            const resultsDiv = document.getElementById('upstreams-results');
            resultsDiv.innerHTML = '<div class="feature-card"><div class="spinner"></div></div>';

            try {
                const response = await fetch('/api/v1/bgp/asn/upstreams', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ asn })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.error?.message || data.error || 'Lookup failed');

                displayUpstreamsResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="feature-card"><div class="alert alert-error">${error.message}</div></div>`;
            }
        });

        // AS Downstreams
        document.getElementById('downstreams-lookup-btn').addEventListener('click', async () => {
            const asn = document.getElementById('downstreams-asn-input').value.trim();
            if (!asn) {
                showAlert('Please enter an ASN', 'error');
                return;
            }

            const resultsDiv = document.getElementById('downstreams-results');
            resultsDiv.innerHTML = '<div class="feature-card"><div class="spinner"></div></div>';

            try {
                const response = await fetch('/api/v1/bgp/asn/downstreams', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ asn })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.error?.message || data.error || 'Lookup failed');

                displayDownstreamsResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="feature-card"><div class="alert alert-error">${error.message}</div></div>`;
            }
        });

        // Search
        document.getElementById('search-btn').addEventListener('click', async () => {
            const query = document.getElementById('search-input').value.trim();
            if (!query) {
                showAlert('Please enter a search query', 'error');
                return;
            }

            const resultsDiv = document.getElementById('search-results');
            resultsDiv.innerHTML = '<div class="feature-card"><div class="spinner"></div></div>';

            try {
                const response = await fetch('/api/v1/bgp/search', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ query })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.error?.message || data.error || 'Search failed');

                displaySearchResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="feature-card"><div class="alert alert-error">${error.message}</div></div>`;
            }
        });

        // Display functions
        function displayPrefixResults(data) {
            const rpkiBadge = data.rpki_validation === 'valid' ? 'badge-success' :
                             data.rpki_validation === 'invalid' ? 'badge-error' : 'badge-warning';

            const html = `
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Prefix Information</h2>
                    <div class="info-grid">
                        <div class="info-card">
                            <h4>Prefix</h4>
                            <p><code>${data.prefix}</code></p>
                        </div>
                        <div class="info-card">
                            <h4>Name</h4>
                            <p>${data.name || 'N/A'}</p>
                        </div>
                        <div class="info-card">
                            <h4>Country</h4>
                            <p>${data.country_code || 'N/A'}</p>
                        </div>
                        <div class="info-card">
                            <h4>RIR</h4>
                            <p>${data.rir_name || 'N/A'}</p>
                        </div>
                        <div class="info-card">
                            <h4>Origin ASNs</h4>
                            <p>${data.asns && data.asns.length > 0 ? data.asns.map(a => 'AS' + a.asn).join(', ') : 'N/A'}</p>
                        </div>
                        <div class="info-card">
                            <h4>RPKI Status</h4>
                            <p><span class="badge ${rpkiBadge}">${data.rpki_validation || 'Unknown'}</span></p>
                        </div>
                    </div>
                    ${data.description ? `<p style="margin-top: 1rem; color: var(--text-secondary);">${data.description}</p>` : ''}
                </div>
            `;
            document.getElementById('prefix-results').innerHTML = html;
        }

        function displayASNResults(data) {
            const html = `
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS${data.asn} - ${data.name || 'Unknown'}</h2>
                    <div class="info-grid">
                        <div class="info-card">
                            <h4>ASN</h4>
                            <p>AS${data.asn}</p>
                        </div>
                        <div class="info-card">
                            <h4>Country</h4>
                            <p>${data.country_code || 'N/A'}</p>
                        </div>
                        <div class="info-card">
                            <h4>Traffic Estimation</h4>
                            <p>${data.traffic_estimation || 'N/A'}</p>
                        </div>
                        <div class="info-card">
                            <h4>Traffic Ratio</h4>
                            <p>${data.traffic_ratio || 'N/A'}</p>
                        </div>
                    </div>
                    ${data.description ? `<p style="margin-top: 1rem;">${data.description}</p>` : ''}
                    ${data.website ? `<p style="margin-top: 0.5rem;"><strong>Website:</strong> <a href="${data.website}" target="_blank" style="color: var(--primary-color);">${data.website}</a></p>` : ''}
                    ${data.looking_glass ? `<p style="margin-top: 0.5rem;"><strong>Looking Glass:</strong> <a href="${data.looking_glass}" target="_blank" style="color: var(--primary-color);">${data.looking_glass}</a></p>` : ''}
                    ${data.email_contacts && data.email_contacts.length > 0 ? `<p style="margin-top: 0.5rem;"><strong>Contacts:</strong> ${data.email_contacts.join(', ')}</p>` : ''}
                </div>
            `;
            document.getElementById('asn-results').innerHTML = html;
        }

        function displayPrefixesResults(data) {
            let html = `
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS${data.asn} Announced Prefixes</h2>
                    <div class="info-grid">
                        <div class="info-card">
                            <h4>IPv4 Prefixes</h4>
                            <p>${data.ipv4_count}</p>
                        </div>
                        <div class="info-card">
                            <h4>IPv6 Prefixes</h4>
                            <p>${data.ipv6_count}</p>
                        </div>
                    </div>
            `;

            if (data.ipv4_count > 0) {
                html += '<h3 style="margin-top: 2rem; color: var(--primary-color);">IPv4 Prefixes</h3><div class="prefix-list">';
                data.ipv4_prefixes.slice(0, 100).forEach(prefix => {
                    html += `
                        <div class="prefix-item">
                            <div>
                                <strong><code>${prefix.prefix}</code></strong>
                                ${prefix.name ? `<br><small style="color: var(--text-secondary);">${prefix.name}</small>` : ''}
                            </div>
                            <div>${prefix.description || ''}</div>
                        </div>
                    `;
                });
                if (data.ipv4_count > 100) {
                    html += `<p style="text-align: center; margin-top: 1rem; color: var(--text-secondary);">Showing first 100 of ${data.ipv4_count} prefixes</p>`;
                }
                html += '</div>';
            }

            html += '</div>';
            document.getElementById('prefixes-results').innerHTML = html;
        }

        function displayPeersResults(data) {
            let html = `
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS${data.asn} Peering Relationships</h2>
                    <div class="info-grid">
                        <div class="info-card">
                            <h4>IPv4 Peers</h4>
                            <p>${data.ipv4_peer_count}</p>
                        </div>
                        <div class="info-card">
                            <h4>IPv6 Peers</h4>
                            <p>${data.ipv6_peer_count}</p>
                        </div>
                    </div>
            `;

            if (data.ipv4_peer_count > 0) {
                html += '<h3 style="margin-top: 2rem; color: var(--primary-color);">IPv4 Peers</h3><div class="peer-list">';
                data.ipv4_peers.forEach(peer => {
                    html += `
                        <div class="peer-item">
                            <div>
                                <strong>AS${peer.asn}</strong> - ${peer.name || 'Unknown'}
                                <br><small style="color: var(--text-secondary);">${peer.description || ''}</small>
                            </div>
                            <div>${peer.country_code || ''}</div>
                        </div>
                    `;
                });
                html += '</div>';
            }

            html += '</div>';
            document.getElementById('peers-results').innerHTML = html;
        }

        function displayUpstreamsResults(data) {
            let html = `
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS${data.asn} Transit Providers</h2>
                    <div class="info-grid">
                        <div class="info-card">
                            <h4>IPv4 Upstreams</h4>
                            <p>${data.ipv4_upstream_count}</p>
                        </div>
                        <div class="info-card">
                            <h4>IPv6 Upstreams</h4>
                            <p>${data.ipv6_upstream_count}</p>
                        </div>
                    </div>
            `;

            if (data.ipv4_upstream_count > 0) {
                html += '<h3 style="margin-top: 2rem; color: var(--primary-color);">IPv4 Transit Providers</h3><div class="peer-list">';
                data.ipv4_upstreams.forEach(upstream => {
                    html += `
                        <div class="peer-item">
                            <div>
                                <strong>AS${upstream.asn}</strong> - ${upstream.name || 'Unknown'}
                                <br><small style="color: var(--text-secondary);">${upstream.description || ''}</small>
                            </div>
                            <div>${upstream.country_code || ''}</div>
                        </div>
                    `;
                });
                html += '</div>';
            }

            html += '</div>';
            document.getElementById('upstreams-results').innerHTML = html;
        }

        function displayDownstreamsResults(data) {
            let html = `
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">AS${data.asn} Customers</h2>
                    <div class="info-grid">
                        <div class="info-card">
                            <h4>IPv4 Downstreams</h4>
                            <p>${data.ipv4_downstream_count}</p>
                        </div>
                        <div class="info-card">
                            <h4>IPv6 Downstreams</h4>
                            <p>${data.ipv6_downstream_count}</p>
                        </div>
                    </div>
            `;

            if (data.ipv4_downstream_count > 0) {
                html += '<h3 style="margin-top: 2rem; color: var(--primary-color);">IPv4 Customers</h3><div class="peer-list">';
                data.ipv4_downstreams.forEach(downstream => {
                    html += `
                        <div class="peer-item">
                            <div>
                                <strong>AS${downstream.asn}</strong> - ${downstream.name || 'Unknown'}
                                <br><small style="color: var(--text-secondary);">${downstream.description || ''}</small>
                            </div>
                            <div>${downstream.country_code || ''}</div>
                        </div>
                    `;
                });
                html += '</div>';
            }

            html += '</div>';
            document.getElementById('downstreams-results').innerHTML = html;
        }

        function displaySearchResults(data) {
            const results = data.results || {};
            const allResults = [
                ...(results.asns || []),
                ...(results.ipv4_prefixes || []),
                ...(results.ipv6_prefixes || [])
            ];

            if (allResults.length === 0) {
                document.getElementById('search-results').innerHTML = `
                    <div class="feature-card">
                        <p style="text-align: center; color: var(--text-secondary);">No results found</p>
                    </div>
                `;
                return;
            }

            let html = '<div class="feature-card"><h2 style="margin-bottom: 1.5rem;">Search Results</h2>';

            allResults.forEach(result => {
                html += `
                    <div class="search-result">
                        <strong>${result.asn ? 'AS' + result.asn : result.prefix}</strong>
                        ${result.name ? ' - ' + result.name : ''}
                        <br><small style="color: var(--text-secondary);">${result.description || ''}</small>
                        ${result.country_code ? ` <span class="badge badge-success">${result.country_code}</span>` : ''}
                    </div>
                `;
            });

            html += '</div>';
            document.getElementById('search-results').innerHTML = html;
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
