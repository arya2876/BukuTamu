/**
 * Buku Tamu (Guest Book) Application
 * CRUD Operations with PHP/MySQL API
 */

// API Base URL
const API_URL = 'api/guests.php';

// ========================================
// Guest Manager Class (API-based)
// ========================================
class GuestManager {
    constructor() {
        this.guests = [];
    }

    // Fetch all guests from API
    async loadGuests() {
        try {
            const response = await fetch(API_URL);
            const result = await response.json();

            if (result.success) {
                this.guests = result.data;
                return this.guests;
            } else {
                console.error('Error loading guests:', result.message);
                return [];
            }
        } catch (error) {
            console.error('Network error:', error);
            return [];
        }
    }

    // Create - Add new guest
    async addGuest(guestData) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(guestData)
            });

            const result = await response.json();

            if (result.success) {
                this.guests.unshift(result.data);
                return { success: true, data: result.data };
            } else {
                return { success: false, errors: result.errors, message: result.message };
            }
        } catch (error) {
            console.error('Network error:', error);
            return { success: false, message: 'Network error' };
        }
    }

    // Read - Get all guests
    getAllGuests() {
        return this.guests;
    }

    // Read - Get guest by ID
    getGuestById(id) {
        return this.guests.find(guest => guest.id == id);
    }

    // Update - Edit guest
    async updateGuest(id, guestData) {
        try {
            const response = await fetch(API_URL, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id, ...guestData })
            });

            const result = await response.json();

            if (result.success) {
                const index = this.guests.findIndex(guest => guest.id == id);
                if (index !== -1) {
                    this.guests[index] = result.data;
                }
                return { success: true, data: result.data };
            } else {
                return { success: false, errors: result.errors, message: result.message };
            }
        } catch (error) {
            console.error('Network error:', error);
            return { success: false, message: 'Network error' };
        }
    }

    // Delete - Remove guest
    async deleteGuest(id) {
        try {
            const response = await fetch(`${API_URL}?id=${id}`, {
                method: 'DELETE'
            });

            const result = await response.json();

            if (result.success) {
                this.guests = this.guests.filter(guest => guest.id != id);
                return { success: true };
            } else {
                return { success: false, message: result.message };
            }
        } catch (error) {
            console.error('Network error:', error);
            return { success: false, message: 'Network error' };
        }
    }

    // Search guests
    async searchGuests(query) {
        try {
            const response = await fetch(`${API_URL}?search=${encodeURIComponent(query)}`);
            const result = await response.json();

            if (result.success) {
                return result.data;
            } else {
                return [];
            }
        } catch (error) {
            console.error('Network error:', error);
            return [];
        }
    }

    // Get statistics
    async getStats() {
        try {
            const response = await fetch(`${API_URL}?action=stats`);
            const result = await response.json();

            if (result.success) {
                return result.data;
            } else {
                return { total: 0, today: 0 };
            }
        } catch (error) {
            console.error('Network error:', error);
            return { total: 0, today: 0 };
        }
    }
}

// ========================================
// Validation Class
// ========================================
class FormValidator {
    constructor(form) {
        this.form = form;
        this.errors = {};
    }

    // Validate single field
    validateField(field) {
        const name = field.id;
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        switch (name) {
            case 'nama':
                if (!value) {
                    isValid = false;
                    errorMessage = 'Nama harus diisi';
                } else if (value.length < 3) {
                    isValid = false;
                    errorMessage = 'Nama minimal 3 karakter';
                } else if (value.length > 100) {
                    isValid = false;
                    errorMessage = 'Nama maksimal 100 karakter';
                }
                break;

            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!value) {
                    isValid = false;
                    errorMessage = 'Email harus diisi';
                } else if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Format email tidak valid';
                }
                break;

