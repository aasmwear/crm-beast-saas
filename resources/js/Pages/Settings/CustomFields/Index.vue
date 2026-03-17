<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageShell from '@/Components/ui/PageShell.vue'

defineOptions({ layout: AuthenticatedLayout })

type CustomField = {
  id: number
  label: string
  slug: string
  type: string
  options: string[] | null
  is_required: boolean
  sort_order: number
}

const props = defineProps<{
  organizationSlug: string
  customFields: CustomField[]
}>()

const page = usePage<{ flash?: { success?: string } }>()
const org = computed(() => props.organizationSlug ?? 'acme')
const routeGlobal = (window as any).route
const r = (name: string, params: Record<string, string | number> = {}) =>
  routeGlobal ? routeGlobal(name, { ...params, organization: org.value }) : '#'

const TYPE_LABELS: Record<string, string> = {
  text: 'Text',
  number: 'Number',
  date: 'Date',
  select: 'Select',
  multiselect: 'Multi-select',
}

const showCreateModal = ref(false)
const createForm = useForm({
  label: '',
  slug: '',
  type: 'text' as string,
  options: '' as string, // comma-separated for select/multiselect
  is_required: false,
  sort_order: 0,
})

function openCreateModal() {
  createForm.reset()
  createForm.clearErrors()
  createForm.type = 'text'
  createForm.options = ''
  createForm.is_required = false
  createForm.sort_order = (props.customFields?.length ?? 0)
  showCreateModal.value = true
}

function closeCreateModal() {
  showCreateModal.value = false
}

function submitCreate() {
  const payload: Record<string, unknown> = {
    label: createForm.label,
    type: createForm.type,
    is_required: createForm.is_required,
    sort_order: createForm.sort_order,
  }
  if (createForm.slug) payload.slug = createForm.slug
  if (['select', 'multiselect'].includes(createForm.type) && createForm.options) {
    payload.options = createForm.options.split(',').map(s => s.trim()).filter(Boolean)
  }
  createForm.transform(() => payload)
  createForm.post(r('custom-fields.store'), {
    preserveScroll: true,
    onSuccess: () => closeCreateModal(),
  })
}

const showEditModal = ref(false)
const editingField = ref<CustomField | null>(null)
const editForm = useForm({
  label: '',
  slug: '',
  type: 'text' as string,
  options: '' as string,
  is_required: false,
  sort_order: 0,
})

function openEditModal(field: CustomField) {
  editingField.value = field
  editForm.label = field.label
  editForm.slug = field.slug
  editForm.type = field.type
  editForm.options = Array.isArray(field.options) ? field.options.join(', ') : ''
  editForm.is_required = field.is_required
  editForm.sort_order = field.sort_order
  editForm.clearErrors()
  showEditModal.value = true
}

function closeEditModal() {
  showEditModal.value = false
  editingField.value = null
}

function submitEdit() {
  if (!editingField.value) return
  const payload: Record<string, unknown> = {
    label: editForm.label,
    type: editForm.type,
    is_required: editForm.is_required,
    sort_order: editForm.sort_order,
  }
  if (editForm.slug) payload.slug = editForm.slug
  if (['select', 'multiselect'].includes(editForm.type) && editForm.options) {
    payload.options = editForm.options.split(',').map(s => s.trim()).filter(Boolean)
  }
  editForm.transform(() => payload)
  editForm.put(r('custom-fields.update', { customField: editingField.value.id }), {
    preserveScroll: true,
    onSuccess: () => closeEditModal(),
  })
}

const showDeleteConfirm = ref(false)
const deletingField = ref<CustomField | null>(null)
const deleteForm = useForm({})

function openDeleteConfirm(field: CustomField) {
  deletingField.value = field
  showDeleteConfirm.value = true
}

function closeDeleteConfirm() {
  showDeleteConfirm.value = false
  deletingField.value = null
}

function submitDelete() {
  if (!deletingField.value) return
  deleteForm.delete(r('custom-fields.destroy', { customField: deletingField.value.id }), {
    preserveScroll: true,
    onSuccess: () => closeDeleteConfirm(),
  })
}

const inputClass =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90 placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition'
const labelClass = 'block text-xs font-medium text-white/60 tracking-wide mb-1'
</script>

