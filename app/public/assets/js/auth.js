// VeriBits Authentication

// Login form handler
const loginForm = document.getElementById('login-form');
if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        try {
            const data = await apiRequest('/auth/login', {
                method: 'POST',
                body: JSON.stringify({ email, password })
            });

            if (data.token) {
                setAuthToken(data.token);
                localStorage.setItem('veribits_user', JSON.stringify(data.user));
                window.location.href = '/dashboard.html';
            }
        } catch (error) {
            showAlert(error.message || 'Login failed', 'error');
        }
    });
}

// Signup form handler
const signupForm = document.getElementById('signup-form');
if (signupForm) {
    signupForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        if (password !== confirmPassword) {
            showAlert('Passwords do not match', 'error');
            return;
        }

        try {
            const data = await apiRequest('/auth/register', {
                method: 'POST',
                body: JSON.stringify({ email, password })
            });

            if (data.token) {
                setAuthToken(data.token);
                localStorage.setItem('veribits_user', JSON.stringify(data.user));
                showAlert('Account created successfully!', 'success');
                setTimeout(() => {
                    window.location.href = '/dashboard.html';
                }, 1500);
            }
        } catch (error) {
            showAlert(error.message || 'Registration failed', 'error');
        }
    });
}

// Reset password form handler
const resetForm = document.getElementById('reset-form');
if (resetForm) {
    resetForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;

        try {
            await apiRequest('/auth/reset-password', {
                method: 'POST',
                body: JSON.stringify({ email })
            });

            showAlert('Password reset email sent! Check your inbox.', 'success');
        } catch (error) {
            showAlert(error.message || 'Reset failed', 'error');
        }
    });
}
