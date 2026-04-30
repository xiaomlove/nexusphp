/* Modern2025 theme bootstrap.
 * Runs synchronously from <head> via the stylesheet `addicode` field
 * so the data-theme attribute is set before first paint (no FOUC).
 */
(function () {
    try {
        var saved = null;
        try { saved = localStorage.getItem('nexus-theme'); } catch (e) {}
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = saved === 'light' || saved === 'dark' ? saved : (prefersDark ? 'dark' : 'light');
        var html = document.documentElement;
        html.classList.add('theme-modern');
        html.setAttribute('data-theme', theme);
    } catch (e) { /* ignore */ }
})();

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('theme-toggle')) return;
    if (!document.body) return;

    var btn = document.createElement('button');
    btn.id = 'theme-toggle';
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Toggle dark / light theme');
    btn.title = 'Toggle theme';

    var icon = document.createElement('span');
    icon.className = 'theme-toggle-icon';
    icon.setAttribute('aria-hidden', 'true');
    btn.appendChild(icon);

    btn.addEventListener('click', function () {
        var html = document.documentElement;
        var current = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', current);
        try { localStorage.setItem('nexus-theme', current); } catch (e) {}
    });

    document.body.appendChild(btn);
});
