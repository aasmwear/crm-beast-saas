<script setup lang="ts">
const props = defineProps<{ users: Array<{id:number; name:string; email:string}>, roles: Array<{id:number; name:string}> }>();
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">Role Assignment</h1>
    <form method="post" :action="route('admin.roles.save')" class="glass rounded-2xl p-4 space-y-4">
      <label class="block">
        <div class="text-sm text-slate-400 mb-1">User</div>
        <select name="user_id" class="w-full rounded bg-white/5 border border-white/10 p-2">
          <option v-for="u in props.users" :value="u.id" :key="u.id">{{ u.name }} — {{ u.email }}</option>
        </select>
      </label>
      <div class="text-sm text-slate-400">Roles</div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <label v-for="r in props.roles" :key="r.id" class="flex items-center gap-2 bg-white/5 border border-white/10 rounded p-2">
          <input type="checkbox" name="roles[]" :value="r.name" />
          <span>{{ r.name }}</span>
        </label>
      </div>
      <button class="px-3 py-2 rounded-lg bg-[var(--primary)]">Save</button>
    </form>
  </div>
</template>
