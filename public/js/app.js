/**
 * DICT RO2 HRIS — minimal UI behaviours (vanilla JS, no dependencies).
 * Mobile sidebar drawer + table overflow hints.
 */
(function () {
    'use strict';

    var body = document.body;
    var toggle = document.getElementById('sidebar-toggle');
    var backdrop = document.getElementById('sidebar-backdrop');
    var nav = document.querySelector('.nav');

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

    // Navigating from the drawer closes it (works even with page reload).
    if (nav) {
        nav.addEventListener('click', function (event) {
            if (event.target.closest('a.nav-link')) closeSidebar();
        });
    }

    // Never leave the drawer open after rotating to a desktop-size window.
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) closeSidebar();
    });

    // Show a "swipe to see more" hint only when a table actually overflows.
    function markTableHints() {
        document.querySelectorAll('.table-wrap').forEach(function (wrap) {
            var table = wrap.querySelector('table');
            var hasMore = table && table.scrollWidth > wrap.clientWidth + 8;
            wrap.classList.toggle('has-more', hasMore);
        });
    }

    markTableHints();
    window.addEventListener('resize', markTableHints);
})();
