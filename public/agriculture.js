
const prefix = 'agrioffice-record:' + document.body.dataset.user + ':';
const syncLabel = document.querySelector('[data-sync-label]');
const items = document.querySelector('[data-sync-items]');
const toast = document.querySelector('.toast');
const offlineWorkspace = document.body.hasAttribute('data-offline-workspace');
let busy = false;
let toastTimer;

function queued() {
    try {
        return Object.keys(localStorage).filter(key => key.startsWith(prefix)).flatMap(key => {
            try {
                const entry = JSON.parse(localStorage.getItem(key));
                return entry?.fields && entry.url ? [[key, entry]] : [];
            } catch {
                return [];
            }
        });
    } catch {
        return [];
    }
}

function notify(message) {
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, 6500);
}

function showState() {
    const entries = queued();
    const connected = navigator.onLine;
    syncLabel.textContent = (connected ? 'Online' : 'Offline') + (entries.length ? ' · ' + entries.length + ' queued' : '');
    syncLabel.parentElement.classList.toggle('offline', !connected);
    items.replaceChildren();
    if (!entries.length) items.textContent = 'No saved drafts.';
    const editing = new Map(Array.from(document.querySelectorAll('form[data-editing-draft]')).map(form => [form.dataset.editingDraft, form]));
    for (const [key, entry] of entries) {
        const row = document.createElement('div');
        row.className = 'queue-item';
        const description = document.createElement('span');
        const title = entry.fields.name || entry.fields.farmer_name || entry.fields.first_name || 'Record draft';
        description.textContent = title + ' · ' + (entry.error || 'Waiting to sync');
        const editForm = offlineWorkspace && !entry.fields._method
            ? Array.from(document.querySelectorAll('[data-draft-form]')).find(form => form.dataset.draftForm ===
                (entry.fields.register || entry.fields.crop || (entry.fields.transaction ? 'tilapia' : new URL(entry.url, location.href).pathname.split('/').pop())))
            : null;
        if (editForm) {
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'secondary';
            edit.textContent = 'Edit draft';
            edit.disabled = busy || editing.has(key);
            edit.addEventListener('click', () => {
                editForm.dataset.editingDraft = key;
                for (const [field, value] of Object.entries(entry.fields)) {
                    const input = editForm.elements.namedItem(field);
                    if (!input || input.type === 'hidden' && field !== 'submission_id') continue;
                    if (input.type === 'checkbox') input.checked = input.value === value;
                    else input.value = value ?? '';
                }
                editForm.closest('dialog').showModal();
            });
            row.append(description, edit);
        }
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'secondary';
        remove.textContent = 'Discard';
        remove.disabled = busy || editing.has(key);
        remove.addEventListener('click', () => {
            if (confirm('Discard this unsynced entry?')) {
                localStorage.removeItem(key);
                showState();
            }
        });
        if (!editForm) row.append(description);
        row.append(remove);
        items.append(row);
    }
}

async function currentSession() {
    const result = await fetch(document.body.dataset.sync, {headers: {Accept: 'application/json'}, cache: 'no-store'});
    if (!result.ok || result.redirected) return null;
    const session = await result.json();
    return String(session.user) === document.body.dataset.user ? session : null;
}

async function send(entry, token) {
    const url = new URL(entry.url, location.href);
    if (url.origin !== location.origin) return {status: 422, error: 'Invalid draft destination.'};
    const response = await fetch(url, {
        method: entry.fields._method || 'POST',
        headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token},
        body: JSON.stringify(entry.fields),
    });
    if (response.ok && !response.redirected) return {};
    const payload = await response.json().catch(() => null);
    return {status: response.status, error: payload?.message || 'Sign in again before syncing.'};
}

