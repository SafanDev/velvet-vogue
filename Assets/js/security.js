(() => {
    'use strict';

    const CSRF_PROTOCOL_VERSION = 7;
    const tokenSelector = 'meta[name="csrf-token"]';
    const baseUrlSelector = 'meta[name="app-base-url"]';
    const unsafeMethods = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
    const currentTokenPattern = /^v7\.\d{10}\.[a-f0-9]{32}\.[a-f0-9]{64}$/i;
    const originalFetch = window.fetch.bind(window);
    let tokenRefreshPromise = null;
    let tokenVerifiedAt = 0;
    let hiddenAt = 0;

    const getToken = () => {
        const metaToken = document.querySelector(tokenSelector)?.content;
        if (metaToken) return metaToken;
        return document.querySelector('[data-csrf-token]')?.dataset.csrfToken || '';
    };

    const getAppBaseUrl = () => {
        const configured = document.querySelector(baseUrlSelector)?.content
            || document.querySelector('[data-app-base-url]')?.dataset.appBaseUrl;

        if (configured) {
            try {
                const configuredUrl = new URL(configured, window.location.href);
                if (configuredUrl.origin === window.location.origin) {
                    return configuredUrl.href.replace(/\/$/, '');
                }
            } catch {
                // Fall back to the current request path below.
            }
        }

        const path = window.location.pathname
            .replace(/\/(?:Customer|Admin|Actions|Config|scripts)(?:\/.*)?$/i, '')
            .replace(/\/[^/]+\.php$/i, '');
        return `${window.location.origin}${path}`.replace(/\/$/, '');
    };

    const updateToken = (token, verified = true) => {
        if (typeof token !== 'string' || !currentTokenPattern.test(token)) return false;

        const meta = document.querySelector(tokenSelector);
        if (meta) meta.content = token;

        document.querySelectorAll('[data-csrf-token]').forEach((element) => {
            element.dataset.csrfToken = token;
        });

        document.querySelectorAll('input[name="_csrf"]').forEach((input) => {
            input.value = token;
        });

        if (verified) tokenVerifiedAt = Date.now();
        document.dispatchEvent(new CustomEvent('vv:csrf-updated', { detail: { token } }));
        return true;
    };

    const refreshToken = () => {
        if (tokenRefreshPromise) return tokenRefreshPromise;

        const endpoint = new URL('Actions/csrf_token.php', `${getAppBaseUrl()}/`);
        tokenRefreshPromise = originalFetch(endpoint.href, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(async (response) => {
                const headerToken = response.headers.get('X-CSRF-Token');
                const payload = await response.json().catch(() => ({}));
                const token = headerToken || payload.token || '';
                if (!response.ok || !updateToken(token)) {
                    throw new Error('Unable to refresh the session token.');
                }
                return token;
            })
            .finally(() => {
                tokenRefreshPromise = null;
            });

        return tokenRefreshPromise;
    };

    const ensureFreshToken = async (force = false) => {
        const currentToken = getToken();
        const recentlyVerified = tokenVerifiedAt > 0 && Date.now() - tokenVerifiedAt < 60000;

        if (!force && currentTokenPattern.test(currentToken) && recentlyVerified) return currentToken;

        try {
            return await refreshToken();
        } catch (error) {
            if (currentTokenPattern.test(currentToken)) return currentToken;
            throw error;
        }
    };

    const isUnsafeMethod = (method) => unsafeMethods.has(String(method || 'GET').toUpperCase());

    const isSameOrigin = (input) => {
        try {
            const value = input instanceof Request ? input.url : input;
            const url = new URL(value instanceof URL ? value.href : String(value), window.location.href);
            return url.origin === window.location.origin;
        } catch {
            return false;
        }
    };

    const addTokenToBody = (body, token) => {
        if (!token || !body) return;
        if (body instanceof FormData || body instanceof URLSearchParams) {
            body.set('_csrf', token);
        }
    };

    const addTokenToForm = (form) => {
        if (!(form instanceof HTMLFormElement)) return;
        if ((form.getAttribute('method') || '').toLowerCase() === 'get') return;

        let input = form.querySelector('input[name="_csrf"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_csrf';
            form.appendChild(input);
        }
        input.value = getToken();
    };

    const prepareForms = (root = document) => {
        if (root instanceof HTMLFormElement) addTokenToForm(root);
        root.querySelectorAll?.('form').forEach(addTokenToForm);
    };

    const responseHasInvalidToken = async (response) => {
        if (response.ok) return false;
        try {
            const payload = await response.clone().json();
            return payload?.code === 'request_verification_failed';
        } catch {
            return false;
        }
    };

    window.fetch = async (input, init = {}) => {
        const method = init.method || (input instanceof Request ? input.method : 'GET');
        if (!isUnsafeMethod(method) || !isSameOrigin(input)) {
            return originalFetch(input, init);
        }

        const send = async (token, attempt = 0) => {
            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
            if (token) headers.set('X-CSRF-Token', token);
            headers.set('X-Requested-With', 'XMLHttpRequest');
            headers.set('Accept', headers.get('Accept') || 'application/json');
            addTokenToBody(init.body, token);

            const response = await originalFetch(input, {
                ...init,
                headers,
                credentials: init.credentials || 'same-origin',
                cache: 'no-store',
            });

            const responseToken = response.headers.get('X-CSRF-Token');
            if (responseToken) updateToken(responseToken);

            if (!(input instanceof Request) && attempt < 1 && await responseHasInvalidToken(response)) {
                if (response.body && typeof response.body.cancel === 'function') {
                    response.body.cancel().catch(() => {});
                }

                const freshToken = await ensureFreshToken(true);
                return send(freshToken, attempt + 1);
            }

            return response;
        };

        // Send immediately with the token already rendered into the page.
        // Strict same-origin AJAX verification protects the request even if a
        // browser cookie was renewed between page load and this action.
        return send(getToken());
    };

    const fetchJson = async (input, init = {}) => {
        const headers = new Headers(init.headers || {});
        if (!headers.has('Accept')) headers.set('Accept', 'application/json');

        const response = await window.fetch(input, {
            ...init,
            headers,
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload?.status === 'error') {
            const error = new Error(payload?.message || `Request failed (${response.status}).`);
            error.status = response.status;
            error.code = payload?.code || '';
            error.payload = payload;
            throw error;
        }

        return payload;
    };

    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function(method, url, ...rest) {
        this.__vvMethod = method;
        this.__vvUrl = url;
        return originalOpen.call(this, method, url, ...rest);
    };

    XMLHttpRequest.prototype.send = function(body) {
        if (isUnsafeMethod(this.__vvMethod) && isSameOrigin(this.__vvUrl)) {
            const token = getToken();
            if (token) this.setRequestHeader('X-CSRF-Token', token);
            this.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            addTokenToBody(body, token);

            this.addEventListener('load', () => {
                const refreshedToken = this.getResponseHeader('X-CSRF-Token');
                if (refreshedToken) updateToken(refreshedToken);
            }, { once: true });
        }
        return originalSend.call(this, body);
    };

    const initialise = () => {
        prepareForms();

        document.addEventListener('submit', (event) => {
            if (event.target instanceof HTMLFormElement) addTokenToForm(event.target);
        }, true);

        document.querySelectorAll('img:not([loading])').forEach((image) => {
            if (!image.closest('.hero-section, .hero, .home-hero')) {
                image.loading = 'lazy';
                image.decoding = 'async';
            }
        });

        const observer = new MutationObserver((records) => {
            records.forEach((record) => {
                record.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) return;
                    if (node.matches('form')) {
                        addTokenToForm(node);
                    } else if (node.querySelector('form')) {
                        prepareForms(node);
                    }
                });
            });
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
    } else {
        initialise();
    }

    window.addEventListener('pageshow', (event) => {
        prepareForms();
        if (event.persisted) refreshToken().catch(() => {});
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            hiddenAt = Date.now();
            return;
        }

        if (hiddenAt && Date.now() - hiddenAt > 120000) {
            refreshToken().catch(() => {});
        }
        hiddenAt = 0;
    });

    window.VelvetVogueSecurity = Object.freeze({
        get csrfToken() { return getToken(); },
        updateToken,
        refreshToken,
        ensureFreshToken,
        fetchJson,
        prepareForms,
    });
})();
