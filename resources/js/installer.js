/**
 * First-run setup wizard. Each step is its own <form> so the browser's built-in
 * validation runs before we move on; the server re-validates everything on install.
 */
const STEP_FOR_FIELD = { app_: 1, db_: 2, admin_: 3 };

export default (config) => ({
    step: 0,
    steps: ['Welcome', 'Site', 'Database', 'Admin', 'Done'],
    busy: false,
    errors: {},
    message: null,
    dbStatus: null,
    loginUrl: '/login',

    form: {
        app_name: config.appName,
        app_url: config.appUrl,
        app_env: 'production',
        db_driver: config.defaultDriver,
        db_host: '127.0.0.1',
        db_port: config.drivers[config.defaultDriver]?.port ?? '',
        db_database: config.defaultDriver === 'sqlite' ? config.sqlitePath : '',
        db_username: '',
        db_password: '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
    },

    init() {
        this.$watch('form.db_driver', (driver, previous) => {
            // Swap in the new default port unless the user typed their own.
            if (!this.form.db_port || String(this.form.db_port) === String(config.drivers[previous]?.port)) {
                this.form.db_port = config.drivers[driver]?.port ?? '';
            }
            if (driver === 'sqlite') this.form.db_database ||= config.sqlitePath;
            if (previous === 'sqlite' && this.form.db_database === config.sqlitePath) this.form.db_database = '';
            this.dbStatus = null;
        });
    },

    get isSqlite() {
        return this.form.db_driver === 'sqlite';
    },

    error(field) {
        return this.errors[field]?.[0];
    },

    goTo(step) {
        this.step = step;
        this.$nextTick(() => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            this.$root.querySelector(`[data-step="${step}"] input:not([type=radio]), [data-step="${step}"] button`)?.focus();
        });
    },

    next() {
        this.errors = {};
        this.message = null;
        this.goTo(this.step + 1);
    },

    back() {
        this.goTo(this.step - 1);
    },

    async checkDatabase() {
        this.busy = true;
        this.dbStatus = null;
        this.errors = {};

        const { ok, data } = await this.post(config.routes.database, this.databaseFields());

        this.busy = false;
        this.dbStatus = { ok, message: data.message };
        if (!ok) this.errors = data.errors ?? {};

        return ok;
    },

    async continueFromDatabase() {
        if (await this.checkDatabase()) this.next();
    },

    async install() {
        this.busy = true;
        this.errors = {};
        this.message = null;

        const { ok, data } = await this.post(config.routes.install, this.form);

        this.busy = false;

        if (ok) {
            this.loginUrl = data.login;
            this.goTo(4);
            return;
        }

        this.errors = data.errors ?? {};
        this.message = data.message;

        // Jump back to the first step that has a problem.
        const field = Object.keys(this.errors)[0] ?? '';
        const prefix = Object.keys(STEP_FOR_FIELD).find((p) => field.startsWith(p));
        if (prefix) this.goTo(STEP_FOR_FIELD[prefix]);
        if (field === 'db_connection') this.dbStatus = { ok: false, message: data.message };
    },

    databaseFields() {
        const { db_driver, db_host, db_port, db_database, db_username, db_password } = this.form;
        return { db_driver, db_host, db_port, db_database, db_username, db_password };
    },

    async post(url, body) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({ message: `The server returned an error (${response.status}).` }));

            return { ok: response.ok, data };
        } catch {
            return { ok: false, data: { message: 'Could not reach the server. Check your connection and try again.' } };
        }
    },
});
