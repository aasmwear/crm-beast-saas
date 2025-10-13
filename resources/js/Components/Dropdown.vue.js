/// <reference types="../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { computed, onMounted, onUnmounted, ref } from 'vue';
const props = withDefaults(defineProps(), {
    align: 'right',
    width: '48',
    contentClasses: 'py-1 bg-white',
});
const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};
onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));
const widthClass = computed(() => {
    return {
        48: 'w-48',
    }[props.width.toString()];
});
const alignmentClasses = computed(() => {
    if (props.align === 'left') {
        return 'ltr:origin-top-left rtl:origin-top-right start-0';
    }
    else if (props.align === 'right') {
        return 'ltr:origin-top-right rtl:origin-top-left end-0';
    }
    else {
        return 'origin-top';
    }
});
const open = ref(false);
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_withDefaultsArg = (function (t) { return t; })({
    align: 'right',
    width: '48',
    contentClasses: 'py-1 bg-white',
});
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "relative" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ onClick: (...[$event]) => {
            __VLS_ctx.open = !__VLS_ctx.open;
        } },
});
var __VLS_0 = {};
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ onClick: (...[$event]) => {
            __VLS_ctx.open = false;
        } },
    ...{ class: "fixed inset-0 z-40" },
});
__VLS_asFunctionalDirective(__VLS_directives.vShow)(null, { ...__VLS_directiveBindingRestFields, value: (__VLS_ctx.open) }, null, null);
const __VLS_2 = {}.Transition;
/** @type {[typeof __VLS_components.Transition, typeof __VLS_components.Transition, ]} */ ;
// @ts-ignore
const __VLS_3 = __VLS_asFunctionalComponent(__VLS_2, new __VLS_2({
    enterActiveClass: "transition ease-out duration-200",
    enterFromClass: "opacity-0 scale-95",
    enterToClass: "opacity-100 scale-100",
    leaveActiveClass: "transition ease-in duration-75",
    leaveFromClass: "opacity-100 scale-100",
    leaveToClass: "opacity-0 scale-95",
    persisted: true,
}));
const __VLS_4 = __VLS_3({
    enterActiveClass: "transition ease-out duration-200",
    enterFromClass: "opacity-0 scale-95",
    enterToClass: "opacity-100 scale-100",
    leaveActiveClass: "transition ease-in duration-75",
    leaveFromClass: "opacity-100 scale-100",
    leaveToClass: "opacity-0 scale-95",
    persisted: true,
}, ...__VLS_functionalComponentArgsRest(__VLS_3));
__VLS_5.slots.default;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ onClick: (...[$event]) => {
            __VLS_ctx.open = false;
        } },
    ...{ class: "absolute z-50 mt-2 rounded-md shadow-lg" },
    ...{ class: ([__VLS_ctx.widthClass, __VLS_ctx.alignmentClasses]) },
    ...{ style: {} },
});
__VLS_asFunctionalDirective(__VLS_directives.vShow)(null, { ...__VLS_directiveBindingRestFields, value: (__VLS_ctx.open) }, null, null);
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "rounded-md ring-1 ring-black ring-opacity-5" },
    ...{ class: (__VLS_ctx.contentClasses) },
});
var __VLS_6 = {};
var __VLS_5;
/** @type {__VLS_StyleScopedClasses['relative']} */ ;
/** @type {__VLS_StyleScopedClasses['fixed']} */ ;
/** @type {__VLS_StyleScopedClasses['inset-0']} */ ;
/** @type {__VLS_StyleScopedClasses['z-40']} */ ;
/** @type {__VLS_StyleScopedClasses['absolute']} */ ;
/** @type {__VLS_StyleScopedClasses['z-50']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-md']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-md']} */ ;
/** @type {__VLS_StyleScopedClasses['ring-1']} */ ;
/** @type {__VLS_StyleScopedClasses['ring-black']} */ ;
/** @type {__VLS_StyleScopedClasses['ring-opacity-5']} */ ;
// @ts-ignore
var __VLS_1 = __VLS_0, __VLS_7 = __VLS_6;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            widthClass: widthClass,
            alignmentClasses: alignmentClasses,
            open: open,
        };
    },
    __typeProps: {},
    props: {},
});
const __VLS_component = (await import('vue')).defineComponent({
    setup() {
        return {};
    },
    __typeProps: {},
    props: {},
});
export default {};
; /* PartiallyEnd: #4569/main.vue */
