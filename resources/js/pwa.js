/**
 * Register the service worker and expose a small push-subscribe API.
 *
 * Subscription is opt-in: nothing happens unless the user calls
 * `window.NexusPwa.requestPush()` (e.g. from a settings page button).
 * Permission is never auto-prompted on page load.
 */

const VAPID_PUBLIC_KEY = (import.meta.env && import.meta.env.VITE_VAPID_PUBLIC_KEY) || '';

function urlBase64ToUint8Array(b64) {
    const padding = '='.repeat((4 - (b64.length % 4)) % 4);
    const base64 = (b64 + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const out = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) out[i] = rawData.charCodeAt(i);
    return out;
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

async function postSubscription(sub) {
    const keys = sub.toJSON().keys || {};
    return fetch('/api/push/subscribe', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            endpoint: sub.endpoint,
            p256dh: keys.p256dh || '',
            auth: keys.auth || '',
            content_encoding: 'aes128gcm',
        }),
    });
}

async function deleteSubscription(endpoint) {
    return fetch('/api/push/unsubscribe', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ endpoint }),
    });
}

async function register() {
    if (!('serviceWorker' in navigator)) return null;
    try {
        return await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    } catch (e) {
        // eslint-disable-next-line no-console
        console.warn('SW registration failed', e);
        return null;
    }
}

async function requestPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        throw new Error('Push not supported in this browser');
    }
    if (!VAPID_PUBLIC_KEY) {
        throw new Error('VITE_VAPID_PUBLIC_KEY is not configured');
    }
    const reg = (await navigator.serviceWorker.ready) || (await register());
    if (!reg) throw new Error('Service worker not ready');

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new Error('Notification permission denied');
    }

    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
        sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
        });
    }
    const res = await postSubscription(sub);
    if (!res.ok) throw new Error(`Subscribe failed: HTTP ${res.status}`);
    return sub;
}

async function disablePush() {
    if (!('serviceWorker' in navigator)) return false;
    const reg = await navigator.serviceWorker.ready;
    const sub = reg ? await reg.pushManager.getSubscription() : null;
    if (!sub) return false;
    await deleteSubscription(sub.endpoint);
    await sub.unsubscribe();
    return true;
}

window.NexusPwa = { register, requestPush, disablePush };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', register);
} else {
    register();
}
