/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
defineOptions({ layout: AuthenticatedLayout });
// ---------- Route & props ----------
const routeGlobal = window.route;
const r = (name, params = {}, absolute = false, config) => routeGlobal ? routeGlobal(name, params, absolute, config) : '#';
const page = usePage();
const props = defineProps();
const org = computed(() => {
    if (props.organizationSlug)
        return props.organizationSlug;
    const p = page.props;
    return p.tenant?.slug ?? p.organization?.slug ?? 'acme';
});
// ---------- Form ----------
const form = useForm({
    company_name: '',
    industry: null,
    niche: null,
    website: null,
    primary_contact_name: null,
    primary_contact_email: null,
    primary_contact_phone: null,
    address: null,
    fronter_id: null, // Single user ID (strict accountability)
    closer_id: null, // Single user ID (strict accountability)
    assigned_account_manager_id: null,
    google_business_profile_status: null,
    google_business_profile_access_status: null,
    client_activation_status: 'lead',
    status: 'lead',
    notes_by_sales: null,
    notes_by_cst: null,
    notes_by_tech: null,
});
const submit = () => {
    form.post(r('clients.store', { organization: org.value }));
};
// ---------- UI helpers ----------
const inputClass = 'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition';
const textareaClass = 'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition';
const labelClass = 'block text-xs font-medium text-white/60 tracking-wide mb-1';
const statusOptions = [
    { value: 'lead', label: 'Lead' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'paused', label: 'Paused' },
    { value: 'churned', label: 'Churned' },
];
const activationOptions = [
    { value: 'lead', label: 'Lead (not started)' },
    { value: 'onboarding', label: 'Onboarding' },
    { value: 'active', label: 'Active' },
    { value: 'paused', label: 'Paused' },
    { value: 'churned', label: 'Churned / Lost' },
];
const gbpStatusOptions = [
    { value: '', label: '— Select GBP status —' },
    { value: 'not_created', label: 'Not Created' },
    { value: 'created', label: 'Created' },
    { value: 'verified', label: 'Verified' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'in_progress', label: 'In Progress' },
];
const gbpAccessOptions = [
    { value: '', label: '— Select access level —' },
    { value: 'none', label: 'No Access' },
    { value: 'pending', label: 'Access Pending' },
    { value: 'manager', label: 'Manager Access' },
    { value: 'owner', label: 'Owner Access' },
];
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "space-y-6" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({
    ...{ class: "hero-slab" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center justify-between gap-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "text-xs text-white/60" },
});
(__VLS_ctx.org.toUpperCase());
__VLS_asFunctionalElement(__VLS_intrinsicElements.h1, __VLS_intrinsicElements.h1)({
    ...{ class: "mt-1 text-3xl font-semibold tracking-tight" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "mt-1 text-white/60" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center gap-2" },
});
const __VLS_0 = {}.Link;
/** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
// @ts-ignore
const __VLS_1 = __VLS_asFunctionalComponent(__VLS_0, new __VLS_0({
    href: (__VLS_ctx.r('clients.index', { organization: __VLS_ctx.org })),
    ...{ class: "btn-capsule text-xs" },
}));
const __VLS_2 = __VLS_1({
    href: (__VLS_ctx.r('clients.index', { organization: __VLS_ctx.org })),
    ...{ class: "btn-capsule text-xs" },
}, ...__VLS_functionalComponentArgsRest(__VLS_1));
__VLS_3.slots.default;
var __VLS_3;
__VLS_asFunctionalElement(__VLS_intrinsicElements.form, __VLS_intrinsicElements.form)({
    ...{ onSubmit: (__VLS_ctx.submit) },
    ...{ class: "card-neo p-6 space-y-8" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "space-y-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-sm font-semibold text-white/80" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "grid grid-cols-1 md:grid-cols-2 gap-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    ...{ class: (__VLS_ctx.inputClass) },
    required: true,
    placeholder: "Acme Roofing Co.",
});
(__VLS_ctx.form.company_name);
if (__VLS_ctx.form.errors.company_name) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.company_name);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    ...{ class: (__VLS_ctx.inputClass) },
    placeholder: "https://example.com",
});
(__VLS_ctx.form.website);
if (__VLS_ctx.form.errors.website) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.website);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    ...{ class: (__VLS_ctx.inputClass) },
    placeholder: "Roofing / HVAC / Plumbing",
});
(__VLS_ctx.form.industry);
if (__VLS_ctx.form.errors.industry) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.industry);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    ...{ class: (__VLS_ctx.inputClass) },
    placeholder: "Residential Roofing / Commercial HVAC",
});
(__VLS_ctx.form.niche);
if (__VLS_ctx.form.errors.niche) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.niche);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "border-t border-white/10 pt-6 space-y-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-sm font-semibold text-white/80" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "grid grid-cols-1 md:grid-cols-2 gap-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    ...{ class: (__VLS_ctx.inputClass) },
    placeholder: "John Doe",
});
(__VLS_ctx.form.primary_contact_name);
if (__VLS_ctx.form.errors.primary_contact_name) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.primary_contact_name);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    ...{ class: (__VLS_ctx.inputClass) },
    type: "email",
    placeholder: "owner@example.com",
});
(__VLS_ctx.form.primary_contact_email);
if (__VLS_ctx.form.errors.primary_contact_email) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.primary_contact_email);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    ...{ class: (__VLS_ctx.inputClass) },
    placeholder: "+1 (555) 123-4567",
});
(__VLS_ctx.form.primary_contact_phone);
if (__VLS_ctx.form.errors.primary_contact_phone) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.primary_contact_phone);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.textarea, __VLS_intrinsicElements.textarea)({
    value: (__VLS_ctx.form.address),
    rows: "3",
    ...{ class: (__VLS_ctx.textareaClass) },
    placeholder: "Street, city, state, ZIP",
});
if (__VLS_ctx.form.errors.address) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.address);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "border-t border-white/10 pt-6 space-y-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-sm font-semibold text-white/80" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "text-xs text-white/50" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "grid grid-cols-1 md:grid-cols-3 gap-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
    value: (__VLS_ctx.form.fronter_id),
    ...{ class: (__VLS_ctx.inputClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
    value: (null),
});
for (const [user] of __VLS_getVForSourceType((props.users))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
        key: (user.id),
        value: (user.id),
    });
    (user.name);
}
if (__VLS_ctx.form.errors.fronter_id) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.fronter_id);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-1 text-xs text-white/40" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
    value: (__VLS_ctx.form.closer_id),
    ...{ class: (__VLS_ctx.inputClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
    value: (null),
});
for (const [user] of __VLS_getVForSourceType((props.users))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
        key: (user.id),
        value: (user.id),
    });
    (user.name);
}
if (__VLS_ctx.form.errors.closer_id) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.closer_id);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-1 text-xs text-white/40" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
    value: (__VLS_ctx.form.assigned_account_manager_id),
    ...{ class: (__VLS_ctx.inputClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
    value: (null),
});
for (const [user] of __VLS_getVForSourceType((props.users))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
        key: (user.id),
        value: (user.id),
    });
    (user.name);
}
if (__VLS_ctx.form.errors.assigned_account_manager_id) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.assigned_account_manager_id);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-1 text-xs text-white/40" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "border-t border-white/10 pt-6 space-y-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-sm font-semibold text-white/80" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "grid grid-cols-1 md:grid-cols-2 gap-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
    value: (__VLS_ctx.form.client_activation_status),
    ...{ class: (__VLS_ctx.inputClass) },
});
for (const [opt] of __VLS_getVForSourceType((__VLS_ctx.activationOptions))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
        key: (opt.value),
        value: (opt.value),
    });
    (opt.label);
}
if (__VLS_ctx.form.errors.client_activation_status) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.client_activation_status);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
    value: (__VLS_ctx.form.status),
    ...{ class: (__VLS_ctx.inputClass) },
});
for (const [opt] of __VLS_getVForSourceType((__VLS_ctx.statusOptions))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
        key: (opt.value),
        value: (opt.value),
    });
    (opt.label);
}
if (__VLS_ctx.form.errors.status) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.status);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
    value: (__VLS_ctx.form.google_business_profile_status),
    ...{ class: (__VLS_ctx.inputClass) },
});
for (const [opt] of __VLS_getVForSourceType((__VLS_ctx.gbpStatusOptions))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
        key: (opt.value),
        value: (opt.value || null),
    });
    (opt.label);
}
if (__VLS_ctx.form.errors.google_business_profile_status) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.google_business_profile_status);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
    value: (__VLS_ctx.form.google_business_profile_access_status),
    ...{ class: (__VLS_ctx.inputClass) },
});
for (const [opt] of __VLS_getVForSourceType((__VLS_ctx.gbpAccessOptions))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
        key: (opt.value),
        value: (opt.value || null),
    });
    (opt.label);
}
if (__VLS_ctx.form.errors.google_business_profile_access_status) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.google_business_profile_access_status);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "border-t border-white/10 pt-6 space-y-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-sm font-semibold text-white/80" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "grid grid-cols-1 md:grid-cols-3 gap-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.textarea, __VLS_intrinsicElements.textarea)({
    value: (__VLS_ctx.form.notes_by_sales),
    rows: "4",
    ...{ class: (__VLS_ctx.textareaClass) },
});
if (__VLS_ctx.form.errors.notes_by_sales) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.notes_by_sales);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.textarea, __VLS_intrinsicElements.textarea)({
    value: (__VLS_ctx.form.notes_by_cst),
    rows: "4",
    ...{ class: (__VLS_ctx.textareaClass) },
});
if (__VLS_ctx.form.errors.notes_by_cst) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.notes_by_cst);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: (__VLS_ctx.labelClass) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.textarea, __VLS_intrinsicElements.textarea)({
    value: (__VLS_ctx.form.notes_by_tech),
    rows: "4",
    ...{ class: (__VLS_ctx.textareaClass) },
});
if (__VLS_ctx.form.errors.notes_by_tech) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-1 text-sm text-red-400" },
    });
    (__VLS_ctx.form.errors.notes_by_tech);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "pt-4 flex justify-end" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
    type: "submit",
    ...{ class: "btn-capsule bg-[var(--primary)] text-white hover:opacity-90" },
    disabled: (__VLS_ctx.form.processing),
});
(__VLS_ctx.form.processing ? 'Saving…' : 'Create client');
/** @type {__VLS_StyleScopedClasses['space-y-6']} */ ;
/** @type {__VLS_StyleScopedClasses['hero-slab']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-3xl']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-tight']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['btn-capsule']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['card-neo']} */ ;
/** @type {__VLS_StyleScopedClasses['p-6']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-8']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['grid-cols-1']} */ ;
/** @type {__VLS_StyleScopedClasses['md:grid-cols-2']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['border-t']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['pt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['grid-cols-1']} */ ;
/** @type {__VLS_StyleScopedClasses['md:grid-cols-2']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['border-t']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['pt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['grid-cols-1']} */ ;
/** @type {__VLS_StyleScopedClasses['md:grid-cols-3']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/40']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/40']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/40']} */ ;
/** @type {__VLS_StyleScopedClasses['border-t']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['pt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['grid-cols-1']} */ ;
/** @type {__VLS_StyleScopedClasses['md:grid-cols-2']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['border-t']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['pt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/80']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['grid-cols-1']} */ ;
/** @type {__VLS_StyleScopedClasses['md:grid-cols-3']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-red-400']} */ ;
/** @type {__VLS_StyleScopedClasses['pt-4']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-end']} */ ;
/** @type {__VLS_StyleScopedClasses['btn-capsule']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:opacity-90']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            Link: Link,
            r: r,
            org: org,
            form: form,
            submit: submit,
            inputClass: inputClass,
            textareaClass: textareaClass,
            labelClass: labelClass,
            statusOptions: statusOptions,
            activationOptions: activationOptions,
            gbpStatusOptions: gbpStatusOptions,
            gbpAccessOptions: gbpAccessOptions,
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
