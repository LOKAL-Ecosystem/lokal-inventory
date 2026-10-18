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
