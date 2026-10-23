import './bootstrap';

// Registra las fuentes en el manifest de Vite para poder precargarlas en el <head>.
import.meta.glob(['../fonts/*.woff2']);

import Alpine from 'alpinejs';
import flatpickr from 'flatpickr';
import {
    Anchor,
    ArrowRight,
    BadgeCheck,
    Clock3,
    Container,
    createIcons,
    Download,
    FileCheck,
    FlagTriangleRight,
    MapPinned,
    PackageCheck,
    Plane,
    PlaneLanding,
    PlaneTakeoff,
    Ship,
    Trash2,
    Truck,
    Warehouse,
} from 'lucide';

window.Alpine = Alpine;

Alpine.data('filterCombobox', (config = {}) => ({
    options: config.options ?? [],
    value: String(config.value ?? ''),
    placeholder: config.placeholder ?? 'Seleccionar',
    searchPlaceholder: config.searchPlaceholder ?? 'Buscar...',
    emptyText: config.emptyText ?? 'No hay resultados.',
    allLabel: config.allLabel ?? 'Todos',
    searchEnabled: config.searchEnabled ?? false,
    query: '',
    open: false,
    activeIndex: -1,

    init() {
        this.value = String(this.value ?? '');
    },

    get selectedOption() {
        return this.options.find((option) => String(option.value) === this.value) ?? null;
    },

    get buttonLabel() {
        return this.selectedOption?.label ?? this.allLabel ?? this.placeholder;
    },

    get filteredOptions() {
        if (!this.searchEnabled || this.query.trim() === '') {
            return this.options;
        }

        const term = this.query.trim().toLowerCase();

        return this.options.filter((option) => option.label.toLowerCase().includes(term));
    },

    toggle() {
        this.open ? this.closePanel() : this.openPanel();
    },

    openPanel() {
        this.open = true;
        this.activeIndex = this.filteredOptions.findIndex((option) => String(option.value) === this.value);

        this.$nextTick(() => {
            if (this.searchEnabled) {
                this.$refs.searchInput?.focus();
                this.$refs.searchInput?.select();
            }
        });
    },

    closePanel() {
        this.open = false;
        this.query = '';
        this.activeIndex = -1;
    },

    choose(option) {
        this.value = String(option.value);
        this.closePanel();
    },

    clear() {
        this.value = '';
        this.closePanel();
    },

    move(step) {
        if (!this.open) {
            this.openPanel();
            return;
        }

        const total = this.filteredOptions.length;
        if (total === 0) {
            return;
        }

        if (this.activeIndex === -1) {
            this.activeIndex = step > 0 ? 0 : total - 1;
        } else {
            this.activeIndex = (this.activeIndex + step + total) % total;
        }

        this.$nextTick(() => {
            this.$refs.optionNodes?.[this.activeIndex]?.scrollIntoView({ block: 'nearest' });
        });
    },

    confirmActive() {
        if (!this.open) {
            this.openPanel();
            return;
        }

        const option = this.filteredOptions[this.activeIndex];
        if (option) {
            this.choose(option);
        }
    },
}));

Alpine.data('formAutocomplete', (config = {}) => ({
    options: config.options ?? [],
    value: String(config.value ?? ''),
    placeholder: config.placeholder ?? '',
    searchPlaceholder: config.searchPlaceholder ?? 'Buscar...',
    emptyText: config.emptyText ?? 'No hay coincidencias.',
    open: false,
    activeIndex: -1,

    get filteredOptions() {
        const term = this.value.trim().toLowerCase();

        if (term === '') {
            return this.options;
        }

        return this.options.filter((option) => option.label.toLowerCase().includes(term));
    },

    openPanel() {
        this.open = true;
        this.activeIndex = this.filteredOptions.findIndex((option) => option.label.toLowerCase() === this.value.trim().toLowerCase());
    },

    closePanel() {
        this.open = false;
        this.activeIndex = -1;
    },

    move(step) {
        if (!this.open) {
            this.openPanel();
            return;
        }

        const total = this.filteredOptions.length;
        if (total === 0) {
            return;
        }

        if (this.activeIndex === -1) {
            this.activeIndex = step > 0 ? 0 : total - 1;
        } else {
            this.activeIndex = (this.activeIndex + step + total) % total;
        }

        this.$nextTick(() => {
            this.$refs.optionNodes?.[this.activeIndex]?.scrollIntoView({ block: 'nearest' });
        });
    },

    confirmActive() {
        if (!this.open) {
            this.openPanel();
            return;
        }

        const option = this.filteredOptions[this.activeIndex];
        if (option) {
            this.pick(option.label);
        } else {
            this.closePanel();
        }
    },

    pick(label) {
        this.value = label;
        this.closePanel();
    },
}));

