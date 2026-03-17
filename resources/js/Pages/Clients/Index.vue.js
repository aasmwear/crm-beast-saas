/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { ref, computed, watch } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageShell from '@/Components/ui/PageShell.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
// Set the layout for the page
defineOptions({ layout: AuthenticatedLayout });
const page = usePage();
// --- Re-used Dashboard Logic for Routing ---
// Centralized route helper (Ziggy)
const routeGlobal = window.route;
const r = (name, params = {}, absolute = false, config) => routeGlobal ? routeGlobal(name, params, absolute, config) : '#';
const props = withDefaults(defineProps(), {
    customFields: () => [],
    filters: () => ({
        status: null,
        industry: null,
        search: null,
        q: null,
        cf: {},
    }),
});
// Reactive resolution of the organization slug
const org = computed(() => {
    return (props.organizationSlug ||
        page.props.tenant?.slug ||
        page.props.organization?.slug ||
        'acme');
});
// Local filter state, hydrated from props.filters
const search = ref(props.filters.search ?? props.filters.q ?? '');
const status = ref(props.filters.status ?? '');
const industry = ref(props.filters.industry ?? '');
const cfValues = ref({ ...(props.filters.cf ?? {}) });
// Keep local state in sync when Inertia updates props
watch(() => props.filters, (f) => {
    search.value = f.search ?? f.q ?? '';
    status.value = f.status ?? '';
    industry.value = f.industry ?? '';
    cfValues.value = { ...(f.cf ?? {}) };
});
const hasActiveCfFilters = computed(() => Object.values(cfValues.value).some((v) => v !== '' && v != null));
// Options for the status filter (maps to `clients.status` column)
const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'lead', label: 'Lead' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'paused', label: 'Paused' },
    { value: 'churned', label: 'Churned' },
];
// Build cf filter object (only non-empty values)
function buildCfParams() {
    const out = {};
    for (const [slug, v] of Object.entries(cfValues.value)) {
        if (v != null && String(v).trim() !== '') {
            out[slug] = String(v).trim();
        }
    }
    return out;
}
// Apply filters + search via Inertia GET
function applyFilters() {
    const cf = buildCfParams();
    router.get(r('clients.index', { organization: org.value }), {
        search: search.value || null,
        status: status.value || null,
        industry: industry.value || null,
        cf: Object.keys(cf).length ? cf : undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}
function doSearch() {
    applyFilters();
}
function resetFilters() {
    search.value = '';
    status.value = '';
    industry.value = '';
    cfValues.value = {};
    applyFilters();
}
// Helper function to apply the status chip styling
function getCfValue(slug) {
    return cfValues.value[slug] ?? '';
}
function setCfValue(slug, v) {
    cfValues.value = { ...cfValues.value, [slug]: v };
}
function getStatusClass(statusValue) {
    const base = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ';
    if (!statusValue) {
        return base + 'bg-gray-700/50 text-gray-300';
    }
    const statusLower = statusValue.toLowerCase();
    switch (statusLower) {
        case 'active':
            return base + 'bg-emerald-900/50 text-emerald-300';
        case 'inactive':
        case 'churned': // pipeline/status mapping
            return base + 'bg-red-900/50 text-red-300';
        case 'lead':
        case 'pending':
            return base + 'bg-yellow-900/50 text-yellow-300';
        case 'paused':
            return base + 'bg-gray-700/50 text-gray-300';
        default:
            return base + 'bg-gray-700/50 text-gray-300';
    }
}
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_withDefaultsArg = (function (t) { return t; })({
    customFields: () => [],
    filters: () => ({
        status: null,
        industry: null,
        search: null,
        q: null,
        cf: {},
    }),
});
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
// CSS variable injection 
// CSS variable injection end 
/** @type {[typeof PageShell, typeof PageShell, ]} */ ;
// @ts-ignore
const __VLS_0 = __VLS_asFunctionalComponent(PageShell, new PageShell({
    header: ({
        breadcrumb: `Organization • ${__VLS_ctx.org.toUpperCase()}`,
        title: 'Client Management',
        subtitle: 'Search and manage your active and inactive clients.',
    }),
}));
const __VLS_1 = __VLS_0({
    header: ({
        breadcrumb: `Organization • ${__VLS_ctx.org.toUpperCase()}`,
        title: 'Client Management',
        subtitle: 'Search and manage your active and inactive clients.',
    }),
}, ...__VLS_functionalComponentArgsRest(__VLS_0));
var __VLS_3 = {};
__VLS_2.slots.default;
{
    const { 'header-actions': __VLS_thisSlot } = __VLS_2.slots;
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "flex items-center gap-3" },
    });
    const __VLS_4 = {}.Link;
    /** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
    // @ts-ignore
    const __VLS_5 = __VLS_asFunctionalComponent(__VLS_4, new __VLS_4({
        href: (__VLS_ctx.r('clients.pipeline', { organization: __VLS_ctx.org })),
        ...{ class: "btn-capsule text-sm" },
    }));
    const __VLS_6 = __VLS_5({
        href: (__VLS_ctx.r('clients.pipeline', { organization: __VLS_ctx.org })),
        ...{ class: "btn-capsule text-sm" },
    }, ...__VLS_functionalComponentArgsRest(__VLS_5));
    __VLS_7.slots.default;
    var __VLS_7;
    if (__VLS_ctx.canCreate !== false) {
        const __VLS_8 = {}.Link;
        /** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
        // @ts-ignore
        const __VLS_9 = __VLS_asFunctionalComponent(__VLS_8, new __VLS_8({
            href: (__VLS_ctx.r('clients.create', { organization: __VLS_ctx.org })),
            ...{ class: "chip" },
        }));
        const __VLS_10 = __VLS_9({
            href: (__VLS_ctx.r('clients.create', { organization: __VLS_ctx.org })),
            ...{ class: "chip" },
        }, ...__VLS_functionalComponentArgsRest(__VLS_9));
        __VLS_11.slots.default;
        var __VLS_11;
    }
    if (__VLS_ctx.canImport !== false) {
        const __VLS_12 = {}.Link;
        /** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
        // @ts-ignore
        const __VLS_13 = __VLS_asFunctionalComponent(__VLS_12, new __VLS_12({
            href: (__VLS_ctx.r('clients.import', { organization: __VLS_ctx.org })),
            ...{ class: "btn-capsule text-sm" },
        }));
        const __VLS_14 = __VLS_13({
            href: (__VLS_ctx.r('clients.import', { organization: __VLS_ctx.org })),
            ...{ class: "btn-capsule text-sm" },
        }, ...__VLS_functionalComponentArgsRest(__VLS_13));
        __VLS_15.slots.default;
        var __VLS_15;
    }
    if (__VLS_ctx.canExport !== false) {
        const __VLS_16 = {}.Link;
        /** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
        // @ts-ignore
        const __VLS_17 = __VLS_asFunctionalComponent(__VLS_16, new __VLS_16({
            href: (__VLS_ctx.r('export.csv', {
                organization: __VLS_ctx.org,
                entity: 'clients',
                include_deleted: 1,
            })),
            ...{ class: "btn-capsule text-sm" },
        }));
        const __VLS_18 = __VLS_17({
            href: (__VLS_ctx.r('export.csv', {
                organization: __VLS_ctx.org,
                entity: 'clients',
                include_deleted: 1,
            })),
            ...{ class: "btn-capsule text-sm" },
        }, ...__VLS_functionalComponentArgsRest(__VLS_17));
        __VLS_19.slots.default;
        var __VLS_19;
    }
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({
    ...{ class: "space-y-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex flex-wrap items-center justify-between gap-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex flex-wrap items-center gap-2" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    ...{ onKeyup: (__VLS_ctx.doSearch) },
    placeholder: "Search clients...",
    ...{ class: "search-pill-lite" },
});
(__VLS_ctx.search);
__VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
    ...{ onClick: (__VLS_ctx.doSearch) },
    ...{ class: "btn-capsule text-sm" },
});
if (__VLS_ctx.search || __VLS_ctx.status || __VLS_ctx.industry || __VLS_ctx.hasActiveCfFilters) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
        ...{ onClick: (__VLS_ctx.resetFilters) },
        ...{ class: "text-xs text-white/60 hover:text-white underline-offset-2 hover:underline" },
    });
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex flex-wrap items-center gap-2" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
    ...{ onChange: (__VLS_ctx.applyFilters) },
    value: (__VLS_ctx.status),
    ...{ class: "rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/80 focus:outline-none focus:ring-1 focus:ring-[var(--primary)]" },
});
for (const [opt] of __VLS_getVForSourceType((__VLS_ctx.statusOptions))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
        key: (opt.value),
        value: (opt.value),
    });
    (opt.label);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    ...{ onKeyup: (__VLS_ctx.doSearch) },
    placeholder: "Industry",
    ...{ class: "rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/80 focus:outline-none focus:ring-1 focus:ring-[var(--primary)]" },
});
(__VLS_ctx.industry);
if (__VLS_ctx.customFields && __VLS_ctx.customFields.length) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "flex flex-wrap items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
        ...{ class: "text-xs text-white/50" },
    });
    for (const [f] of __VLS_getVForSourceType((__VLS_ctx.customFields))) {
        (f.slug);
        if (f.type === 'select') {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
                ...{ onChange: ((e) => {
                        __VLS_ctx.cfValues[f.slug] = e.target.value;
                        __VLS_ctx.applyFilters();
                    }) },
                value: (__VLS_ctx.cfValues[f.slug] ?? ''),
                ...{ class: "rounded-full border border-white/10 bg-white/5 px-2 py-1 text-xs text-white/80 focus:outline-none focus:ring-1 focus:ring-[var(--primary)]" },
            });
            __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
                value: "",
            });
            (f.label);
            for (const [opt] of __VLS_getVForSourceType(((f.options ?? [])))) {
                __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
                    key: (opt),
                    value: (opt),
                });
                (opt);
            }
        }
        else {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
                ...{ onInput: ((e) => {
                        __VLS_ctx.cfValues[f.slug] = e.target.value;
                    }) },
                ...{ onKeyup: (__VLS_ctx.applyFilters) },
                value: (__VLS_ctx.cfValues[f.slug] ?? ''),
                type: (f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : 'text'),
                placeholder: (f.label),
                ...{ class: "w-28 rounded-full border border-white/10 bg-white/5 px-2 py-1 text-xs text-white/80 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)]" },
            });
        }
    }
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "card-neo overflow-hidden" },
});
if (!props.clients.data.length) {
    /** @type {[typeof EmptyState, typeof EmptyState, ]} */ ;
    // @ts-ignore
    const __VLS_20 = __VLS_asFunctionalComponent(EmptyState, new EmptyState({
        title: "Add your first Client",
        description: "Get started by adding your first client to the CRM.",
        icon: "👤",
    }));
    const __VLS_21 = __VLS_20({
        title: "Add your first Client",
        description: "Get started by adding your first client to the CRM.",
        icon: "👤",
    }, ...__VLS_functionalComponentArgsRest(__VLS_20));
    __VLS_22.slots.default;
    if (__VLS_ctx.canCreate !== false) {
        {
            const { action: __VLS_thisSlot } = __VLS_22.slots;
            const __VLS_23 = {}.Link;
            /** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
            // @ts-ignore
            const __VLS_24 = __VLS_asFunctionalComponent(__VLS_23, new __VLS_23({
                href: (__VLS_ctx.r('clients.create', { organization: __VLS_ctx.org })),
                ...{ class: "inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[var(--primary)]/20 hover:opacity-90 transition" },
            }));
            const __VLS_25 = __VLS_24({
                href: (__VLS_ctx.r('clients.create', { organization: __VLS_ctx.org })),
                ...{ class: "inline-flex items-center gap-2 rounded-xl bg-[var(--primary)] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[var(--primary)]/20 hover:opacity-90 transition" },
            }, ...__VLS_functionalComponentArgsRest(__VLS_24));
            __VLS_26.slots.default;
            __VLS_asFunctionalElement(__VLS_intrinsicElements.svg, __VLS_intrinsicElements.svg)({
                ...{ class: "w-5 h-5" },
                fill: "none",
                stroke: "currentColor",
                viewBox: "0 0 24 24",
            });
            __VLS_asFunctionalElement(__VLS_intrinsicElements.path)({
                'stroke-linecap': "round",
                'stroke-linejoin': "round",
                'stroke-width': "2",
                d: "M12 4v16m8-8H4",
            });
            var __VLS_26;
        }
    }
    var __VLS_22;
}
else {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.table, __VLS_intrinsicElements.table)({
        ...{ class: "w-full text-left text-sm text-white" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.thead, __VLS_intrinsicElements.thead)({
        ...{ class: "border-b border-white/10 text-white/50" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.tr, __VLS_intrinsicElements.tr)({});
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "p-4" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "p-4" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "p-4" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "p-4" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "w-40 p-4" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.tbody, __VLS_intrinsicElements.tbody)({});
    for (const [c] of __VLS_getVForSourceType((props.clients.data))) {
        __VLS_asFunctionalElement(__VLS_intrinsicElements.tr, __VLS_intrinsicElements.tr)({
            key: (c.id),
            ...{ class: "border-t border-white/10 transition duration-150 hover:bg-white/5" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "p-4 font-medium" },
        });
        (c.company_name);
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "p-4 text-white/70" },
        });
        (c.primary_contact_name);
        (c.primary_contact_email);
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "p-4" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
            ...{ class: (__VLS_ctx.getStatusClass(c.status)) },
        });
        (c.status || '—');
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "p-4 text-white/70" },
        });
        (c.projects_count ?? 0);
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "p-4 text-right" },
        });
        const __VLS_27 = {}.Link;
        /** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
        // @ts-ignore
        const __VLS_28 = __VLS_asFunctionalComponent(__VLS_27, new __VLS_27({
            href: (__VLS_ctx.r('clients.show', { organization: __VLS_ctx.org, client: c.id })),
            ...{ class: "text-indigo-400 hover:underline" },
        }));
        const __VLS_29 = __VLS_28({
            href: (__VLS_ctx.r('clients.show', { organization: __VLS_ctx.org, client: c.id })),
            ...{ class: "text-indigo-400 hover:underline" },
        }, ...__VLS_functionalComponentArgsRest(__VLS_28));
        __VLS_30.slots.default;
        var __VLS_30;
    }
}
if (props.clients.links && props.clients.links.length > 1) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "border-t border-white/10 bg-black/20 px-4 py-3" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.nav, __VLS_intrinsicElements.nav)({
        ...{ class: "flex flex-wrap items-center justify-end gap-1 text-xs" },
    });
    for (const [link] of __VLS_getVForSourceType((props.clients.links))) {
        const __VLS_31 = {}.Link;
        /** @type {[typeof __VLS_components.Link, ]} */ ;
        // @ts-ignore
        const __VLS_32 = __VLS_asFunctionalComponent(__VLS_31, new __VLS_31({
            key: (link.label + (link.url || '')),
            href: (link.url || '#'),
            ...{ class: "rounded-full px-3 py-1" },
            ...{ class: ([
                    link.active
                        ? 'bg-white/20 text-white'
                        : link.url
                            ? 'text-white/70 hover:bg-white/10'
                            : 'text-white/30 cursor-default',
                ]) },
        }));
        const __VLS_33 = __VLS_32({
            key: (link.label + (link.url || '')),
            href: (link.url || '#'),
            ...{ class: "rounded-full px-3 py-1" },
            ...{ class: ([
                    link.active
                        ? 'bg-white/20 text-white'
                        : link.url
                            ? 'text-white/70 hover:bg-white/10'
                            : 'text-white/30 cursor-default',
                ]) },
        }, ...__VLS_functionalComponentArgsRest(__VLS_32));
        __VLS_asFunctionalDirective(__VLS_directives.vHtml)(null, { ...__VLS_directiveBindingRestFields, value: (link.label) }, null, null);
    }
}
var __VLS_2;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-3']} */ ;
/** @type {__VLS_StyleScopedClasses['btn-capsule']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['chip']} */ ;
/** @type {__VLS_StyleScopedClasses['btn-capsule']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['btn-capsule']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['search-pill-lite']} */ ;
/** @type {__VLS_StyleScopedClasses['btn-capsule']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['underline-offset-2']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:underline']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-1']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-1']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['px-2']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-1']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['w-28']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['px-2']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['placeholder-white/40']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-1']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['card-neo']} */ ;
/** @type {__VLS_StyleScopedClasses['overflow-hidden']} */ ;
/** @type {__VLS_StyleScopedClasses['inline-flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['px-4']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2.5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow-[var(--primary)]/20']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:opacity-90']} */ ;
/** @type {__VLS_StyleScopedClasses['transition']} */ ;
/** @type {__VLS_StyleScopedClasses['w-5']} */ ;
/** @type {__VLS_StyleScopedClasses['h-5']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['text-left']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['border-b']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['w-40']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['border-t']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['transition']} */ ;
/** @type {__VLS_StyleScopedClasses['duration-150']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/70']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/70']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-right']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-400']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:underline']} */ ;
/** @type {__VLS_StyleScopedClasses['border-t']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-black/20']} */ ;
/** @type {__VLS_StyleScopedClasses['px-4']} */ ;
/** @type {__VLS_StyleScopedClasses['py-3']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-end']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            Link: Link,
            PageShell: PageShell,
            EmptyState: EmptyState,
            r: r,
            org: org,
            search: search,
            status: status,
            industry: industry,
            cfValues: cfValues,
            hasActiveCfFilters: hasActiveCfFilters,
            statusOptions: statusOptions,
            applyFilters: applyFilters,
            doSearch: doSearch,
            resetFilters: resetFilters,
            getStatusClass: getStatusClass,
        };
    },
    __typeProps: {},
    props: {},
});
export default (await import('vue')).defineComponent({
    setup() {
        return {};
    },
    __typeProps: {},
    props: {},
});
; /* PartiallyEnd: #4569/main.vue */
