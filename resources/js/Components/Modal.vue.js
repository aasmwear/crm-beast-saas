/// <reference types="../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
const props = withDefaults(defineProps(), {
    show: false,
    maxWidth: '2xl',
    closeable: true,
});
const emit = defineEmits(['close']);
const dialog = ref();
const showSlot = ref(props.show);
watch(() => props.show, () => {
    if (props.show) {
        document.body.style.overflow = 'hidden';
        showSlot.value = true;
        dialog.value?.showModal();
    }
    else {
        document.body.style.overflow = '';
        setTimeout(() => {
            dialog.value?.close();
            showSlot.value = false;
        }, 200);
    }
});
const close = () => {
    if (props.closeable) {
        emit('close');
    }
};
const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();
        if (props.show) {
            close();
        }
    }
};
onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = '';
});
const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_withDefaultsArg = (function (t) { return t; })({
    show: false,
    maxWidth: '2xl',
    closeable: true,
});
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
__VLS_asFunctionalElement(__VLS_intrinsicElements.dialog, __VLS_intrinsicElements.dialog)({
    ...{ class: "z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent" },
    ref: "dialog",
});
/** @type {typeof __VLS_ctx.dialog} */ ;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" },
    'scroll-region': true,
});
const __VLS_0 = {}.Transition;
/** @type {[typeof __VLS_components.Transition, typeof __VLS_components.Transition, ]} */ ;
// @ts-ignore
const __VLS_1 = __VLS_asFunctionalComponent(__VLS_0, new __VLS_0({
    enterActiveClass: "ease-out duration-300",
    enterFromClass: "opacity-0",
    enterToClass: "opacity-100",
    leaveActiveClass: "ease-in duration-200",
    leaveFromClass: "opacity-100",
    leaveToClass: "opacity-0",
    persisted: true,
}));
const __VLS_2 = __VLS_1({
    enterActiveClass: "ease-out duration-300",
    enterFromClass: "opacity-0",
    enterToClass: "opacity-100",
    leaveActiveClass: "ease-in duration-200",
    leaveFromClass: "opacity-100",
    leaveToClass: "opacity-0",
    persisted: true,
}, ...__VLS_functionalComponentArgsRest(__VLS_1));
__VLS_3.slots.default;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ onClick: (__VLS_ctx.close) },
    ...{ class: "fixed inset-0 transform transition-all" },
});
__VLS_asFunctionalDirective(__VLS_directives.vShow)(null, { ...__VLS_directiveBindingRestFields, value: (__VLS_ctx.show) }, null, null);
__VLS_asFunctionalElement(__VLS_intrinsicElements.div)({
    ...{ class: "absolute inset-0 bg-gray-500 opacity-75" },
});
var __VLS_3;
const __VLS_4 = {}.Transition;
/** @type {[typeof __VLS_components.Transition, typeof __VLS_components.Transition, ]} */ ;
// @ts-ignore
const __VLS_5 = __VLS_asFunctionalComponent(__VLS_4, new __VLS_4({
    enterActiveClass: "ease-out duration-300",
    enterFromClass: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95",
    enterToClass: "opacity-100 translate-y-0 sm:scale-100",
    leaveActiveClass: "ease-in duration-200",
    leaveFromClass: "opacity-100 translate-y-0 sm:scale-100",
    leaveToClass: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95",
    persisted: true,
}));
const __VLS_6 = __VLS_5({
    enterActiveClass: "ease-out duration-300",
    enterFromClass: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95",
    enterToClass: "opacity-100 translate-y-0 sm:scale-100",
    leaveActiveClass: "ease-in duration-200",
    leaveFromClass: "opacity-100 translate-y-0 sm:scale-100",
    leaveToClass: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95",
    persisted: true,
}, ...__VLS_functionalComponentArgsRest(__VLS_5));
__VLS_7.slots.default;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mb-6 transform overflow-hidden rounded-lg bg-white shadow-xl transition-all sm:mx-auto sm:w-full" },
    ...{ class: (__VLS_ctx.maxWidthClass) },
});
__VLS_asFunctionalDirective(__VLS_directives.vShow)(null, { ...__VLS_directiveBindingRestFields, value: (__VLS_ctx.show) }, null, null);
if (__VLS_ctx.showSlot) {
    var __VLS_8 = {};
}
var __VLS_7;
/** @type {__VLS_StyleScopedClasses['z-50']} */ ;
/** @type {__VLS_StyleScopedClasses['m-0']} */ ;
/** @type {__VLS_StyleScopedClasses['min-h-full']} */ ;
/** @type {__VLS_StyleScopedClasses['min-w-full']} */ ;
/** @type {__VLS_StyleScopedClasses['overflow-y-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-transparent']} */ ;
/** @type {__VLS_StyleScopedClasses['backdrop:bg-transparent']} */ ;
/** @type {__VLS_StyleScopedClasses['fixed']} */ ;
/** @type {__VLS_StyleScopedClasses['inset-0']} */ ;
/** @type {__VLS_StyleScopedClasses['z-50']} */ ;
/** @type {__VLS_StyleScopedClasses['overflow-y-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['px-4']} */ ;
/** @type {__VLS_StyleScopedClasses['py-6']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:px-0']} */ ;
/** @type {__VLS_StyleScopedClasses['fixed']} */ ;
/** @type {__VLS_StyleScopedClasses['inset-0']} */ ;
/** @type {__VLS_StyleScopedClasses['transform']} */ ;
/** @type {__VLS_StyleScopedClasses['transition-all']} */ ;
/** @type {__VLS_StyleScopedClasses['absolute']} */ ;
/** @type {__VLS_StyleScopedClasses['inset-0']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-gray-500']} */ ;
/** @type {__VLS_StyleScopedClasses['opacity-75']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-6']} */ ;
/** @type {__VLS_StyleScopedClasses['transform']} */ ;
/** @type {__VLS_StyleScopedClasses['overflow-hidden']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['transition-all']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:mx-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:w-full']} */ ;
// @ts-ignore
var __VLS_9 = __VLS_8;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            dialog: dialog,
            showSlot: showSlot,
            close: close,
            maxWidthClass: maxWidthClass,
        };
    },
    emits: {},
    __typeProps: {},
    props: {},
});
const __VLS_component = (await import('vue')).defineComponent({
    setup() {
        return {};
    },
    emits: {},
    __typeProps: {},
    props: {},
});
export default {};
; /* PartiallyEnd: #4569/main.vue */
