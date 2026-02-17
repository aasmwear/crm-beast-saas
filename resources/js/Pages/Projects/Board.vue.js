/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
defineOptions({ layout: AuthenticatedLayout });
const props = defineProps();
const page = usePage();
const orgSlug = computed(() => {
    const p = page.props;
    return props.organization?.slug ?? p?.tenant?.slug ?? p?.organization?.slug ?? 'acme';
});
const columns = [
    { key: 'planned', label: 'Planned' },
    { key: 'in-progress', label: 'In Progress' },
    { key: 'on-hold', label: 'On Hold' },
    { key: 'completed', label: 'Completed' },
    { key: 'cancelled', label: 'Cancelled' },
];
function normalizeStatus(status) {
    if (!status)
        return 'planned';
    const s = status.trim().toLowerCase();
    if (['planned'].includes(s))
        return 'planned';
    if (['in progress', 'in_progress', 'active', 'ongoing'].includes(s))
        return 'in-progress';
    if (['on hold', 'on_hold', 'paused'].includes(s))
        return 'on-hold';
    if (['completed', 'done', 'finished', 'closed'].includes(s))
        return 'completed';
    if (['cancelled', 'canceled'].includes(s))
        return 'cancelled';
    return 'planned';
}
function projectsInColumn(key) {
    return props.projects.filter((p) => normalizeStatus(p.status) === key);
}
const isUpdating = ref(null);
function updateStatus(project, columnKey) {
    const targetColumn = columns.find((c) => c.key === columnKey);
    if (!targetColumn)
        return;
    const newStatus = targetColumn.label;
    isUpdating.value = project.id;
    router.post(route('projects.pipeline.update', {
        organization: orgSlug.value,
        project: project.id,
    }), { status: newStatus }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isUpdating.value = null;
        },
    });
}
function onColumnChange(project, event) {
    const target = event.target;
    const value = target.value;
    updateStatus(project, value);
}
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "space-y-6" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "mb-2 rounded-2xl bg-gradient-to-br from-[rgba(13,15,18,0.9)] via-[rgba(18,18,40,0.85)] to-transparent border border-white/5 px-5 py-4" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
__VLS_asFunctionalElement(__VLS_intrinsicElements.h1, __VLS_intrinsicElements.h1)({
    ...{ class: "text-lg font-semibold tracking-tight text-white" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.p, __VLS_intrinsicElements.p)({
    ...{ class: "mt-1 text-sm text-white/60" },
});
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "inline-flex items-center gap-1 rounded-full bg-white/5 p-1 text-xs" },
});
const __VLS_0 = {}.Link;
/** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
// @ts-ignore
const __VLS_1 = __VLS_asFunctionalComponent(__VLS_0, new __VLS_0({
    href: (__VLS_ctx.route('projects.index', { organization: __VLS_ctx.orgSlug })),
    ...{ class: "rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10" },
}));
const __VLS_2 = __VLS_1({
    href: (__VLS_ctx.route('projects.index', { organization: __VLS_ctx.orgSlug })),
    ...{ class: "rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10" },
}, ...__VLS_functionalComponentArgsRest(__VLS_1));
__VLS_3.slots.default;
var __VLS_3;
__VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({
    ...{ class: "rounded-full bg-[var(--primary)] px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white shadow-[0_0_24px_rgba(139,124,255,0.65)]" },
});
const __VLS_4 = {}.Link;
/** @type {[typeof __VLS_components.Link, typeof __VLS_components.Link, ]} */ ;
// @ts-ignore
const __VLS_5 = __VLS_asFunctionalComponent(__VLS_4, new __VLS_4({
    href: (__VLS_ctx.route('projects.calendar', { organization: __VLS_ctx.orgSlug })),
    ...{ class: "rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10" },
}));
const __VLS_6 = __VLS_5({
    href: (__VLS_ctx.route('projects.calendar', { organization: __VLS_ctx.orgSlug })),
    ...{ class: "rounded-full px-3 py-1 text-[11px] uppercase tracking-wide text-white/70 hover:bg-white/10" },
}, ...__VLS_functionalComponentArgsRest(__VLS_5));
__VLS_7.slots.default;
var __VLS_7;
__VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
    ...{ class: "grid gap-4 md:grid-cols-2 xl:grid-cols-5" },
});
for (const [column] of __VLS_getVForSourceType((__VLS_ctx.columns))) {
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        key: (column.key),
        ...{ class: "glass flex min-h-[260px] flex-col rounded-2xl border border-white/5 bg-slate-950/40 p-3" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "mb-2 flex items-center justify-between gap-2" },
    });
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "text-xs font-semibold uppercase tracking-wide text-slate-100" },
    });
    (column.label);
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "rounded-full bg-white/5 px-2 py-0.5 text-[10px] text-white/60" },
    });
    (__VLS_ctx.projectsInColumn(column.key).length);
    __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
        ...{ class: "flex-1 space-y-2 overflow-y-auto pr-1" },
    });
    for (const [project] of __VLS_getVForSourceType((__VLS_ctx.projectsInColumn(column.key)))) {
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            key: (project.id),
            ...{ class: "group rounded-xl border border-white/5 bg-white/5 p-3 text-sm transition hover:border-[var(--primary)] hover:bg-white/10" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "flex items-start justify-between gap-2" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({});
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "line-clamp-2 font-medium text-white" },
        });
        (project.title);
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "mt-1 text-[11px] text-white/50" },
        });
        if (project.project_manager_name) {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({});
            (project.project_manager_name);
        }
        else {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.span, __VLS_intrinsicElements.span)({});
        }
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "mt-3 flex items-center justify-between gap-2" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "flex items-center gap-1" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div)({
            ...{ class: "h-1.5 w-1.5 rounded-full bg-[var(--primary)]" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "text-[10px] uppercase tracking-wide text-white/50" },
        });
        (column.label);
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "flex items-center gap-1" },
        });
        __VLS_asFunctionalElement(__VLS_intrinsicElements.select, __VLS_intrinsicElements.select)({
            ...{ onChange: (...[$event]) => {
                    __VLS_ctx.onColumnChange(project, $event);
                } },
            ...{ class: "rounded-full border border-white/15 bg-slate-950/60 px-2 py-1 text-[10px] text-white/70 focus:border-[var(--primary)] focus:outline-none" },
            value: (column.key),
            disabled: (__VLS_ctx.isUpdating === project.id),
        });
        for (const [target] of __VLS_getVForSourceType((__VLS_ctx.columns))) {
            __VLS_asFunctionalElement(__VLS_intrinsicElements.option, __VLS_intrinsicElements.option)({
                key: (target.key),
                value: (target.key),
            });
            (target.label);
        }
    }
    if (!__VLS_ctx.projectsInColumn(column.key).length) {
        __VLS_asFunctionalElement(__VLS_intrinsicElements.div, __VLS_intrinsicElements.div)({
            ...{ class: "mt-4 rounded-xl border border-dashed border-white/10 bg-slate-950/40 p-4 text-center text-xs text-white/40" },
        });
    }
}
/** @type {__VLS_StyleScopedClasses['space-y-6']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-gradient-to-br']} */ ;
/** @type {__VLS_StyleScopedClasses['from-[rgba(13,15,18,0.9)]']} */ ;
/** @type {__VLS_StyleScopedClasses['via-[rgba(18,18,40,0.85)]']} */ ;
/** @type {__VLS_StyleScopedClasses['to-transparent']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['px-5']} */ ;
/** @type {__VLS_StyleScopedClasses['py-4']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-col']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-3']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:flex-row']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['sm:justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['text-lg']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-tight']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['inline-flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-1']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['p-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[11px]']} */ ;
/** @type {__VLS_StyleScopedClasses['uppercase']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-wide']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/70']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[11px]']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['uppercase']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-wide']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['shadow-[0_0_24px_rgba(139,124,255,0.65)]']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['px-3']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[11px]']} */ ;
/** @type {__VLS_StyleScopedClasses['uppercase']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-wide']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/70']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['grid']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-4']} */ ;
/** @type {__VLS_StyleScopedClasses['md:grid-cols-2']} */ ;
/** @type {__VLS_StyleScopedClasses['xl:grid-cols-5']} */ ;
/** @type {__VLS_StyleScopedClasses['glass']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['min-h-[260px]']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-col']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-2xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/40']} */ ;
/** @type {__VLS_StyleScopedClasses['p-3']} */ ;
/** @type {__VLS_StyleScopedClasses['mb-2']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['font-semibold']} */ ;
/** @type {__VLS_StyleScopedClasses['uppercase']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-wide']} */ ;
/** @type {__VLS_StyleScopedClasses['text-slate-100']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['px-2']} */ ;
/** @type {__VLS_StyleScopedClasses['py-0.5']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[10px]']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/60']} */ ;
/** @type {__VLS_StyleScopedClasses['flex-1']} */ ;
/** @type {__VLS_StyleScopedClasses['space-y-2']} */ ;
/** @type {__VLS_StyleScopedClasses['overflow-y-auto']} */ ;
/** @type {__VLS_StyleScopedClasses['pr-1']} */ ;
/** @type {__VLS_StyleScopedClasses['group']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-white/5']} */ ;
/** @type {__VLS_StyleScopedClasses['p-3']} */ ;
/** @type {__VLS_StyleScopedClasses['text-sm']} */ ;
/** @type {__VLS_StyleScopedClasses['transition']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:border-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['hover:bg-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-start']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['line-clamp-2']} */ ;
/** @type {__VLS_StyleScopedClasses['font-medium']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[11px]']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-3']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['justify-between']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-2']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-1']} */ ;
/** @type {__VLS_StyleScopedClasses['h-1.5']} */ ;
/** @type {__VLS_StyleScopedClasses['w-1.5']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[10px]']} */ ;
/** @type {__VLS_StyleScopedClasses['uppercase']} */ ;
/** @type {__VLS_StyleScopedClasses['tracking-wide']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/50']} */ ;
/** @type {__VLS_StyleScopedClasses['flex']} */ ;
/** @type {__VLS_StyleScopedClasses['items-center']} */ ;
/** @type {__VLS_StyleScopedClasses['gap-1']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-full']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/15']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/60']} */ ;
/** @type {__VLS_StyleScopedClasses['px-2']} */ ;
/** @type {__VLS_StyleScopedClasses['py-1']} */ ;
/** @type {__VLS_StyleScopedClasses['text-[10px]']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/70']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:border-[var(--primary)]']} */ ;
/** @type {__VLS_StyleScopedClasses['focus:outline-none']} */ ;
/** @type {__VLS_StyleScopedClasses['mt-4']} */ ;
/** @type {__VLS_StyleScopedClasses['rounded-xl']} */ ;
/** @type {__VLS_StyleScopedClasses['border']} */ ;
/** @type {__VLS_StyleScopedClasses['border-dashed']} */ ;
/** @type {__VLS_StyleScopedClasses['border-white/10']} */ ;
/** @type {__VLS_StyleScopedClasses['bg-slate-950/40']} */ ;
/** @type {__VLS_StyleScopedClasses['p-4']} */ ;
/** @type {__VLS_StyleScopedClasses['text-center']} */ ;
/** @type {__VLS_StyleScopedClasses['text-xs']} */ ;
/** @type {__VLS_StyleScopedClasses['text-white/40']} */ ;
var __VLS_dollars;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {
            Link: Link,
            orgSlug: orgSlug,
            columns: columns,
            projectsInColumn: projectsInColumn,
            isUpdating: isUpdating,
            onColumnChange: onColumnChange,
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
