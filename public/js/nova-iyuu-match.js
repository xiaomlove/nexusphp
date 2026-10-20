(function () {
    'use strict';

    function renderSite(container, site) {
        var link = document.createElement('a');
        link.className = 'nova-iyuu-match__site';
        link.href = site.url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.title = '前往 ' + site.name + ' 的对应资源';

        var icon = document.createElement('img');
        icon.className = 'nova-iyuu-match__icon';
        const host = (() => { try { return new URL(site.url || '').hostname.toLowerCase(); } catch (e) { return ''; } })();
        const localFallback = host ? '/pic/site-icons/' + host.replace(/[^a-z0-9.-]/g, '_') + '.svg' : '/pic/site-icons/default.svg';
        const iconPath = (site.icon && site.icon.indexOf('/pic/site-icons/') === 0) ? site.icon : localFallback;
        icon.src = iconPath + (iconPath.indexOf('?') >= 0 ? '&' : '?') + 'v=20260912';
        icon.onerror = function () { this.onerror = null; this.src = '/pic/site-icons/default.svg?v=20260912'; };
        icon.alt = '';
        icon.width = 46;
        icon.height = 46;
        icon.style.setProperty('display', 'block', 'important');
        icon.style.setProperty('width', '46px', 'important');
        icon.style.setProperty('height', '46px', 'important');
        icon.style.setProperty('min-width', '46px', 'important');
        icon.style.setProperty('min-height', '46px', 'important');
        icon.style.setProperty('max-width', '46px', 'important');
        icon.style.setProperty('max-height', '46px', 'important');
        icon.style.setProperty('object-fit', 'contain', 'important');
        icon.loading = 'lazy';
        icon.addEventListener('error', function () {
            frame.hidden = true;
        });

        var frame = document.createElement('span');
        frame.className = 'nova-iyuu-match__icon-frame';
        frame.style.setProperty('display', 'inline-flex', 'important');
        frame.style.setProperty('width', '46px', 'important');
        frame.style.setProperty('height', '46px', 'important');
        frame.style.setProperty('min-width', '46px', 'important');
        frame.style.setProperty('min-height', '46px', 'important');
        frame.style.setProperty('max-width', '46px', 'important');
        frame.style.setProperty('max-height', '46px', 'important');
        frame.style.setProperty('overflow', 'hidden', 'important');
        frame.style.setProperty('align-items', 'center', 'important');
        frame.style.setProperty('justify-content', 'center', 'important');
        frame.appendChild(icon);

        var name = document.createElement('span');
        name.textContent = site.name;
        link.appendChild(frame);
        link.appendChild(name);
        container.appendChild(link);
    }

    function load(container) {
        if (container.dataset.configured !== 'yes') {
            return;
        }
        fetch(container.dataset.endpoint, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            container.textContent = '';
            if (!payload.ok) {
                throw new Error(payload.message || 'query failed');
            }
            if (!payload.configured) {
                container.textContent = '尚未配置 IYUU Token';
                return;
            }
            if (payload.limited) {
                if (payload.match_count > 0) {
                    container.textContent = 'IYUU 已收录，匹配 ' + payload.match_count + ' 个站点（当前接口未提供站点明细）';
                } else {
                    container.textContent = 'IYUU 暂未收录该资源';
                }
                return;
            }
            if (!payload.sites || payload.sites.length === 0) {
                container.textContent = '暂未发现其他站点收录';
                return;
            }

            var list = document.createElement('div');
            list.className = 'nova-iyuu-match__list';
            payload.sites.forEach(function (site) {
                renderSite(list, site);
            });
            container.appendChild(list);

            if (payload.updated_at) {
                var updated = document.createElement('span');
                updated.className = 'nova-iyuu-match__updated';
                updated.textContent = '数据更新于 ' + payload.updated_at;
                container.appendChild(updated);
            }
        }).catch(function () {
            container.textContent = '其他站点查询暂时不可用';
            container.classList.add('nova-iyuu-match--error');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.nova-iyuu-match').forEach(load);
    });
}());
