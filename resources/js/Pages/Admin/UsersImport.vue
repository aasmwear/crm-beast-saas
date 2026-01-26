<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">Users Import/Export</h1>
    <div class="glass rounded-2xl p-4 space-y-4">
      <div class="flex items-center gap-3">
        <a :href="route('admin.users.export')" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10">Export CSV</a>
      </div>
      <form method="post" :action="route('admin.users.import')" enctype="multipart/form-data" class="space-y-2">
        <input type="file" name="file" accept=".csv" />
        <button class="px-3 py-2 rounded-lg bg-[var(--primary)]">Import</button>
      </form>
    </div>
  </div>
</template>
