/**
 * Shared JavaScript Functions
 * Buku Tamu Application
 */

// API Base URL
const API_URL = 'api/guests.php';

// ========================================
// Guest API Class
// ========================================
const GuestAPI = {
    // Get all guests
    async getAll(search = '') {
        try {
            const url = search ? `${API_URL}?search=${encodeURIComponent(search)}` : API_URL;
            const response = await fetch(url);
            const result = await response.json();
            return result.success ? result.data : [];
        } catch (error) {
            console.error('Error fetching guests:', error);
            return [];
        }
    },

    // Get single guest
    async getById(id) {
        try {
            const response = await fetch(`${API_URL}?action=single&id=${id}`);
            const result = await response.json();
            return result.success ? result.data : null;
        } catch (error) {
            console.error('Error fetching guest:', error);
            return null;
        }
    },

    // Get statistics
    async getStats() {
        try {
            const response = await fetch(`${API_URL}?action=stats`);
            const result = await response.json();
            return result.success ? result.data : null;
        } catch (error) {
            console.error('Error fetching stats:', error);
            return null;
        }
    },

    // Create new guest
    async create(data) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating guest:', error);
            return { success: false, message: 'Network error' };
        }
    },

    // Update guest
    async update(id, data) {
        try {
            const response = await fetch(API_URL, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, ...data })
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating guest:', error);
            return { success: false, message: 'Network error' };
        }
    },

    // Delete guest
    async delete(id) {
        try {
            const response = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
            return await response.json();
        } catch (error) {
            console.error('Error deleting guest:', error);
            return { success: false, message: 'Network error' };
        }
    },

    // Verify QR Code
    async verify(code) {
        try {
            const response = await fetch(`${API_URL}?action=verify&code=${encodeURIComponent(code)}`);
            return await response.json();
        } catch (error) {
            console.error('Error verifying QR:', error);
            return { success: false, message: 'Network error' };
        }
    },

    // Check-in guest
    async checkIn(id) {
        try {
            const response = await fetch(`${API_URL}?action=checkin`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            return await response.json();
        } catch (error) {
            console.error('Error checking in:', error);
            return { success: false, message: 'Network error' };
        }
    }
};

// ========================================
// Form Validation
// ========================================
function validateField(field) {
    const name = field.id.replace('edit', '').toLowerCase();
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';

    if (name === 'nama' || field.id === 'nama' || field.id === 'editNama') {
        if (!value) {
            isValid = false;
            errorMessage = 'Nama harus diisi';
        } else if (value.length < 3) {
            isValid = false;
            errorMessage = 'Nama minimal 3 karakter';
        }
    } else if (name === 'email' || field.id === 'email' || field.id === 'editEmail') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!value) {
            isValid = false;
            errorMessage = 'Email harus diisi';
        } else if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Format email tidak valid';
        }
    } else if (name === 'telepon' || field.id === 'telepon' || field.id === 'editTelepon') {
        const phoneRegex = /^[0-9]{10,13}$/;
        if (!value) {
            isValid = false;
            errorMessage = 'Telepon harus diisi';
        } else if (!phoneRegex.test(value.replace(/\D/g, ''))) {
            isValid = false;
            errorMessage = 'Telepon harus 10-13 digit';
        }
    } else if (name === 'pesan' || field.id === 'pesan' || field.id === 'editPesan') {
        if (!value) {
            isValid = false;
            errorMessage = 'Pesan harus diisi';
        } else if (value.length < 10) {
            isValid = false;
            errorMessage = 'Pesan minimal 10 karakter';
        }
    }

    // Update UI
    const feedback = field.nextElementSibling?.nextElementSibling;
    if (isValid) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    } else {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = errorMessage;
        }
    }

    return isValid;
}

function validateForm(form) {
    const fields = form.querySelectorAll('.form-control');
    let allValid = true;
    fields.forEach(field => {
        if (!validateField(field)) allValid = false;
    });
    return allValid;
}

function resetValidation(form) {
    const fields = form.querySelectorAll('.form-control');
    fields.forEach(field => {
        field.classList.remove('is-valid', 'is-invalid');
    });
}

// ========================================
// Toast Notifications
// ========================================
function showToast(message, type = 'info') {
    const toast = document.getElementById('liveToast');
    if (!toast) return;

    const toastIcon = document.getElementById('toastIcon');
    const toastTitle = document.getElementById('toastTitle');
    const toastMessage = document.getElementById('toastMessage');

    toast.classList.remove('toast-success', 'toast-danger', 'toast-warning', 'toast-info');
    toast.classList.add(`toast-${type}`);

    const config = {
        success: { icon: 'fa-check-circle', title: 'Berhasil' },
        danger: { icon: 'fa-times-circle', title: 'Error' },
        warning: { icon: 'fa-exclamation-triangle', title: 'Peringatan' },
        info: { icon: 'fa-info-circle', title: 'Info' }
    };

    toastIcon.className = `fas ${config[type]?.icon || config.info.icon} me-2`;
    toastTitle.textContent = config[type]?.title || 'Notifikasi';
    toastMessage.textContent = message;

    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

// ========================================
// Utility Functions
// ========================================
function formatDate(dateString, full = false) {
    const date = new Date(dateString);
    const options = full
        ? { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }
        : { day: '2-digit', month: 'short', year: 'numeric' };
    return date.toLocaleDateString('id-ID', options);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function animateNumber(element, target) {
    if (!element) return;
    const current = parseInt(element.textContent) || 0;
    const increment = target > current ? 1 : -1;
    const duration = 500;
    const steps = Math.abs(target - current);

    if (steps === 0) return;

    const stepDuration = duration / steps;
    let currentValue = current;

    const timer = setInterval(() => {
        currentValue += increment;
        element.textContent = currentValue;
        if (currentValue === target) clearInterval(timer);
    }, Math.max(stepDuration, 30));
}

// ========================================
// QR Code Generation (using qrcodejs)
// ========================================
async function generateQRCode(container, text, size = 200) {
    try {
        container.innerHTML = '';

        // Create QR code using qrcodejs library
        new QRCode(container, {
            text: text,
            width: size,
            height: size,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });

        // Wait a bit for the canvas to be created
        await new Promise(resolve => setTimeout(resolve, 100));

        // Find the canvas element
        const canvas = container.querySelector('canvas');
        return canvas;
    } catch (error) {
        console.error('Error generating QR code:', error);
        container.innerHTML = '<p class="text-danger">Error generating QR Code</p>';
        return null;
    }
}

function downloadQRCode(canvas, filename) {
    if (!canvas) return;

    // Create a white background version
    const downloadCanvas = document.createElement('canvas');
    const ctx = downloadCanvas.getContext('2d');
    downloadCanvas.width = canvas.width + 40;
    downloadCanvas.height = canvas.height + 40;

    // White background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, downloadCanvas.width, downloadCanvas.height);

    // Draw QR code centered
    ctx.drawImage(canvas, 20, 20);

    // Download
    const link = document.createElement('a');
    link.download = `${filename}.png`;
    link.href = downloadCanvas.toDataURL('image/png');
    link.click();
}

// ========================================
// Navbar Scroll Effect
// ========================================
document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.querySelector('.glass-navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
});
