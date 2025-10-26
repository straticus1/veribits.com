<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/dashboard.php">Dashboard</a></li>
                <li><a href="/settings.php">Settings</a></li>
                <li><a href="#" onclick="logout()">Logout</a></li>
            </ul>
        </div>
    </nav>

    <section class="dashboard">
        <div class="container">
            <h1 style="font-size: 3rem; margin-bottom: 3rem;">Dashboard</h1>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-value" id="scans-remaining">--</div>
                    <div class="stat-label">Scans Remaining</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value" id="total-scans">--</div>
                    <div class="stat-label">Total Scans</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value" id="plan-name">--</div>
                    <div class="stat-label">Current Plan</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value" id="account-status">Active</div>
                    <div class="stat-label">Account Status</div>
                </div>
            </div>

            <div class="feature-card" style="margin-bottom: 2rem;">
                <h2 style="margin-bottom: 1rem;">Recent Scans</h2>
                <div id="recent-scans">
                    <div class="spinner"></div>
                </div>
            </div>

            <div class="feature-card">
                <h2 style="margin-bottom: 1rem;">Quick Actions</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="/tool/file-magic.php" class="btn btn-primary">File Magic Scan</a>
                    <a href="/tool/file-signature.php" class="btn btn-primary">Verify Signature</a>
                    <a href="/tools.php" class="btn btn-secondary">All Tools</a>
                    <a href="/settings.php" class="btn btn-secondary">Settings</a>
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
            <p style="margin-top: 1rem;">
                <a href="/privacy.php" style="color: var(--text-secondary); margin: 0 1rem;">Privacy</a>
                <a href="/terms.php" style="color: var(--text-secondary); margin: 0 1rem;">Terms</a>
                <a href="/support.php" style="color: var(--text-secondary); margin: 0 1rem;">Support</a>
            </p>
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/dashboard.js"></script>
</body>
</html>
