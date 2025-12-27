/**
 * Scan Page JavaScript
 * Buku Tamu Application - QR Code Scanner
 */

document.addEventListener('DOMContentLoaded', () => {
    const cameraSelect = document.getElementById('cameraSelect');
    const startBtn = document.getElementById('startScanner');
    const stopBtn = document.getElementById('stopScanner');
    const manualCode = document.getElementById('manualCode');
    const verifyManualBtn = document.getElementById('verifyManual');

    // Result states
    const initialState = document.getElementById('initialState');
    const successState = document.getElementById('successState');
    const checkedInState = document.getElementById('checkedInState');
    const errorState = document.getElementById('errorState');

    let html5QrCode = null;
    let currentGuest = null;
    let isScanning = false;

    // Initialize camera list
    initCameras();

    async function initCameras() {
        try {
            const devices = await Html5Qrcode.getCameras();
            if (devices && devices.length > 0) {
                cameraSelect.innerHTML = '<option value="">Pilih Kamera...</option>';
                devices.forEach(device => {
                    const option = document.createElement('option');
                    option.value = device.id;
                    option.textContent = device.label || `Camera ${device.id}`;
                    cameraSelect.appendChild(option);
                });

                // Auto-select first camera
                if (devices.length === 1) {
                    cameraSelect.value = devices[0].id;
                }
            }
        } catch (error) {
            console.error('Error getting cameras:', error);
            showToast('Tidak dapat mengakses kamera', 'warning');
        }
    }

    // Start Scanner
    startBtn.addEventListener('click', async () => {
        const cameraId = cameraSelect.value;

        if (!cameraId) {
            showToast('Pilih kamera terlebih dahulu', 'warning');
            return;
        }

        try {
            html5QrCode = new Html5Qrcode('reader');

            await html5QrCode.start(
                cameraId,
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                onScanSuccess,
                onScanError
            );

            isScanning = true;
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';
            cameraSelect.disabled = true;

            showToast('Scanner aktif', 'info');
        } catch (error) {
            console.error('Error starting scanner:', error);
            showToast('Gagal memulai scanner: ' + error.message, 'danger');
        }
    });

    // Stop Scanner
    stopBtn.addEventListener('click', stopScanner);

    async function stopScanner() {
        if (html5QrCode && isScanning) {
            try {
                await html5QrCode.stop();
                html5QrCode = null;
                isScanning = false;
            } catch (error) {
                console.error('Error stopping scanner:', error);
            }
        }

        startBtn.style.display = 'inline-block';
        stopBtn.style.display = 'none';
        cameraSelect.disabled = false;
    }

    // On successful scan
    async function onScanSuccess(decodedText) {
        // Stop scanning after success
        await stopScanner();

        // Vibrate on success (mobile)
        if (navigator.vibrate) {
            navigator.vibrate(200);
        }

        // Verify the QR code
        await verifyQRCode(decodedText);
    }

    function onScanError(error) {
        // Ignore scan errors (normal when no QR is detected)
    }

    // Manual verification
    verifyManualBtn.addEventListener('click', async () => {
        const code = manualCode.value.trim();
        if (code) {
            await verifyQRCode(code);
        } else {
            showToast('Masukkan kode QR', 'warning');
        }
    });

    manualCode.addEventListener('keypress', async (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const code = manualCode.value.trim();
            if (code) {
                await verifyQRCode(code);
            }
        }
    });

    // Verify QR Code
    async function verifyQRCode(code) {
        hideAllStates();

        const result = await GuestAPI.verify(code);

        if (result.success) {
            currentGuest = result.data;

            if (currentGuest.status === 'checked_in') {
                // Already checked in
                showCheckedInState(currentGuest);
            } else {
                // Valid, not yet checked in
                showSuccessState(currentGuest);
            }
        } else {
            showErrorState(result.message);
        }
    }

    // Show success state
    function showSuccessState(guest) {
        document.getElementById('resultNama').textContent = guest.nama;
        document.getElementById('resultEmail').textContent = guest.email;
        document.getElementById('resultTelepon').textContent = guest.telepon;
        document.getElementById('resultTanggal').textContent = formatDate(guest.tanggal, true);
        document.getElementById('resultStatus').innerHTML = '<span class="badge bg-warning">Belum Hadir</span>';

        successState.style.display = 'block';

        // Play success sound if available
        playSound('success');
    }

    // Show already checked in state
    function showCheckedInState(guest) {
        document.getElementById('checkedNama').textContent = guest.nama;
        document.getElementById('checkedTime').textContent = guest.checkedInAt
            ? formatDate(guest.checkedInAt, true)
            : 'Sudah check-in';

        checkedInState.style.display = 'block';

        playSound('info');
    }

    // Show error state
    function showErrorState(message) {
        document.getElementById('errorMessage').textContent = message || 'QR Code tidak valid';
        errorState.style.display = 'block';

        playSound('error');
    }

    // Hide all result states
    function hideAllStates() {
        initialState.style.display = 'none';
        successState.style.display = 'none';
        checkedInState.style.display = 'none';
        errorState.style.display = 'none';
    }

    // Reset to initial state
    function resetToInitial() {
        hideAllStates();
        initialState.style.display = 'block';
        currentGuest = null;
        manualCode.value = '';
    }

    // Check-in button
    document.getElementById('checkInBtn').addEventListener('click', async () => {
        if (!currentGuest) return;

        const result = await GuestAPI.checkIn(currentGuest.id);

        if (result.success) {
            showToast(`${currentGuest.nama} berhasil check-in!`, 'success');

            // Update display
            document.getElementById('resultStatus').innerHTML = '<span class="badge bg-success">Sudah Hadir</span>';
            document.getElementById('checkInBtn').disabled = true;
            document.getElementById('checkInBtn').innerHTML = '<i class="fas fa-check me-2"></i>Sudah Check-In';
        } else {
            showToast(result.message || 'Gagal check-in', 'danger');
        }
    });

    // Scan again buttons
    ['scanAgainBtn', 'scanAgainBtn2', 'scanAgainBtn3'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener('click', () => {
                resetToInitial();
                // Re-enable check-in button
                const checkInBtn = document.getElementById('checkInBtn');
                if (checkInBtn) {
                    checkInBtn.disabled = false;
                    checkInBtn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Check-In Tamu';
                }
            });
        }
    });

    // Simple sound feedback
    function playSound(type) {
        // Could implement actual sound here
        // For now, just visual feedback through toast
    }
});
