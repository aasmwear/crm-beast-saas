/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { computed } from 'vue';
const props = defineProps();
const width = 560, height = 100, pad = 8;
const min = computed(() => Math.min(...props.data));
const max = computed(() => Math.max(...props.data));
function x(i) { return pad + i * ((width - pad * 2) / Math.max(props.data.length - 1, 1)); }
function y(v) {
    if (max.value === min.value)
        return height / 2;
    const t = (v - min.value) / (max.value - min.value);
    return height - pad - t * (height - pad * 2);
}
const d = computed(() => {
    return props.data.map((v, i) => (i === 0 ? `M ${x(i)} ${y(v)}` : `L ${x(i)} ${y(v)}`)).join(' ');
});
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {};
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