            case 'telepon':
                const phoneRegex = /^[0-9]{10,13}$/;
                if (!value) {
                    isValid = false;
                    errorMessage = 'Nomor telepon harus diisi';
                } else if (!phoneRegex.test(value.replace(/\D/g, ''))) {
                    isValid = false;
                    errorMessage = 'Nomor telepon harus 10-13 digit angka';
                }
                break;

            case 'pesan':
                if (!value) {
                    isValid = false;
                    errorMessage = 'Pesan harus diisi';
                } else if (value.length < 10) {
                    isValid = false;
                    errorMessage = 'Pesan minimal 10 karakter';
                } else if (value.length > 500) {
                    isValid = false;
                    errorMessage = 'Pesan maksimal 500 karakter';
                }
                break;
        }

        // Update UI
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            delete this.errors[name];
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            this.errors[name] = errorMessage;

            // Update error message
            const feedback = field.nextElementSibling?.nextElementSibling;
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = errorMessage;
            }
        }

        return isValid;
    }

    // Validate all fields
    validateAll() {
        const fields = this.form.querySelectorAll('.form-control');
        let allValid = true;

        fields.forEach(field => {
            if (!this.validateField(field)) {
                allValid = false;
            }
        });

        return allValid;
    }

    // Reset validation state
    reset() {
        this.errors = {};
        const fields = this.form.querySelectorAll('.form-control');
        fields.forEach(field => {
            field.classList.remove('is-valid', 'is-invalid');
        });
    }
}

// ========================================
// UI Controller
// ========================================
class UIController {
    constructor(guestManager) {
        this.guestManager = guestManager;
        this.currentDeleteId = null;
        this.isEditMode = false;
        this.searchTimeout = null;

        // Cache DOM elements
        this.form = document.getElementById('guestForm');
        this.formTitle = document.getElementById('formTitle');
        this.submitBtn = document.getElementById('submitBtn');
        this.resetBtn = document.getElementById('resetBtn');
        this.guestIdInput = document.getElementById('guestId');
        this.tableBody = document.getElementById('guestTableBody');
        this.emptyState = document.getElementById('emptyState');
        this.searchInput = document.getElementById('searchInput');
        this.totalGuests = document.getElementById('totalGuests');
        this.todayGuests = document.getElementById('todayGuests');

        // Modals
        this.detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
        this.deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

        // Initialize validator
        this.validator = new FormValidator(this.form);

        // Initialize
        this.init();
    }

    async init() {
        this.showLoading(true);
        await this.guestManager.loadGuests();
        this.bindEvents();
        this.renderTable();
        await this.updateStats();
        this.initNavbarScroll();
        this.showLoading(false);
    }

    // Show/hide loading state
    showLoading(show) {
        if (show) {
            this.submitBtn.disabled = true;
            this.submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
        } else {
            this.submitBtn.disabled = false;
            this.submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Simpan';
        }
    }

