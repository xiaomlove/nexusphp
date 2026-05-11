// Lightweight Echo bootstrap for Laravel Reverb (Pusher-protocol websocket).
//
// Loaded via Vite from public/build/assets/echo-*.js. Currently included by:
//   - public/shoutbox.php (legacy iframe page) — for shoutbox.{sb,hb}
//   - include/functions.php stdhead() — when REVERB_APP_KEY is set,
//     for notifications.user.{id} and torrent.{id}.peers
//
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

// ---- notifications.user.{id}: per-user inbox / toast --------------------
//
// Private channel — server-side auth in routes/channels.php enforces
// that the subscriber is the user whose id is in the channel name. Wired
// up by stdhead() when CURUSER is set.
//
// Updates the inbox count badge in the standard header (id="inboxbadge"
// is best-effort; if the page doesn't have that element, just no-op).
// If a payload html snippet is present and there is a #notify-toast host,
// inserts it as a transient toast.
if (window.Echo && document.body && document.body.dataset.notifyUserId) {
    const userId = parseInt(document.body.dataset.notifyUserId, 10) | 0;
    if (userId > 0) {
        window.Echo.private('notifications.user.' + userId).listen('.NotificationReceived', (data) => {
            try {
                if (!data) return;
                const badge = document.getElementById('inboxbadge');
                if (badge && typeof data.unread_count !== 'undefined') {
                    badge.textContent = String(data.unread_count | 0);
                }
                if (data.html) {
                    const host = document.getElementById('notify-toast');
                    if (host) {
                        const wrap = document.createElement('div');
                        wrap.innerHTML = data.html;
                        const node = wrap.firstElementChild;
                        if (node) {
                            host.appendChild(node);
                            // Auto-dismiss after 8s; pages are free to override.
                            setTimeout(() => {
                                if (node.parentNode === host) host.removeChild(node);
                            }, 8000);
                        }
                    }
                }
            } catch (e) { /* swallow — websocket UX should never break the page */ }
        });
    }
}

// ---- torrent.{id}.peers: per-torrent peer count refresh -----------------
//
// Public channel — peer counts are visible on details.php anyway, no
// auth needed. Wired up by stdhead() when details.php sets
// $GLOBALS['REVERB_TORRENT_PEERS_ID'].
//
// Updates the existing #peercount block in details.php. Server may ship
// pre-rendered html for byte-identical replacement; otherwise we
// reconstruct from numeric counts using the same "<b>N seeder(s)</b> | <b>M
// leecher(s)</b>" shape that details.php renders server-side.
if (window.Echo && document.body && document.body.dataset.torrentPeersId) {
    const torrentId = parseInt(document.body.dataset.torrentPeersId, 10) | 0;
    if (torrentId > 0) {
        window.Echo.channel('torrent.' + torrentId + '.peers').listen('.TorrentPeersUpdated', (data) => {
            try {
                if (!data) return;
                const cell = document.getElementById('peercount');
                if (!cell) return;
                if (data.html) {
                    cell.innerHTML = data.html;
                    return;
                }
                const seeders = data.seeders | 0;
                const leechers = data.leechers | 0;
                cell.innerHTML = '<b>' + seeders + ' seeder' + (seeders === 1 ? '' : 's') + '</b> | <b>' +
                    leechers + ' leecher' + (leechers === 1 ? '' : 's') + '</b>';
            } catch (e) { /* swallow */ }
        });
    }
}
