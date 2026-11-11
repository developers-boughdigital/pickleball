import './bootstrap';
import Alpine from 'alpinejs';
import { start as startRouter, visit } from './router';
import installer from './installer';
import { permissionForm, permissionPicker, settingsForm, userForm } from './forms';

Alpine.store('nav', { loading: false });
Alpine.magic('visit', () => visit);
Alpine.data('installer', installer);
Alpine.data('userForm', userForm);
Alpine.data('permissionForm', permissionForm);
Alpine.data('permissionPicker', permissionPicker);
Alpine.data('settingsForm', settingsForm);

// Ticked rows on the venue and club lists, for <x-admin.publish-bar>. "all" means
// every row matching the filters, not just this page.
Alpine.data('publishing', (page) => ({
    selected: [],
    all: false,
    page,

    init() {
        this.$watch('selected', (ids) => ids.length < this.page.length && (this.all = false));
    },

    togglePage(checked) {
        this.selected = checked ? [...this.page] : [];
    },
}));

// "Use my location" / "Near me" on the public search forms. Put it on the <form>,
// with hidden lat and lng inputs (x-ref="lat", x-ref="lng"); a fix submits the form.
Alpine.data('nearMe', (fallback = 'Try a suburb or postcode instead.') => ({
    locating: false,
    problem: '',

    locate() {
        this.problem = '';
        if (!window.isSecureContext) {
            this.problem = `Your browser only shares your location with sites on https. ${fallback}`;
            return;
        }
        if (!navigator.geolocation) {
            this.problem = `Your browser can’t share your location. ${fallback}`;
            return;
        }

        this.locating = true;
        navigator.geolocation.getCurrentPosition(
            (spot) => {
                for (const [ref, value] of [['lat', spot.coords.latitude], ['lng', spot.coords.longitude]]) {
                    this.$refs[ref].value = value.toFixed(4);
                    this.$refs[ref].disabled = false;
                }
                // $root, not $el: $el is the button that was clicked.
                this.$root.requestSubmit();
            },
            (error) => {
                this.locating = false;
                this.problem = error.code === error.PERMISSION_DENIED
                    ? `Location is blocked for this site. Allow it in your browser’s address bar, or ${fallback.charAt(0).toLowerCase()}${fallback.slice(1)}`
                    : error.code === error.POSITION_UNAVAILABLE
                        // Usually the device itself: Windows Settings › Privacy › Location is off, or no Wi-Fi to place it.
                        ? `Your device couldn’t find where you are. Check that location is turned on in your device settings, or ${fallback.charAt(0).toLowerCase()}${fallback.slice(1)}`
                        : `We couldn’t get your location in time. ${fallback}`;
            },
            { timeout: 10000, maximumAge: 300000 },
        );
    },
}));

// Admin shell: on desktop the menu button collapses the sidebar to the icon rail
// (remembered between visits); on smaller screens it opens the sidebar as a drawer.
Alpine.data('adminLayout', () => ({
    sidebarOpen: false,
    collapsed: document.documentElement.classList.contains('sidebar-collapsed'),

    toggleSidebar() {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            this.collapsed = !this.collapsed;
            document.documentElement.classList.toggle('sidebar-collapsed', this.collapsed);
            localStorage.setItem('sidebar-collapsed', this.collapsed ? '1' : '0');
        } else {
            this.sidebarOpen = !this.sidebarOpen;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();

startRouter();
