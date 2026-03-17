<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline — TMA Operations 360</title>
    <link rel="stylesheet" href="static/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .offline-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--navy);
            text-align: center;
            padding: 40px 24px;
        }
        .offline-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 50% 50%, rgba(99,102,241,0.1) 0%, transparent 60%);
            pointer-events: none;
        }
        .offline-icon {
            font-size: 4rem;
            color: var(--slate-400);
            margin-bottom: 24px;
            position: relative;
        }
        .offline-page h1 {
            color: var(--white);
            font-size: 2rem;
            margin-bottom: 12px;
            position: relative;
        }
        .offline-page p {
            color: var(--slate-400);
            max-width: 440px;
            margin-bottom: 32px;
            line-height: 1.7;
            position: relative;
        }
        .offline-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            position: relative;
        }
        .offline-status.is-offline {
            background: rgba(244,63,94,0.12);
            color: var(--rose);
        }
        .offline-status.is-online {
            background: rgba(16,185,129,0.12);
            color: var(--emerald);
        }
        .offline-status .dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: currentColor;
        }
        .offline-queued {
            margin-top: 32px;
            padding: 20px 28px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius);
            color: var(--slate-300);
            font-size: 0.9rem;
            max-width: 440px;
            position: relative;
        }
        .offline-queued strong { color: var(--white); }
    </style>
</head>
<body>
    <div class="offline-page">
        <div class="offline-icon">
            <i class="fa-solid fa-satellite-dish"></i>
        </div>
        <h1>You're Offline</h1>
        <p>
            It looks like you've lost your connection — common at sea.
            Previously visited pages are still available from cache.
            Any forms you submit will be saved and sent automatically
            when connectivity returns.
        </p>
        <div class="offline-status is-offline" id="conn-status">
            <span class="dot"></span>
            <span>No Connection</span>
        </div>
        <div class="offline-queued" id="queue-info" style="display:none;">
            <strong id="queue-count">0</strong> form submission(s) queued.
            They'll sync automatically when you're back online.
        </div>
        <div style="margin-top: 24px; position: relative;">
            <a href="/" class="btn btn-outline btn-md" style="border-color: var(--slate-400); color: var(--slate-300);">
                <i class="fa-solid fa-arrow-left"></i> Try Home Page
            </a>
        </div>
    </div>
    <script>
        // Live connection status indicator
        function updateStatus() {
            const el = document.getElementById('conn-status');
            if (navigator.onLine) {
                el.className = 'offline-status is-online';
                el.innerHTML = '<span class="dot"></span><span>Back Online — Refreshing…</span>';
                setTimeout(() => location.reload(), 1500);
            } else {
                el.className = 'offline-status is-offline';
                el.innerHTML = '<span class="dot"></span><span>No Connection</span>';
            }
        }
        window.addEventListener('online', updateStatus);
        window.addEventListener('offline', updateStatus);

        // Show queued form count
        const queueKey = 'tmaops360_form_queue';
        const queue = JSON.parse(localStorage.getItem(queueKey) || '[]');
        if (queue.length > 0) {
            document.getElementById('queue-info').style.display = 'block';
            document.getElementById('queue-count').textContent = queue.length;
        }
    </script>
</body>
</html>
