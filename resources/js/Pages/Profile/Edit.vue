<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageShell from '@/Components/ui/PageShell.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: AuthenticatedLayout,
});

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head title="Profile" />
    <PageShell
        :header="{
            breadcrumb: 'Account',
            title: 'Profile & Account Settings',
            subtitle: 'Manage your profile details, password, and account lifecycle.',
        }"
    >
        <div class="space-y-4">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-[0_20px_60px_rgba(2,6,23,0.35)] backdrop-blur-xl sm:p-6">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-2xl"
                />
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-[0_20px_60px_rgba(2,6,23,0.35)] backdrop-blur-xl sm:p-6">
                <UpdatePasswordForm class="max-w-2xl" />
            </div>

            <div class="rounded-2xl border border-red-400/20 bg-red-500/5 p-5 shadow-[0_20px_60px_rgba(2,6,23,0.35)] backdrop-blur-xl sm:p-6">
                <DeleteUserForm class="max-w-2xl" />
            </div>
        </div>
    </PageShell>
</template>
