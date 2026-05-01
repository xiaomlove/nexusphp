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

// Subscribe the shoutbox iframe to its channel and reload on each new
// shout. We keep the legacy DOM intact and rely on a full reload to pick
// up the freshly-inserted row — simplest and safest integration with the
// XHTML rendering in public/shoutbox.php.
if (window.Echo && document.body && document.body.dataset.shoutChannel) {
    const channel = document.body.dataset.shoutChannel;
    window.Echo.channel(channel).listen('.ShoutSent', () => {
        // Light debounce: wait a tick so multiple near-simultaneous
        // shouts only trigger one reload.
        if (window.__shoutReloadTimer) {
            return;
        }
        window.__shoutReloadTimer = setTimeout(() => {
            window.location.reload();
        }, 250);
    });
}
