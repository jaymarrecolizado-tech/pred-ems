/**
 * DICT RO2 HRIS — UI behaviours (vanilla JS, no dependencies).
 * Mobile sidebar drawer, table overflow hints, and the dashboard
 * headcount chart (SVG area / bar / table views).
 */
(function () {
    'use strict';

    var body = document.body;
    // Both toggles (sidebar hamburger on desktop + topbar hamburger on
    // mobile) share the .js-sidebar-toggle class so one handler drives the
    // rail collapse and the mobile drawer.
    var toggles = Array.prototype.slice.call(document.querySelectorAll('.js-sidebar-toggle'));
    var backdrop = document.getElementById('sidebar-backdrop');
    var nav = document.querySelector('.nav');
    var COLLAPSE_KEY = 'hris-nav:sidebar-collapsed';

    function setToggleExpanded(expanded) {
        toggles.forEach(function (t) { t.setAttribute('aria-expanded', String(expanded)); });
    }

    /* ---------------- Sidebar drawer (mobile) ---------------- */

    function openSidebar() {
        body.classList.add('sidebar-open');
        setToggleExpanded(true);
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
        setToggleExpanded(false);
    }

    /* ---------------- App-drawer rail (desktop) ---------------- */

    function isDesktop() { return window.innerWidth >= 1024; }

    function setCollapsed(collapsed) {
        body.classList.toggle('sidebar-collapsed', collapsed);
        try { localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0'); } catch (e) { /* storage unavailable */ }
        setToggleExpanded(!collapsed);
    }

    function toggleSidebar() {
        if (isDesktop()) {
            setCollapsed(!body.classList.contains('sidebar-collapsed'));
        } else if (body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    toggles.forEach(function (t) { t.addEventListener('click', toggleSidebar); });

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (isDesktop() && body.classList.contains('sidebar-collapsed')) {
            setCollapsed(false);
        } else {
            closeSidebar();
        }
    });

    if (nav) {
        nav.addEventListener('click', function (event) {
            var linkClick = event.target.closest('a.nav-link');
            if (!linkClick) return;
            // Navigating from the collapsed rail always expands the drawer so
            // the destination is visible in context on the next page.
            if (isDesktop()) {
                if (body.classList.contains('sidebar-collapsed')) setCollapsed(false);
            } else {
                closeSidebar(); // mobile drawer hides on navigation
            }
        });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) closeSidebar();
    });

    // Restore the persisted desktop rail state and keep both hamburgers'
    // aria-expanded truthful (collapsed rail => 'false', else 'true').
    if (isDesktop()) {
        var savedCollapsed = null;
        try { savedCollapsed = localStorage.getItem(COLLAPSE_KEY); } catch (e) { /* ignore */ }
        var collapsed = savedCollapsed === '1';
        body.classList.toggle('sidebar-collapsed', collapsed);
        setToggleExpanded(!collapsed);
    }

    /* ---------------- Collapsible nav groups ----------------
       The CSS hides panels via `[aria-expanded="false"] + .nav-group-panel`,
       so JS only manages the aria-expanded state + localStorage. */

    document.querySelectorAll('.nav-group-toggle').forEach(function (toggle) {
        var panel = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!panel) return;
        var key = 'hris-nav:' + toggle.getAttribute('aria-controls');
        var saved = null;
        try { saved = localStorage.getItem(key); } catch (e) { /* storage unavailable */ }

        // The group containing the active link always stays open (and overrides
        // any saved preference); otherwise honor the saved state (default open).
        var hasActive = panel.querySelector('.nav-link.active');
        var expanded = hasActive ? true : (saved === null ? true : saved === '1');
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

        toggle.addEventListener('click', function () {
            // Expanding a group from the collapsed rail first restores the full
            // drawer so the group's items are actually visible.
            if (isDesktop() && body.classList.contains('sidebar-collapsed')) {
                setCollapsed(false);
            }
            var next = toggle.getAttribute('aria-expanded') !== 'true';
            toggle.setAttribute('aria-expanded', next ? 'true' : 'false');
            try { localStorage.setItem(key, next ? '1' : '0'); } catch (e) { /* ignore */ }
        });
    });

    /* ---------------- Table overflow hints ---------------- */

    function markTableHints() {
        document.querySelectorAll('.table-wrap').forEach(function (wrap) {
            var table = wrap.querySelector('table');
            var hasMore = table && table.scrollWidth > wrap.clientWidth + 8;
            wrap.classList.toggle('has-more', hasMore);
        });
    }

    markTableHints();
    window.addEventListener('resize', markTableHints);

    /* ---------------- Notifications: click marks read, then navigates ---------------- */

    document.addEventListener('click', function (event) {
        var item = event.target.closest('.notif-dd-item[data-id]');
        if (!item) return;

        event.preventDefault();
        var id = item.getAttribute('data-id');
        var url = item.getAttribute('data-url') || '/notifications';
        var token = document.querySelector('meta[name="csrf-token"]');

        function go() { window.location.href = url; }

        if (!id || !token) { go(); return; }

        fetch('/notifications/' + id + '/read', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token.getAttribute('content'), 'Accept': 'application/json' }
        }).catch(function () { /* soft-fail: still navigate */ }).finally(go);
    });

    /* ---------------- Toasts (action feedback) ----------------
       Server-rendered toasts (flash success/error/validation from the
       layout) get auto-dismiss timers + close buttons; hrisToast() creates
       new toasts from JS (e.g. attendance punch feedback). */

    var toastStack = document.querySelector('.toast-stack');
    if (!toastStack) {
        toastStack = document.createElement('div');
        toastStack.className = 'toast-stack';
        toastStack.setAttribute('aria-live', 'polite');
        document.body.appendChild(toastStack);
    }

    var TOAST_ICONS = {
        success: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        error: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>'
    };

    function dismissToast(toast) {
        if (toast._dismissed) return;
        toast._dismissed = true;
        clearTimeout(toast._timer);
        toast.classList.add('toast-leaving');
        setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 240);
    }

    function bindToastLifecycle(toast, ms) {
        var close = toast.querySelector('.toast-close');
        if (close) close.addEventListener('click', function () { dismissToast(toast); });
        toast.addEventListener('mouseenter', function () { clearTimeout(toast._timer); });
        toast.addEventListener('mouseleave', function () {
            toast._timer = setTimeout(function () { dismissToast(toast); }, ms);
        });
        toast._timer = setTimeout(function () { dismissToast(toast); }, ms);
    }

    /**
     * Show a toast. message: string (textContent-safe) or an HTMLElement/
     * DocumentFragment for richer content (e.g. error lists).
     * type: 'success' | 'error' | 'info'.
     */
    window.hrisToast = function (message, type) {
        type = type || 'info';
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

        var icon = document.createElement('span');
        icon.className = 'toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' + (TOAST_ICONS[type] || TOAST_ICONS.info) + '</svg>';

        var msg = document.createElement('div');
        msg.className = 'toast-msg';
        if (typeof message === 'string') msg.textContent = message;
        else msg.appendChild(message);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.textContent = '\u00d7';

        toast.appendChild(icon);
        toast.appendChild(msg);
        toast.appendChild(close);
        toastStack.appendChild(toast);

        bindToastLifecycle(toast, type === 'error' ? 7000 : 4500);
        return toast;
    };

    toastStack.querySelectorAll('.toast').forEach(function (toast) {
        var ms = parseInt(toast.getAttribute('data-dismiss') || '', 10);
        if (!ms) ms = toast.classList.contains('toast-error') ? 7000 : 4500;
        bindToastLifecycle(toast, ms);
    });

    /* ---------------- Dashboard chart ---------------- */

    var chartHost = document.getElementById('headcount-chart');
    if (chartHost && chartHost.dataset.chart) {
        var raw;
        try { raw = JSON.parse(chartHost.dataset.chart); } catch (e) { raw = []; }
        var data = raw.map(function (d) {
            return { label: String(d.label || ''), value: Number(d.value) || 0 };
        });

        var toggleGroup = document.getElementById('headcount-toggle');
        var currentView = 'area';

        var SVG_NS = 'http://www.w3.org/2000/svg';
        var BRAND = '#2563eb';

        function el(tag, attrs, parent) {
            var node = document.createElementNS(SVG_NS, tag);
            Object.keys(attrs).forEach(function (k) {
                node.setAttribute(k, attrs[k]);
            });
            if (parent) parent.appendChild(node);
            return node;
        }

        function fmt(n) { return Number(n).toLocaleString(); }

        function renderTable() {
            var wrap = document.createElement('div');
            wrap.style.overflowX = 'auto';
            var tbl = document.createElement('table');
            tbl.className = 'chart-tbl';
            tbl.innerHTML =
                '<thead><tr><th>Employment Type</th><th class="num">Employees</th></tr></thead><tbody>' +
                data.map(function (d) {
                    return '<tr><td>' + d.label.replace(/</g, '&lt;') + '</td><td class="num">' + fmt(d.value) + '</td></tr>';
                }).join('') +
                '</tbody>';
            wrap.appendChild(tbl);
            return wrap;
        }

        function renderArea() {
            var svg = el('svg', {
                viewBox: '0 0 640 260', width: '100%', height: '260',
                role: 'img', 'aria-label': 'Headcount by employment type, area chart'
            });

            var pad = { top: 24, right: 16, bottom: 30, left: 40 };
            var w = 640 - pad.left - pad.right;
            var h = 260 - pad.top - pad.bottom;
            var max = Math.max.apply(null, data.map(function (d) { return d.value; })) || 1;
            var n = Math.max(data.length, 1);
            var stepX = n > 1 ? w / (n - 1) : 0;

            function px(i) { return pad.left + (n > 1 ? i * stepX : w / 2); }
            function py(v) { return pad.top + h - (v / max) * h; }

            // Horizontal gridlines
            [0, 0.5, 1].forEach(function (f) {
                var y = pad.top + h - f * h;
                el('line', { x1: pad.left, x2: 640 - pad.right, y1: y, y2: y, stroke: '#e5e7eb', 'stroke-width': 1 }, svg);
            });

            // Y labels
            [0, 0.5, 1].forEach(function (f) {
                var y = pad.top + h - f * h;
                var t = el('text', { x: pad.left - 8, y: y + 4, 'text-anchor': 'end', 'font-size': 11, fill: '#9ca3af' }, svg);
                t.textContent = fmt(Math.round(f * max));
            });

            var linePath = data.map(function (d, i) {
                return (i === 0 ? 'M' : 'L') + px(i).toFixed(1) + ' ' + py(d.value).toFixed(1);
            }).join(' ');
            var areaPath = linePath + ' L' + px(n - 1).toFixed(1) + ' ' + (pad.top + h) + ' L' + px(0).toFixed(1) + ' ' + (pad.top + h) + ' Z';

            var grad = el('defs', {}, svg);
            var g = el('linearGradient', { id: 'headcountFill', x1: '0', y1: '0', x2: '0', y2: '1' }, grad);
            el('stop', { offset: '0%', 'stop-color': BRAND, 'stop-opacity': 0.35 }, g);
            el('stop', { offset: '100%', 'stop-color': BRAND, 'stop-opacity': 0.02 }, g);

            el('path', { d: areaPath, fill: 'url(#headcountFill)' }, svg);
            el('path', { d: linePath, fill: 'none', stroke: BRAND, 'stroke-width': 2, 'stroke-linejoin': 'round' }, svg);

            // Peak value bubble
            var peak = 0;
            data.forEach(function (d, i) { if (d.value > data[peak].value) peak = i; });
            var bpx = px(peak);
            var bpy = py(data[peak].value);
            el('rect', { x: bpx - 14, y: bpy - 28, width: 28, height: 18, rx: 6, fill: BRAND }, svg);
            var bt = el('text', { x: bpx, y: bpy - 15, 'text-anchor': 'middle', 'font-size': 11, 'font-weight': 600, fill: '#fff' }, svg);
            bt.textContent = fmt(data[peak].value);

            // X labels
            var every = Math.ceil(n / 10);
            data.forEach(function (d, i) {
                if (i % every !== 0 && i !== n - 1) return;
                var t = el('text', {
                    x: px(i), y: 260 - 10, 'text-anchor': 'middle',
                    'font-size': 10, fill: '#6b7280'
                }, svg);
                t.textContent = d.label;
            });

            return svg;
        }

        function renderBar() {
            var svg = el('svg', {
                viewBox: '0 0 640 260', width: '100%', height: '260',
                role: 'img', 'aria-label': 'Headcount by employment type, bar chart'
            });

            var pad = { top: 24, right: 16, bottom: 30, left: 40 };
            var w = 640 - pad.left - pad.right;
            var h = 260 - pad.top - pad.bottom;
            var max = Math.max.apply(null, data.map(function (d) { return d.value; })) || 1;
            var n = Math.max(data.length, 1);
            var slot = w / n;
            var barW = Math.max(Math.min(slot * 0.55, 42), 6);

            [0, 0.5, 1].forEach(function (f) {
                var y = pad.top + h - f * h;
                el('line', { x1: pad.left, x2: 640 - pad.right, y1: y, y2: y, stroke: '#e5e7eb', 'stroke-width': 1 }, svg);
                var t = el('text', { x: pad.left - 8, y: y + 4, 'text-anchor': 'end', 'font-size': 11, fill: '#9ca3af' }, svg);
                t.textContent = fmt(Math.round(f * max));
            });

            data.forEach(function (d, i) {
                var x = pad.left + i * slot + (slot - barW) / 2;
                var bh = (d.value / max) * h;
                var y = pad.top + h - bh;
                var rect = el('rect', {
                    x: x, y: y, width: barW, height: bh,
                    rx: Math.min(4, barW / 2), fill: BRAND
                }, svg);
                rect.style.transition = 'fill .12s';

                if (d.value > 0) {
                    var t = el('text', { x: x + barW / 2, y: y - 6, 'text-anchor': 'middle', 'font-size': 11, 'font-weight': 600, fill: '#111827' }, svg);
                    t.textContent = fmt(d.value);
                }

                var every = Math.ceil(n / 10);
                if (i % every === 0 || i === n - 1) {
                    var l = el('text', { x: x + barW / 2, y: 260 - 10, 'text-anchor': 'middle', 'font-size': 10, fill: '#6b7280' }, svg);
                    l.textContent = d.label;
                }
            });

            return svg;
        }

        function renderView(view) {
            chartHost.innerHTML = '';
            if (!data.length) {
                var empty = document.createElement('div');
                empty.className = 'empty';
                empty.textContent = 'No headcount data yet.';
                chartHost.appendChild(empty);
                return;
            }
            if (view === 'table') chartHost.appendChild(renderTable());
            else if (view === 'bar') chartHost.appendChild(renderBar());
            else chartHost.appendChild(renderArea());
        }

        if (toggleGroup) {
            toggleGroup.addEventListener('click', function (event) {
                var btn = event.target.closest('button[data-view]');
                if (!btn) return;
                currentView = btn.dataset.view;
                toggleGroup.querySelectorAll('button').forEach(function (b) {
                    var isActive = b === btn;
                    b.classList.toggle('active', isActive);
                    b.setAttribute('aria-pressed', String(isActive));
                });
                renderView(currentView);
            });
        }

        renderView(currentView);
    }
})();
