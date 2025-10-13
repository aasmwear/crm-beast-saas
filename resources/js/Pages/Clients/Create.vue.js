/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
const form = useForm({
    company_name: '', primary_contact_name: '', primary_contact_email: '',
    primary_contact_phone: '', industry: '', niche: '', website: '', address: '',
    status: ''
});
function submit() {
    form.post(route('clients.store', route().params.organization));
}
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "p-6 text-white" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h1, __VLS_intrinsicElements.h1)({
    ...{ class: "text-2xl font-semibold mb-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "grid gap-4 md:grid-cols-2" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    placeholder: "Company name",
    ...{ class: "rounded bg-black/40 px-3 py-2" },
});
(__VLS_ctx.form.company_name);
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    placeholder: "Primary email",
    ...{ class: "rounded bg-black/40 px-3 py-2" },
});
(__VLS_ctx.form.primary_contact_email);
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    placeholder: "Primary contact name",
    ...{ class: "rounded bg-black/40 px-3 py-2" },
});
(__VLS_ctx.form.primary_contact_name);
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    placeholder: "Phone",
    ...{ class: "rounded bg-black/40 px-3 py-2" },
});
(__VLS_ctx.form.primary_contact_phone);
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    placeholder: "Industry",
    ...{ class: "rounded bg-black/40 px-3 py-2" },
});
(__VLS_ctx.form.industry);
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    placeholder: "Niche",
    ...{ class: "rounded bg-black/40 px-3 py-2" },
});
(__VLS_ctx.form.niche);
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    placeholder: "Website (https://…)",
    ...{ class: "rounded bg-black/40 px-3 py-2 md:col-span-2" },
});
(__VLS_ctx.form.website);
__VLS_asFunctionalElement(__VLS_intrinsicElements.input)({
    placeholder: "Address",
    ...{ class: "rounded bg-black/40 px-3 py-2 md:col-span-2" },
});
(__VLS_ctx.form.address);
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-6" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.button, __VLS_intrinsicElements.button)({
    ...{ onClick: (__VLS_ctx.submit) },
    ...{ class: "rounded-xl bg-green-600 px-4 py-2" },
});
/** @type {__VLS_StyleScopedClasses['p-6']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['text-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-4']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['md:grid-cols-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-black/40']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-black/40']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-black/40']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-black/40']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-black/40']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-black/40']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-black/40']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['md:col-span-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-black/40']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
/** @type {__VLS_StyleScopedClasses['md:col-span-2']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-green-600']} */ ;
/** @type {__VLS_StyleScopedClasses['px-4']} */ ;
/** @type {__VLS_StyleScopedClasses['py-2']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            form: form,
            submit: submit,
        };
    },
});
export default (await import('vue')).defineComponent({
    setup() {
        return {};
    },
});
; /* PartiallyEnd: #4569/main.vue */
