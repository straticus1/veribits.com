<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/cli.php">CLI</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li><a href="/login.php">Login</a></li>
            </ul>
        </div>
    </nav>

    <div class="form-container">
        <h1 style="text-align: center; margin-bottom: 2rem;">Create Your Account</h1>

        <div id="alert-container"></div>

        <form id="signup-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8">
                <small style="color: var(--text-secondary);">Minimum 8 characters</small>
            </div>

            <div class="form-group">
                <label for="confirm-password">Confirm Password</label>
                <input type="password" id="confirm-password" name="confirm-password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Sign Up</button>
        </form>

        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">Or sign up with</p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button class="btn btn-secondary" onclick="socialLogin('google')">🔍 Google</button>
                <button class="btn btn-secondary" onclick="socialLogin('github')">🐙 GitHub</button>
            </div>
        </div>

        <p style="text-align: center; margin-top: 2rem; color: var(--text-secondary);">
            Already have an account? <a href="/login.php" style="color: var(--primary-color);">Log in</a>
        </p>
    </div>

    <script src="/assets/js/auth.js"></script>
</body>
</html>
