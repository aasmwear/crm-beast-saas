/// <reference types="../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
const page = usePage();
const tenant = computed(() => page.props.tenant);
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "min-h-screen bg-slate-900 text-slate-200" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.nav, __VLS_intrinsicElements.nav)({
    ...{ class: "border-b border-white/10 bg-slate-900/80 backdrop-blur" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex h-16 items-center justify-between" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center gap-3" },
});
const __VLS_0 = {}.Link;
/** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
// @ts-ignore
const __VLS_1 = __VLS_asFunctionalComponent(__VLS_0, new __VLS_0({
    href: (__VLS_ctx.route('dashboard', { org: __VLS_ctx.tenant?.slug })),
    ...{ class: "text-slate-100 font-semibold" },
}));
const __VLS_2 = __VLS_1({
    href: (__VLS_ctx.route('dashboard', { org: __VLS_ctx.tenant?.slug })),
    ...{ class: "text-slate-100 font-semibold" },
}, ...__VLS_functionalComponentArgsRest(__VLS_1));
__VLS_3.slots.default;
var __VLS_3;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "hidden md:flex items-center gap-3 ml-6" },
});
/** @type {[typeof NavLink, typeof NavLink, ]} */ ;
// @ts-ignore
const __VLS_4 = __VLS_asFunctionalComponent(NavLink, new NavLink({
    href: (__VLS_ctx.route('dashboard', { org: __VLS_ctx.tenant?.slug })),
    active: (__VLS_ctx.route().current('dashboard')),
}));
const __VLS_5 = __VLS_4({
    href: (__VLS_ctx.route('dashboard', { org: __VLS_ctx.tenant?.slug })),
    active: (__VLS_ctx.route().current('dashboard')),
}, ...__VLS_functionalComponentArgsRest(__VLS_4));
__VLS_6.slots.default;
var __VLS_6;
/** @type {[typeof NavLink, typeof NavLink, ]} */ ;
// @ts-ignore
const __VLS_7 = __VLS_asFunctionalComponent(NavLink, new NavLink({
    href: (__VLS_ctx.route('projects.board', { org: __VLS_ctx.tenant?.slug })),
    active: (__VLS_ctx.route().current('projects.board')),
}));
const __VLS_8 = __VLS_7({
    href: (__VLS_ctx.route('projects.board', { org: __VLS_ctx.tenant?.slug })),
    active: (__VLS_ctx.route().current('projects.board')),
}, ...__VLS_functionalComponentArgsRest(__VLS_7));
__VLS_9.slots.default;
var __VLS_9;
/** @type {[typeof NavLink, typeof NavLink, ]} */ ;
// @ts-ignore
const __VLS_10 = __VLS_asFunctionalComponent(NavLink, new NavLink({
    href: ('#'),
    active: (false),
}));
const __VLS_11 = __VLS_10({
    href: ('#'),
    active: (false),
}, ...__VLS_functionalComponentArgsRest(__VLS_10));
__VLS_12.slots.default;
var __VLS_12;
const __VLS_13 = {}.Link;
/** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
// @ts-ignore
const __VLS_14 = __VLS_asFunctionalComponent(__VLS_13, new __VLS_13({
    href: (__VLS_ctx.route('clients.index', __VLS_ctx.route().params.organization)),
    ...{ class: "..." },
}));
const __VLS_15 = __VLS_14({
    href: (__VLS_ctx.route('clients.index', __VLS_ctx.route().params.organization)),
    ...{ class: "..." },
}, ...__VLS_functionalComponentArgsRest(__VLS_14));
__VLS_16.slots.default;
var __VLS_16;
/** @type {[typeof NavLink, typeof NavLink, ]} */ ;
// @ts-ignore
const __VLS_17 = __VLS_asFunctionalComponent(NavLink, new NavLink({
    href: ('#'),
    active: (false),
}));
const __VLS_18 = __VLS_17({
    href: ('#'),
    active: (false),
}, ...__VLS_functionalComponentArgsRest(__VLS_17));
__VLS_19.slots.default;
var __VLS_19;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex items-center gap-2" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
    ...{ class: "px-3 py-1 rounded-full bg-white/10 text-xs" },
});
(__VLS_ctx.tenant?.name ?? '—');
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "md:hidden border-t border-white/10" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "px-2 py-3 space-y-1" },
});
/** @type {[typeof ResponsiveNavLink, typeof ResponsiveNavLink, ]} */ ;
// @ts-ignore
const __VLS_20 = __VLS_asFunctionalComponent(ResponsiveNavLink, new ResponsiveNavLink({
    href: (__VLS_ctx.route('dashboard', { org: __VLS_ctx.tenant?.slug })),
    active: (__VLS_ctx.route().current('dashboard')),
}));
const __VLS_21 = __VLS_20({
    href: (__VLS_ctx.route('dashboard', { org: __VLS_ctx.tenant?.slug })),
    active: (__VLS_ctx.route().current('dashboard')),
}, ...__VLS_functionalComponentArgsRest(__VLS_20));
__VLS_22.slots.default;
var __VLS_22;
/** @type {[typeof ResponsiveNavLink, typeof ResponsiveNavLink, ]} */ ;
// @ts-ignore
const __VLS_23 = __VLS_asFunctionalComponent(ResponsiveNavLink, new ResponsiveNavLink({
    href: (__VLS_ctx.route('projects.board', { org: __VLS_ctx.tenant?.slug })),
    active: (__VLS_ctx.route().current('projects.board')),
}));
const __VLS_24 = __VLS_23({
    href: (__VLS_ctx.route('projects.board', { org: __VLS_ctx.tenant?.slug })),
    active: (__VLS_ctx.route().current('projects.board')),
}, ...__VLS_functionalComponentArgsRest(__VLS_23));
__VLS_25.slots.default;
var __VLS_25;
/** @type {[typeof ResponsiveNavLink, typeof ResponsiveNavLink, ]} */ ;
// @ts-ignore
const __VLS_26 = __VLS_asFunctionalComponent(ResponsiveNavLink, new ResponsiveNavLink({
    href: "#",
}));
const __VLS_27 = __VLS_26({
    href: "#",
}, ...__VLS_functionalComponentArgsRest(__VLS_26));
__VLS_28.slots.default;
var __VLS_28;
/** @type {[typeof ResponsiveNavLink, typeof ResponsiveNavLink, ]} */ ;
// @ts-ignore
const __VLS_29 = __VLS_asFunctionalComponent(ResponsiveNavLink, new ResponsiveNavLink({
    href: "#",
}));
const __VLS_30 = __VLS_29({
    href: "#",
}, ...__VLS_functionalComponentArgsRest(__VLS_29));
__VLS_31.slots.default;
var __VLS_31;
/** @type {[typeof ResponsiveNavLink, typeof ResponsiveNavLink, ]} */ ;
// @ts-ignore
const __VLS_32 = __VLS_asFunctionalComponent(ResponsiveNavLink, new ResponsiveNavLink({
    href: "#",
}));
const __VLS_33 = __VLS_32({
    href: "#",
}, ...__VLS_functionalComponentArgsRest(__VLS_32));
__VLS_34.slots.default;
var __VLS_34;
__VLS_asFunctionalElement(__VLS_intrinsicElements.main, __VLS_intrinsicElements.main)({});
var __VLS_35 = {};
/** @type {__VLS_StyleScopedClasses['min-h-screen']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-900']} */ ;
/** @type {__VLS_StyleScopedClasses['text-slate-200']} */ ;
/** @type {__VLS_StyleScopedClasses['border-b']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-900/80']} */ ;
/** @type {__VLS_StyleScopedClasses['backdrop-blur']} */ ;
/** @type {__VLS_StyleScopedClasses['mx-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['max-w-7xl']} */ ;
/** @type {__VLS_StyleScopedClasses['px-4']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:px-6']} */ ;
/** @type {__VLS_StyleScopedClasses['lg:px-8']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['h-16']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-3']} */ ;
/** @type {__VLS_StyleScopedClasses['text-slate-100']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['hidden']} */ ;
/** @type {__VLS_StyleScopedClasses['md:flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-3']} */ ;
/** @type {__VLS_StyleScopedClasses['ml-6']} */ ;
/** @type {__VLS_StyleScopedClasses['...']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['md:hidden']} */ ;
/** @type {__VLS_StyleScopedClasses['border-t']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['px-2']} */ ;
/** @type {__VLS_StyleScopedClasses['py-3']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-1']} */ ;
// @ts-ignore
var __VLS_36 = __VLS_35;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            Link: Link,
            NavLink: NavLink,
            ResponsiveNavLink: ResponsiveNavLink,
            tenant: tenant,
        };
    },
});
const __VLS_component = (await import('vue')).defineComponent({
    setup() {
        return {};
    },
});
export default {};
; /* PartiallyEnd: #4569/main.vue */
