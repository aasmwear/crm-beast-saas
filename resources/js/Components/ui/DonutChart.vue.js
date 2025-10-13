/// <reference types="../../../../node_modules/.vue-global-types/vue_3.5_0_0_0.d.ts" />
import { computed } from 'vue';
const props = withDefaults(defineProps(), {
    size: 140
});
const total = computed(() => Math.max(props.series.reduce((a, b) => a + (b.value || 0), 0), 1));
const radius = computed(() => (Number(props.size) / 2) - 8);
const circ = computed(() => 2 * Math.PI * radius.value);
function segOffset(idx) {
    const prior = props.series.slice(0, idx).reduce((a, b) => a + b.value, 0);
    return (prior / total.value) * circ.value;
}
debugger; /* PartiallyEnd: #3632/scriptSetup.vue */
const __VLS_withDefaultsArg = (function (t) { return t; })({
    size: 140
});
const __VLS_ctx = {};
let __VLS_components;
let __VLS_directives;
const __VLS_self = (await import('vue')).defineComponent({
    setup() {
        return {};
    },
    __typeProps: {},
    props: {},
});
export default (await import('vue')).defineComponent({
    setup() {
        return {};
    },
    __typeProps: {},
    props: {},
});
; /* PartiallyEnd: #4569/main.vue */
