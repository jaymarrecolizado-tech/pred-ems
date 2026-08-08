/**
 * DICT RO2 HRIS — UI behaviours (vanilla JS, no dependencies).
 * Mobile sidebar drawer, table overflow hints, and the dashboard
 * headcount chart (SVG area / bar / table views).
 */
(function () {
    'use strict';

    var body = document.body;
    var toggle = document.getElementById('sidebar-toggle');
    var backdrop = document.getElementById('sidebar-backdrop');
    var nav = document.querySelector('.nav');

    /* ---------------- Sidebar drawer ---------------- */

    function openSidebar() {
        body.classList.add('sidebar-open');
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (body.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeSidebar();
    });

    if (nav) {
        nav.addEventListener('click', function (event) {
            if (event.target.closest('a.nav-link')) closeSidebar();
        });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) closeSidebar();
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
