/**
 * Small client-side navigation for the admin area.
 *
 * Links and GET forms are fetched in the background, and every element marked
 * with [data-swap] is replaced by the element with the same id in the response.
 * The rest of the layout (sidebar shell, header, open dropdowns) stays put.
 */
import Alpine from 'alpinejs';

let controller = null;

/**
 * @param {string|URL} url
 * @param {{ push?: boolean, method?: string, body?: FormData, fallback?: () => void }} options
 *   fallback runs when a non-GET request can't be shown in place (e.g. a 419
 *   or 500 page), so the browser can submit the form for real instead.
 */
export async function visit(url, { push = true, method = 'GET', body = null, fallback = null } = {}) {
    controller?.abort();
    const current = (controller = new AbortController());

    const target = new URL(url, location.href);
    const fullLoad = fallback ?? (() => location.assign(target));

    // Only show the progress bar if the request is noticeably slow.
    const showLoading = setTimeout(() => (Alpine.store('nav').loading = true), 150);

    try {
        const response = await fetch(target, {
            method,
            body,
            signal: current.signal,
            headers: { Accept: 'text/html' },
        });
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');

        // We ended up outside the admin layout (e.g. logged out, or the session
        // expired and we got the login page), so load that page normally. A
        // GET or a redirect is safe to repeat as a GET; anything else isn't.
        if (!doc.querySelector('[data-swap]')) {
            if (method === 'GET' || response.redirected) location.assign(response.url);
            else fullLoad();
            return;
        }

        const samePath = new URL(response.url).pathname === location.pathname;

        const focused = rememberFocus();

        document.querySelectorAll('[data-swap]').forEach((region) => {
            const fresh = doc.getElementById(region.id);
            if (fresh) region.replaceWith(fresh);
        });
        document.title = doc.title;

        if (push) history.pushState({}, '', response.url);
        if (!samePath) window.scrollTo({ top: 0 });

        restoreFocus(focused);
        window.dispatchEvent(new CustomEvent('spa-navigated'));
    } catch (error) {
        if (error.name !== 'AbortError') fullLoad();
    } finally {
        clearTimeout(showLoading);
        if (controller === current) Alpine.store('nav').loading = false;
    }
}

export function start() {
    if (!document.querySelector('[data-swap]')) return;

    history.replaceState({}, '', location.href);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || !shouldHandle(link, event)) return;

        event.preventDefault();
        visit(link.href);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        // A handler already cancelled it (e.g. a "Delete?" confirm was dismissed).
        if (event.defaultPrevented || form.hasAttribute('data-reload')) return;

        event.preventDefault();

        if (form.method.toLowerCase() === 'get') {
            visit(formUrl(form));
            return;
        }

        // POST, with Laravel's _method field carrying PUT/DELETE. Laravel answers
        // with a redirect, which fetch follows, so we swap in the page it lands on.
        visit(form.action, {
            method: 'POST',
            body: new FormData(form, event.submitter),
            fallback: () => HTMLFormElement.prototype.submit.call(form),
        });
    });

    window.addEventListener('popstate', () => visit(location.href, { push: false }));
}

function shouldHandle(link, event) {
    const href = link.getAttribute('href');

    return (
        !event.defaultPrevented &&
        event.button === 0 &&
        !(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) &&
        link.origin === location.origin &&
        link.target !== '_blank' &&
        !link.hasAttribute('download') &&
        !link.hasAttribute('data-reload') &&
        !href.startsWith('#')
    );
}

function formUrl(form) {
    const url = new URL(form.action);
    const params = new URLSearchParams();

    // Leave out empty filters so the URL stays readable.
    for (const [key, value] of new FormData(form)) {
        if (value !== '') params.append(key, value);
    }

    url.search = params.toString();
    return url;
}

// Live search re-renders the form, so put the cursor back where it was.
function rememberFocus() {
    const el = document.activeElement;
    if (!el?.form || !el.name) return null;

    return { name: el.name, start: el.selectionStart, end: el.selectionEnd };
}

function restoreFocus(focused) {
    if (!focused) return;

    const el = document.querySelector(`#content [name="${focused.name}"]`);
    if (!el) return;

    el.focus();
    if (focused.start != null && typeof el.setSelectionRange === 'function') {
        el.setSelectionRange(focused.start, focused.end);
    }
}
