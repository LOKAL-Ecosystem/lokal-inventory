/*
 * Connection state of this page, shown to the person and used to hold back transactions.
 *
 *   browserOnline   what the browser says (navigator.onLine)
 *   reachable       the browser says online AND the last requests actually got answers
 *
 * While not reachable: a "Tidak ada koneksi" badge shows, buttons marked `data-requires-online`
 * and every form submit are blocked with a clear message, and polling pauses (browser offline)
 * or keeps probing slowly (server unreachable). Nothing about a transaction is stored here.
 */

const listeners = new Set();
let failures = 0;
let browserOnline = typeof navigator === 'undefined' ? true : navigator.onLine !== false;

const FAILURES_BEFORE_OFFLINE = 2;

export const isBrowserOnline = () => browserOnline;
export const isReachable = () => browserOnline && failures < FAILURES_BEFORE_OFFLINE;

export function onConnectionChange(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

function notify(before) {
    const after = isReachable();
    if (after !== before) listeners.forEach((listener) => listener(after));
}

export function reportNetworkFailure() {
    const before = isReachable();
    failures += 1;
    notify(before);
}

export function reportNetworkSuccess() {
    const before = isReachable();
    failures = 0;
    notify(before);
}

if (typeof window !== 'undefined') {
    window.addEventListener('online', () => {
        const before = isReachable();
        browserOnline = true;
        failures = 0;
        notify(before);
    });

    window.addEventListener('offline', () => {
        const before = isReachable();
        browserOnline = false;
        notify(before);
    });
}

/* ------------------------------------------------------------------ UI */

const MESSAGE = 'Tidak ada koneksi. Aksi ini butuh koneksi ke server; coba lagi setelah tersambung.';

function applyState() {
    const offline = !isReachable();

    document.body.classList.toggle('is-offline', offline);
    document.getElementById('connection-badge')?.classList.toggle('hidden', !offline);

    document.querySelectorAll('[data-requires-online]').forEach((element) => {
        element.setAttribute('aria-disabled', String(offline));

        if (offline) {
            if (element.dataset.onlineTitle === undefined) element.dataset.onlineTitle = element.title ?? '';
            element.title = MESSAGE;
        } else if (element.dataset.onlineTitle !== undefined) {
            element.title = element.dataset.onlineTitle;
            delete element.dataset.onlineTitle;
        }
    });
}

function tell() {
    if (window.Swal) {
        window.Swal.fire({ toast: true, position: 'top', icon: 'warning', title: 'Tidak ada koneksi', text: 'Aksi ini ditunda sampai koneksi kembali.', showConfirmButton: false, timer: 3500 });
    } else {
        window.alert(MESSAGE);
    }
}

/** Wire the badge and the guards on a page. Call once. */
export function initConnectionUi() {
    onConnectionChange(applyState);
    applyState();

    // Capture phase: runs before the button's own handler, which then never fires.
    document.addEventListener('click', (event) => {
        if (isReachable()) return;

        if (event.target.closest?.('[data-requires-online]')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            tell();
        }
    }, true);

    // Every form posts to the server.
    document.addEventListener('submit', (event) => {
        if (isReachable() || event.target.hasAttribute?.('data-offline-ok')) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        tell();
    }, true);

    // Rows drawn later (the orders table) get the same title / aria state.
    new MutationObserver(() => {
        if (!isReachable()) applyState();
    }).observe(document.body, { childList: true, subtree: true });
}