Alpine.data('formDatePicker', (config = {}) => ({
    picker: null,
    init() {
        this.picker = flatpickr(this.$refs.input, {
            allowInput: true,
            altInput: true,
            altInputClass: 'fx-input fx-date-alt-input',
            altFormat: config.enableTime ? 'd/m/Y H:i' : 'd/m/Y',
            dateFormat: config.enableTime ? 'Y-m-d H:i:S' : 'Y-m-d',
            enableTime: Boolean(config.enableTime),
            time_24hr: true,
            minuteIncrement: 5,
            defaultDate: config.value || null,
            locale: {
                firstDayOfWeek: 1,
            },
            onReady: (_selectedDates, _dateStr, instance) => {
                if (instance.altInput) {
                    instance.altInput.placeholder = config.placeholder || '';
                }
            },
            onOpen: () => {
                this.$refs.wrapper?.setAttribute('data-open', 'true');
            },
            onClose: () => {
                this.$refs.wrapper?.setAttribute('data-open', 'false');
            },
        });
    },

    open() {
        this.picker?.open();
    },
}));

// Formulario de cotizacion: busca el RUC entre los clientes, lineas de tarifa y totales con IGV.
Alpine.data('quotationForm', (config = {}) => ({
    lookupUrl: config.lookupUrl,
    igvRate: Number(config.igvRate ?? 0.18),
    concepts: config.concepts ?? [],
    customer: Object.assign({ ruc: '', razon_social: '', direccion: '', contacto_nombre: '', contacto_correo: '', contacto_telefono: '' }, config.customer ?? {}),
    rows: (config.rows ?? []).length ? config.rows : [],
    lookup: { state: config.linked ? 'found' : '', contacts: [], name: '' },
    lastRuc: '',

    init() {
        if (this.rows.length === 0) {
            this.add();
        }
        this.lastRuc = this.customer.ruc;
    },

    blankRow(currency = 'USD') {
        return { rate_concept_id: '', descripcion: '', cantidad: '1', precio_unitario: '', moneda: currency, afecto_igv: true };
    },

    add() {
        const last = this.rows[this.rows.length - 1];
        this.rows.push(this.blankRow(last ? last.moneda : 'USD'));
    },

    remove(index) {
        this.rows.splice(index, 1);
        if (this.rows.length === 0) {
            this.add();
        }
    },

    // Al elegir un concepto se usa su nombre. El flete internacional es inafecto a IGV.
    pickConcept(row) {
        const concept = this.concepts.find((item) => String(item.id) === String(row.rate_concept_id));
        if (!concept) {
            return;
        }
        row.descripcion = concept.nombre;
        row.afecto_igv = !/flete/i.test(concept.nombre);
    },

    async findRuc() {
        const ruc = (this.customer.ruc || '').replace(/\D+/g, '');
        this.customer.ruc = ruc;
        if (ruc.length !== 11) {
            this.lookup.state = ruc.length ? 'invalid' : '';
            return;
        }
        if (ruc === this.lastRuc && this.lookup.state !== '' && this.lookup.state !== 'invalid') {
            return;
        }
        this.lastRuc = ruc;
        this.lookup.state = 'loading';
        try {
            const response = await fetch(`${this.lookupUrl}?ruc=${ruc}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!data.found) {
                this.lookup = { state: 'missing', contacts: [], name: '' };
                return;
            }
            const client = data.client;
            this.lookup = { state: 'found', contacts: client.contacts, name: client.razon_social };
            this.customer.razon_social = client.razon_social;
            this.customer.direccion = client.direccion || '';
            const main = client.contacts[0];
            this.customer.contacto_nombre = main ? main.nombre : '';
            this.customer.contacto_correo = main ? main.correo || '' : client.correo || '';
            this.customer.contacto_telefono = main ? main.telefono || '' : client.telefono || '';
        } catch (error) {
            this.lookup = { state: 'error', contacts: [], name: '' };
        }
    },

    pickContact(event) {
        const contact = this.lookup.contacts[event.target.value];
        if (!contact) {
            return;
        }
        this.customer.contacto_nombre = contact.nombre;
        this.customer.contacto_correo = contact.correo || '';
        this.customer.contacto_telefono = contact.telefono || '';
    },

    lineTotal(row) {
        return (parseFloat(row.cantidad) || 0) * (parseFloat(row.precio_unitario) || 0);
    },

    currencies() {
        return ['USD', 'PEN'].filter((currency) => this.rows.some((row) => row.moneda === currency));
    },

    totals(currency) {
        const rows = this.rows.filter((row) => row.moneda === currency);
        const subtotal = rows.reduce((sum, row) => sum + this.lineTotal(row), 0);
        const igv = rows.filter((row) => row.afecto_igv).reduce((sum, row) => sum + this.lineTotal(row) * this.igvRate, 0);
        return { subtotal, igv, total: subtotal + igv };
    },

    money(amount, currency) {
        const symbol = currency === 'PEN' ? 'S/' : 'US$';
        return `${symbol} ${amount.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
}));

/* ------------------------------------------------------------------------
 * Modulo de Productividad (solo se usa si el modulo esta encendido)
 * --------------------------------------------------------------------- */

const jsonHeaders = { headers: { Accept: 'application/json' } };

// Post-it flotante: tareas de hoy, se arrastra, se minimiza y recuerda su lugar.
Alpine.data('stickyNote', (config = {}) => ({
    tasks: config.tasks ?? [],
    storeUrl: config.storeUrl,
    key: `fx-postit-${config.userId}`,
    minimized: false,
    x: null,
    y: null,
    dragging: false,
    moved: false,
    offset: { x: 0, y: 0 },
    draft: '',
    saving: false,
    error: '',
    mobile: window.matchMedia('(max-width: 639px)').matches,

    init() {
        let saved = {};
        try {
            saved = JSON.parse(localStorage.getItem(this.key) || '{}');
        } catch (error) {
            saved = {};
        }
        // En el celular empieza minimizado para no tapar la pantalla.
        this.minimized = this.mobile ? true : Boolean(saved.minimized);
        this.x = this.mobile ? null : saved.x ?? null;
        this.y = this.mobile ? null : saved.y ?? null;
        this.$nextTick(() => this.clamp());
        window.addEventListener('resize', () => this.clamp());
    },

    get pending() {
        return this.tasks.filter((task) => !task.completada).length;
    },

    get done() {
        return this.tasks.filter((task) => task.completada).length;
    },

    position() {
        return this.x === null ? '' : `left:${this.x}px;top:${this.y}px;right:auto;bottom:auto;`;
    },

    startDrag(event) {
        if (this.mobile || (event.button !== undefined && event.button !== 0) || event.target.closest('[data-no-drag]')) {
            return;
        }
        const rect = this.$refs.box.getBoundingClientRect();
        this.offset = { x: event.clientX - rect.left, y: event.clientY - rect.top };
        this.dragging = true;
        this.moved = false;
    },

    onDrag(event) {
        if (!this.dragging) {
            return;
        }
        const rect = this.$refs.box.getBoundingClientRect();
        const nextX = event.clientX - this.offset.x;
        const nextY = event.clientY - this.offset.y;
        if (!this.moved && Math.abs(nextX - rect.left) + Math.abs(nextY - rect.top) < 4) {
            return;
        }
        this.moved = true;
        this.x = nextX;
        this.y = nextY;
        this.clamp();
    },

    endDrag() {
        if (!this.dragging) {
            return;
        }
        this.dragging = false;
        if (this.moved) {
            this.save();
        }
    },

    clamp() {
        if (this.x === null || !this.$refs.box) {
            return;
        }
        const rect = this.$refs.box.getBoundingClientRect();
        const margin = 8;
        this.x = Math.min(Math.max(margin, this.x), window.innerWidth - rect.width - margin);
        this.y = Math.min(Math.max(margin, this.y), window.innerHeight - rect.height - margin);
    },

    save() {
        localStorage.setItem(this.key, JSON.stringify({ x: this.x, y: this.y, minimized: this.minimized }));
    },

    toggleMinimized() {
        // Si se acaba de arrastrar, el clic no cuenta.
        if (this.moved) {
            this.moved = false;
            return;
        }
        this.minimized = !this.minimized;
        this.save();
        this.$nextTick(() => {
            this.clamp();
            if (!this.minimized) {
                this.$refs.draft?.focus();
            }
        });
    },

    async add() {
        const titulo = this.draft.trim();
        if (!titulo || this.saving) {
            return;
        }
        this.saving = true;
        this.error = '';
        try {
            const { data } = await window.axios.post(this.storeUrl, { titulo }, jsonHeaders);
            this.tasks.push(data.task);
            this.draft = '';
        } catch (error) {
            this.error = error.response?.data?.message || 'No se pudo guardar. Intenta otra vez.';
        } finally {
            this.saving = false;
        }
    },

    async toggle(task) {
        task.completada = !task.completada;
        try {
            const { data } = await window.axios.post(task.toggle_url, {}, jsonHeaders);
            task.completada = data.completada;
        } catch (error) {
            task.completada = !task.completada;
        }
    },

    clearDone() {
        this.tasks = this.tasks.filter((task) => !task.completada);
    },
}));

// Campanita y avisos de la esquina (sutiles: maximo 3, se van solos).
Alpine.data('notificationBell', (config = {}) => ({
    count: config.count ?? 0,
    items: [],
    open: false,
    loading: false,
    loaded: false,
    ring: false,
    toasts: [],
    key: `fx-avisos-${config.userId}`,

    init() {
        const seen = Date.parse(localStorage.getItem(this.key) || '') || 0;
        (config.recent ?? [])
            .filter((notice) => Date.parse(notice.creado) > seen)
            .forEach((notice, index) => setTimeout(() => this.toast(notice), 500 + index * 400));
        this.markSeen(config.now);

        setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.poll();
            }
        }, 120000);
    },

    markSeen(timestamp) {
        if (timestamp) {
            localStorage.setItem(this.key, timestamp);
        }
    },

    async poll() {
        try {
            const { data } = await window.axios.get(config.pollUrl, { params: { desde: localStorage.getItem(this.key) || '' } });
            if (data.count > this.count) {
                this.ringBell();
            }
            this.count = data.count;
            data.nuevos.forEach((notice, index) => setTimeout(() => this.toast(notice), index * 400));
            if (data.nuevos.length) {
                this.loaded = false;
            }
            this.markSeen(data.ahora);
        } catch (error) {
            // Sin conexion: se intenta en el siguiente ciclo.
        }
    },

    async toggle() {
        this.open = !this.open;
        if (this.open && !this.loaded) {
            await this.load();
        }
    },

    async load() {
        this.loading = true;
        try {
            const { data } = await window.axios.get(config.listUrl);
            this.items = data.items;
            this.count = data.count;
            this.loaded = true;
        } catch (error) {
            this.items = [];
        } finally {
            this.loading = false;
        }
    },

    async readAll() {
        try {
            await window.axios.post(config.readAllUrl, {}, jsonHeaders);
            this.count = 0;
            this.items.forEach((item) => {
                item.leido = true;
            });
        } catch (error) {
            // Se reintenta la proxima vez.
        }
    },

    toast(notice) {
        if (this.toasts.some((toast) => toast.id === notice.id)) {
            return;
        }
        if (this.toasts.length >= 3) {
            this.dismiss(this.toasts[0]);
        }
        this.toasts.push({ ...notice, key: `${notice.id}-${Date.now()}`, show: false, timer: null });
        // Se toma el objeto reactivo para que la entrada se anime.
        const toast = this.toasts[this.toasts.length - 1];
        this.$nextTick(() => {
            toast.show = true;
        });
        this.ringBell();
        this.schedule(toast, 6000);
    },

    schedule(toast, ms) {
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => this.dismiss(toast), ms);
    },

    pause(toast) {
        clearTimeout(toast.timer);
    },

    resume(toast) {
        this.schedule(toast, 2500);
    },

    dismiss(toast) {
        if (!toast) {
            return;
        }
        clearTimeout(toast.timer);
        toast.show = false;
        setTimeout(() => {
            this.toasts = this.toasts.filter((item) => item.key !== toast.key);
        }, 250);
    },

    ringBell() {
        this.ring = false;
        this.$nextTick(() => {
            this.ring = true;
            setTimeout(() => {
                this.ring = false;
            }, 1000);
        });
    },
}));

