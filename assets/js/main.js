// =============================================
// Sistem Keuangan Kontrakan - Main JS
// =============================================

document.addEventListener('DOMContentLoaded', () => {

    // --- Sidebar Toggle (Mobile) ---
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    // --- Auto-dismiss alerts ---
    document.querySelectorAll('.alert[data-dismiss]').forEach(el => {
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(-8px)';
            el.style.transition = 'all 0.3s';
            setTimeout(() => el.remove(), 300);
        }, parseInt(el.dataset.dismiss) || 4000);
    });

    // --- Modal logic ---
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.modalOpen;
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('active');
        });
    });

    document.querySelectorAll('[data-modal-close], .modal-overlay').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el) {
                const overlay = el.closest('.modal-overlay') || document.getElementById(el.dataset.modalClose);
                if (overlay) overlay.classList.remove('active');
            }
        });
    });

    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-overlay')?.classList.remove('active');
        });
    });

    // --- Toggle password visibility ---
    document.querySelectorAll('.toggle-pw').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.previousElementSibling;
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                btn.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // --- Confirm delete dialogs ---
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const msg = el.dataset.confirm || 'Apakah Anda yakin?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // --- Number formatting input ---
    document.querySelectorAll('input[data-rupiah]').forEach(input => {
        input.addEventListener('input', () => {
            let val = input.value.replace(/\D/g, '');
            input.value = val;
        });
    });

    // Active nav link highlight
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.endsWith(href.split('/').pop())) {
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        }
    });

    // --- Fade-in page elements ---
    document.querySelectorAll('.fade-in').forEach((el, i) => {
        el.style.animationDelay = `${i * 60}ms`;
    });
});

// =============================================
// Toast Notification
// =============================================
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fa-solid ${icons[type] || icons.info} toast-icon"></i>
        <span class="toast-msg">${message}</span>
    `;
    toast.addEventListener('click', () => removeToast(toast));
    container.appendChild(toast);

    setTimeout(() => removeToast(toast), 4000);
}

function removeToast(toast) {
    toast.classList.add('removing');
    setTimeout(() => toast.remove(), 300);
}

// =============================================
// Format Rupiah
// =============================================
function formatRupiah(num) {
    return 'Rp ' + parseInt(num || 0).toLocaleString('id-ID');
}

// =============================================
// Chart Helpers
// =============================================
function createGradient(ctx, color1, color2) {
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, color1);
    gradient.addColorStop(1, color2);
    return gradient;
}