async function sync() {
    if (busy || !navigator.onLine || !queued().some(([, entry]) => !entry.error)) return;
    busy = true;
    showState();
    let saved = 0;
    try {
        const session = await currentSession();
        if (!session) {
            notify('Sign in to the same account to sync your entries.');
            return;
        }
        for (const [key, entry] of queued()) {
            if (entry.error || !localStorage.getItem(key) || Array.from(document.querySelectorAll('form[data-editing-draft]'))
                .some(form => form.dataset.editingDraft === key)) continue;
            const result = await send(entry, session.token);
            if (!result.error) {
                localStorage.removeItem(key);
                saved++;
            } else if ([403, 404, 409, 422].includes(result.status)) {
                localStorage.setItem(key, JSON.stringify({...entry, error: result.error}));
            } else {
                notify(result.error);
                break;
            }
        }
    } catch {
        notify('The office server could not be reached. Entries are still queued.');
    } finally {
        busy = false;
        showState();
        if (saved) notify(saved + (saved === 1 ? ' entry synced. Reload to refresh the records.' : ' entries synced. Reload to refresh the records.'));
    }
}

const sidebarToggle = document.querySelector('.sidebar-toggle');
if (sidebarToggle) {
    const sidebar = document.getElementById(sidebarToggle.getAttribute('aria-controls'));
    const navigation = sidebar.querySelector('nav');
    const sidebarStateKey = `map-sidebar:${document.body.dataset.user}`;
    let savedSidebar;
    try {
        savedSidebar = JSON.parse(sessionStorage.getItem(sidebarStateKey));
    } catch {
        // The menu still works when browser storage is unavailable.
    }
    const setSidebarOpen = open => {
        sidebar.hidden = !open;
        sidebar.closest('.shell').classList.toggle('sidebar-closed', !open);
        sidebarToggle.setAttribute('aria-expanded', String(open));
    };
    setSidebarOpen(savedSidebar?.open ?? !window.matchMedia('(max-width: 900px)').matches);
    sidebarToggle.hidden = false;
    const restoreSidebar = () => {
        if (sidebar.hidden) return;
        if (savedSidebar) {
            sidebar.scrollTop = savedSidebar.scrollTop ?? 0;
            if (savedSidebar.href === location.href && Number.isFinite(savedSidebar.linkOffset)) {
                const link = Array.from(navigation.querySelectorAll('a')).find(item => item.href === savedSidebar.href);
                if (link) {
                    sidebar.scrollTop += link.getBoundingClientRect().top - sidebar.getBoundingClientRect().top
                        - savedSidebar.linkOffset;
                }
            }
        }
        const activeLink = navigation.querySelector('a.active');
        if (!activeLink) return;
        const bounds = sidebar.getBoundingClientRect();
        const active = activeLink.getBoundingClientRect();
        const inset = 8;
        if (active.top < bounds.top + inset) {
            sidebar.scrollTop += active.top - bounds.top - inset;
        } else if (active.bottom > bounds.bottom - inset) {
            sidebar.scrollTop += active.bottom - bounds.bottom + inset;
        }
    };
    restoreSidebar();
    window.addEventListener('pageshow', restoreSidebar);
    const saveSidebar = link => {
        savedSidebar = {
            open: !sidebar.hidden,
            scrollTop: sidebar.hidden ? (savedSidebar?.scrollTop ?? 0) : sidebar.scrollTop,
            href: link?.href,
            linkOffset: link ? link.getBoundingClientRect().top - sidebar.getBoundingClientRect().top : undefined,
        };
        try {
            sessionStorage.setItem(sidebarStateKey, JSON.stringify(savedSidebar));
        } catch {
            // Navigation still works when browser storage is unavailable.
        }
    };
    let followingSidebarLink = false;
    navigation.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        followingSidebarLink = link.hash === '' || link.pathname !== location.pathname || link.search !== location.search;
        saveSidebar(link);
    });
    window.addEventListener('pagehide', () => {
        if (!followingSidebarLink) saveSidebar();
        followingSidebarLink = false;
    });
    sidebarToggle.addEventListener('click', () => {
        if (!sidebar.hidden) saveSidebar();
        setSidebarOpen(sidebar.hidden);
        if (!sidebar.hidden) restoreSidebar();
        saveSidebar();
    });
    sidebar.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            saveSidebar();
            setSidebarOpen(false);
            saveSidebar();
            sidebarToggle.focus();
        }
    });
}

