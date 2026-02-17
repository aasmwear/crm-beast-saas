<script setup lang="ts">
import { route } from '@ziggy';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import GuestSplitLayout from '@/Layouts/GuestSplitLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_name: '',
    subdomain: '',
});

function syncSubdomainFromCompany(): void {
    if (form.company_name) {
        form.subdomain = slugify(form.company_name);
    }
}

const submit = () => {
    form.post(route('register.company.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestSplitLayout>
        <Head title="Start your free trial" />

        <div class="flex min-h-[80vh] flex-col lg:flex-row lg:min-h-0">
            <!-- Left: Marketing -->
            <div
                class="flex flex-1 flex-col justify-center bg-gradient-to-br from-slate-800 to-slate-900 px-8 py-12 text-white lg:px-12 lg:py-16"
            >
                <Link href="/" class="mb-8 inline-block">
                    <ApplicationLogo class="h-12 w-12 fill-current text-white/90" />
                </Link>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    Start your 14-day free trial.
                </h1>
                <p class="mt-4 text-lg text-slate-300">
                    Manage your agency like a pro.
                </p>
                <ul class="mt-8 space-y-3 text-slate-300">
                    <li class="flex items-center gap-2">
                        <span class="text-emerald-400">✓</span>
                        Projects, clients & tasks in one place
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-emerald-400">✓</span>
                        Team collaboration & permissions
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-emerald-400">✓</span>
                        No credit card required
                    </li>
                </ul>
            </div>

            <!-- Right: Form -->
            <div class="flex flex-1 flex-col justify-center bg-white px-6 py-8 shadow-lg lg:px-12 lg:py-12">
                <h2 class="text-xl font-semibold text-gray-900">Create your workspace</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Your company, your URL. You can always change this later.
                </p>

                <form @submit.prevent="submit" class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.name"
                            required
                            autofocus
                            autocomplete="name"
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="email"
                        />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Password" />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" value="Confirm Password" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-1" :message="form.errors.password_confirmation" />
                    </div>

                    <div>
                        <InputLabel for="company_name" value="Company Name" />
                        <TextInput
                            id="company_name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.company_name"
                            placeholder="e.g. Acme Co"
                            required
                            autocomplete="organization"
                            @input="syncSubdomainFromCompany"
                        />
                        <InputError class="mt-1" :message="form.errors.company_name" />
                    </div>

                    <div>
                        <InputLabel for="subdomain" value="Workspace URL" />
                        <div class="mt-1 flex items-center rounded-md border border-gray-300 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                            <input
                                id="subdomain"
                                type="text"
                                v-model="form.subdomain"
                                placeholder="yourcompany"
                                class="block w-full flex-1 rounded-l-md border-0 bg-transparent py-2 pl-3 pr-1 text-gray-900 placeholder-gray-400 focus:ring-0 sm:text-sm"
                                :class="{ 'border-red-500': form.errors.subdomain }"
                            />
                            <span class="inline-flex items-center rounded-r-md border-0 border-l border-gray-300 bg-gray-50 px-3 text-gray-500 sm:text-sm">
                                .crmbeast.com
                            </span>
                        </div>
                        <p v-if="!form.errors.subdomain" class="mt-1 text-xs text-gray-500">
                            Letters, numbers, and hyphens only. Must be unique.
                        </p>
                        <InputError class="mt-1" :message="form.errors.subdomain" />
                    </div>

                    <div class="flex flex-col gap-4 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <Link
                            :href="route('login')"
                            class="text-sm text-gray-600 underline hover:text-gray-900"
                        >
                            Already have an account?
                        </Link>
                        <PrimaryButton
                            type="submit"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Start free trial
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </GuestSplitLayout>
</template>
