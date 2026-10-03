/**
 * JPOS Instant Navigation & Transition Engine
 *
 * Akselerasi transisi halaman dan penghapusan jeda/delay perpindahan menu di JPOS.
 *
 * CARA KERJA:
 * 1. Mendeteksi hover (pointerenter) dengan debounce 65ms pada link internal.
 * 2. Mendeteksi sentuhan (touchstart) atau tekanan mouse (pointerdown) seketika.
 * 3. Memanfaatkan Speculation Rules API (Chromium 109+, Edge, Chrome) untuk
 *    melakukan prefetch di latar belakang pada prioritas rendah.
 * 4. Fallback dengan injeksi <link rel="prefetch"> untuk peramban yang belum
 *    mendukung Speculation Rules.
 * 5. Pre-warm tautan navigasi utama saat peramban berada dalam kondisi idle (requestIdleCallback),
 *    sehingga saat kasir mengklik menu, halaman tujuan sudah ada di memori.
 *
 * KEAMANAN:
 * - Hanya tautan same-origin (localhost JPOS).
 * - Melewati seluruh rute mutasi data (logout, hapus, delete, wipe, restore, backup, ekspor, cetak).
 * - Tidak menambah dependensi eksternal apa pun (100% vanilla JS).
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') return;

    var prefetchedUrls = new Set();
    var speculationSupported = typeof HTMLScriptElement !== 'undefined' &&
        HTMLScriptElement.supports && HTMLScriptElement.supports('speculationrules');

    // Rute yang TIDAK BOLEH di-prefetch (rute mutasi, aksi administratif, cetak, atau unduh berkas)
    var IGNORED_PATTERNS = [
        /\/logout/i,
        /\/hapus/i,
        /\/delete/i,
        /\/wipe/i,
        /\/restore/i,
        /\/backup\/unduh/i,
        /\/export/i,
        /\/pdf/i,
        /\/xlsx/i,
        /\/print/i,
        /\/receipt/i,
        /\/download/i
    ];

    function isEligibleLink(anchor) {
        if (!anchor || anchor.tagName !== 'A') return false;

        var href = anchor.getAttribute('href');
        if (!href || href === '#' || href.startsWith('#') || href.startsWith('javascript:')) return false;

        // Jangan prefetch jika memiliki atribut khusus
        if (anchor.hasAttribute('download') || anchor.hasAttribute('data-no-instant')) return false;
        var target = anchor.getAttribute('target');
        if (target && target !== '_self') return false;

        try {
            var url = new URL(anchor.href, window.location.href);

            // Hanya same-origin
            if (url.origin !== window.location.origin) return false;

            // Jangan prefetch halaman yang sedang dibuka
            if (url.pathname === window.location.pathname && url.search === window.location.search) return false;

            // Lewati pola terlarang
            for (var i = 0; i < IGNORED_PATTERNS.length; i++) {
                if (IGNORED_PATTERNS[i].test(url.pathname)) return false;
            }

            return url.href;
        } catch (e) {
            return false;
        }
    }

    function prefetchUrl(url) {
        if (!url || prefetchedUrls.has(url)) return;
        prefetchedUrls.add(url);

        if (speculationSupported) {
            try {
                var script = document.createElement('script');
                script.type = 'speculationrules';
                script.textContent = JSON.stringify({
                    prefetch: [
                        {
                            source: 'list',
                            urls: [url]
                        }
                    ]
                });
                document.head.appendChild(script);
                return;
            } catch (e) {}
        }

        // Fallback: <link rel="prefetch">
        try {
            var link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = url;
            link.as = 'document';
            document.head.appendChild(link);
        } catch (e) {}
    }

    var hoverTimer = null;
    var currentTarget = null;

    function handlePointerEnter(e) {
        var anchor = e.target && e.target.closest ? e.target.closest('a') : null;
        var url = isEligibleLink(anchor);
        if (!url) return;

        currentTarget = url;
        if (hoverTimer) clearTimeout(hoverTimer);

        // 65ms debounce untuk menyaring gerakan mouse cepat tanpa menahan klik nyata
        hoverTimer = setTimeout(function () {
            if (currentTarget === url) {
                prefetchUrl(url);
            }
        }, 65);
    }

    function handlePointerLeave(e) {
        if (hoverTimer) {
            clearTimeout(hoverTimer);
            hoverTimer = null;
        }
        currentTarget = null;
    }

    function handlePointerDown(e) {
        var anchor = e.target && e.target.closest ? e.target.closest('a') : null;
        var url = isEligibleLink(anchor);
        if (url) {
            prefetchUrl(url);
        }
    }

    // Pre-warm menu utama saat idle (prioritas rendah)
    function prewarmSidebar() {
        var nav = document.querySelector('[data-sidebar-nav]');
        if (!nav) return;

        var links = nav.querySelectorAll('a');
        var count = 0;
        for (var i = 0; i < links.length && count < 5; i++) {
            var url = isEligibleLink(links[i]);
            if (url) {
                prefetchUrl(url);
                count++;
            }
        }
    }

    function init() {
        document.addEventListener('pointerenter', handlePointerEnter, { capture: true, passive: true });
        document.addEventListener('pointerleave', handlePointerLeave, { capture: true, passive: true });
        document.addEventListener('pointerdown', handlePointerDown, { capture: true, passive: true });
        document.addEventListener('touchstart', handlePointerDown, { capture: true, passive: true });

        // Prewarm saat browser idle setelah halaman pertama termuat
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(prewarmSidebar, { timeout: 2000 });
        } else {
            setTimeout(prewarmSidebar, 1200);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
