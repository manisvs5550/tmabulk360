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
