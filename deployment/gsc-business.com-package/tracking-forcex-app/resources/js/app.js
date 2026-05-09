import './bootstrap';

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
