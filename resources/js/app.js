import './bootstrap';
import { initConnectionUi } from './lib/connection';
import Swal from 'sweetalert2';
import {
    createIcons,
    AlertTriangle,
    ArrowDownLeft,
    ArrowLeft,
    BarChart3,
    Bell,
    ChefHat,
    ClipboardCheck,
    Edit2,
    Eye,
    EyeOff,
    FileSpreadsheet,
    FileText,
    LayoutDashboard,
    Lock,
    LogIn,
    LogOut,
    Mail,
    Menu,
    Package,
    PackageSearch,
    Plug,
    PlugZap,
    Plus,
    RefreshCw,
    ShieldCheck,
    Trash2,
    Truck,
    User,
    X,
} from 'lucide';

const icons = {
    AlertTriangle, ArrowDownLeft, ArrowLeft, BarChart3, Bell, ChefHat, ClipboardCheck, Edit2, Eye, EyeOff,
    FileSpreadsheet, FileText, LayoutDashboard, Lock, LogIn, LogOut, Mail, Menu, Package, PackageSearch,
    Plug, PlugZap, Plus, RefreshCw, ShieldCheck, Trash2, Truck, User, X,
};

// Shared SweetAlert2 instance with the app's primary colour.
const Toast = Swal.mixin({ confirmButtonColor: '#4285F4' });

// Globals kept for the inline scripts in the pages.
window.Swal = Toast;
window.lucide = { createIcons: () => createIcons({ icons }) };
window.initLucideIcons = window.lucide.createIcons;

document.addEventListener('DOMContentLoaded', () => {
    window.lucide.createIcons();
    initConnectionUi();

    // Flash messages are passed through data attributes (never interpolated into JS).
    const flash = document.getElementById('flash-data');
    if (flash?.dataset.success) {
        Toast.fire({ icon: 'success', title: 'Berhasil', text: flash.dataset.success });
    }
    if (flash?.dataset.error) {
        Toast.fire({ icon: 'error', title: 'Gagal', text: flash.dataset.error });
    }
});

// <form data-confirm="Pesan..."> asks before submitting (replaces window.confirm).
document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return;

    event.preventDefault();
    const { isConfirmed } = await Toast.fire({
        icon: 'warning',
        title: 'Yakin?',
        text: form.dataset.confirm,
        showCancelButton: true,
        confirmButtonText: 'Ya, lanjutkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#DB4437',
    });

    if (isConfirmed) {
        form.dataset.confirmed = '1';
        form.requestSubmit();
    }
});

/*
 * Double-submit guard. Every POST form disables its submit buttons ("Menyimpan...") once sent.
 * Forms carrying an idempotency token are sent with fetch so a network failure leaves the page
 * (and everything typed in it) untouched: the person can simply press the button again, and the
 * same token makes the retry safe on the server.
 */
function lockForm(form, submitter) {
    const buttons = [...form.querySelectorAll('button[type="submit"]'), ...document.querySelectorAll(`button[type="submit"][form="${form.id}"]`)];
    if (submitter && !buttons.includes(submitter)) buttons.push(submitter);

    buttons.forEach((button) => {
        if (button.dataset.lockedHtml !== undefined) return;
        button.dataset.lockedHtml = button.innerHTML;
        button.disabled = true;
        if (button === submitter || buttons.length === 1) button.textContent = 'Menyimpan...';
    });

    return () => buttons.forEach((button) => {
        if (button.dataset.lockedHtml === undefined) return;
        button.innerHTML = button.dataset.lockedHtml;
        delete button.dataset.lockedHtml;
        button.disabled = false;
        window.lucide.createIcons();
    });
}

function errorText(payload) {
    if (payload?.errors) return Object.values(payload.errors).flat().join('\n');
    return payload?.message ?? 'Terjadi kesalahan. Coba lagi.';
}

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.method.toLowerCase() !== 'post' || form.hasAttribute('data-no-lock')) return;

    if (!form.querySelector('input[name="_idempotency_token"]')) {
        // Plain submit: lock after the browser has collected the form data (a disabled submitter is not sent).
        const submitter = event.submitter;
        setTimeout(() => lockForm(form, submitter), 0);
        return;
    }

    event.preventDefault();
    const unlock = lockForm(form, event.submitter);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form, event.submitter),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Form-Fetch': '1' },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => null);

        if (response.ok && payload?.redirect) {
            window.location.assign(payload.redirect);
            return; // keep the buttons locked while the next page loads
        }

        unlock();
        if (response.status === 419) {
            Toast.fire({ icon: 'warning', title: 'Sesi kedaluwarsa', text: 'Muat ulang halaman, lalu coba lagi. Isian Anda perlu diperiksa ulang.' });
        } else {
            Toast.fire({ icon: 'error', title: 'Tidak tersimpan', text: errorText(payload) });
        }
    } catch {
        // Network failure: stay on the page with everything intact.
        unlock();
        Toast.fire({ icon: 'warning', title: 'Koneksi bermasalah', text: 'Data belum tersimpan. Isian Anda masih utuh, coba kirim lagi.' });
    }
});

// Coming back with the browser's Back button must not leave a page stuck on "Menyimpan...".
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('button[data-locked-html]').forEach((button) => {
        button.innerHTML = button.dataset.lockedHtml;
        delete button.dataset.lockedHtml;
        button.disabled = false;
    });
    window.lucide.createIcons();
});

// <button data-copy="text"> copies to the clipboard.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;

    try {
        await navigator.clipboard.writeText(button.dataset.copy);
        Toast.fire({ icon: 'success', title: 'Disalin', timer: 1200, showConfirmButton: false });
    } catch {
        Toast.fire({ icon: 'error', title: 'Gagal menyalin' });
    }
});

// Sidebar: open by default on desktop, and remember the choice. On phones it stays
// closed (there it is an overlay that would cover the page).
(() => {
    const toggle = document.getElementById('my-drawer-4');
    if (!toggle) return;

    const desktop = window.matchMedia('(min-width: 1024px)');
    const KEY = 'lokal.sidebar';
    let saved = null;
    try { saved = localStorage.getItem(KEY); } catch { /* storage blocked: use the default */ }

    toggle.checked = desktop.matches && saved !== 'closed';

    toggle.addEventListener('change', () => {
        if (!desktop.matches) return;
        try { localStorage.setItem(KEY, toggle.checked ? 'open' : 'closed'); } catch { /* ignore */ }
    });
})();
