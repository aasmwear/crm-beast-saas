/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageShell from '@/Components/ui/PageShell.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
defineOptions({
    layout: AuthenticatedLayout,
});
const __VLS_props = defineProps();
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
const __VLS_0 = {}.Head;
/** @type {[typeof __VLS_components.Head, ]} */ ;
// @ts-ignore
const __VLS_1 = __VLS_asFunctionalComponent(__VLS_0, new __VLS_0({
    title: "Profile",
}));
const __VLS_2 = __VLS_1({
    title: "Profile",
}, ...__VLS_functionalComponentArgsRest(__VLS_1));
/** @type {[typeof PageShell, typeof PageShell, ]} */ ;
// @ts-ignore
const __VLS_4 = __VLS_asFunctionalComponent(PageShell, new PageShell({
    header: ({
        breadcrumb: 'Account',
        title: 'Profile & Account Settings',
        subtitle: 'Manage your profile details, password, and account lifecycle.',
    }),
}));
const __VLS_5 = __VLS_4({
    header: ({
        breadcrumb: 'Account',
        title: 'Profile & Account Settings',
        subtitle: 'Manage your profile details, password, and account lifecycle.',
    }),
}, ...__VLS_functionalComponentArgsRest(__VLS_4));
__VLS_6.slots.default;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "space-y-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "rounded-2xl border border-white/10 bg-white/5 p-5 shadow-[0_20px_60px_rgba(2,6,23,0.35)] backdrop-blur-xl sm:p-6" },
});
/** @type {[typeof UpdateProfileInformationForm, ]} */ ;
// @ts-ignore
const __VLS_7 = __VLS_asFunctionalComponent(UpdateProfileInformationForm, new UpdateProfileInformationForm({
    mustVerifyEmail: (__VLS_ctx.mustVerifyEmail),
    status: (__VLS_ctx.status),
    ...{ class: "max-w-2xl" },
}));
const __VLS_8 = __VLS_7({
    mustVerifyEmail: (__VLS_ctx.mustVerifyEmail),
    status: (__VLS_ctx.status),
    ...{ class: "max-w-2xl" },
}, ...__VLS_functionalComponentArgsRest(__VLS_7));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "rounded-2xl border border-white/10 bg-white/5 p-5 shadow-[0_20px_60px_rgba(2,6,23,0.35)] backdrop-blur-xl sm:p-6" },
});
/** @type {[typeof UpdatePasswordForm, ]} */ ;
// @ts-ignore
const __VLS_10 = __VLS_asFunctionalComponent(UpdatePasswordForm, new UpdatePasswordForm({
    ...{ class: "max-w-2xl" },
}));
const __VLS_11 = __VLS_10({
    ...{ class: "max-w-2xl" },
}, ...__VLS_functionalComponentArgsRest(__VLS_10));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "rounded-2xl border border-red-400/20 bg-red-500/5 p-5 shadow-[0_20px_60px_rgba(2,6,23,0.35)] backdrop-blur-xl sm:p-6" },
});
/** @type {[typeof DeleteUserForm, ]} */ ;
// @ts-ignore
const __VLS_13 = __VLS_asFunctionalComponent(DeleteUserForm, new DeleteUserForm({
    ...{ class: "max-w-2xl" },
}));
const __VLS_14 = __VLS_13({
    ...{ class: "max-w-2xl" },
}, ...__VLS_functionalComponentArgsRest(__VLS_13));
var __VLS_6;
/** @type {__VLS_StyleScopedClasses['space-y-4']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['p-5']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow-[0_20px_60px_rgba(2,6,23,0.35)]']} */ ;
/** @type {__VLS_StyleScopedClasses['backdrop-blur-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:p-6']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['p-5']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow-[0_20px_60px_rgba(2,6,23,0.35)]']} */ ;
/** @type {__VLS_StyleScopedClasses['backdrop-blur-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:p-6']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-red-400/20']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-red-500/5']} */ ;
/** @type {__VLS_StyleScopedClasses['p-5']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow-[0_20px_60px_rgba(2,6,23,0.35)]']} */ ;
/** @type {__VLS_StyleScopedClasses['backdrop-blur-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:p-6']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-2xl']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            PageShell: PageShell,
            DeleteUserForm: DeleteUserForm,
            UpdatePasswordForm: UpdatePasswordForm,
            UpdateProfileInformationForm: UpdateProfileInformationForm,
            Head: Head,
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