<template>
  <PageShell
    :header="{
      breadcrumb: `Organization • ${org.toUpperCase()}`,
      title: 'Custom Fields',
      subtitle: 'Define custom fields for Clients. These appear on Client create/edit forms.',
    }"
  >
    <template #header-actions>
      <Link
        :href="r('settings.index')"
        class="btn-capsule text-xs"
      >
        ← Settings
      </Link>
      <button
        type="button"
        class="btn-capsule bg-[var(--primary)] text-white hover:opacity-90"
        @click="openCreateModal"
      >
        Add field
      </button>
    </template>

    <div v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-300">
      {{ page.props.flash.success }}
    </div>

    <div class="card-neo overflow-hidden">
      <table class="w-full">
        <thead class="border-b border-white/10 bg-white/5">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-white/60">Label</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-white/60">Slug</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-white/60">Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-white/60">Required</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-white/60">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="f in customFields"
            :key="f.id"
            class="border-b border-white/5 hover:bg-white/5"
          >
            <td class="px-4 py-3 text-sm text-white/90">{{ f.label }}</td>
            <td class="px-4 py-3 text-sm text-white/60 font-mono">{{ f.slug }}</td>
            <td class="px-4 py-3 text-sm text-white/70">{{ TYPE_LABELS[f.type] ?? f.type }}</td>
            <td class="px-4 py-3">
              <span v-if="f.is_required" class="rounded px-2 py-0.5 text-xs bg-amber-500/20 text-amber-300">Yes</span>
              <span v-else class="text-white/40">—</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button
                type="button"
                class="text-sm text-[var(--primary)] hover:underline mr-3"
                @click="openEditModal(f)"
              >
                Edit
              </button>
              <button
                type="button"
                class="text-sm text-red-400 hover:underline"
                @click="openDeleteConfirm(f)"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!customFields?.length" class="p-8 text-center text-white/50">
        No custom fields yet. Click "Add field" to create one.
      </div>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div
        v-if="showCreateModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        @click.self="closeCreateModal"
      >
        <div class="card-neo w-full max-w-md p-6">
          <h3 class="text-lg font-semibold text-white/90 mb-4">
            Add custom field
          </h3>
          <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
              <label :class="labelClass">Label *</label>
              <input
                v-model="createForm.label"
                :class="inputClass"
                required
                placeholder="e.g. Contract Value"
              />
              <p v-if="createForm.errors.label" class="mt-1 text-sm text-red-400">{{ createForm.errors.label }}</p>
            </div>
            <div>
              <label :class="labelClass">Slug (optional, auto-generated from label)</label>
              <input
                v-model="createForm.slug"
                :class="inputClass"
                placeholder="contract_value"
              />
              <p v-if="createForm.errors.slug" class="mt-1 text-sm text-red-400">{{ createForm.errors.slug }}</p>
            </div>
            <div>
              <label :class="labelClass">Type *</label>
              <select v-model="createForm.type" :class="inputClass" required>
                <option value="text">Text</option>
                <option value="number">Number</option>
                <option value="date">Date</option>
                <option value="select">Select</option>
                <option value="multiselect">Multi-select</option>
              </select>
            </div>
            <div v-if="['select', 'multiselect'].includes(createForm.type)">
              <label :class="labelClass">Options (comma-separated) *</label>
              <input
                v-model="createForm.options"
                :class="inputClass"
                :required="['select', 'multiselect'].includes(createForm.type)"
                placeholder="Option A, Option B, Option C"
              />
              <p v-if="createForm.errors.options" class="mt-1 text-sm text-red-400">{{ createForm.errors.options }}</p>
            </div>
            <div class="flex items-center gap-2">
              <input
                id="create-required"
                v-model="createForm.is_required"
                type="checkbox"
                class="rounded border-white/20"
              />
              <label for="create-required" class="text-sm text-white/70">Required</label>
            </div>
            <div class="flex justify-end gap-2 pt-4">
              <button type="button" class="btn-capsule" @click="closeCreateModal">
                Cancel
              </button>
              <button type="submit" class="btn-capsule bg-[var(--primary)] text-white" :disabled="createForm.processing">
                {{ createForm.processing ? 'Saving…' : 'Create' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Edit modal -->
    <Teleport to="body">
      <div
        v-if="showEditModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        @click.self="closeEditModal"
      >
        <div class="card-neo w-full max-w-md p-6">
          <h3 class="text-lg font-semibold text-white/90 mb-4">
            Edit field
          </h3>
          <form @submit.prevent="submitEdit" class="space-y-4">
            <div>
              <label :class="labelClass">Label *</label>
              <input v-model="editForm.label" :class="inputClass" required />
              <p v-if="editForm.errors.label" class="mt-1 text-sm text-red-400">{{ editForm.errors.label }}</p>
            </div>
            <div>
              <label :class="labelClass">Slug</label>
              <input v-model="editForm.slug" :class="inputClass" />
              <p v-if="editForm.errors.slug" class="mt-1 text-sm text-red-400">{{ editForm.errors.slug }}</p>
            </div>
            <div>
              <label :class="labelClass">Type *</label>
              <select v-model="editForm.type" :class="inputClass" required>
                <option value="text">Text</option>
                <option value="number">Number</option>
                <option value="date">Date</option>
                <option value="select">Select</option>
                <option value="multiselect">Multi-select</option>
              </select>
            </div>
            <div v-if="['select', 'multiselect'].includes(editForm.type)">
              <label :class="labelClass">Options (comma-separated) *</label>
              <input
                v-model="editForm.options"
                :class="inputClass"
                :required="['select', 'multiselect'].includes(editForm.type)"
                placeholder="Option A, Option B, Option C"
              />
              <p v-if="editForm.errors.options" class="mt-1 text-sm text-red-400">{{ editForm.errors.options }}</p>
            </div>
            <div class="flex items-center gap-2">
              <input id="edit-required" v-model="editForm.is_required" type="checkbox" class="rounded border-white/20" />
              <label for="edit-required" class="text-sm text-white/70">Required</label>
            </div>
            <div class="flex justify-end gap-2 pt-4">
              <button type="button" class="btn-capsule" @click="closeEditModal">Cancel</button>
              <button type="submit" class="btn-capsule bg-[var(--primary)] text-white" :disabled="editForm.processing">
                {{ editForm.processing ? 'Saving…' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete confirm -->
    <Teleport to="body">
      <div
        v-if="showDeleteConfirm"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        @click.self="closeDeleteConfirm"
      >
        <div class="card-neo w-full max-w-md p-6">
          <h3 class="text-lg font-semibold text-white/90 mb-2">
            Delete field?
          </h3>
          <p v-if="deletingField" class="text-sm text-white/60 mb-4">
            Are you sure you want to delete "{{ deletingField.label }}"? Existing values will be removed.
          </p>
          <div class="flex justify-end gap-2">
            <button type="button" class="btn-capsule" @click="closeDeleteConfirm">Cancel</button>
            <button
              type="button"
              class="btn-capsule bg-red-500/20 text-red-400 hover:bg-red-500/30"
              :disabled="deleteForm.processing"
              @click="submitDelete"
            >
              {{ deleteForm.processing ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </PageShell>
</template>
