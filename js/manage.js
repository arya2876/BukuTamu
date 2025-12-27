/**
 * Manage Page JavaScript
 * Buku Tamu Application
 */

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('guestTableBody');
    const emptyState = document.getElementById('emptyState');
    const guestTable = document.getElementById('guestTable');
    const searchInput = document.getElementById('searchInput');

    // Modals
    const qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    let guests = [];
    let currentDeleteId = null;
    let currentQRCanvas = null;
    let searchTimeout = null;

    // Initial load
    loadGuests();
    loadStats();

    // Search functionality
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadGuests(e.target.value);
        }, 300);
    });

    // Load guests
    async function loadGuests(search = '') {
        guests = await GuestAPI.getAll(search);
        renderTable();
    }

    // Load statistics
    async function loadStats() {
        const stats = await GuestAPI.getStats();
        if (stats) {
            document.getElementById('totalCount').textContent = stats.total;
            document.getElementById('checkedInCount').textContent = stats.checkedIn;
            document.getElementById('pendingCount').textContent = stats.pending;
        }
    }

    // Render table
    function renderTable() {
        if (guests.length === 0) {
            tableBody.innerHTML = '';
            emptyState.classList.add('show');
            guestTable.style.display = 'none';
            return;
        }

        emptyState.classList.remove('show');
        guestTable.style.display = 'table';

        tableBody.innerHTML = guests.map((guest, index) => `
            <tr data-id="${guest.id}">
                <td>${index + 1}</td>
                <td><strong>${escapeHtml(guest.nama)}</strong></td>
                <td>
                    <a href="mailto:${escapeHtml(guest.email)}" class="text-info text-decoration-none">
                        ${escapeHtml(guest.email)}
                    </a>
                </td>
                <td>${escapeHtml(guest.telepon)}</td>
                <td>
                    <span class="badge ${guest.status === 'checked_in' ? 'bg-success' : 'bg-warning'}">
                        ${guest.status === 'checked_in' ? 'Hadir' : 'Pending'}
                    </span>
                </td>
                <td><small class="text-muted">${formatDate(guest.tanggal)}</small></td>
                <td>
                    <button class="btn btn-info btn-action" data-action="qr" data-id="${guest.id}" title="QR Code">
                        <i class="fas fa-qrcode"></i>
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

    // Table action handler
    tableBody.addEventListener('click', async (e) => {
        const button = e.target.closest('button[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const id = parseInt(button.dataset.id);
        const guest = guests.find(g => g.id === id);

        if (!guest) return;

        switch (action) {
            case 'qr':
                showQRModal(guest);
                break;
            case 'edit':
                showEditModal(guest);
                break;
            case 'delete':
                showDeleteModal(guest);
                break;
        }
    });

    // Show QR Modal
    async function showQRModal(guest) {
        document.getElementById('modalGuestName').textContent = guest.nama;
        document.getElementById('modalQRText').textContent = guest.qrCode;

        const qrContainer = document.getElementById('modalQRCode');
        currentQRCanvas = await generateQRCode(qrContainer, guest.qrCode, 200);

        qrModal.show();
    }

    // Download QR from modal
    document.getElementById('modalDownloadQR').addEventListener('click', () => {
        const guestName = document.getElementById('modalGuestName').textContent;
        if (currentQRCanvas) {
            downloadQRCode(currentQRCanvas, `QR_${guestName.replace(/\s+/g, '_')}`);
            showToast('QR Code berhasil di-download!', 'success');
        }
    });

    // Show Edit Modal
    function showEditModal(guest) {
        document.getElementById('editId').value = guest.id;
        document.getElementById('editNama').value = guest.nama;
        document.getElementById('editEmail').value = guest.email;
        document.getElementById('editTelepon').value = guest.telepon;
        document.getElementById('editPesan').value = guest.pesan;

        resetValidation(document.getElementById('editForm'));
        editModal.show();
    }

    // Edit form submission
    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const form = e.target;
        if (!validateForm(form)) {
            showToast('Periksa kembali input Anda', 'warning');
            return;
        }

        const id = document.getElementById('editId').value;
        const data = {
            nama: document.getElementById('editNama').value.trim(),
            email: document.getElementById('editEmail').value.trim(),
            telepon: document.getElementById('editTelepon').value.trim(),
            pesan: document.getElementById('editPesan').value.trim()
        };

        const result = await GuestAPI.update(id, data);

        if (result.success) {
            editModal.hide();
            showToast('Data tamu berhasil diperbarui!', 'success');
            loadGuests(searchInput.value);
        } else {
            showToast(result.message || 'Gagal memperbarui data', 'danger');
        }
    });

    // Show Delete Modal
    function showDeleteModal(guest) {
        currentDeleteId = guest.id;
        document.getElementById('deleteGuestName').textContent = guest.nama;
        deleteModal.show();
    }

    // Confirm Delete
    document.getElementById('confirmDelete').addEventListener('click', async () => {
        if (!currentDeleteId) return;

        const result = await GuestAPI.delete(currentDeleteId);

        if (result.success) {
            deleteModal.hide();
            showToast('Data tamu berhasil dihapus!', 'success');
            loadGuests(searchInput.value);
            loadStats();
        } else {
            showToast(result.message || 'Gagal menghapus data', 'danger');
        }

        currentDeleteId = null;
    });
});
