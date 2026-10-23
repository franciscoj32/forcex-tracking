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
