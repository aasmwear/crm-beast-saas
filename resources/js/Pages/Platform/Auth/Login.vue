<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const route = (window as any).route;

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('platform.login.store'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-950 text-white relative overflow-hidden">
        <Head title="Platform Access" />

        <!-- Background Effects -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute w-96 h-96 bg-purple-600 rounded-full blur-3xl opacity-20 -top-48 -left-48"></div>
            <div class="absolute w-96 h-96 bg-blue-600 rounded-full blur-3xl opacity-20 top-1/3 -right-48"></div>
            <div class="absolute w-80 h-80 bg-pink-600 rounded-full blur-3xl opacity-20 bottom-0 left-1/3"></div>
        </div>

        <!-- Login Card -->
        <div class="relative w-full max-w-md px-6">
            <!-- Logo / Branding -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-600 to-blue-600 mb-4">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white mb-2">Platform Access</h1>
                <p class="text-white/60 text-sm">Sign in to the Super Admin Console</p>
                
                <!-- Security Badge -->
                <div class="inline-flex items-center gap-2 mt-4 px-3 py-1.5 rounded-full bg-purple-600/20 border border-purple-500/30">
                    <svg class="w-3.5 h-3.5 text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    <span class="text-xs font-medium text-purple-400">Secure Platform Login</span>
                </div>
            </div>

            <!-- Status Message -->
            <div v-if="status" class="mb-6 p-4 rounded-lg bg-green-600/20 border border-green-500/30">
                <p class="text-sm font-medium text-green-400">{{ status }}</p>
            </div>

            <!-- Login Form -->
            <div class="glass-card p-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Email -->
                    <div>
                        <InputLabel for="email" value="Email Address" class="text-white/80" />

                        <TextInput
                            id="email"
                            type="email"
                            class="mt-2 block w-full bg-white/5 border-white/10 text-white placeholder-white/40 focus:border-purple-500 focus:ring-purple-500/20"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="admin@crmbeast.com"
                        />

                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div>
                        <InputLabel for="password" value="Password" class="text-white/80" />

                        <TextInput
                            id="password"
                            type="password"
                            class="mt-2 block w-full bg-white/5 border-white/10 text-white placeholder-white/40 focus:border-purple-500 focus:ring-purple-500/20"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />

                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <label class="flex items-center">
                            <Checkbox 
                                name="remember" 
                                v-model:checked="form.remember"
                                class="bg-white/5 border-white/10"
                            />
                            <span class="ms-2 text-sm text-white/60">Remember this device</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-between gap-4">
                        <a
                            v-if="canResetPassword"
                            href="#"
                            class="text-sm text-purple-400 hover:text-purple-300 transition"
                        >
                            Forgot password?
                        </a>

                        <PrimaryButton
                            class="ms-auto px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 border-0"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span v-if="!form.processing">Access Platform</span>
                            <span v-else>Authenticating...</span>
                        </PrimaryButton>
                    </div>
                </form>

                <!-- Security Notice -->
                <div class="mt-6 pt-6 border-t border-white/10">
                    <div class="flex items-start gap-2 text-xs text-white/50">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        <p>
                            This is a secure platform administration area. All login attempts are logged and monitored for security purposes.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-6 text-center">
                <p class="text-xs text-white/40">
                    CRM Beast Platform v1.0 &copy; {{ new Date().getFullYear() }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Inherit glass-card from theme.css */
.glass-card {
    @apply backdrop-blur-xl;
}
</style>
