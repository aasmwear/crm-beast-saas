/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
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
/** @type {[typeof AuthenticatedLayout, typeof AuthenticatedLayout, ]} */ ;
// @ts-ignore
const __VLS_4 = __VLS_asFunctionalComponent(AuthenticatedLayout, new AuthenticatedLayout({}));
const __VLS_5 = __VLS_4({}, ...__VLS_functionalComponentArgsRest(__VLS_4));
__VLS_6.slots.default;
{
    const { header: __VLS_thisSlot } = __VLS_6.slots;
    __VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
        ...{ class: "text-xl font-semibold leading-tight text-gray-800" },
    });
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "py-12" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "bg-white p-4 shadow sm:rounded-lg sm:p-8" },
});
/** @type {[typeof UpdateProfileInformationForm, ]} */ ;
// @ts-ignore
const __VLS_7 = __VLS_asFunctionalComponent(UpdateProfileInformationForm, new UpdateProfileInformationForm({
    mustVerifyEmail: (__VLS_ctx.mustVerifyEmail),
    status: (__VLS_ctx.status),
    ...{ class: "max-w-xl" },
}));
const __VLS_8 = __VLS_7({
    mustVerifyEmail: (__VLS_ctx.mustVerifyEmail),
    status: (__VLS_ctx.status),
    ...{ class: "max-w-xl" },
}, ...__VLS_functionalComponentArgsRest(__VLS_7));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "bg-white p-4 shadow sm:rounded-lg sm:p-8" },
});
/** @type {[typeof UpdatePasswordForm, ]} */ ;
// @ts-ignore
const __VLS_10 = __VLS_asFunctionalComponent(UpdatePasswordForm, new UpdatePasswordForm({
    ...{ class: "max-w-xl" },
}));
const __VLS_11 = __VLS_10({
    ...{ class: "max-w-xl" },
}, ...__VLS_functionalComponentArgsRest(__VLS_10));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "bg-white p-4 shadow sm:rounded-lg sm:p-8" },
});
/** @type {[typeof DeleteUserForm, ]} */ ;
// @ts-ignore
const __VLS_13 = __VLS_asFunctionalComponent(DeleteUserForm, new DeleteUserForm({
    ...{ class: "max-w-xl" },
}));
const __VLS_14 = __VLS_13({
    ...{ class: "max-w-xl" },
}, ...__VLS_functionalComponentArgsRest(__VLS_13));
var __VLS_6;
/** @type {__VLS_StyleScopedClasses['text-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['leading-tight']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-800']} */ ;
/** @type {__VLS_StyleScopedClasses['py-12']} */ ;
/** @type {__VLS_StyleScopedClasses['mx-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-7xl']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-6']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:px-6']} */ ;
/** @type {__VLS_StyleScopedClasses['lg:px-8']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:rounded-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:p-8']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:rounded-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:p-8']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:rounded-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:p-8']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-xl']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            AuthenticatedLayout: AuthenticatedLayout,
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
