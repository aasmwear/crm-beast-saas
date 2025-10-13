/// <reference types="../../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { route } from 'ziggy-js';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps();
const page = usePage();
const user = computed(() => page.props.auth.user);
const form = useForm({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
});
function submit() {
    form.patch(route('profile.update'));
}
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
    ...{ onSubmit: (...[$event]) => {
            __VLS_ctx.form.patch(__VLS_ctx.route('profile.update'));
        } },
    ...{ class: "mt-6 space-y-6" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
/** @type {[typeof InputLabel, ]} */ ;
// @ts-ignore
const __VLS_0 = __VLS_asFunctionalComponent(InputLabel, new InputLabel({
    for: "name",
    value: "Name",
}));
const __VLS_1 = __VLS_0({
    for: "name",
    value: "Name",
}, ...__VLS_functionalComponentArgsRest(__VLS_0));
/** @type {[typeof TextInput, ]} */ ;
// @ts-ignore
const __VLS_3 = __VLS_asFunctionalComponent(TextInput, new TextInput({
    id: "name",
    type: "text",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.name),
    required: true,
    autofocus: true,
    autocomplete: "name",
}));
const __VLS_4 = __VLS_3({
    id: "name",
    type: "text",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.name),
    required: true,
    autofocus: true,
    autocomplete: "name",
}, ...__VLS_functionalComponentArgsRest(__VLS_3));
/** @type {[typeof InputError, ]} */ ;
// @ts-ignore
const __VLS_6 = __VLS_asFunctionalComponent(InputError, new InputError({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.name),
}));
const __VLS_7 = __VLS_6({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.name),
}, ...__VLS_functionalComponentArgsRest(__VLS_6));
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
/** @type {[typeof InputLabel, ]} */ ;
// @ts-ignore
const __VLS_9 = __VLS_asFunctionalComponent(InputLabel, new InputLabel({
    for: "email",
    value: "Email",
}));
const __VLS_10 = __VLS_9({
    for: "email",
    value: "Email",
}, ...__VLS_functionalComponentArgsRest(__VLS_9));
/** @type {[typeof TextInput, ]} */ ;
// @ts-ignore
const __VLS_12 = __VLS_asFunctionalComponent(TextInput, new TextInput({
    id: "email",
    type: "email",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.email),
    required: true,
    autocomplete: "username",
}));
const __VLS_13 = __VLS_12({
    id: "email",
    type: "email",
    ...{ class: "mt-1 block w-full" },
    modelValue: (__VLS_ctx.form.email),
    required: true,
    autocomplete: "username",
}, ...__VLS_functionalComponentArgsRest(__VLS_12));
/** @type {[typeof InputError, ]} */ ;
// @ts-ignore
const __VLS_15 = __VLS_asFunctionalComponent(InputError, new InputError({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.email),
}));
const __VLS_16 = __VLS_15({
    ...{ class: "mt-2" },
    message: (__VLS_ctx.form.errors.email),
}, ...__VLS_functionalComponentArgsRest(__VLS_15));
if (__VLS_ctx.mustVerifyEmail && __VLS_ctx.user.email_verified_at === null) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "mt-2 text-sm text-gray-800" },
    });
    const __VLS_18 = {}.Link;
    /** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
    // @ts-ignore
    const __VLS_19 = __VLS_asFunctionalComponent(__VLS_18, new __VLS_18({
        href: (__VLS_ctx.route('verification.send')),
        method: "post",
        as: "button",
        ...{ class: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" },
    }));
    const __VLS_20 = __VLS_19({
        href: (__VLS_ctx.route('verification.send')),
        method: "post",
        as: "button",
        ...{ class: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" },
    }, ...__VLS_functionalComponentArgsRest(__VLS_19));
    __VLS_21.slots.default;
    var __VLS_21;
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mt-2 text-sm font-medium text-green-600" },
    });
    __VLS_asFunctionalDirective(__VLS_directives.vShow)(null, { ...__VLS_directiveBindingRestFields, value: (__VLS_ctx.status === 'verification-link-sent') }, null, null);
}
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center gap-4" },
});
/** @type {[typeof PrimaryButton, typeof PrimaryButton, ]} */ ;
// @ts-ignore
const __VLS_22 = __VLS_asFunctionalComponent(PrimaryButton, new PrimaryButton({
    disabled: (__VLS_ctx.form.processing),
}));
const __VLS_23 = __VLS_22({
    disabled: (__VLS_ctx.form.processing),
}, ...__VLS_functionalComponentArgsRest(__VLS_22));
__VLS_24.slots.default;
var __VLS_24;
const __VLS_25 = {}.Transition;
/** @type {[typeof __VLS_components.Transition, typeof __VLS_components.Transition, ]} */ ;
// @ts-ignore
const __VLS_26 = __VLS_asFunctionalComponent(__VLS_25, new __VLS_25({
    enterActiveClass: "transition ease-in-out",
    enterFromClass: "opacity-0",
    leaveActiveClass: "transition ease-in-out",
    leaveToClass: "opacity-0",
}));
const __VLS_27 = __VLS_26({
    enterActiveClass: "transition ease-in-out",
    enterFromClass: "opacity-0",
    leaveActiveClass: "transition ease-in-out",
    leaveToClass: "opacity-0",
}, ...__VLS_functionalComponentArgsRest(__VLS_26));
__VLS_28.slots.default;
if (__VLS_ctx.form.recentlySuccessful) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
        ...{ class: "text-sm text-gray-600" },
    });
}
var __VLS_28;
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
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-800']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-md']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-600']} */ ;
/** @type {__VLS_StyleScopedClasses['underline']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:text-gray-900']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-2']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-indigo-500']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:ring-offset-2']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-green-600']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-gray-600']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            route: route,
            InputError: InputError,
            InputLabel: InputLabel,
            PrimaryButton: PrimaryButton,
            TextInput: TextInput,
            Link: Link,
            user: user,
            form: form,
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
