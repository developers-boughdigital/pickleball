const STRENGTH = [
    { label: 'Too short', color: 'bg-red-500' },
    { label: 'Weak', color: 'bg-red-500' },
    { label: 'Okay', color: 'bg-amber-500' },
    { label: 'Good', color: 'bg-brand-500' },
    { label: 'Strong', color: 'bg-emerald-500' },
];

const slug = (value) =>
    value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

export const userForm = ({ name, email }) => ({
    dirty: false,
    name: name ?? '',
    email: email ?? '',
    password: '',
    showPassword: false,

    get initials() {
        const parts = this.name.split(/\s+/).filter(Boolean).slice(0, 2);
        return parts.map((part) => part[0].toUpperCase()).join('') || '?';
    },

    // A rough guide only; the server enforces the real password rules.
    get strength() {
        const password = this.password;
        let score = 0;

        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/\d/.test(password) && /[^A-Za-z0-9]/.test(password)) score++;

        return { score, ...STRENGTH[score] };
    },
});

export const permissionForm = ({ module, name, nameTouched }) => ({
    dirty: false,
    module: module ?? '',
    name: name ?? '',
    nameTouched: Boolean(nameTouched),

    // Start the name with the module ("Customers" → "customers.") until it's typed by hand.
    suggestName() {
        if (!this.nameTouched) this.name = this.module ? `${slug(this.module)}.` : '';
    },

    pickModule(module) {
        this.module = module;
        this.dirty = true;
        this.suggestName();
        this.$nextTick(() => this.$refs.name.focus());
    },
});

export const permissionPicker = () => ({
    selected: 0,
    total: 0,

    init() {
        this.refresh();
    },

    boxes(scope = this.$root) {
        return [...scope.querySelectorAll('input[name="permissions[]"]')];
    },

    countChecked(scope) {
        return this.boxes(scope).filter((box) => box.checked).length;
    },

    refresh() {
        this.total = this.boxes().length;
        this.selected = this.countChecked();
    },

    setAll(checked, scope = this.$root) {
        this.boxes(scope)
            .filter((box) => !box.disabled)
            .forEach((box) => (box.checked = checked));

        // Changing .checked from code fires no change event, so tell the
        // module cards to recount and the form that something changed.
        this.refresh();
        window.dispatchEvent(new CustomEvent('permissions-changed'));
        this.$dispatch('input');
    },
});
