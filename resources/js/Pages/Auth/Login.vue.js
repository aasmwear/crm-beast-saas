/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { route } from '@ziggy';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const __VLS_props = defineProps();
const form = useForm({
    email: '',
    password: '',
    remember: false,
});
const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
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
    title: "Log in",
}));
const __VLS_6 = __VLS_5({
    title: "Log in",
}, ...__VLS_functionalComponentArgsRest(__VLS_5));
if (__VLS_ctx.status) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mb-4 text-sm font-medium text-green-600" },
    });
    (__VLS_ctx.status);
}
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
    autocomplete: "current-password",
}));
const __VLS_21 = __VLS_20({
    id: "password",
    type: "password",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.password),
    required: true,
    autocomplete: "current-password",
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
    ...{ class: "mt-4 block" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.label, __VLS_intrinsicElements.label)({
    ...{ class: "flex items-center" },
});
/** @type {[typeof Checkbox, ]} */ ;
// @ts-ignore
const __VLS_26 = __VLS_asFunctionalComponent(Checkbox, new Checkbox({
    name: "remember",
    checked: (__VLS_ctx.form.remember),
}));
const __VLS_27 = __VLS_26({
    name: "remember",
    checked: (__VLS_ctx.form.remember),
}, ...__VLS_functionalComponentArgsRest(__VLS_26));
__VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
    ...{ class: "ms-2 text-sm text-gray-600" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex flex-wrap gap-x-4 gap-y-1 text-sm" },
});
if (__VLS_ctx.canResetPassword) {
    const __VLS_29 = {}.Link;
    /** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
    // @ts-ignore
    const __VLS_30 = __VLS_asFunctionalComponent(__VLS_29, new __VLS_29({
        href: (__VLS_ctx.route('password.request')),
        ...{ class: "text-gray-600 underline hover:text-gray-900" },
    }));
    const __VLS_31 = __VLS_30({
        href: (__VLS_ctx.route('password.request')),
        ...{ class: "text-gray-600 underline hover:text-gray-900" },
    }, ...__VLS_functionalComponentArgsRest(__VLS_30));
    __VLS_32.slots.default;
    var __VLS_32;
}
const __VLS_33 = {}.Link;
/** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
// @ts-ignore
const __VLS_34 = __VLS_asFunctionalComponent(__VLS_33, new __VLS_33({
    href: (__VLS_ctx.route('register.company')),
    ...{ class: "text-gray-600 underline hover:text-gray-900" },
}));
const __VLS_35 = __VLS_34({
    href: (__VLS_ctx.route('register.company')),
    ...{ class: "text-gray-600 underline hover:text-gray-900" },
}, ...__VLS_functionalComponentArgsRest(__VLS_34));
__VLS_36.slots.default;
var __VLS_36;
/** @type {[typeof PrimaryButton, typeof PrimaryButton, ]} */ ;
// @ts-ignore
const __VLS_37 = __VLS_asFunctionalComponent(PrimaryButton, new PrimaryButton({
    ...{ class: "sm:ms-4" },
    ...{ class: ({ 'opacity-25': __VLS_ctx.form.processing }) },
    disabled: (__VLS_ctx.form.processing),
}));
const __VLS_38 = __VLS_37({
    ...{ class: "sm:ms-4" },
    ...{ class: ({ 'opacity-25': __VLS_ctx.form.processing }) },
    disabled: (__VLS_ctx.form.processing),
}, ...__VLS_functionalComponentArgsRest(__VLS_37));
__VLS_39.slots.default;
var __VLS_39;
var __VLS_2;
/** @type {__VLS_StyleScopedClasses['mb-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-green-600']} */ ;
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
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['ms-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-600']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-4']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-col']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:flex-row']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-wrap']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-x-4']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-y-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-600']} */ ;
/** @type {__VLS_StyleScopedClasses['underline']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-gray-900']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-600']} */ ;
/** @type {__VLS_StyleScopedClasses['underline']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-gray-900']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:ms-4']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            route: route,
            Checkbox: Checkbox,
            GuestLayout: GuestLayout,
            InputError: InputError,
            InputLabel: InputLabel,
            PrimaryButton: PrimaryButton,
            TextInput: TextInput,
            Head: Head,
            Link: Link,
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