document.querySelectorAll('[data-open]').forEach(button => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.open);
        const form = dialog.querySelector('[data-draft-form]');
        if (form) {
            form.reset();
            form.elements.record_date.value = new Intl.DateTimeFormat('en-CA', {timeZone: 'Asia/Manila'}).format(new Date());
        }
        dialog.showModal();
    });
});
document.querySelectorAll('[data-close]').forEach(button => {
    button.addEventListener('click', () => button.closest('dialog').close());
});
document.querySelector('[data-retry]').addEventListener('click', sync);

document.querySelectorAll('[data-record]').forEach(form => {
    if (form.dataset.draftForm) {
        form.closest('dialog').addEventListener('close', () => {
            delete form.dataset.editingDraft;
            form.reset();
            showState();
        });
    }
    const grossOutput = form.querySelector('[data-crop-gross]');
    if (grossOutput) {
        const netOutput = form.querySelector('[data-crop-net]');
        const format = new Intl.NumberFormat('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const updateIncome = () => {
            const {quantity, price, expenses} = form.elements;
            const gross = quantity.valueAsNumber * price.valueAsNumber;
            const roundedGross = Math.round((gross + Number.EPSILON) * 100) / 100;
            grossOutput.value = Number.isFinite(gross) ? format.format(roundedGross) : '—';
            netOutput.value = Number.isFinite(roundedGross - expenses.valueAsNumber)
                ? format.format(roundedGross - expenses.valueAsNumber) : '—';
        };
        form.addEventListener('input', updateIncome);
        form.addEventListener('reset', () => queueMicrotask(updateIncome));
        updateIncome();
    }
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button[type=submit], button:not([type])');
        const fields = Object.fromEntries(new FormData(form));
        const token = fields._token;
        delete fields._token;
        const entry = {url: form.action, fields};
        const queueKey = form.dataset.editingDraft || prefix + crypto.randomUUID();
        if (fields.submission_id === '') fields.submission_id = crypto.randomUUID();
        button.disabled = true;
        try {
            if (navigator.onLine && !offlineWorkspace) {
                let result;
                try {
                    result = await send(entry, token);
                } catch {
                    // A reconnect can retry the same submission ID or revision.
                }
                if (result) {
                    if (!result.error) {
                        location.reload();
                        return;
                    }
                    if (result.status < 500) {
                        notify(result.error);
                        return;
                    }
                }
            }
            localStorage.setItem(queueKey, JSON.stringify(entry));
            if (fields.submission_id) {
                form.reset();
                form.elements.submission_id.value = crypto.randomUUID();
            }
            form.closest('dialog').close();
            notify('Draft saved on this device. It will sync when the server is reachable.');
            showState();
            if (offlineWorkspace) {
                delete form.dataset.editingDraft;
                sync();
            }
        } catch {
            notify('This device could not store the entry. Keep the form open and try again.');
        } finally {
            button.disabled = false;
        }
    });
});

window.addEventListener('online', () => { showState(); sync(); });
window.addEventListener('offline', showState);
window.addEventListener('storage', showState);
showState();
sync();
setInterval(sync, 30000);

const offlineStatus = document.querySelector('[data-offline-status]');
if (offlineStatus && 'serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register(document.body.dataset.workerUrl)
        .then(() => navigator.serviceWorker.ready)
        .then(registration => {
            const channel = new MessageChannel();
            channel.port1.onmessage = ({data}) => {
                offlineStatus.textContent = data.ready ? 'Ready to use offline.' : 'Could not prepare the offline workspace.';
                channel.port1.close();
            };
            registration.active.postMessage({type: 'prepare-workspace', user: document.body.dataset.user}, [channel.port2]);
        })
        .catch(() => { offlineStatus.textContent = 'Offline workspace could not be prepared.'; });
} else if (offlineStatus) {
    offlineStatus.textContent = 'Offline reopening requires HTTPS or localhost.';
}
