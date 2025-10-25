// VeriBits Main JavaScript
const API_BASE = window.location.hostname === 'localhost' ? 'http://localhost/api/v1' : '/api/v1';

// Check authentication status
function isAuthenticated() {
    return localStorage.getItem('veribits_token') !== null;
}

// Get auth token
function getAuthToken() {
    return localStorage.getItem('veribits_token');
}

// Set auth token
function setAuthToken(token) {
    localStorage.setItem('veribits_token', token);
}

// Remove auth token
function removeAuthToken() {
    localStorage.removeItem('veribits_token');
    localStorage.removeItem('veribits_user');
}

// Logout function
function logout() {
    removeAuthToken();
    window.location.href = '/login.html';
}

// API request helper
async function apiRequest(endpoint, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };

    const token = getAuthToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    try {
        const response = await fetch(`${API_BASE}${endpoint}`, {
            ...options,
            headers
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error?.message || 'Request failed');
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Upload file helper
async function uploadFile(endpoint, formData) {
    const headers = {};
    const token = getAuthToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    try {
        const response = await fetch(`${API_BASE}${endpoint}`, {
            method: 'POST',
            headers,
            body: formData
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error?.message || 'Upload failed');
        }

        return data;
    } catch (error) {
        console.error('Upload Error:', error);
        throw error;
    }
}

// Show alert message
function showAlert(message, type = 'success') {
    const container = document.getElementById('alert-container');
    if (!container) return;

    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;

    container.innerHTML = '';
    container.appendChild(alert);

    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showAlert('Copied to clipboard!', 'success');
    }).catch(err => {
        showAlert('Failed to copy', 'error');
    });
}

// Social login (placeholder)
function socialLogin(provider) {
    showAlert(`Social login with ${provider} coming soon!`, 'warning');
}

// Protected page check
function requireAuth() {
    if (!isAuthenticated()) {
        window.location.href = '/login.html';
    }
}

// Check if on protected page
if (window.location.pathname.includes('dashboard') || window.location.pathname.includes('settings')) {
    requireAuth();
}
