/// <reference types="../../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { route } from 'ziggy-js';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);
const form = useForm({
    password: '',
});
const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value?.focus());
};
const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => {
            form.reset();
        },
    });
};
const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
__VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({
    ...{ class: "space-y-6" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.header, __VLS_intrinsicElements.header)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-medium text-gray-900" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "mt-1 text-sm text-gray-600" },
});
/** @type {[typeof DangerButton, typeof DangerButton, ]} */ ;
// @ts-ignore
const __VLS_0 = __VLS_asFunctionalComponent(DangerButton, new DangerButton({
    ...{ 'onClick': {} },
}));
const __VLS_1 = __VLS_0({
    ...{ 'onClick': {} },
}, ...__VLS_functionalComponentArgsRest(__VLS_0));
let __VLS_3;
let __VLS_4;
let __VLS_5;
const __VLS_6 = {
    onClick: (__VLS_ctx.confirmUserDeletion)
};
__VLS_2.slots.default;
var __VLS_2;
/** @type {[typeof Modal, typeof Modal, ]} */ ;
// @ts-ignore
const __VLS_7 = __VLS_asFunctionalComponent(Modal, new Modal({
    ...{ 'onClose': {} },
    show: (__VLS_ctx.confirmingUserDeletion),
}));
const __VLS_8 = __VLS_7({
    ...{ 'onClose': {} },
    show: (__VLS_ctx.confirmingUserDeletion),
}, ...__VLS_functionalComponentArgsRest(__VLS_7));
let __VLS_10;
let __VLS_11;
let __VLS_12;
const __VLS_13 = {
    onClose: (__VLS_ctx.closeModal)
};
__VLS_9.slots.default;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "p-6" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-medium text-gray-900" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "mt-1 text-sm text-gray-600" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-6" },
});
/** @type {[typeof InputLabel, ]} */ ;
// @ts-ignore
const __VLS_14 = __VLS_asFunctionalComponent(InputLabel, new InputLabel({
    for: "password",
    value: "Password",
    ...{ class: "sr-only" },
}));
const __VLS_15 = __VLS_14({
    for: "password",
    value: "Password",
    ...{ class: "sr-only" },
}, ...__VLS_functionalComponentArgsRest(__VLS_14));
/** @type {[typeof TextInput, ]} */ ;
// @ts-ignore
const __VLS_17 = __VLS_asFunctionalComponent(TextInput, new TextInput({
    ...{ 'onKeyup': {} },
    id: "password",
    ref: "passwordInput",
    modelValue: (__VLS_ctx.form.password),
    type: "password",
    ...{ class: "mt-1 block w-3/4" },
    placeholder: "Password",
}));
const __VLS_18 = __VLS_17({
    ...{ 'onKeyup': {} },
    id: "password",
    ref: "passwordInput",
    modelValue: (__VLS_ctx.form.password),
    type: "password",
    ...{ class: "mt-1 block w-3/4" },
    placeholder: "Password",
}, ...__VLS_functionalComponentArgsRest(__VLS_17));
let __VLS_20;
let __VLS_21;
let __VLS_22;
const __VLS_23 = {
    onKeyup: (__VLS_ctx.deleteUser)
};
/** @type {typeof __VLS_ctx.passwordInput} */ ;
var __VLS_24 = {};
var __VLS_19;
/** @type {[typeof InputError, ]} */ ;
// @ts-ignore
const __VLS_26 = __VLS_asFunctionalComponent(InputError, new InputError({
    message: (__VLS_ctx.form.errors.password),
    ...{ class: "mt-2" },
}));
const __VLS_27 = __VLS_26({
    message: (__VLS_ctx.form.errors.password),
    ...{ class: "mt-2" },
}, ...__VLS_functionalComponentArgsRest(__VLS_26));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-6 flex justify-end" },
});
/** @type {[typeof SecondaryButton, typeof SecondaryButton, ]} */ ;
// @ts-ignore
const __VLS_29 = __VLS_asFunctionalComponent(SecondaryButton, new SecondaryButton({
    ...{ 'onClick': {} },
}));
const __VLS_30 = __VLS_29({
    ...{ 'onClick': {} },
}, ...__VLS_functionalComponentArgsRest(__VLS_29));
let __VLS_32;
let __VLS_33;
let __VLS_34;
const __VLS_35 = {
    onClick: (__VLS_ctx.closeModal)
};
__VLS_31.slots.default;
var __VLS_31;
/** @type {[typeof DangerButton, typeof DangerButton, ]} */ ;
// @ts-ignore
const __VLS_36 = __VLS_asFunctionalComponent(DangerButton, new DangerButton({
    ...{ 'onClick': {} },
    ...{ class: "ms-3" },
    ...{ class: ({ 'opacity-25': __VLS_ctx.form.processing }) },
    disabled: (__VLS_ctx.form.processing),
}));
const __VLS_37 = __VLS_36({
    ...{ 'onClick': {} },
    ...{ class: "ms-3" },
    ...{ class: ({ 'opacity-25': __VLS_ctx.form.processing }) },
    disabled: (__VLS_ctx.form.processing),
}, ...__VLS_functionalComponentArgsRest(__VLS_36));
let __VLS_39;
let __VLS_40;
let __VLS_41;
const __VLS_42 = {
    onClick: (__VLS_ctx.deleteUser)
};
__VLS_38.slots.default;
var __VLS_38;
var __VLS_9;
/** @type {__VLS_StyleScopedClasses['space-y-6']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-900']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-600']} */ ;
/** @type {__VLS_StyleScopedClasses['p-6']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-900']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-600']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['sr-only']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['w-3/4']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-end']} */ ;
/** @type {__VLS_StyleScopedClasses['ms-3']} */ ;
// @ts-ignore
var __VLS_25 = __VLS_24;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            DangerButton: DangerButton,
            InputError: InputError,
            InputLabel: InputLabel,
            Modal: Modal,
            SecondaryButton: SecondaryButton,
            TextInput: TextInput,
            confirmingUserDeletion: confirmingUserDeletion,
            passwordInput: passwordInput,
            form: form,
            confirmUserDeletion: confirmUserDeletion,
            deleteUser: deleteUser,
            closeModal: closeModal,
        };
    },
});
export default (await import('vue')).defineComponent({
    setup() {
        return {};
    },
});
; /* PartiallyEnd: #4569/main.vue */
