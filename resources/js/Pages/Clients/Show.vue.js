/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed, nextTick, ref } from 'vue';
defineOptions({ layout: AuthenticatedLayout });
const props = defineProps();
const activeTab = ref('overview');
const editingNotes = ref(false);
const activeNoteField = ref('notes_sales');
const noteTextarea = ref(null);
const notesForm = useForm({
    company_name: props.client.company_name ?? '',
    primary_contact_name: props.client.primary_contact_name ?? '',
    primary_contact_email: props.client.primary_contact_email ?? '',
    notes_sales: props.client.notes_sales ?? '',
    notes_cst: props.client.notes_cst ?? '',
    notes_tech: props.client.notes_tech ?? '',
});
const noteLabel = computed(() => {
    switch (activeNoteField.value) {
        case 'notes_sales': return 'Sales';
        case 'notes_cst': return 'CST';
        case 'notes_tech': return 'Tech';
        default: return 'Notes';
    }
});
const activeNoteValue = computed({
    get() {
        if (activeNoteField.value === 'notes_sales')
            return notesForm.notes_sales ?? '';
        if (activeNoteField.value === 'notes_cst')
            return notesForm.notes_cst ?? '';
        return notesForm.notes_tech ?? '';
    },
    set(v) {
        if (activeNoteField.value === 'notes_sales')
            notesForm.notes_sales = v;
        if (activeNoteField.value === 'notes_cst')
            notesForm.notes_cst = v;
        if (activeNoteField.value === 'notes_tech')
            notesForm.notes_tech = v;
    },
});
function openNotesEditor(field) {
    activeNoteField.value = field;
    notesForm.clearErrors();
    notesForm.company_name = props.client.company_name ?? '';
    notesForm.primary_contact_name = props.client.primary_contact_name ?? '';
    notesForm.primary_contact_email = props.client.primary_contact_email ?? '';
    notesForm.notes_sales = props.client.notes_sales ?? '';
    notesForm.notes_cst = props.client.notes_cst ?? '';
    notesForm.notes_tech = props.client.notes_tech ?? '';
    editingNotes.value = true;
    nextTick(() => noteTextarea.value?.focus());
}
function closeNotesEditor() {
    editingNotes.value = false;
    notesForm.clearErrors();
}
function saveNotes() {
    notesForm.put(r('clients.update', { organization: props.organizationSlug, client: props.client.id }), { preserveScroll: true, onSuccess: () => closeNotesEditor() });
}
const routeGlobal = window.route;
const r = (name, params = {}, absolute = false, config) => routeGlobal ? routeGlobal(name, params, absolute, config) : '#';
function destroyClient() {
    if (!confirm('Are you sure you want to permanently delete this client? This cannot be undone.'))
        return;
    router.delete(r('clients.destroy', { organization: props.organizationSlug, client: props.client.id }));
}
const getStatusClass = (status) => {
    const s = (status || 'unknown').toLowerCase();
    const base = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ';
    switch (s) {
        case 'active': return base + 'bg-emerald-900/50 text-emerald-300';
        case 'lead':
        case 'pending': return base + 'bg-indigo-900/50 text-indigo-300';
        case 'inactive':
        case 'lost': return base + 'bg-rose-900/40 text-rose-300';
        default: return base + 'bg-white/10 text-white/70';
    }
};
const contacts = computed(() => props.client.contacts ?? []);
const projects = computed(() => props.client.projects ?? []);
// Contact modal: null = add new, otherwise edit that contact
const contactModalOpen = ref(false);
const editingContact = ref(null);
const contactForm = useForm({
    name: '',
    email: '',
    phone: '',
    position: '',
    is_primary: false,
});
function openAddContact() {
    editingContact.value = null;
    contactForm.reset();
    contactForm.clearErrors();
    contactForm.name = '';
    contactForm.email = '';
    contactForm.phone = '';
    contactForm.position = '';
    contactForm.is_primary = false;
    contactModalOpen.value = true;
}
function openEditContact(c) {
    editingContact.value = c;
    contactForm.reset();
    contactForm.clearErrors();
    contactForm.name = c.name;
    contactForm.email = c.email ?? '';
    contactForm.phone = c.phone ?? '';
    contactForm.position = c.position ?? '';
    contactForm.is_primary = c.is_primary;
    contactModalOpen.value = true;
}
function closeContactModal() {
    contactModalOpen.value = false;
    editingContact.value = null;
    contactForm.clearErrors();
}
function submitContact() {
    if (editingContact.value) {
        contactForm.put(r('clients.contacts.update', {
            organization: props.organizationSlug,
            client: props.client.id,
            contact: editingContact.value.id,
        }), { preserveScroll: true, onSuccess: () => closeContactModal() });
    }
    else {
        contactForm.post(r('clients.contacts.store', { organization: props.organizationSlug, client: props.client.id }), { preserveScroll: true, onSuccess: () => closeContactModal() });
    }
}
function enablePortal(c) {
    if (!c.email)
        return;
    router.post(r('clients.contacts.portal.store', {
        organization: props.organizationSlug,
        client: props.client.id,
        contact: c.id,
    }), {}, { preserveScroll: true });
}
function deleteContact(c) {
    if (!confirm('Remove this contact?'))
        return;
    router.delete(r('clients.contacts.destroy', {
        organization: props.organizationSlug,
        client: props.client.id,
        contact: c.id,
    }), { preserveScroll: true });
}
const contactModalTitle = computed(() => (editingContact.value ? 'Edit contact' : 'Add contact'));
const hasProjects = () => projects.value.length > 0;
const firstTasks = (p) => (p.tasks ?? []).slice(0, 3);
function formatMoney(cents, currency) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(cents / 100);
}
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "min-h-screen bg-slate-950" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "border-b border-white/10 bg-slate-950/60" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex flex-col gap-4 md:flex-row md:items-center md:justify-between" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "min-w-0" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center gap-3 flex-wrap" },
});
const __VLS_0 = {}.Link;
/** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
// @ts-ignore
const __VLS_1 = __VLS_asFunctionalComponent(__VLS_0, new __VLS_0({
    href: (__VLS_ctx.r('clients.index', { organization: __VLS_ctx.organizationSlug })),
    ...{ class: "text-white/60 hover:text-white text-sm" },
}));
const __VLS_2 = __VLS_1({
    href: (__VLS_ctx.r('clients.index', { organization: __VLS_ctx.organizationSlug })),
    ...{ class: "text-white/60 hover:text-white text-sm" },
}, ...__VLS_functionalComponentArgsRest(__VLS_1));
__VLS_3.slots.default;
var __VLS_3;
if (__VLS_ctx.client.status) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
        ...{ class: (__VLS_ctx.getStatusClass(__VLS_ctx.client.status)) },
    });
    (__VLS_ctx.client.status);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.h1, __VLS_intrinsicElements.h1)({
    ...{ class: "mt-2 text-2xl font-semibold text-white" },
});
(__VLS_ctx.client.company_name);
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "mt-1 text-sm text-white/60 flex items-center gap-2 flex-wrap" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({});
(__VLS_ctx.client.id);
if (__VLS_ctx.client.website) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.a, __VLS_intrinsicElements.a)({
        href: (__VLS_ctx.client.website),
        target: "_blank",
        rel: "noreferrer",
        ...{ class: "text-indigo-300 hover:text-indigo-200" },
    });
    (__VLS_ctx.client.website);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center gap-2" },
});
const __VLS_4 = {}.Link;
/** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
// @ts-ignore
const __VLS_5 = __VLS_asFunctionalComponent(__VLS_4, new __VLS_4({
    href: (__VLS_ctx.r('clients.edit', { organization: __VLS_ctx.organizationSlug, client: __VLS_ctx.client.id })),
    ...{ class: "rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/80 hover:bg-white/10" },
}));
const __VLS_6 = __VLS_5({
    href: (__VLS_ctx.r('clients.edit', { organization: __VLS_ctx.organizationSlug, client: __VLS_ctx.client.id })),
    ...{ class: "rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/80 hover:bg-white/10" },
}, ...__VLS_functionalComponentArgsRest(__VLS_5));
__VLS_7.slots.default;
var __VLS_7;
__VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
    ...{ onClick: (__VLS_ctx.destroyClient) },
    type: "button",
    ...{ class: "rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-sm text-rose-200 hover:bg-rose-500/20" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.nav, __VLS_intrinsicElements.nav)({
    ...{ class: "flex gap-1 -mb-px" },
});
for (const [t] of __VLS_getVForSourceType(([
    { id: 'overview', label: 'Overview' },
    { id: 'projects', label: 'Projects' },
    { id: 'contacts', label: 'Contacts' },
    { id: 'notes', label: 'Notes' },
]))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
        ...{ onClick: (...[$event]) => {
                __VLS_ctx.activeTab = t.id;
            } },
        key: (t.id),
        type: "button",
        ...{ class: ([
                'px-4 py-3 text-sm font-medium border-b-2 transition',
                __VLS_ctx.activeTab === t.id
                    ? 'border-indigo-500 text-indigo-300'
                    : 'border-transparent text-white/60 hover:text-white/80 hover:border-white/20'
            ]) },
    });
    (t.label);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "space-y-6" },
});
__VLS_asFunctionalDirective(__VLS_directives.vShow)(null, { ...__VLS_directiveBindingRestFields, value: (__VLS_ctx.activeTab === 'overview') }, null, null);
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "grid grid-cols-1 gap-6 lg:grid-cols-2" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({
    ...{ class: "rounded-2xl border border-white/10 bg-slate-950/60 p-5" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-medium text-white mb-3" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dl, __VLS_intrinsicElements.dl)({
    ...{ class: "space-y-2 text-sm" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dt, __VLS_intrinsicElements.dt)({
    ...{ class: "text-white/50" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dd, __VLS_intrinsicElements.dd)({
    ...{ class: "text-white/80" },
});
(__VLS_ctx.client.address || '—');
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dt, __VLS_intrinsicElements.dt)({
    ...{ class: "text-white/50" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dd, __VLS_intrinsicElements.dd)({
    ...{ class: "text-white/80" },
});
(__VLS_ctx.client.tax_id || '—');
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dt, __VLS_intrinsicElements.dt)({
    ...{ class: "text-white/50" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dd, __VLS_intrinsicElements.dd)({
    ...{ class: "text-white/80" },
});
(__VLS_ctx.client.currency || 'USD');
__VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({
    ...{ class: "rounded-2xl border border-white/10 bg-slate-950/60 p-5" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-medium text-white mb-3" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dl, __VLS_intrinsicElements.dl)({
    ...{ class: "space-y-2 text-sm" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dt, __VLS_intrinsicElements.dt)({
    ...{ class: "text-white/50" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dd, __VLS_intrinsicElements.dd)({
    ...{ class: "text-white/80" },
});
(__VLS_ctx.client.primary_contact_name || '—');
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dt, __VLS_intrinsicElements.dt)({
    ...{ class: "text-white/50" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dd, __VLS_intrinsicElements.dd)({
    ...{ class: "text-white/80" },
});
if (__VLS_ctx.client.primary_contact_email) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.a, __VLS_intrinsicElements.a)({
        href: (`mailto:${__VLS_ctx.client.primary_contact_email}`),
        ...{ class: "text-indigo-300 hover:text-indigo-200" },
    });
    (__VLS_ctx.client.primary_contact_email);
}
else {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({});
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dt, __VLS_intrinsicElements.dt)({
    ...{ class: "text-white/50" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.dd, __VLS_intrinsicElements.dd)({
    ...{ class: "text-white/80" },
});
(__VLS_ctx.client.primary_contact_phone || '—');
if (__VLS_ctx.financial_summary) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({
        ...{ class: "rounded-2xl border border-white/10 bg-slate-950/60 p-5" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
        ...{ class: "text-lg font-medium text-white mb-3" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "flex flex-wrap gap-6 text-sm" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
    __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
        ...{ class: "text-white/50" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "text-white font-medium" },
    });
    (__VLS_ctx.formatMoney(__VLS_ctx.financial_summary.total_budget_cents, __VLS_ctx.financial_summary.currency));
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
    __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
        ...{ class: "text-white/50" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "text-white font-medium" },
    });
    (__VLS_ctx.formatMoney(__VLS_ctx.financial_summary.total_invoiced_cents, __VLS_ctx.financial_summary.currency));
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "space-y-4" },
});
__VLS_asFunctionalDirective(__VLS_directives.vShow)(null, { ...__VLS_directiveBindingRestFields, value: (__VLS_ctx.activeTab === 'projects') }, null, null);
__VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({
    ...{ class: "rounded-2xl border border-white/10 bg-slate-950/60 p-5" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center justify-between mb-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-medium text-white" },
});
const __VLS_8 = {}.Link;
/** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
// @ts-ignore
const __VLS_9 = __VLS_asFunctionalComponent(__VLS_8, new __VLS_8({
    href: (__VLS_ctx.r('projects.index', { organization: __VLS_ctx.organizationSlug })),
    ...{ class: "text-sm text-indigo-300 hover:text-indigo-200" },
}));
const __VLS_10 = __VLS_9({
    href: (__VLS_ctx.r('projects.index', { organization: __VLS_ctx.organizationSlug })),
    ...{ class: "text-sm text-indigo-300 hover:text-indigo-200" },
}, ...__VLS_functionalComponentArgsRest(__VLS_9));
__VLS_11.slots.default;
var __VLS_11;
if (!__VLS_ctx.hasProjects()) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "text-sm text-white/60" },
    });
}
else {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "grid gap-4 sm:grid-cols-2 lg:grid-cols-3" },
    });
    for (const [project] of __VLS_getVForSourceType((__VLS_ctx.projects))) {
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            key: (project.id),
            ...{ class: "rounded-xl border border-white/10 bg-slate-900/40 p-4" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "flex items-center justify-between gap-4" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "min-w-0" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "flex items-center gap-2" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.h3, __VLS_intrinsicElements.h3)({
            ...{ class: "text-sm font-semibold text-white truncate" },
        });
        (project.title);
        if (project.status) {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
                ...{ class: "inline-flex items-center rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/70" },
            });
            (project.status);
        }
        __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
            ...{ class: "mt-1 text-xs text-white/50" },
        });
        (project.id);
        const __VLS_12 = {}.Link;
        /** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
        // @ts-ignore
        const __VLS_13 = __VLS_asFunctionalComponent(__VLS_12, new __VLS_12({
            href: (__VLS_ctx.r('projects.show', { organization: __VLS_ctx.organizationSlug, project: project.id })),
            ...{ class: "text-sm text-indigo-300 hover:text-indigo-200 whitespace-nowrap" },
        }));
        const __VLS_14 = __VLS_13({
            href: (__VLS_ctx.r('projects.show', { organization: __VLS_ctx.organizationSlug, project: project.id })),
            ...{ class: "text-sm text-indigo-300 hover:text-indigo-200 whitespace-nowrap" },
        }, ...__VLS_functionalComponentArgsRest(__VLS_13));
        __VLS_15.slots.default;
        var __VLS_15;
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "mt-3" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
            ...{ class: "text-[11px] uppercase tracking-wide text-white/40" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.ul, __VLS_intrinsicElements.ul)({
            ...{ class: "mt-2 space-y-1" },
        });
        for (const [t] of __VLS_getVForSourceType((__VLS_ctx.firstTasks(project)))) {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.li, __VLS_intrinsicElements.li)({
                key: (t.id),
                ...{ class: "flex items-center justify-between gap-3 text-sm" },
            });
            __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
                ...{ class: "truncate text-white/80" },
            });
            (t.title);
            __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
                ...{ class: "text-xs text-white/50 whitespace-nowrap" },
            });
            (t.status || '—');
        }
        if ((project.tasks ?? []).length === 0) {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.li, __VLS_intrinsicElements.li)({
                ...{ class: "text-sm text-white/60" },
            });
        }
    }
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "space-y-4" },
});
__VLS_asFunctionalDirective(__VLS_directives.vShow)(null, { ...__VLS_directiveBindingRestFields, value: (__VLS_ctx.activeTab === 'contacts') }, null, null);
__VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({
    ...{ class: "rounded-2xl border border-white/10 bg-slate-950/60 p-5" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center justify-between mb-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-medium text-white" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
    ...{ onClick: (__VLS_ctx.openAddContact) },
    type: "button",
    ...{ class: "rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition" },
});
if (__VLS_ctx.contacts.length === 0) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "text-sm text-white/60" },
    });
}
else {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "overflow-x-auto" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.table, __VLS_intrinsicElements.table)({
        ...{ class: "w-full text-sm" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.thead, __VLS_intrinsicElements.thead)({});
    __VLS_asFunctionalElement(__VLS_intrinsicElements.tr, __VLS_intrinsicElements.tr)({
        ...{ class: "text-left text-white/50 border-b border-white/10" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "pb-2 pr-4" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "pb-2 pr-4" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "pb-2 pr-4" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "pb-2 pr-4" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.th, __VLS_intrinsicElements.th)({
        ...{ class: "pb-2 w-24" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.tbody, __VLS_intrinsicElements.tbody)({});
    for (const [c] of __VLS_getVForSourceType((__VLS_ctx.contacts))) {
        __VLS_asFunctionalElement(__VLS_intrinsicElements.tr, __VLS_intrinsicElements.tr)({
            key: (c.id),
            ...{ class: "border-b border-white/5" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "py-2 pr-4 text-white/90" },
        });
        (c.name);
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "py-2 pr-4" },
        });
        if (c.email) {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.a, __VLS_intrinsicElements.a)({
                href: (`mailto:${c.email}`),
                ...{ class: "text-indigo-300 hover:text-indigo-200" },
            });
            (c.email);
        }
        else {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({});
        }
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "py-2 pr-4 text-white/80" },
        });
        (c.position || '—');
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "py-2 pr-4" },
        });
        if (c.is_primary) {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
                ...{ class: "text-emerald-400 text-xs" },
            });
        }
        else {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({});
        }
        __VLS_asFunctionalElement(__VLS_intrinsicElements.td, __VLS_intrinsicElements.td)({
            ...{ class: "py-2 pr-4" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "flex items-center gap-2" },
        });
        if (c.has_portal_access) {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
                ...{ class: "inline-flex items-center gap-1 rounded-full bg-emerald-900/50 px-2 py-0.5 text-xs text-emerald-300" },
                title: "Has portal access",
            });
            __VLS_asFunctionalElement(__VLS_intrinsicElements.svg, __VLS_intrinsicElements.svg)({
                ...{ class: "w-3.5 h-3.5" },
                fill: "none",
                stroke: "currentColor",
                viewBox: "0 0 24 24",
            });
            __VLS_asFunctionalElement(__VLS_intrinsicElements.path)({
                'stroke-linecap': "round",
                'stroke-linejoin': "round",
                'stroke-width': "2",
                d: "M5 13l4 4L19 7",
            });
        }
        else if (c.email) {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
                ...{ onClick: (...[$event]) => {
                        if (!!(__VLS_ctx.contacts.length === 0))
                            return;
                        if (!!(c.has_portal_access))
                            return;
                        if (!(c.email))
                            return;
                        __VLS_ctx.enablePortal(c);
                    } },
                type: "button",
                ...{ class: "rounded-lg p-1.5 text-white/60 hover:text-amber-300 hover:bg-amber-500/10 transition" },
                title: "Enable portal access",
            });
            __VLS_asFunctionalElement(__VLS_intrinsicElements.svg, __VLS_intrinsicElements.svg)({
                ...{ class: "w-4 h-4" },
                fill: "none",
                stroke: "currentColor",
                viewBox: "0 0 24 24",
            });
            __VLS_asFunctionalElement(__VLS_intrinsicElements.path)({
                'stroke-linecap': "round",
                'stroke-linejoin': "round",
                'stroke-width': "2",
                d: "M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z",
            });
        }
        __VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
            ...{ onClick: (...[$event]) => {
                    if (!!(__VLS_ctx.contacts.length === 0))
                        return;
                    __VLS_ctx.openEditContact(c);
                } },
            type: "button",
            ...{ class: "rounded-lg p-1.5 text-white/60 hover:text-white hover:bg-white/10 transition" },
            title: "Edit",
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.svg, __VLS_intrinsicElements.svg)({
            ...{ class: "w-4 h-4" },
            fill: "none",
            stroke: "currentColor",
            viewBox: "0 0 24 24",
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.path)({
            'stroke-linecap': "round",
            'stroke-linejoin': "round",
            'stroke-width': "2",
            d: "M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z",
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
            ...{ onClick: (...[$event]) => {
                    if (!!(__VLS_ctx.contacts.length === 0))
                        return;
                    __VLS_ctx.deleteContact(c);
                } },
            type: "button",
            ...{ class: "rounded-lg p-1.5 text-white/60 hover:text-rose-300 hover:bg-rose-500/10 transition" },
            title: "Delete",
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.svg, __VLS_intrinsicElements.svg)({
            ...{ class: "w-4 h-4" },
            fill: "none",
            stroke: "currentColor",
            viewBox: "0 0 24 24",
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.path)({
            'stroke-linecap': "round",
            'stroke-linejoin': "round",
            'stroke-width': "2",
            d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16",
        });
    }
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "space-y-4" },
});
__VLS_asFunctionalDirective(__VLS_directives.vShow)(null, { ...__VLS_directiveBindingRestFields, value: (__VLS_ctx.activeTab === 'notes') }, null, null);
__VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({
    ...{ class: "rounded-2xl border border-white/10 bg-slate-950/60 p-5" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-medium text-white mb-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "grid grid-cols-1 gap-3 md:grid-cols-3" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "rounded-xl border border-white/10 bg-slate-900/60 p-3" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center justify-between mb-2" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h3, __VLS_intrinsicElements.h3)({
    ...{ class: "text-[11px] font-semibold uppercase tracking-wide text-white/60" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
    ...{ onClick: (...[$event]) => {
            __VLS_ctx.openNotesEditor('notes_sales');
        } },
    type: "button",
    ...{ class: "text-xs font-medium text-indigo-300 hover:text-indigo-200" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]" },
});
(__VLS_ctx.client.notes_sales || 'No notes yet');
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "rounded-xl border border-white/10 bg-slate-900/60 p-3" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center justify-between mb-2" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h3, __VLS_intrinsicElements.h3)({
    ...{ class: "text-[11px] font-semibold uppercase tracking-wide text-white/60" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
    ...{ onClick: (...[$event]) => {
            __VLS_ctx.openNotesEditor('notes_cst');
        } },
    type: "button",
    ...{ class: "text-xs font-medium text-indigo-300 hover:text-indigo-200" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]" },
});
(__VLS_ctx.client.notes_cst || 'No notes yet');
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "rounded-xl border border-white/10 bg-slate-900/60 p-3" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center justify-between mb-2" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h3, __VLS_intrinsicElements.h3)({
    ...{ class: "text-[11px] font-semibold uppercase tracking-wide text-white/60" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
    ...{ onClick: (...[$event]) => {
            __VLS_ctx.openNotesEditor('notes_tech');
        } },
    type: "button",
    ...{ class: "text-xs font-medium text-indigo-300 hover:text-indigo-200" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "text-xs text-white/70 whitespace-pre-wrap min-h-[3rem]" },
});
(__VLS_ctx.client.notes_tech || 'No notes yet');
/** @type {[typeof Modal, typeof Modal, ]} */ ;
// @ts-ignore
const __VLS_16 = __VLS_asFunctionalComponent(Modal, new Modal({
    ...{ 'onClose': {} },
    show: (__VLS_ctx.editingNotes),
    maxWidth: "2xl",
}));
const __VLS_17 = __VLS_16({
    ...{ 'onClose': {} },
    show: (__VLS_ctx.editingNotes),
    maxWidth: "2xl",
}, ...__VLS_functionalComponentArgsRest(__VLS_16));
let __VLS_19;
let __VLS_20;
let __VLS_21;
const __VLS_22 = {
    onClose: (__VLS_ctx.closeNotesEditor)
};
__VLS_18.slots.default;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "p-6" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-semibold text-white" },
});
(__VLS_ctx.noteLabel);
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "mt-1 text-sm text-white/60" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.textarea)({
    ref: "noteTextarea",
    value: (__VLS_ctx.activeNoteValue),
    rows: "8",
    ...{ class: "w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/80 placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-indigo-500" },
    placeholder: "Write notes…",
});
/** @type {typeof __VLS_ctx.noteTextarea} */ ;
if (__VLS_ctx.notesForm.errors[__VLS_ctx.activeNoteField]) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "mt-2 text-sm text-red-400" },
    });
    (__VLS_ctx.notesForm.errors[__VLS_ctx.activeNoteField]);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-6 flex items-center justify-end gap-2" },
});
/** @type {[typeof SecondaryButton, typeof SecondaryButton, ]} */ ;
// @ts-ignore
const __VLS_23 = __VLS_asFunctionalComponent(SecondaryButton, new SecondaryButton({
    ...{ 'onClick': {} },
    type: "button",
}));
const __VLS_24 = __VLS_23({
    ...{ 'onClick': {} },
    type: "button",
}, ...__VLS_functionalComponentArgsRest(__VLS_23));
let __VLS_26;
let __VLS_27;
let __VLS_28;
const __VLS_29 = {
    onClick: (__VLS_ctx.closeNotesEditor)
};
__VLS_25.slots.default;
var __VLS_25;
/** @type {[typeof PrimaryButton, typeof PrimaryButton, ]} */ ;
// @ts-ignore
const __VLS_30 = __VLS_asFunctionalComponent(PrimaryButton, new PrimaryButton({
    ...{ 'onClick': {} },
    type: "button",
    disabled: (__VLS_ctx.notesForm.processing),
}));
const __VLS_31 = __VLS_30({
    ...{ 'onClick': {} },
    type: "button",
    disabled: (__VLS_ctx.notesForm.processing),
}, ...__VLS_functionalComponentArgsRest(__VLS_30));
let __VLS_33;
let __VLS_34;
let __VLS_35;
const __VLS_36 = {
    onClick: (__VLS_ctx.saveNotes)
};
__VLS_32.slots.default;
var __VLS_32;
var __VLS_18;
/** @type {[typeof Modal, typeof Modal, ]} */ ;
// @ts-ignore
const __VLS_37 = __VLS_asFunctionalComponent(Modal, new Modal({
    ...{ 'onClose': {} },
    show: (__VLS_ctx.contactModalOpen),
    maxWidth: "lg",
}));
const __VLS_38 = __VLS_37({
    ...{ 'onClose': {} },
    show: (__VLS_ctx.contactModalOpen),
    maxWidth: "lg",
}, ...__VLS_functionalComponentArgsRest(__VLS_37));
let __VLS_40;
let __VLS_41;
let __VLS_42;
const __VLS_43 = {
    onClose: (__VLS_ctx.closeContactModal)
};
__VLS_39.slots.default;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "p-6" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-semibold text-white" },
});
(__VLS_ctx.contactModalTitle);
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "mt-1 text-sm text-white/60" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.form, __VLS_intrinsicElements.form)({
    ...{ onSubmit: (__VLS_ctx.submitContact) },
    ...{ class: "mt-4 space-y-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: "block text-xs font-medium text-white/60 mb-1" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input, __VLS_intrinsicElements.input)({
    value: (__VLS_ctx.contactForm.name),
    type: "text",
    required: true,
    ...{ class: "w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/90 placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500" },
    placeholder: "Full name",
});
if (__VLS_ctx.contactForm.errors.name) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.contactForm.errors.name);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: "block text-xs font-medium text-white/60 mb-1" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input, __VLS_intrinsicElements.input)({
    type: "email",
    ...{ class: "w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/90 placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500" },
    placeholder: "email@example.com",
});
(__VLS_ctx.contactForm.email);
if (__VLS_ctx.contactForm.errors.email) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.contactForm.errors.email);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: "block text-xs font-medium text-white/60 mb-1" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input, __VLS_intrinsicElements.input)({
    value: (__VLS_ctx.contactForm.phone),
    type: "text",
    ...{ class: "w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/90 placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500" },
    placeholder: "+1 234 567 8900",
});
if (__VLS_ctx.contactForm.errors.phone) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.contactForm.errors.phone);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: "block text-xs font-medium text-white/60 mb-1" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input, __VLS_intrinsicElements.input)({
    value: (__VLS_ctx.contactForm.position),
    type: "text",
    ...{ class: "w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white/90 placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-500" },
    placeholder: "Job title",
});
if (__VLS_ctx.contactForm.errors.position) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.contactForm.errors.position);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center gap-2" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input, __VLS_intrinsicElements.input)({
    id: "contact-primary",
    type: "checkbox",
    ...{ class: "h-4 w-4 rounded border-white/20 bg-slate-800 text-indigo-600 focus:ring-indigo-500" },
});
(__VLS_ctx.contactForm.is_primary);
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    for: "contact-primary",
    ...{ class: "text-sm text-white/80" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-6 flex items-center justify-end gap-2" },
});
/** @type {[typeof SecondaryButton, typeof SecondaryButton, ]} */ ;
// @ts-ignore
const __VLS_44 = __VLS_asFunctionalComponent(SecondaryButton, new SecondaryButton({
    ...{ 'onClick': {} },
    type: "button",
}));
const __VLS_45 = __VLS_44({
    ...{ 'onClick': {} },
    type: "button",
}, ...__VLS_functionalComponentArgsRest(__VLS_44));
let __VLS_47;
let __VLS_48;
let __VLS_49;
const __VLS_50 = {
    onClick: (__VLS_ctx.closeContactModal)
};
__VLS_46.slots.default;
var __VLS_46;
/** @type {[typeof PrimaryButton, typeof PrimaryButton, ]} */ ;
// @ts-ignore
const __VLS_51 = __VLS_asFunctionalComponent(PrimaryButton, new PrimaryButton({
    type: "submit",
    disabled: (__VLS_ctx.contactForm.processing),
}));
const __VLS_52 = __VLS_51({
    type: "submit",
    disabled: (__VLS_ctx.contactForm.processing),
}, ...__VLS_functionalComponentArgsRest(__VLS_51));
__VLS_53.slots.default;
(__VLS_ctx.contactForm.processing ? 'Saving…' : 'Save');
var __VLS_53;
var __VLS_39;
/** @type {__VLS_StyleScopedClasses['min-h-screen']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950']} */ ;
/** @type {__VLS_StyleScopedClasses['border-b']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['mx-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-7xl']} */ ;
/** @type {__VLS_StyleScopedClasses['px-4']} */ ;
/** @type {__VLS_StyleScopedClasses['py-6']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:px-6']} */ ;
/** @type {__VLS_StyleScopedClasses['lg:px-8']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-col']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['md:flex-row']} */ ;
/** @type {__VLS_StyleScopedClasses['md:items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['md:justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['min-w-0']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-3']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-indigo-200']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-rose-500/20']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-rose-500/10']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-rose-200']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-rose-500/20']} */ ;
/** @type {__VLS_StyleScopedClasses['mx-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-7xl']} */ ;
/** @type {__VLS_StyleScopedClasses['px-4']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:px-6']} */ ;
/** @type {__VLS_StyleScopedClasses['lg:px-8']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-1']} */ ;
/** @type {__VLS_StyleScopedClasses['-mb-px']} */ ;
/** @type {__VLS_StyleScopedClasses['mx-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-7xl']} */ ;
/** @type {__VLS_StyleScopedClasses['px-4']} */ ;
/** @type {__VLS_StyleScopedClasses['py-8']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:px-6']} */ ;
/** @type {__VLS_StyleScopedClasses['lg:px-8']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-6']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['grid-cols-1']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-6']} */ ;
/** @type {__VLS_StyleScopedClasses['lg:grid-cols-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['p-5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-3']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['p-5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-3']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-indigo-200']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['p-5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-3']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-6']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['p-5']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-indigo-200']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:grid-cols-2']} */ ;
/** @type {__VLS_StyleScopedClasses['lg:grid-cols-3']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-900/40']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['min-w-0']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['truncate']} */ ;
/** @type {__VLS_StyleScopedClasses['inline-flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['px-2']} */ ;
/** @type {__VLS_StyleScopedClasses['py-0.5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/70']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-indigo-200']} */ ;
/** @type {__VLS_StyleScopedClasses['whitespace-nowrap']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-3']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[11px]']} */ ;
/** @type {__VLS_StyleScopedClasses['uppercase']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-wide']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/40']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-1']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-3']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['truncate']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['whitespace-nowrap']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['p-5']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-indigo-600']} */ ;
/** @type {__VLS_StyleScopedClasses['px-4']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-indigo-700']} */ ;
/** @type {__VLS_StyleScopedClasses['transition']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['overflow-x-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-left']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['border-b']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['pb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-4']} */ ;
/** @type {__VLS_StyleScopedClasses['pb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-4']} */ ;
/** @type {__VLS_StyleScopedClasses['pb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-4']} */ ;
/** @type {__VLS_StyleScopedClasses['pb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-4']} */ ;
/** @type {__VLS_StyleScopedClasses['pb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['w-24']} */ ;
/** @type {__VLS_StyleScopedClasses['border-b']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/90']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-indigo-200']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-emerald-400']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-4']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['inline-flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-1']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-emerald-900/50']} */ ;
/** @type {__VLS_StyleScopedClasses['px-2']} */ ;
/** @type {__VLS_StyleScopedClasses['py-0.5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-emerald-300']} */ ;
/** @type {__VLS_StyleScopedClasses['w-3.5']} */ ;
/** @type {__VLS_StyleScopedClasses['h-3.5']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['p-1.5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-amber-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-amber-500/10']} */ ;
/** @type {__VLS_StyleScopedClasses['transition']} */ ;
/** @type {__VLS_StyleScopedClasses['w-4']} */ ;
/** @type {__VLS_StyleScopedClasses['h-4']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['p-1.5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['transition']} */ ;
/** @type {__VLS_StyleScopedClasses['w-4']} */ ;
/** @type {__VLS_StyleScopedClasses['h-4']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['p-1.5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-rose-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-rose-500/10']} */ ;
/** @type {__VLS_StyleScopedClasses['transition']} */ ;
/** @type {__VLS_StyleScopedClasses['w-4']} */ ;
/** @type {__VLS_StyleScopedClasses['h-4']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['p-5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-4']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['grid-cols-1']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-3']} */ ;
/** @type {__VLS_StyleScopedClasses['md:grid-cols-3']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-900/60']} */ ;
/** @type {__VLS_StyleScopedClasses['p-3']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[11px]']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['uppercase']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-wide']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-indigo-200']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/70']} */ ;
/** @type {__VLS_StyleScopedClasses['whitespace-pre-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['min-h-[3rem]']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-900/60']} */ ;
/** @type {__VLS_StyleScopedClasses['p-3']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[11px]']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['uppercase']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-wide']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-indigo-200']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/70']} */ ;
/** @type {__VLS_StyleScopedClasses['whitespace-pre-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['min-h-[3rem]']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-900/60']} */ ;
/** @type {__VLS_StyleScopedClasses['p-3']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[11px]']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['uppercase']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-wide']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-300']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-indigo-200']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/70']} */ ;
/** @type {__VLS_StyleScopedClasses['whitespace-pre-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['min-h-[3rem]']} */ ;
/** @type {__VLS_StyleScopedClasses['p-6']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-4']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['placeholder:text-white/30']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-2']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-indigo-500']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-end']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['p-6']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-4']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-1']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/90']} */ ;
/** @type {__VLS_StyleScopedClasses['placeholder:text-white/40']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-2']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-indigo-500']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-1']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/90']} */ ;
/** @type {__VLS_StyleScopedClasses['placeholder:text-white/40']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-2']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-indigo-500']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-1']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/90']} */ ;
/** @type {__VLS_StyleScopedClasses['placeholder:text-white/40']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-2']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-indigo-500']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-1']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/90']} */ ;
/** @type {__VLS_StyleScopedClasses['placeholder:text-white/40']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-2']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-indigo-500']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['h-4']} */ ;
/** @type {__VLS_StyleScopedClasses['w-4']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/20']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-800']} */ ;
/** @type {__VLS_StyleScopedClasses['text-indigo-600']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-indigo-500']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-end']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            Link: Link,
            Modal: Modal,
            PrimaryButton: PrimaryButton,
            SecondaryButton: SecondaryButton,
            activeTab: activeTab,
            editingNotes: editingNotes,
            activeNoteField: activeNoteField,
            noteTextarea: noteTextarea,
            notesForm: notesForm,
            noteLabel: noteLabel,
            activeNoteValue: activeNoteValue,
            openNotesEditor: openNotesEditor,
            closeNotesEditor: closeNotesEditor,
            saveNotes: saveNotes,
            r: r,
            destroyClient: destroyClient,
            getStatusClass: getStatusClass,
            contacts: contacts,
            projects: projects,
            contactModalOpen: contactModalOpen,
            contactForm: contactForm,
            openAddContact: openAddContact,
            openEditContact: openEditContact,
            closeContactModal: closeContactModal,
            submitContact: submitContact,
            enablePortal: enablePortal,
            deleteContact: deleteContact,
            contactModalTitle: contactModalTitle,
            hasProjects: hasProjects,
            firstTasks: firstTasks,
            formatMoney: formatMoney,
        };
    },
    __typeProps: {},
});
export default (await import('vue')).defineComponent({
    setup() {
        return {};
    },
    __typeProps: {},
});
; /* PartiallyEnd: #4569/main.vue */
