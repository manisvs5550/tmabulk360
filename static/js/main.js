// TMA Operations 360 — Main JS

// Navbar scroll effect
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 40);
});

// Mobile menu toggle
const toggle = document.querySelector('.nav-toggle');
const navLinks = document.querySelector('.nav-links');
if (toggle) {
    toggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
    });
}

// Close mobile menu on link click
document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', () => {
        navLinks.classList.remove('active');
    });
});

// Animate feature cards on scroll
const observerOptions = { threshold: 0.15 };
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

document.querySelectorAll('.feature-card, .info-card, .offer-card, .fleet-stat').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
});

// ====== OFFLINE SUPPORT ======

// Connectivity banner
const offlineBanner = document.getElementById('offline-banner');
function updateConnBanner() {
    if (offlineBanner) {
        offlineBanner.style.display = navigator.onLine ? 'none' : 'flex';
    }
}
window.addEventListener('online', () => {
    updateConnBanner();
    syncQueuedForms();
});
window.addEventListener('offline', updateConnBanner);
updateConnBanner();

// Offline form queue — intercept contact form submission
const QUEUE_KEY = 'tmaops360_form_queue';

const contactForm = document.querySelector('.contact-form');
if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
        if (!navigator.onLine) {
            e.preventDefault();
            const formData = new FormData(contactForm);
            const data = Object.fromEntries(formData.entries());
            const queue = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
            queue.push({ data, timestamp: Date.now() });
            localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
            showToast('Saved offline — will send when connected.');
            contactForm.reset();

            // Request background sync if supported
            if ('serviceWorker' in navigator && 'SyncManager' in window) {
                navigator.serviceWorker.ready.then(reg => {
                    reg.sync.register('sync-contact-form');
                });
            }
        }
    });
}

// Sync queued forms when online
async function syncQueuedForms() {
    const queue = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
    if (queue.length === 0) return;
    const remaining = [];
    for (const item of queue) {
        try {
            const params = new URLSearchParams(item.data);
            const resp = await fetch('contact.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString(),
            });
            if (!resp.ok) remaining.push(item);
        } catch {
            remaining.push(item);
            break;
        }
    }
    localStorage.setItem(QUEUE_KEY, JSON.stringify(remaining));
    if (remaining.length < queue.length) {
        showToast(`Sent ${queue.length - remaining.length} queued message(s).`);
    }
}

// Toast notification
function showToast(msg) {
    let toast = document.getElementById('ov-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'ov-toast';
        Object.assign(toast.style, {
            position: 'fixed', bottom: '24px', left: '50%', transform: 'translateX(-50%)',
            background: '#1a2744', color: '#e2e8f0', padding: '12px 24px',
            borderRadius: '10px', fontSize: '0.9rem', fontFamily: 'Inter, sans-serif',
            zIndex: '9999', boxShadow: '0 4px 20px rgba(0,0,0,0.3)',
            transition: 'opacity 0.3s', opacity: '0',
        });
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 4000);
}

// Try syncing on load if online
if (navigator.onLine) syncQueuedForms();

// ====== INVENTORY OFFLINE QUEUE (IndexedDB) ======

const INV_DB_NAME = 'tmaops360_inventory';
const INV_DB_VERSION = 1;
const INV_STORE = 'pending_submissions';

function openInvDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(INV_DB_NAME, INV_DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(INV_STORE)) {
                db.createObjectStore(INV_STORE, { keyPath: 'id', autoIncrement: true });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function saveInventoryOffline(payload) {
    const db = await openInvDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(INV_STORE, 'readwrite');
        tx.objectStore(INV_STORE).add(payload);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

async function getPendingInventoryCount() {
    const db = await openInvDB();
    return new Promise((resolve) => {
        const tx = db.transaction(INV_STORE, 'readonly');
        const req = tx.objectStore(INV_STORE).count();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => resolve(0);
    });
}

async function getAllPendingInventory() {
    const db = await openInvDB();
    return new Promise((resolve) => {
        const tx = db.transaction(INV_STORE, 'readonly');
        const req = tx.objectStore(INV_STORE).getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => resolve([]);
    });
}

async function deletePendingInventory(id) {
    const db = await openInvDB();
    return new Promise((resolve) => {
        const tx = db.transaction(INV_STORE, 'readwrite');
        tx.objectStore(INV_STORE).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
    });
}

// Sync all pending inventory submissions to server
async function syncPendingInventory() {
    const items = await getAllPendingInventory();
    if (items.length === 0) return;
    let synced = 0;
    for (const entry of items) {
        try {
            const resp = await fetch('inventory_sync.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(entry.payload),
                credentials: 'same-origin',
            });
            if (resp.ok) {
                await deletePendingInventory(entry.id);
                synced++;
            }
        } catch {
            break; // still offline
        }
    }
    if (synced > 0) {
        showToast('Synced ' + synced + ' offline inventory submission(s).');
    }
    updatePendingBadge();
}

// Update the pending-count badge in the UI
async function updatePendingBadge() {
    const badge = document.getElementById('inv-pending-badge');
    if (!badge) return;
    const count = await getPendingInventoryCount();
    if (count > 0) {
        badge.textContent = count + ' pending';
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
}

// Intercept inventory form submission when offline
const invForm = document.querySelector('form[action="inventory_submit.php"]');
if (invForm) {
    invForm.addEventListener('submit', async function (e) {
        if (navigator.onLine) return; // let normal POST proceed

        e.preventDefault();
        const submitBtn = document.getElementById('inv-submit-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving\u2026';
        }

        const fd = new FormData(invForm);
        const shipId = fd.get('ship_id');
        const shipSelect = invForm.querySelector('#ship_id');
        const shipName = shipSelect ? shipSelect.options[shipSelect.selectedIndex].text : '';

        if (!shipId) {
            showToast('Please select a vessel first.');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Inventory'; }
            return;
        }

        // Collect ROB items
        const items = [];
        invForm.querySelectorAll('input[name^="rob_"]').forEach(input => {
            const val = parseInt(input.value);
            if (!isNaN(val) && val >= 0 && input.value.trim() !== '') {
                const itemNo = parseInt(input.name.replace('rob_', ''));
                const row = input.closest('tr');
                const itemName = row ? row.querySelector('.cell-item').textContent.trim() : 'Item ' + itemNo;
                items.push({ item_no: itemNo, qty: val, item_name: itemName });
            }
        });

        if (items.length === 0) {
            showToast('Please enter at least one ROB quantity.');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Inventory'; }
            return;
        }

        const payload = {
            ship_id: parseInt(shipId),
            ship_name: shipName,
            items: items,
            queued_at: new Date().toISOString(),
        };

        await saveInventoryOffline({ payload });

        // Register background sync
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            const reg = await navigator.serviceWorker.ready;
            await reg.sync.register('sync-inventory');
        }

        showToast('Saved offline — will sync when connected.');
        updatePendingBadge();
        invForm.reset();
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Inventory'; }
    });

    // Show badge on page load
    updatePendingBadge();
}

// Also sync inventory when coming back online
window.addEventListener('online', () => {
    syncPendingInventory();
});

// Sync inventory on page load if online
if (navigator.onLine) syncPendingInventory();
