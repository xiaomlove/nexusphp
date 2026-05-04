// Lightweight Echo bootstrap for Laravel Reverb (Pusher-protocol websocket).
//
// Loaded only on pages that need realtime — currently public/shoutbox.php.
// Reads its config from window.__REVERB__ which is rendered server-side
// from config('broadcasting.connections.reverb') values.
//
// If Reverb isn't configured (no key), this file is a no-op.

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const cfg = window.__REVERB__ || {};

if (cfg.key && cfg.host) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: cfg.key,
        wsHost: cfg.host,
        wsPort: cfg.port,
        wssPort: cfg.port,
        forceTLS: cfg.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

// Subscribe the shoutbox iframe to its channel and prepend each new
// shout row directly into the existing table. The server renders the
// same <tr> markup it would render on a meta-refresh load and ships it
// in the broadcast payload (`data.html`), so the live update is byte-
// identical to what a reload would produce.
//
// Falls back transparently:
//   - If Echo isn't initialised (no REVERB_APP_KEY) → meta-refresh handles it.
//   - If the payload has no html field → trigger a debounced reload.
//   - If the row id is already in the table (dup from initial render
//     racing with the broadcast) → drop the duplicate.
if (window.Echo && document.body && document.body.dataset.shoutChannel) {
    const channel = document.body.dataset.shoutChannel;
    window.Echo.channel(channel).listen('.ShoutSent', (data) => {
        try {
            if (!data || !data.html) {
                // Old broadcasters that don't ship html yet — keep the
                // reload behaviour as a safety net.
                if (window.__shoutReloadTimer) return;
                window.__shoutReloadTimer = setTimeout(() => window.location.reload(), 250);
                return;
            }
            const id = (data.id | 0);
            if (id > 0 && document.querySelector('tr[data-shout-id="' + id + '"]')) {
                return; // already rendered (dedupe)
            }
            const table = document.getElementById('shoutbox-table');
            if (!table) return;
            const tbody = table.tBodies[0] || table;
            const holder = document.createElement('tbody');
            holder.innerHTML = data.html;
            const newRow = holder.querySelector('tr');
            if (!newRow) return;
            tbody.insertBefore(newRow, tbody.firstChild);
            // Trim to the same window size the server used so a long-
            // running iframe doesn't grow unbounded between reloads.
            const max = parseInt(table.dataset.shoutLimit || '70', 10);
            while (tbody.rows && tbody.rows.length > max) {
                tbody.deleteRow(tbody.rows.length - 1);
            }
        } catch (e) { /* fall back to meta-refresh */ }
    });
}
