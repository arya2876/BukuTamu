/**
 * Input Page JavaScript
 * Buku Tamu Application
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('guestForm');
    const formSection = document.getElementById('form-section');
    const qrSection = document.getElementById('qr-section');
    const submitBtn = document.getElementById('submitBtn');

    let currentGuest = null;
    let currentQRCanvas = null;

    // Form validation on input
    form.querySelectorAll('.form-control').forEach(field => {
        field.addEventListener('blur', () => validateField(field));
        field.addEventListener('input', () => {
            if (field.classList.contains('is-invalid')) {
                validateField(field);
            }
        });
    });

    // Form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!validateForm(form)) {
            showToast('Periksa kembali input Anda', 'warning');
            return;
        }

        const guestData = {
            nama: document.getElementById('nama').value.trim(),
            email: document.getElementById('email').value.trim(),
            telepon: document.getElementById('telepon').value.trim(),
            pesan: document.getElementById('pesan').value.trim()
        };

        // Show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';

        const result = await GuestAPI.create(guestData);

        if (result.success) {
            currentGuest = result.data;
            showQRResult(currentGuest);
            showToast('Tamu berhasil ditambahkan!', 'success');
        } else {
            showToast(result.message || 'Gagal menambahkan tamu', 'danger');
        }

        // Reset button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Simpan & Generate QR Code';
    });

    // Show QR Result
    async function showQRResult(guest) {
        // Hide form, show QR section
        formSection.style.display = 'none';
        qrSection.style.display = 'block';

        // Populate guest info
        document.getElementById('guestName').textContent = guest.nama;
        document.getElementById('guestEmail').textContent = guest.email;
        document.getElementById('qrCodeText').textContent = guest.qrCode;

        // Generate QR Code
        const qrContainer = document.getElementById('qrcode');
        currentQRCanvas = await generateQRCode(qrContainer, guest.qrCode, 250);

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Download QR Code
    document.getElementById('downloadQR').addEventListener('click', () => {
        if (currentQRCanvas && currentGuest) {
            downloadQRCode(currentQRCanvas, `QR_${currentGuest.nama.replace(/\s+/g, '_')}`);
            showToast('QR Code berhasil di-download!', 'success');
        }
    });

    // Add Another Guest
    document.getElementById('addAnother').addEventListener('click', () => {
        // Reset and show form
        form.reset();
        resetValidation(form);
        currentGuest = null;
        currentQRCanvas = null;

        qrSection.style.display = 'none';
        formSection.style.display = 'block';

        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Reset button
    document.getElementById('resetBtn').addEventListener('click', () => {
        resetValidation(form);
    });
});
