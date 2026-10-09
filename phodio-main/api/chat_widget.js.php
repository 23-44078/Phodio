<?php
/**
 * Shared booking-chat widget.
 *
 * It is served as .php on purpose: vercel.json only routes *.php URLs through
 * api/router.php, so a plain .js file would not be guaranteed to resolve on
 * Vercel. The MIME type is set explicitly below.
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=300');
?>
(function () {
    'use strict';

    var STYLE_ID = 'phodio-chat-styles';

    var CSS = [
        '.pc{--pc-line:#303849;--pc-soft:#171d29;--pc-text:#e5e7eb;--pc-muted:#9aa4b2;--pc-accent:#3b82f6;position:relative;font-size:.9rem}',
        '.pc *{box-sizing:border-box}',
        '.pc-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.55rem .1rem;border-bottom:1px solid var(--pc-line)}',
        '.pc-title{font-weight:700;color:var(--pc-text);display:inline-flex;align-items:center;gap:.4rem;letter-spacing:.02em}',
        '.pc-status{font-size:.72rem;color:var(--pc-muted)}',
        '.pc-log{max-height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:.5rem;padding:.75rem .1rem;scrollbar-width:thin}',
        '.pc-row{display:flex}',
        '.pc-row--out{justify-content:flex-end}',
        '.pc-bubble{max-width:86%;border-radius:12px;padding:.5rem .7rem;background:var(--pc-soft);border:1px solid var(--pc-line);color:var(--pc-text);word-break:break-word;white-space:pre-wrap;line-height:1.45}',
        '.pc-row--out .pc-bubble{background:rgba(59,130,246,.16);border-color:rgba(59,130,246,.45)}',
        '.pc-meta{display:flex;gap:.45rem;align-items:baseline;font-size:.7rem;color:var(--pc-muted);margin-bottom:.2rem}',
        '.pc-who{font-weight:700;color:var(--pc-text)}',
        '.pc-bubble--pending{opacity:.55}',
        '.pc-empty{color:var(--pc-muted);text-align:center;padding:1.25rem .75rem;font-size:.82rem;border:1px dashed var(--pc-line);border-radius:10px;line-height:1.5}',
        '.pc-form{display:flex;gap:.5rem;align-items:flex-end;border-top:1px solid var(--pc-line);padding-top:.6rem}',
        '.pc-input{flex:1;resize:none;min-height:38px;max-height:140px;border-radius:10px;border:1px solid var(--pc-line);background:var(--pc-soft);color:var(--pc-text);padding:.5rem .65rem;font:inherit}',
        '.pc-input::placeholder{color:#6b7280}',
        '.pc-input:focus{outline:none;border-color:var(--pc-accent);box-shadow:0 0 0 2px rgba(59,130,246,.35)}',
        '.pc-input:focus-visible{outline:none}',
        '.pc-send:focus-visible{outline:2px solid #fff;outline-offset:2px}',
        '.pc-send{border:0;border-radius:10px;background:var(--pc-accent);color:#fff;width:44px;height:38px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;flex:0 0 auto;font-size:1rem}',
        '.pc-send:hover:not(:disabled){filter:brightness(1.12)}',
        '.pc-send:disabled{opacity:.5;cursor:not-allowed}',
        '.pc-error{color:#fca5a5;font-size:.78rem;margin:.55rem 0 0;display:flex;gap:.35rem;align-items:flex-start}',
        '.pc-empty i,.pc-title i{font-size:1.05em}'
    ].join('');

    function injectStyles() {
        if (document.getElementById(STYLE_ID)) {
            return;
        }

        var style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = CSS;
        document.head.appendChild(style);
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function relativeTime(iso) {
        if (!iso) {
            return '';
        }

        var then = new Date(iso).getTime();

        if (isNaN(then)) {
            return '';
        }

        var seconds = Math.max(0, Math.round((Date.now() - then) / 1000));

        if (seconds < 45) {
            return 'just now';
        }

        var minutes = Math.round(seconds / 60);

        if (minutes < 60) {
            return minutes + 'm ago';
        }

        var hours = Math.round(minutes / 60);

        if (hours < 24) {
            return hours + 'h ago';
        }

        var days = Math.round(hours / 24);

        if (days < 7) {
            return days + 'd ago';
        }

        return new Date(iso).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric'
        });
    }

    function buildMarkup(options) {
        return ''
            + '<div class="pc-head">'
            + '<span class="pc-title"><i class="ri-chat-3-line"></i>'
            + escapeHtml(options.title)
            + '</span>'
            + '<span class="pc-status" data-role="status" aria-live="polite"></span>'
            + '</div>'
            + '<div class="pc-log" data-role="log" role="log" aria-live="polite" aria-label="' + escapeHtml(options.title) + '"></div>'
            + '<form class="pc-form" data-role="form" autocomplete="off">'
            + '<label class="visually-hidden" for="pc-input-' + options.uid + '">Message</label>'
            + '<textarea class="pc-input" id="pc-input-' + options.uid + '" data-role="input" rows="1" maxlength="' + options.maxBody + '" placeholder="' + escapeHtml(options.placeholder) + '"></textarea>'
            + '<button class="pc-send" type="submit" data-role="send" aria-label="Send message"><i class="ri-send-plane-fill"></i></button>'
            + '</form>'
            + '<p class="pc-error" data-role="error" hidden></p>';
    }

    var uidCounter = 0;

    function mount(options) {
        options = options || {};

        var container = options.container;

        if (typeof container === 'string') {
            container = document.getElementById(container);
        }

        if (!container) {
            return null;
        }

        var bookingId = Number(options.bookingId);

        if (!bookingId) {
            return null;
        }

        if (container._phodioChat && container._phodioChat.destroy) {
            container._phodioChat.destroy();
        }

        injectStyles();

        uidCounter += 1;

        var settings = {
            uid: uidCounter,
            title: options.title || 'Messages',
            placeholder: options.placeholder || 'Write a message…',
            emptyText: options.emptyText || 'No messages yet. Start the conversation.',
            maxBody: Number(options.maxBody) || 2000,
            pollMs: Number(options.pollMs) || 15000,
            endpoint: options.endpoint || 'chat.php'
        };

        container.innerHTML = '<div class="pc">' + buildMarkup(settings) + '</div>';

        var root = container.firstElementChild;
        var log = root.querySelector('[data-role="log"]');
        var form = root.querySelector('[data-role="form"]');
        var input = root.querySelector('[data-role="input"]');
        var sendButton = root.querySelector('[data-role="send"]');
        var statusNode = root.querySelector('[data-role="status"]');
        var errorNode = root.querySelector('[data-role="error"]');

        var viewer = { type: '', id: 0, name: '' };
        var token = '';
        var sending = false;
        var pending = null;
        var destroyed = false;
        var stickToBottom = true;

        function showError(message) {
            if (!message) {
                errorNode.hidden = true;
                errorNode.textContent = '';
                return;
            }

            errorNode.innerHTML = '<i class="ri-error-warning-line"></i><span>'
                + escapeHtml(message) + '</span>';
            errorNode.hidden = false;
        }

        function setStatus(text) {
            statusNode.textContent = text || '';
        }

        function authParams() {
            var params = {};

            if (token) {
                params.chat_token = token;
                params.chat_sender_type = viewer.type;
                params.chat_sender_id = viewer.id;
            }

            return params;
        }

        function authHeaders() {
            return token ? { 'X-Phodio-Chat-Token': token } : {};
        }

        function scrollToBottom() {
            log.scrollTop = log.scrollHeight;
        }

        function bubble(message) {
            var isOut = message.sender_type === viewer.type;
            var row = document.createElement('div');

            row.className = 'pc-row' + (isOut ? ' pc-row--out' : '');

            var who = isOut
                ? 'You'
                : (message.sender_name || (message.sender_type === 'admin' ? 'Studio' : 'Client'));

            var bubbleNode = document.createElement('div');

            bubbleNode.className = 'pc-bubble'
                + (message.pending ? ' pc-bubble--pending' : '');

            bubbleNode.innerHTML = ''
                + '<div class="pc-meta">'
                + '<span class="pc-who">' + escapeHtml(who) + '</span>'
                + '<span>' + escapeHtml(
                    message.pending ? 'Sending…' : relativeTime(message.created_at)
                ) + '</span>'
                + '</div>'
                + '<div class="pc-bubble__body">' + escapeHtml(message.body) + '</div>';

            row.appendChild(bubbleNode);

            return row;
        }

        function render(messages) {
            if (messages && messages !== lastMessages) {
                lastMessages = messages;
            }

            var nearBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 80;

            log.innerHTML = '';

            if (!messages.length && !pending) {
                var empty = document.createElement('div');
                empty.className = 'pc-empty';
                empty.innerHTML = '<i class="ri-chat-1-line"></i><br>'
                    + escapeHtml(settings.emptyText);
                log.appendChild(empty);
                return;
            }

            messages.forEach(function (message) {
                log.appendChild(bubble(message));
            });

            if (pending) {
                log.appendChild(bubble(pending));
            }

            if (stickToBottom || nearBottom) {
                scrollToBottom();
            }
        }

        function applyIdentity(identity) {
            if (!identity) {
                return;
            }

            viewer.type = identity.type || viewer.type;
            viewer.id = Number(identity.id) || viewer.id;
            viewer.name = identity.name || viewer.name;

            if (identity.token) {
                token = identity.token;
            }
        }

        function load(silent) {
            if (destroyed) {
                return Promise.resolve();
            }

            var params = new URLSearchParams();

            params.set('booking_id', String(bookingId));

            var auth = authParams();

            Object.keys(auth).forEach(function (key) {
                params.set(key, String(auth[key]));
            });

            if (!silent) {
                setStatus('Loading…');
            }

            return fetch(settings.endpoint + '?' + params.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: Object.assign({ Accept: 'application/json' }, authHeaders())
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return { ok: false, message: 'Chat is unavailable right now.' };
                    }).then(function (data) {
                        return { response: response, data: data };
                    });
                })
                .then(function (result) {
                    if (destroyed) {
                        return;
                    }

                    if (!result.response.ok || !result.data.ok) {
                        showError(result.data.message || 'Chat is unavailable right now.');
                        setStatus('');
                        return;
                    }

                    showError('');
                    applyIdentity(result.data.viewer);
                    render(result.data.messages || []);
                    setStatus('');
                })
                .catch(function () {
                    if (!destroyed && !silent) {
                        showError('Could not reach the chat service.');
                        setStatus('');
                    }
                });
        }

        function autoResize() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 140) + 'px';
        }

        function submit() {
            var text = input.value.trim();

            if (!text || sending) {
                return;
            }

            sending = true;
            sendButton.disabled = true;
            showError('');

            pending = {
                sender_type: viewer.type || 'client',
                sender_name: viewer.name || 'You',
                body: text,
                created_at: new Date().toISOString(),
                pending: true
            };

            input.value = '';
            autoResize();
            stickToBottom = true;
            render(lastMessages);

            var body = new URLSearchParams();

            body.set('booking_id', String(bookingId));
            body.set('body', text);

            var auth = authParams();

            Object.keys(auth).forEach(function (key) {
                body.set(key, String(auth[key]));
            });

            return fetch(settings.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: Object.assign({
                    'Content-Type': 'application/x-www-form-urlencoded',
                    Accept: 'application/json'
                }, authHeaders()),
                body: body.toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return { ok: false, message: 'Could not send that message.' };
                    }).then(function (data) {
                        return { response: response, data: data };
                    });
                })
                .then(function (result) {
                    pending = null;

                    if (!result.response.ok || !result.data.ok) {
                        input.value = text;
                        autoResize();
                        showError(result.data.message || 'Could not send that message.');
                        return;
                    }

                    applyIdentity(result.data.viewer);
                    setMessages(result.data.messages || []);
                })
                .catch(function () {
                    pending = null;
                    input.value = text;
                    autoResize();
                    showError('Could not reach the chat service.');
                })
                .then(function () {
                    sending = false;
                    sendButton.disabled = false;
                    render(lastMessages);
                });
        }

        var lastMessages = [];

        function setMessages(messages) {
            lastMessages = messages;
            render(messages);
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submit();
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    submit();
                }
            }
        });

        input.addEventListener('input', autoResize);

        log.addEventListener('scroll', function () {
            stickToBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 80;
        });

        var timer = window.setInterval(function () {
            if (!document.hidden && !destroyed && !sending) {
                load(true);
            }
        }, settings.pollMs);

        var api = {
            reload: function () {
                return load(false);
            },
            destroy: function () {
                destroyed = true;
                window.clearInterval(timer);

                if (container) {
                    container._phodioChat = null;
                    container.innerHTML = '';
                }
            }
        };

        container._phodioChat = api;

        load(false);

        return api;
    }

    window.PhodioChat = {
        mount: mount
    };
})();
