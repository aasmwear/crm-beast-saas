<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ data: number[] }>()
const width = 560, height = 100, pad = 8
const min = computed(()=> Math.min(...props.data))
const max = computed(()=> Math.max(...props.data))
function x(i:number){ return pad + i * ((width - pad*2) / Math.max(props.data.length-1,1)) }
function y(v:number){
  if (max.value === min.value) return height/2
  const t = (v - min.value) / (max.value - min.value)
  return height - pad - t * (height - pad*2)
}
const d = computed(()=>{
  return props.data.map((v,i)=> (i===0?`M ${x(i)} ${y(v)}`:`L ${x(i)} ${y(v)}`)).join(' ')
})
</script>
<!-- template stays as you have it -->
