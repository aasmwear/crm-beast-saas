<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

type Actions = 'create' | 'read' | 'update' | 'delete';

type FieldPerm = {
  visible: boolean;
  editable: boolean;
};

type EntityPerm = {
  actions: Record<Actions, boolean>;
  fields: Record<string, FieldPerm>;
};

type RoleRow = {
  id: number;
  name: string;
  permissions_map: Record<string, EntityPerm>;
};

const props = defineProps<{
  roles: Array<{ id: number; name: string; permissions_map: Record<string, any> }>;
  entities: string[];
}>();

// Build a plain (non-reactive) initial payload for useForm
const initial: RoleRow[] = props.roles.map((r) => ({
  id: r.id,
  name: r.name,
  permissions_map: (r.permissions_map ?? {}) as Record<string, EntityPerm>,
}));

const form = useForm<{ roles: RoleRow[] }>({
  roles: initial,
});

const actions: Actions[] = ['create', 'read', 'update', 'delete'];

// Ensure the entity bucket exists before toggling
function ensureEntity(roleIdx: number, entity: string): EntityPerm {
  const pm = form.roles[roleIdx].permissions_map;
  if (!pm[entity]) {
    pm[entity] = { actions: {} as Record<Actions, boolean>, fields: {} };
  }
  return pm[entity];
}

function toggleAction(roleIdx: number, entity: string, action: Actions) {
  const ent = ensureEntity(roleIdx, entity);
  ent.actions[action] = !ent.actions[action];
}

function toggleField(roleIdx: number, entity: string, field: string, kind: 'visible' | 'editable') {
  const ent = ensureEntity(roleIdx, entity);
  ent.fields[field] ??= { visible: false, editable: false };
  ent.fields[field][kind] = !ent.fields[field][kind];
}

function save() {
  // Use your Ziggy route (you mentioned the Ziggy param issue is fixed)
  // If you keep a global tenant slug, this will work as-is:
  form.post(route('admin.permissions.save', { organization: (window as any).tenant?.slug || 'acme' }));
}
</script>

<template>
  <div class="p-6 space-y-6 text-slate-200">
    <div class="flex justify-between items-center">
      <h1 class="text-xl font-semibold">Permission Editor (Admin-only)</h1>
      <button @click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500">Save</button>
    </div>

    <div
      v-for="(role, ri) in form.roles"
      :key="role.id"
      class="rounded-xl border border-slate-700/60 bg-slate-900/50 shadow-md p-4 space-y-4"
    >
      <div class="text-lg font-medium">{{ role.name }}</div>

      <div class="overflow-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr>
              <th class="text-left p-2">Entity</th>
              <th class="p-2" v-for="act in actions" :key="act">{{ act }}</th>
              <th class="p-2">Fields (visible/editable)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ent in props.entities" :key="ent" class="border-t border-slate-700/40">
              <td class="p-2 font-medium">{{ ent }}</td>
              <td class="p-2 text-center" v-for="act in actions" :key="act">
                <input
                  type="checkbox"
                  :checked="!!role.permissions_map?.[ent]?.actions?.[act]"
                  @change="toggleAction(ri, ent, act)"
                />
              </td>
              <td class="p-2">
                <div class="flex flex-wrap gap-2">
                  <template
                    v-for="field in [
                      'id','company_name','industry','niche','primary_contact_name','primary_contact_email','primary_contact_phone',
                      'website','address','tags','fronter','closer','assigned_account_manager_id',
                      'google_business_profile_status','google_business_profile_access_status',
                      'client_activation_status','notes_by_cst','notes_by_sales','notes_by_tech','created_at','updated_at','status'
                    ]"
                    :key="field"
                  >
                    <div class="px-2 py-1 rounded bg-slate-800/70 flex items-center gap-2">
                      <span class="text-xs">{{ field }}</span>
                      <label class="text-[10px] flex items-center gap-1">
                        <input
                          type="checkbox"
                          :checked="!!role.permissions_map?.[ent]?.fields?.[field]?.visible"
                          @change="toggleField(ri, ent, field, 'visible')"
                        />
                        V
                      </label>
                      <label class="text-[10px] flex items-center gap-1">
                        <input
                          type="checkbox"
                          :checked="!!role.permissions_map?.[ent]?.fields?.[field]?.editable"
                          @change="toggleField(ri, ent, field, 'editable')"
                        />
                        E
                      </label>
                    </div>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