// Confeti breve y liviano (sin librerias). Respeta "reducir movimiento".
window.fxConfetti = (duration = 2600) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:70';
    document.body.appendChild(canvas);
    const context = canvas.getContext('2d');
    const ratio = window.devicePixelRatio || 1;
    canvas.width = window.innerWidth * ratio;
    canvas.height = window.innerHeight * ratio;
    context.scale(ratio, ratio);

    const colors = ['#f43f5e', '#f59e0b', '#10b981', '#3b82f6', '#a855f7', '#ec4899'];
    const pieces = Array.from({ length: 130 }, () => ({
        x: window.innerWidth / 2 + (Math.random() - 0.5) * window.innerWidth * 0.5,
        y: -20 - Math.random() * window.innerHeight * 0.3,
        w: 6 + Math.random() * 6,
        h: 8 + Math.random() * 8,
        vx: (Math.random() - 0.5) * 5,
        vy: 2 + Math.random() * 4,
        rotation: Math.random() * Math.PI,
        spin: (Math.random() - 0.5) * 0.3,
        color: colors[Math.floor(Math.random() * colors.length)],
    }));
    const start = performance.now();

    const frame = (time) => {
        const elapsed = time - start;
        context.clearRect(0, 0, window.innerWidth, window.innerHeight);
        context.globalAlpha = Math.max(0, 1 - Math.max(0, elapsed - duration + 600) / 600);
        pieces.forEach((piece) => {
            piece.x += piece.vx;
            piece.y += piece.vy;
            piece.vy += 0.05;
            piece.rotation += piece.spin;
            context.save();
            context.translate(piece.x, piece.y);
            context.rotate(piece.rotation);
            context.fillStyle = piece.color;
            context.fillRect(-piece.w / 2, -piece.h / 2, piece.w, piece.h);
            context.restore();
        });
        if (elapsed < duration) {
            requestAnimationFrame(frame);
        } else {
            canvas.remove();
        }
    };
    requestAnimationFrame(frame);
};

