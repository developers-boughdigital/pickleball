/**
 * Small client-side navigation for the admin area.
 *
 * Links and GET forms are fetched in the background, and every element marked
 * with [data-swap] is replaced by the element with the same id in the response.
 * The rest of the layout (sidebar shell, header, open dropdowns) stays put.
 */
import Alpine from 'alpinejs';

let controller = null;

export async function visit(url, { push = true } = {}) {
    controller?.abort();
    const current = (controller = new AbortController());

    const target = new URL(url, location.href);
    const samePath = target.pathname === location.pathname;

    // Only show the progress bar if the request is noticeably slow.
    const showLoading = setTimeout(() => (Alpine.store('nav').loading = true), 150);

    try {
        const response = await fetch(target, {
            signal: current.signal,
            headers: { Accept: 'text/html' },
        });
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');

        // We were sent somewhere outside the admin layout (e.g. the session
        // expired and we got the login page), so just load it normally.
        if (!doc.querySelector('[data-swap]')) {
            location.assign(response.url);
            return;
        }

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
        if (error.name !== 'AbortError') location.assign(target);
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
        if (form.method.toLowerCase() !== 'get') return;

        event.preventDefault();
        visit(formUrl(form));
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
