/**
 * Venue maps on the public site: OpenStreetMap tiles through Leaflet, with
 * pickleball pins and numbered groups where pins crowd together.
 *
 *   <div x-data="venueMap(@js($pins))"><div x-ref="map"></div></div>
 *
 * Each pin is { lat, lng, name, suburb, url, club }. Leaflet is only fetched once
 * the map scrolls into view, so pages stay quick to open on a phone. The legend
 * can hide and show each kind of pin with toggle('venue') and toggle('club').
 */

const COLOURS = { venue: '#1f4fa3', club: '#5dc293', ball: '#ffc93c', navy: '#0f1b2d' };

let leaflet = null;

async function loadLeaflet() {
    leaflet ??= (async () => {
        const { default: L } = await import('leaflet');
        window.L = L; // the cluster plugin looks for a global L
        await Promise.all([
            import('leaflet.markercluster'),
            import('leaflet/dist/leaflet.css'),
            import('leaflet.markercluster/dist/MarkerCluster.css'),
        ]);
        return L;
    })();

    return leaflet;
}

/** A teardrop pin with a ball in the middle; green when a club plays there. */
function pinIcon(L, club) {
    const fill = club ? COLOURS.club : COLOURS.venue;
    const ball = club ? COLOURS.navy : COLOURS.ball;

    return L.divIcon({
        className: 'map-pin',
        iconSize: [34, 44],
        iconAnchor: [17, 42],
        popupAnchor: [0, -38],
        html: `<svg viewBox="0 0 34 44" width="34" height="44" aria-hidden="true">
            <ellipse cx="17" cy="41.5" rx="7" ry="2.5" fill="rgb(15 27 45 / .25)"/>
            <path d="M17 1.5C8.4 1.5 1.5 8.3 1.5 16.8c0 11.2 13.3 23 14.4 24a1.6 1.6 0 0 0 2.2 0c1.1-1 14.4-12.8 14.4-24C32.5 8.3 25.6 1.5 17 1.5Z" fill="${fill}" stroke="#fff" stroke-width="2.5"/>
            <circle cx="17" cy="16.5" r="7.5" fill="${ball}"/>
            <g fill="${fill}"><circle cx="14.2" cy="13.8" r="1.2"/><circle cx="19.8" cy="13.8" r="1.2"/><circle cx="14.2" cy="19.2" r="1.2"/><circle cx="19.8" cy="19.2" r="1.2"/><circle cx="17" cy="16.5" r="1.2"/></g>
        </svg>`,
    });
}

/** A round badge with the number of venues it stands for. */
function clusterIcon(L, cluster) {
    const count = cluster.getChildCount();
    const size = count < 10 ? 38 : count < 50 ? 46 : 54;

    return L.divIcon({
        className: 'map-cluster',
        iconSize: [size, size],
        html: `<span style="width:${size}px;height:${size}px">${count}</span>`,
    });
}

function escape(text) {
    return String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/** The bounds of the pins with the outer 2% each way left out, so one stray coordinate can't zoom the map out to the whole country. */
function coreBounds(L, pins) {
    if (pins.length < 20) return L.latLngBounds(pins.map((p) => [p.lat, p.lng]));

    const at = (sorted, share) => sorted[Math.floor((sorted.length - 1) * share)];
    const lats = pins.map((p) => p.lat).sort((a, b) => a - b);
    const lngs = pins.map((p) => p.lng).sort((a, b) => a - b);

    return L.latLngBounds([at(lats, 0.02), at(lngs, 0.02)], [at(lats, 0.98), at(lngs, 0.98)]);
}

const kindOf = (pin) => (pin.club ? 'club' : 'venue');

export default (pins = [], { zoom = 15 } = {}) => {
    // Leaflet's objects live out here rather than on the component: Alpine would
    // wrap them in its change tracking, which Leaflet doesn't expect.
    let map = null;
    let group = null;
    let markers = [];
    let observer = null;

    return {
        ready: false,
        failed: false,
        show: { venue: true, club: true },

        /** How many pins are on the map with the legend's current choices. */
        get shown() {
            return pins.filter((pin) => this.show[kindOf(pin)]).length;
        },

        toggle(kind) {
            this.show[kind] = !this.show[kind];
            if (!group) return;

            group.clearLayers();
            group.addLayers(markers.filter((_, i) => this.show[kindOf(pins[i])]));
        },

        init() {
            if (!pins.length) return;

            observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    observer.disconnect();
                    this.draw();
                }
            }, { rootMargin: '200px' });
            observer.observe(this.$el);
        },

        async draw() {
            let L;
            try {
                L = await loadLeaflet();
            } catch {
                this.failed = true;
                return;
            }
            if (!this.$refs.map?.isConnected) return; // navigated away while loading

            // Scrolling the page shouldn't zoom the map; a click on it hands it the wheel.
            map = L.map(this.$refs.map, { scrollWheelZoom: false, zoomControl: true, attributionControl: true });
            map.on('click focus', () => map.scrollWheelZoom.enable());
            map.on('mouseout', () => map.scrollWheelZoom.disable());

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
            }).addTo(map);
            map.attributionControl.setPrefix(false);

            markers = pins.map((pin) => L.marker([pin.lat, pin.lng], { icon: pinIcon(L, pin.club), title: pin.name, riseOnHover: true })
                .bindPopup(`<a href="${escape(pin.url)}" class="map-popup"${pin.external ? ' target="_blank" rel="noopener"' : ''}>
                    <strong>${escape(pin.name)}</strong>
                    ${pin.suburb ? `<span>${escape(pin.suburb)}</span>` : ''}
                    <em>${pin.club ? 'A club plays here · ' : ''}${escape(pin.action ?? 'View venue')} →</em>
                </a>`));

            if (pins.length > 1) {
                group = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 48, spiderfyOnMaxZoom: true, iconCreateFunction: (cluster) => clusterIcon(L, cluster) });
                group.addLayers(markers.filter((_, i) => this.show[kindOf(pins[i])]));
                map.addLayer(group);
                map.fitBounds(coreBounds(L, pins), { padding: [36, 36], maxZoom: 15 });
            } else {
                markers[0].addTo(map);
                map.setView([pins[0].lat, pins[0].lng], zoom);
            }

            this.ready = true;
        },

        destroy() {
            observer?.disconnect();
            map?.remove();
            map = group = observer = null;
            markers = [];
        },
    };
};
