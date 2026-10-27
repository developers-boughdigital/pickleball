import './bootstrap';
import Alpine from 'alpinejs';
import { start as startRouter, visit } from './router';
import installer from './installer';
import { permissionForm, permissionPicker, userForm } from './forms';

Alpine.store('nav', { loading: false });
Alpine.magic('visit', () => visit);
Alpine.data('installer', installer);
Alpine.data('userForm', userForm);
Alpine.data('permissionForm', permissionForm);
Alpine.data('permissionPicker', permissionPicker);

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