    // Bind all event listeners
    bindEvents() {
        // Form submit
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Reset button
        this.resetBtn.addEventListener('click', () => this.resetForm());

        // Real-time validation
        const fields = this.form.querySelectorAll('.form-control');
        fields.forEach(field => {
            field.addEventListener('blur', () => this.validator.validateField(field));
            field.addEventListener('input', () => {
                if (field.classList.contains('is-invalid')) {
                    this.validator.validateField(field);
                }
            });
        });

        // Search with debounce
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.handleSearch(e.target.value);
            }, 300);
        });

        // Confirm delete
        document.getElementById('confirmDelete').addEventListener('click', () => this.confirmDelete());

        // Table action buttons (event delegation)
        this.tableBody.addEventListener('click', (e) => this.handleTableAction(e));
    }

    // Handle form submission
    async handleSubmit(e) {
        e.preventDefault();

        if (!this.validator.validateAll()) {
            this.showToast('Periksa kembali input Anda', 'warning');
            return;
        }

        const guestData = {
            nama: document.getElementById('nama').value,
            email: document.getElementById('email').value,
            telepon: document.getElementById('telepon').value,
            pesan: document.getElementById('pesan').value
        };

        this.showLoading(true);

        if (this.isEditMode) {
            // Update existing guest
            const id = this.guestIdInput.value;
            const result = await this.guestManager.updateGuest(id, guestData);

            if (result.success) {
                this.showToast('Data tamu berhasil diperbarui!', 'success');
                this.resetForm();
                this.renderTable();
                await this.updateStats();
            } else {
                this.showToast(result.message || 'Gagal memperbarui data', 'danger');
            }
        } else {
            // Add new guest
            const result = await this.guestManager.addGuest(guestData);

            if (result.success) {
                this.showToast('Tamu baru berhasil ditambahkan!', 'success');
                this.resetForm();
                this.renderTable();
                await this.updateStats();

                // Scroll to table
                document.getElementById('table-section').scrollIntoView({ behavior: 'smooth' });
            } else {
                this.showToast(result.message || 'Gagal menambahkan tamu', 'danger');
            }
        }

        this.showLoading(false);
    }

    // Reset form to initial state
    resetForm() {
        this.form.reset();
        this.validator.reset();
        this.isEditMode = false;
        this.guestIdInput.value = '';
        this.formTitle.textContent = 'Tambah Tamu Baru';
        this.submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Simpan';
        this.submitBtn.classList.remove('btn-warning');
        this.submitBtn.classList.add('btn-primary');
    }

    // Render table with guests
    renderTable(guests = null) {
        const data = guests || this.guestManager.getAllGuests();

        if (data.length === 0) {
            this.tableBody.innerHTML = '';
            this.emptyState.classList.add('show');
            document.getElementById('guestTable').style.display = 'none';
            return;
        }

        this.emptyState.classList.remove('show');
        document.getElementById('guestTable').style.display = 'table';

        this.tableBody.innerHTML = data.map((guest, index) => `
            <tr data-id="${guest.id}">
                <td>${index + 1}</td>
                <td>
                    <strong>${this.escapeHtml(guest.nama)}</strong>
                </td>
                <td>
                    <a href="mailto:${this.escapeHtml(guest.email)}" class="text-info text-decoration-none">
                        ${this.escapeHtml(guest.email)}
                    </a>
                </td>
                <td>${this.escapeHtml(guest.telepon)}</td>
                <td>
                    <small class="text-muted">${this.formatDate(guest.tanggal)}</small>
                </td>
                <td>
                    <button class="btn btn-info btn-action" data-action="view" data-id="${guest.id}" title="Lihat Detail">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-warning btn-action" data-action="edit" data-id="${guest.id}" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-action" data-action="delete" data-id="${guest.id}" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // Handle table action buttons
    handleTableAction(e) {
        const button = e.target.closest('button[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const id = button.dataset.id;

        switch (action) {
            case 'view':
                this.showGuestDetail(id);
                break;
            case 'edit':
                this.editGuest(id);
                break;
            case 'delete':
                this.showDeleteConfirmation(id);
                break;
        }
    }

    // Show guest detail modal
    showGuestDetail(id) {
        const guest = this.guestManager.getGuestById(id);
        if (!guest) return;

        const content = document.getElementById('detailContent');
        content.innerHTML = `
            <div class="detail-item">
                <i class="fas fa-user"></i>
                <div class="detail-content">
                    <div class="detail-label">Nama</div>
                    <div class="detail-value">${this.escapeHtml(guest.nama)}</div>
                </div>
            </div>
            <div class="detail-item">
                <i class="fas fa-envelope"></i>
                <div class="detail-content">
                    <div class="detail-label">Email</div>
                    <div class="detail-value">${this.escapeHtml(guest.email)}</div>
                </div>
            </div>
            <div class="detail-item">
                <i class="fas fa-phone"></i>
                <div class="detail-content">
                    <div class="detail-label">Telepon</div>
                    <div class="detail-value">${this.escapeHtml(guest.telepon)}</div>
                </div>
            </div>
            <div class="detail-item">
                <i class="fas fa-calendar"></i>
                <div class="detail-content">
                    <div class="detail-label">Tanggal</div>
                    <div class="detail-value">${this.formatDate(guest.tanggal, true)}</div>
                </div>
            </div>
            <div class="detail-item">
                <i class="fas fa-comment"></i>
                <div class="detail-content">
                    <div class="detail-label">Pesan</div>
                    <div class="detail-value">${this.escapeHtml(guest.pesan)}</div>
                </div>
            </div>
        `;

        this.detailModal.show();
    }

    // Edit guest - populate form
    editGuest(id) {
        const guest = this.guestManager.getGuestById(id);
        if (!guest) return;

        this.isEditMode = true;
        this.guestIdInput.value = guest.id;

        document.getElementById('nama').value = guest.nama;
        document.getElementById('email').value = guest.email;
        document.getElementById('telepon').value = guest.telepon;
        document.getElementById('pesan').value = guest.pesan;

        this.formTitle.textContent = 'Edit Data Tamu';
        this.submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Update';
        this.submitBtn.classList.remove('btn-primary');
        this.submitBtn.classList.add('btn-warning');

        // Scroll to form
        document.getElementById('form-section').scrollIntoView({ behavior: 'smooth' });

        this.showToast('Silakan edit data tamu', 'info');
    }

    // Show delete confirmation
    showDeleteConfirmation(id) {
        this.currentDeleteId = id;
        this.deleteModal.show();
    }

    // Confirm and execute delete
    async confirmDelete() {
        if (!this.currentDeleteId) return;

        const row = document.querySelector(`tr[data-id="${this.currentDeleteId}"]`);
        if (row) {
            row.classList.add('fade-out');
        }

        this.deleteModal.hide();

        const result = await this.guestManager.deleteGuest(this.currentDeleteId);

        if (result.success) {
            this.showToast('Data tamu berhasil dihapus!', 'success');
            this.renderTable();
            await this.updateStats();
        } else {
            this.showToast(result.message || 'Gagal menghapus data', 'danger');
            // Remove fade-out class if deletion failed
            if (row) {
                row.classList.remove('fade-out');
            }
        }

        this.currentDeleteId = null;
    }

    // Handle search
    async handleSearch(query) {
        if (!query.trim()) {
            await this.guestManager.loadGuests();
            this.renderTable();
            return;
        }

        const results = await this.guestManager.searchGuests(query);
        this.renderTable(results);
    }

    // Update statistics
    async updateStats() {
        const stats = await this.guestManager.getStats();
        this.animateNumber(this.totalGuests, stats.total);
        this.animateNumber(this.todayGuests, stats.today);
    }

    // Animate number counting
    animateNumber(element, target) {
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

            if (currentValue === target) {
                clearInterval(timer);
            }
        }, Math.max(stepDuration, 30));
    }

    // Show toast notification
    showToast(message, type = 'info') {
        const toast = document.getElementById('liveToast');
        const toastIcon = document.getElementById('toastIcon');
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');

        // Remove previous type classes
        toast.classList.remove('toast-success', 'toast-danger', 'toast-warning', 'toast-info');
        toast.classList.add(`toast-${type}`);

        // Set icon and title based on type
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

    // Format date
    formatDate(dateString, full = false) {
        const date = new Date(dateString);
        const options = full
            ? { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }
            : { day: '2-digit', month: 'short', year: 'numeric' };
        return date.toLocaleDateString('id-ID', options);
    }

    // Escape HTML to prevent XSS
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize navbar scroll effect
    initNavbarScroll() {
        const navbar = document.querySelector('.glass-navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
}

// ========================================
// Initialize Application
// ========================================
document.addEventListener('DOMContentLoaded', () => {
    const guestManager = new GuestManager();
    const ui = new UIController(guestManager);

    // Make available globally for debugging
    window.guestApp = { guestManager, ui };

    console.log('🎉 Buku Tamu Application initialized with PHP/MySQL backend!');
});