// Saludo de cumpleanos: una sola vez por dia en cada equipo.
Alpine.data('birthdayGreeting', (config = {}) => ({
    show: false,
    key: `fx-cumple-${config.userId}-${config.day}`,

    init() {
        if (localStorage.getItem(this.key)) {
            return;
        }
        setTimeout(() => {
            this.show = true;
            window.fxConfetti();
        }, 700);
    },

    close() {
        this.show = false;
        localStorage.setItem(this.key, '1');
    },
}));

Alpine.start();

const disableNativeAutocomplete = () => {
    document
        .querySelectorAll('form[data-disable-native-autocomplete="true"] input:not([type="password"]):not([type="email"]):not([type="hidden"]):not([type="file"]), form[data-disable-native-autocomplete="true"] textarea')
        .forEach((field) => {
            field.setAttribute('autocomplete', 'off');
            field.setAttribute('autocorrect', 'off');
            field.setAttribute('autocapitalize', 'none');
            field.setAttribute('spellcheck', 'false');
        });
};

const renderLucideIcons = () =>
    createIcons({
        icons: {
            Anchor,
            ArrowRight,
            BadgeCheck,
            Clock3,
            Container,
            Download,
            FileCheck,
            FlagTriangleRight,
            MapPinned,
            PackageCheck,
            Plane,
            PlaneLanding,
            PlaneTakeoff,
            Ship,
            Trash2,
            Truck,
            Warehouse,
        },
    });

document.addEventListener('DOMContentLoaded', renderLucideIcons);
document.addEventListener('alpine:navigated', renderLucideIcons);
document.addEventListener('DOMContentLoaded', disableNativeAutocomplete);
document.addEventListener('alpine:navigated', disableNativeAutocomplete);
