<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ series: {label:string; value:number}[]; size?: string | number }>(), {
  size: 140
})
const total  = computed(() => Math.max(props.series.reduce((a,b)=>a+(b.value||0),0), 1))
const radius = computed(() => (Number(props.size)/2) - 8)
const circ   = computed(() => 2 * Math.PI * radius.value)
function segOffset(idx:number) {
  const prior = props.series.slice(0, idx).reduce((a,b)=>a+b.value,0)
  return (prior/total.value) * circ.value
}
</script>
<!-- template stays as you have it -->
