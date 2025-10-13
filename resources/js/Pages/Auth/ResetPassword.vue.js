/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { route } from 'ziggy-js';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
const props = defineProps();
const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});
const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
/** @type {[typeof GuestLayout, typeof GuestLayout, ]} */ ;
// @ts-ignore
const __VLS_0 = __VLS_asFunctionalComponent(GuestLayout, new GuestLayout({}));
const __VLS_1 = __VLS_0({}, ...__VLS_functionalComponentArgsRest(__VLS_0));
var __VLS_3 = {};
__VLS_2.slots.default;
const __VLS_4 = {}.Head;
/** @type {[typeof __VLS_components.Head, ]} */ ;
// @ts-ignore
const __VLS_5 = __VLS_asFunctionalComponent(__VLS_4, new __VLS_4({
    title: "Reset Password",
}));
const __VLS_6 = __VLS_5({
    title: "Reset Password",
}, ...__VLS_functionalComponentArgsRest(__VLS_5));
__VLS_asFunctionalElement(__VLS_intrinsicElements.form, __VLS_intrinsicElements.form)({
    ...{ onSubmit: (__VLS_ctx.submit) },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
/** @type {[typeof InputLabel, ]} */ ;
// @ts-ignore
const __VLS_8 = __VLS_asFunctionalComponent(InputLabel, new InputLabel({
    for: "email",
    value: "Email",
}));
const __VLS_9 = __VLS_8({
    for: "email",
    value: "Email",
}, ...__VLS_functionalComponentArgsRest(__VLS_8));
/** @type {[typeof TextInput, ]} */ ;
// @ts-ignore
const __VLS_11 = __VLS_asFunctionalComponent(TextInput, new TextInput({
    id: "email",
    type: "email",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.email),
    required: true,
    autofocus: true,
    autocomplete: "username",
}));
const __VLS_12 = __VLS_11({
    id: "email",
    type: "email",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.email),
    required: true,
    autofocus: true,
    autocomplete: "username",
}, ...__VLS_functionalComponentArgsRest(__VLS_11));
/** @type {[typeof InputError, ]} */ ;
// @ts-ignore
const __VLS_14 = __VLS_asFunctionalComponent(InputError, new InputError({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.email),
}));
const __VLS_15 = __VLS_14({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.email),
}, ...__VLS_functionalComponentArgsRest(__VLS_14));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-4" },
});
/** @type {[typeof InputLabel, ]} */ ;
// @ts-ignore
const __VLS_17 = __VLS_asFunctionalComponent(InputLabel, new InputLabel({
    for: "password",
    value: "Password",
}));
const __VLS_18 = __VLS_17({
    for: "password",
    value: "Password",
}, ...__VLS_functionalComponentArgsRest(__VLS_17));
/** @type {[typeof TextInput, ]} */ ;
// @ts-ignore
const __VLS_20 = __VLS_asFunctionalComponent(TextInput, new TextInput({
    id: "password",
    type: "password",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.password),
    required: true,
    autocomplete: "new-password",
}));
const __VLS_21 = __VLS_20({
    id: "password",
    type: "password",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.password),
    required: true,
    autocomplete: "new-password",
}, ...__VLS_functionalComponentArgsRest(__VLS_20));
/** @type {[typeof InputError, ]} */ ;
// @ts-ignore
const __VLS_23 = __VLS_asFunctionalComponent(InputError, new InputError({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.password),
}));
const __VLS_24 = __VLS_23({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.password),
}, ...__VLS_functionalComponentArgsRest(__VLS_23));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-4" },
});
/** @type {[typeof InputLabel, ]} */ ;
// @ts-ignore
const __VLS_26 = __VLS_asFunctionalComponent(InputLabel, new InputLabel({
    for: "password_confirmation",
    value: "Confirm Password",
}));
const __VLS_27 = __VLS_26({
    for: "password_confirmation",
    value: "Confirm Password",
}, ...__VLS_functionalComponentArgsRest(__VLS_26));
/** @type {[typeof TextInput, ]} */ ;
// @ts-ignore
const __VLS_29 = __VLS_asFunctionalComponent(TextInput, new TextInput({
    id: "password_confirmation",
    type: "password",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.password_confirmation),
    required: true,
    autocomplete: "new-password",
}));
const __VLS_30 = __VLS_29({
    id: "password_confirmation",
    type: "password",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.password_confirmation),
    required: true,
    autocomplete: "new-password",
}, ...__VLS_functionalComponentArgsRest(__VLS_29));
/** @type {[typeof InputError, ]} */ ;
// @ts-ignore
const __VLS_32 = __VLS_asFunctionalComponent(InputError, new InputError({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.password_confirmation),
}));
const __VLS_33 = __VLS_32({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.password_confirmation),
}, ...__VLS_functionalComponentArgsRest(__VLS_32));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-4 flex items-center justify-end" },
});
/** @type {[typeof PrimaryButton, typeof PrimaryButton, ]} */ ;
// @ts-ignore
const __VLS_35 = __VLS_asFunctionalComponent(PrimaryButton, new PrimaryButton({
    ...{ class: ({ 'opacity-25': __VLS_ctx.form.processing }) },
    disabled: (__VLS_ctx.form.processing),
}));
const __VLS_36 = __VLS_35({
    ...{ class: ({ 'opacity-25': __VLS_ctx.form.processing }) },
    disabled: (__VLS_ctx.form.processing),
}, ...__VLS_functionalComponentArgsRest(__VLS_35));
__VLS_37.slots.default;
var __VLS_37;
var __VLS_2;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-4']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-4']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-4']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-end']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            GuestLayout: GuestLayout,
            InputError: InputError,
            InputLabel: InputLabel,
            PrimaryButton: PrimaryButton,
            TextInput: TextInput,
            Head: Head,
            form: form,
            submit: submit,
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
