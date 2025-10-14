import { route } from '@ziggy';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
const passwordInput = ref(null);
const currentPasswordInput = ref(null);
const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
__VLS_asFunctionalElement(__VLS_intrinsicElements.section, __VLS_intrinsicElements.section)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.header, __VLS_intrinsicElements.header)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h2, __VLS_intrinsicElements.h2)({
    ...{ class: "text-lg font-medium text-gray-900" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "mt-1 text-sm text-gray-600" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.form, __VLS_intrinsicElements.form)({
    ...{ onSubmit: (__VLS_ctx.updatePassword) },
    ...{ class: "mt-6 space-y-6" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
/** @type {[typeof InputLabel, ]} */ ;
// @ts-ignore
const __VLS_0 = __VLS_asFunctionalComponent(InputLabel, new InputLabel({
    for: "current_password",
    value: "Current Password",
}));
const __VLS_1 = __VLS_0({
    for: "current_password",
    value: "Current Password",
}, ...__VLS_functionalComponentArgsRest(__VLS_0));
/** @type {[typeof TextInput, ]} */ ;
// @ts-ignore
const __VLS_3 = __VLS_asFunctionalComponent(TextInput, new TextInput({
    id: "current_password",
    ref: "currentPasswordInput",
    modelValue: (__VLS_ctx.form.current_password),
    type: "password",
    ...{ class: "mt-1 block w-full" },
    autocomplete: "current-password",
}));
const __VLS_4 = __VLS_3({
    id: "current_password",
    ref: "currentPasswordInput",
    modelValue: (__VLS_ctx.form.current_password),
    type: "password",
    ...{ class: "mt-1 block w-full" },
    autocomplete: "current-password",
}, ...__VLS_functionalComponentArgsRest(__VLS_3));
/** @type {typeof __VLS_ctx.currentPasswordInput} */ ;
var __VLS_6 = {};
var __VLS_5;
/** @type {[typeof InputError, ]} */ ;
// @ts-ignore
const __VLS_8 = __VLS_asFunctionalComponent(InputError, new InputError({
    message: (__VLS_ctx.form.errors.current_password),
    ...{ class: "mt-2" },
}));
const __VLS_9 = __VLS_8({
    message: (__VLS_ctx.form.errors.current_password),
    ...{ class: "mt-2" },
}, ...__VLS_functionalComponentArgsRest(__VLS_8));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
/** @type {[typeof InputLabel, ]} */ ;
// @ts-ignore
const __VLS_11 = __VLS_asFunctionalComponent(InputLabel, new InputLabel({
    for: "password",
    value: "New Password",
}));
const __VLS_12 = __VLS_11({
    for: "password",
    value: "New Password",
}, ...__VLS_functionalComponentArgsRest(__VLS_11));
/** @type {[typeof TextInput, ]} */ ;
// @ts-ignore
const __VLS_14 = __VLS_asFunctionalComponent(TextInput, new TextInput({
    id: "password",
    ref: "passwordInput",
    modelValue: (__VLS_ctx.form.password),
    type: "password",
    ...{ class: "mt-1 block w-full" },
    autocomplete: "new-password",
}));
const __VLS_15 = __VLS_14({
    id: "password",
    ref: "passwordInput",
    modelValue: (__VLS_ctx.form.password),
    type: "password",
    ...{ class: "mt-1 block w-full" },
    autocomplete: "new-password",
}, ...__VLS_functionalComponentArgsRest(__VLS_14));
/** @type {typeof __VLS_ctx.passwordInput} */ ;
var __VLS_17 = {};
var __VLS_16;
/** @type {[typeof InputError, ]} */ ;
// @ts-ignore
const __VLS_19 = __VLS_asFunctionalComponent(InputError, new InputError({
    message: (__VLS_ctx.form.errors.password),
    ...{ class: "mt-2" },
}));
const __VLS_20 = __VLS_19({
    message: (__VLS_ctx.form.errors.password),
    ...{ class: "mt-2" },
}, ...__VLS_functionalComponentArgsRest(__VLS_19));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
/** @type {[typeof InputLabel, ]} */ ;
// @ts-ignore
const __VLS_22 = __VLS_asFunctionalComponent(InputLabel, new InputLabel({
    for: "password_confirmation",
    value: "Confirm Password",
}));
const __VLS_23 = __VLS_22({
    for: "password_confirmation",
    value: "Confirm Password",
}, ...__VLS_functionalComponentArgsRest(__VLS_22));
/** @type {[typeof TextInput, ]} */ ;
// @ts-ignore
const __VLS_25 = __VLS_asFunctionalComponent(TextInput, new TextInput({
    id: "password_confirmation",
    modelValue: (__VLS_ctx.form.password_confirmation),
    type: "password",
    ...{ class: "mt-1 block w-full" },
    autocomplete: "new-password",
}));
const __VLS_26 = __VLS_25({
    id: "password_confirmation",
    modelValue: (__VLS_ctx.form.password_confirmation),
    type: "password",
    ...{ class: "mt-1 block w-full" },
    autocomplete: "new-password",
}, ...__VLS_functionalComponentArgsRest(__VLS_25));
/** @type {[typeof InputError, ]} */ ;
// @ts-ignore
const __VLS_28 = __VLS_asFunctionalComponent(InputError, new InputError({
    message: (__VLS_ctx.form.errors.password_confirmation),
    ...{ class: "mt-2" },
}));
const __VLS_29 = __VLS_28({
    message: (__VLS_ctx.form.errors.password_confirmation),
    ...{ class: "mt-2" },
}, ...__VLS_functionalComponentArgsRest(__VLS_28));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center gap-4" },
});
/** @type {[typeof PrimaryButton, typeof PrimaryButton, ]} */ ;
// @ts-ignore
const __VLS_31 = __VLS_asFunctionalComponent(PrimaryButton, new PrimaryButton({
    disabled: (__VLS_ctx.form.processing),
}));
const __VLS_32 = __VLS_31({
    disabled: (__VLS_ctx.form.processing),
}, ...__VLS_functionalComponentArgsRest(__VLS_31));
__VLS_33.slots.default;
var __VLS_33;
const __VLS_34 = {}.Transition;
/** @type {[typeof __VLS_components.Transition, typeof __VLS_components.Transition, ]} */ ;
// @ts-ignore
const __VLS_35 = __VLS_asFunctionalComponent(__VLS_34, new __VLS_34({
    enterActiveClass: "transition ease-in-out",
    enterFromClass: "opacity-0",
    leaveActiveClass: "transition ease-in-out",
    leaveToClass: "opacity-0",
}));
const __VLS_36 = __VLS_35({
    enterActiveClass: "transition ease-in-out",
    enterFromClass: "opacity-0",
    leaveActiveClass: "transition ease-in-out",
    leaveToClass: "opacity-0",
}, ...__VLS_functionalComponentArgsRest(__VLS_35));
__VLS_37.slots.default;
if (__VLS_ctx.form.recentlySuccessful) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "text-sm text-gray-600" },
    });
}
var __VLS_37;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-900']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-600']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-6']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-6']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['block']} */ ;
/** @type {__VLS_StyleScopedClasses['w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-600']} */ ;
// @ts-ignore
var __VLS_7 = __VLS_6, __VLS_18 = __VLS_17;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            InputError: InputError,
            InputLabel: InputLabel,
            PrimaryButton: PrimaryButton,
            TextInput: TextInput,
            passwordInput: passwordInput,
            currentPasswordInput: currentPasswordInput,
            form: form,
            updatePassword: updatePassword,
        };
    },
});
export default (await import('vue')).defineComponent({
    setup() {
        return {};
    },
});
; /* PartiallyEnd: #4569/main.vue */
