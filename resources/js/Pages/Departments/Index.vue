<script setup lang="ts">
const props = defineProps<{ departments: any[] }>()
</script>
<template>
  <div class="p-6"><div class="bg-gray-900 rounded-xl overflow-hidden text-white p-4">
    <div class="font-semibold mb-3">Departments</div><ul class="space-y-2"><li v-for="d in props.departments" :key="d.id">{{ d.name }} ({{ d.code }})</li></ul>
  </div></div>
</template>
