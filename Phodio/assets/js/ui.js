/* ============================================================
   SOULPRINT — Shared UI behaviour (ui.js)
   Loaded on every page (client portal + admin console) so the
   interactions feel identical everywhere.
   ============================================================ */
(function (window, document) {
    'use strict';

    var UI = window.SoulprintUI = window.SoulprintUI || {};
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------------------------------------------------------
       1. Reveal on scroll (with automatic stagger per container)
       --------------------------------------------------------- */
    function initReveal() {
        var nodes = document.querySelectorAll('[data-sp-reveal]');
        if (!nodes.length) { return; }

        if (reduceMotion || !('IntersectionObserver' in window)) {
            nodes.forEach(function (node) { node.classList.add('is-visible'); });
            return;
        }

        // Siblings that share a parent get an increasing delay so groups
        // (stat cards, list rows) cascade instead of appearing all at once.
        var groups = new Map();
        nodes.forEach(function (node) {
            var key = node.parentElement || document.body;
            var index = groups.get(key) || 0;
            if (!node.style.getPropertyValue('--sp-delay')) {
                node.style.setProperty('--sp-delay', (index * 70) + 'ms');
            }
            groups.set(key, index + 1);
        });

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        nodes.forEach(function (node) { observer.observe(node); });
    }

    /* ---------------------------------------------------------
       2. Count-up numbers — data-sp-count="1234.5"
       --------------------------------------------------------- */
    function animateCount(node) {
        var target = parseFloat(node.getAttribute('data-sp-count'));
        if (isNaN(target)) { return; }
        var decimals = parseInt(node.getAttribute('data-sp-decimals') || '0', 10);
        var prefix = node.getAttribute('data-sp-prefix') || '';
        var suffix = node.getAttribute('data-sp-suffix') || '';
        var duration = parseInt(node.getAttribute('data-sp-duration') || '1100', 10);

        function format(value) {
            return prefix + value.toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }) + suffix;
        }

        if (reduceMotion) { node.textContent = format(target); return; }

        var start = performance.now();
        function frame(now) {
            var progress = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            node.textContent = format(target * eased);
            if (progress < 1) { requestAnimationFrame(frame); }
        }
        requestAnimationFrame(frame);
    }

    function initCounters() {
        var nodes = document.querySelectorAll('[data-sp-count]');
        if (!nodes.length) { return; }
        if (reduceMotion || !('IntersectionObserver' in window)) {
            nodes.forEach(animateCount);
            return;
        }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                animateCount(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.4 });
        nodes.forEach(function (node) {
            node.setAttribute('data-sp-raw', node.textContent.trim());
            observer.observe(node);
        });
    }

    /* ---------------------------------------------------------
       3. Meter fill — data-sp-meter="62"
       --------------------------------------------------------- */
    function initMeters() {
        document.querySelectorAll('[data-sp-meter]').forEach(function (meter) {
            var fill = meter.firstElementChild;
            if (!fill) { return; }
            var value = Math.max(0, Math.min(100, parseFloat(meter.getAttribute('data-sp-meter')) || 0));
            window.setTimeout(function () { fill.style.width = value + '%'; }, reduceMotion ? 0 : 180);
        });
    }

    /* ---------------------------------------------------------
       4. Ripple feedback on buttons
       --------------------------------------------------------- */
    function initRipples() {
        if (reduceMotion) { return; }
        document.addEventListener('pointerdown', function (event) {
            var host = event.target.closest('.btn, .nav-link-custom, .sp-ripple-host');
            if (!host || host.disabled) { return; }
            if (getComputedStyle(host).position === 'static') { host.style.position = 'relative'; }
            host.style.overflow = 'hidden';

            var rect = host.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var ripple = document.createElement('span');
            ripple.className = 'sp-ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
            host.appendChild(ripple);
            window.setTimeout(function () { ripple.remove(); }, 640);
        });
    }

    /* ---------------------------------------------------------
       5. Scroll progress bar
       --------------------------------------------------------- */
    function initScrollProgress() {
        if (reduceMotion) { return; }
        var bar = document.createElement('div');
        bar.className = 'sp-scroll-progress';
        bar.innerHTML = '<span></span>';
        document.body.appendChild(bar);
        var fill = bar.firstElementChild;
        var ticking = false;

        function update() {
            var doc = document.documentElement;
            var max = doc.scrollHeight - window.innerHeight;
            fill.style.width = (max > 0 ? (window.scrollY / max) * 100 : 0) + '%';
            ticking = false;
        }
        window.addEventListener('scroll', function () {
            if (ticking) { return; }
            ticking = true;
            requestAnimationFrame(update);
        }, { passive: true });
        update();
    }

    /* ---------------------------------------------------------
       6. Toasts — SoulprintUI.toast('Saved', 'success')
       --------------------------------------------------------- */
    var toastHost = null;
    UI.toast = function (message, type) {
        type = type || 'dark';
        if (!toastHost) {
            toastHost = document.createElement('div');
            toastHost.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastHost.style.zIndex = '2000';
            document.body.appendChild(toastHost);
        }
        var icons = {
            success: 'ri-check-line',
            danger: 'ri-error-warning-line',
            error: 'ri-error-warning-line',
            warning: 'ri-alert-line',
            info: 'ri-information-line',
            dark: 'ri-notification-3-line'
        };
        var element = document.createElement('div');
        element.className = 'toast align-items-center border-0 mb-2';
        element.setAttribute('role', 'status');
        element.setAttribute('aria-live', 'polite');
        element.innerHTML =
            '<div class="d-flex">' +
            '<div class="toast-body d-flex align-items-center gap-2">' +
            '<i class="' + (icons[type] || icons.dark) + ' fs-5"></i><span></span></div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '</div>';
        element.querySelector('span').textContent = message;
        toastHost.appendChild(element);

        if (window.bootstrap && bootstrap.Toast) {
            var instance = new bootstrap.Toast(element, { delay: 4200 });
            instance.show();
            element.addEventListener('hidden.bs.toast', function () { element.remove(); });
        } else {
            element.classList.add('show');
            window.setTimeout(function () { element.remove(); }, 4200);
        }
    };

    /* ---------------------------------------------------------
       7. Themed confirm dialog (replaces window.confirm)
          Usage: <a href="..." data-sp-confirm="Delete this record?">
       --------------------------------------------------------- */
    function buildConfirm() {
        var wrapper = document.createElement('div');
        wrapper.className = 'modal fade';
        wrapper.id = 'spConfirmModal';
        wrapper.tabIndex = -1;
        wrapper.innerHTML =
            '<div class="modal-dialog modal-dialog-centered modal-sm">' +
            '<div class="modal-content">' +
            '<div class="modal-body p-4 text-center">' +
            '<div class="sp-empty__icon mb-3" style="width:54px;height:54px;font-size:1.4rem"><i class="ri-alert-line"></i></div>' +
            '<h2 class="h6 fw-bold mb-2" data-role="title">Are you sure?</h2>' +
            '<p class="small text-muted mb-4" data-role="message">This action cannot be undone.</p>' +
            '<div class="d-flex gap-2 justify-content-center">' +
            '<button type="button" class="btn btn-outline-light px-3" data-bs-dismiss="modal">Cancel</button>' +
            '<button type="button" class="btn btn-danger px-3" data-role="confirm">Confirm</button>' +
            '</div></div></div></div>';
        document.body.appendChild(wrapper);
        return wrapper;
    }

    var confirmModal = null;
    var confirmResolve = null;

    UI.confirm = function (title, message) {
        return new Promise(function (resolve) {
            if (!window.bootstrap || !bootstrap.Modal) { resolve(window.confirm(title + ' ' + (message || ''))); return; }
            var host = confirmModal || (confirmModal = buildConfirm());
            host.querySelector('[data-role="title"]').textContent = title || 'Are you sure?';
            host.querySelector('[data-role="message"]').textContent = message || 'This action cannot be undone.';
            confirmResolve = resolve;

            var instance = bootstrap.Modal.getOrCreateInstance(host);
            host.querySelector('[data-role="confirm"]').onclick = function () { instance.hide(); resolve(true); };
            host.addEventListener('hidden.bs.modal', function handler() {
                host.removeEventListener('hidden.bs.modal', handler);
                if (confirmResolve) { var done = confirmResolve; confirmResolve = null; done(false); }
            });
            instance.show();
        });
    };

    function initConfirmLinks() {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('[data-sp-confirm]');
            if (!link) { return; }
            event.preventDefault();
            event.stopPropagation();
            UI.confirm(
                link.getAttribute('data-sp-confirm'),
                link.getAttribute('data-sp-confirm-message') || 'This action cannot be undone.'
            ).then(function (ok) {
                if (!ok) { return; }
                if (link.dataset.spConfirmAction === 'submit' && link.form) { link.form.submit(); return; }
                window.location.href = link.getAttribute('href');
            });
        });
    }

    /* ---------------------------------------------------------
       8. Password visibility toggle
       --------------------------------------------------------- */
    function initPasswordToggles() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-sp-toggle-password]');
            if (!button) { return; }
            var input = document.getElementById(button.getAttribute('data-sp-toggle-password'));
            if (!input) { return; }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            var icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('ri-eye-line', !show);
                icon.classList.toggle('ri-eye-off-line', show);
            }
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    }

    /* ---------------------------------------------------------
       9. Sidebar (admin, mobile)
       --------------------------------------------------------- */
    function initSidebar() {
        var sidebar = document.getElementById('mainSidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (!sidebar) { return; }

        UI.toggleSidebar = function (force) {
            var open = typeof force === 'boolean' ? force : !sidebar.classList.contains('show');
            sidebar.classList.toggle('show', open);
            if (overlay) { overlay.classList.toggle('show', open); }
            sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.body.style.overflow = open ? 'hidden' : '';
        };

        document.addEventListener('click', function (event) {
            if (event.target.closest('[data-sp-sidebar-toggle]')) { UI.toggleSidebar(); }
            if (overlay && event.target === overlay) { UI.toggleSidebar(false); }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('show')) { UI.toggleSidebar(false); }
        });
    }

    /* ---------------------------------------------------------
       10. Flash messages auto-dismiss + shake on error
       --------------------------------------------------------- */
    function initFlashes() {
        document.querySelectorAll('[data-sp-flash]').forEach(function (flash) {
            var delay = parseInt(flash.getAttribute('data-sp-flash'), 10);
            if (delay > 0) { window.setTimeout(function () {
                flash.style.transition = 'opacity .4s ease, transform .4s ease';
                flash.style.opacity = '0';
                flash.style.transform = 'translateY(-8px)';
                window.setTimeout(function () { flash.remove(); }, 420);
            }, delay); }

            var close = flash.querySelector('[data-sp-flash-close]');
            if (close) { close.addEventListener('click', function () { flash.remove(); }); }
        });

        document.querySelectorAll('.sp-alert--danger, .alert-danger').forEach(function (alert) {
            if (!reduceMotion) { alert.classList.add('sp-shake'); }
        });
    }

    /* ---------------------------------------------------------
       11. Submitting state for forms
       --------------------------------------------------------- */
    function initFormStates() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (form.dataset.spAjax === '1' || form.dataset.spSubmitting === '1') { return; }
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) { return; }
            form.dataset.spSubmitting = '1';
            var button = form.querySelector('button[type="submit"], input[type="submit"]');
            if (!button) { return; }
            button.dataset.spLabel = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="sp-spinner me-2"></span>Saving…';
            // Safety net: never leave a button stuck if navigation is cancelled.
            window.setTimeout(function () {
                if (button.dataset.spLabel) { button.innerHTML = button.dataset.spLabel; button.disabled = false; }
            }, 8000);
        });
    }

    /* ---------------------------------------------------------
       12. Smooth in-page anchors with header offset
       --------------------------------------------------------- */
    function initAnchors() {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href^="#"]');
            if (!link) { return; }
            var id = link.getAttribute('href').slice(1);
            if (!id) { return; }
            var target = document.getElementById(id);
            if (!target) { return; }
            event.preventDefault();
            target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            if (history.replaceState) { history.replaceState(null, '', '#' + id); }
        });
    }

    /* ---------------------------------------------------------
       Boot
       --------------------------------------------------------- */
    function boot() {
        document.documentElement.classList.remove('sp-no-js');
        initReveal();
        initCounters();
        initMeters();
        initRipples();
        initScrollProgress();
        initConfirmLinks();
        initPasswordToggles();
        initSidebar();
        initFlashes();
        initFormStates();
        initAnchors();
        document.dispatchEvent(new CustomEvent('sp:ready', { detail: UI }));
    }

    UI.initReveal = initReveal;
    UI.animateCount = animateCount;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
