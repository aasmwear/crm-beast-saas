<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import Card from '@/Components/ui/Card.vue';

defineProps<{
    total_mrr: number;
    active_tenants: number;
    total_users: number;
    recent_signups?: Array<{
        name: string;
        slug: string;
        created_at: string;
        plan: string;
        users_count: number;
    }>;
}>();

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Platform Dashboard" />

    <PlatformLayout>
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">System Overview</h1>
            <p class="text-white/60">Monitor platform metrics and tenant activity</p>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- MRR Card -->
            <Card class="card-neo">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-white/50 mb-2">Monthly Recurring Revenue</p>
                        <p class="text-3xl font-bold text-white mb-1">{{ formatCurrency(total_mrr) }}</p>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 text-xs text-green-400">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="18 15 12 9 6 15"/>
                                </svg>
                                +12.5%
                            </span>
                            <span class="text-xs text-white/40">vs last month</span>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-600/20 to-emerald-600/20 border border-green-500/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                        </svg>
                    </div>
                </div>
            </Card>

            <!-- Active Tenants Card -->
            <Card class="card-neo">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-white/50 mb-2">Active Organizations</p>
                        <p class="text-3xl font-bold text-white mb-1">{{ active_tenants }}</p>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 text-xs text-blue-400">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="18 15 12 9 6 15"/>
                                </svg>
                                +3
                            </span>
                            <span class="text-xs text-white/40">this week</span>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-600/20 to-indigo-600/20 border border-blue-500/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                            <path d="M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                    </div>
                </div>
            </Card>

            <!-- Total Users Card -->
            <Card class="card-neo">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-white/50 mb-2">Total Platform Users</p>
                        <p class="text-3xl font-bold text-white mb-1">{{ total_users.toLocaleString() }}</p>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 text-xs text-purple-400">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="18 15 12 9 6 15"/>
                                </svg>
                                +24
                            </span>
                            <span class="text-xs text-white/40">today</span>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-600/20 to-pink-600/20 border border-purple-500/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <polyline points="17 11 19 13 23 9"/>
                        </svg>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Recent Tenant Signups -->
        <Card class="card-neo">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-white mb-1">Recent Tenant Signups</h2>
                <p class="text-sm text-white/50">New organizations registered in the last 7 days</p>
            </div>

            <div class="overflow-hidden rounded-lg border border-white/10">
                <table class="w-full">
                    <thead>
                        <tr class="bg-white/5 border-b border-white/10">
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">
                                Organization
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">
                                Slug
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">
                                Plan
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">
                                Users
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">
                                Created
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-white/70 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        <tr 
                            v-for="tenant in recent_signups" 
                            :key="tenant.slug"
                            class="hover:bg-white/5 transition"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center text-sm font-bold text-white mr-3">
                                        {{ tenant.name.substring(0, 2).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-white">{{ tenant.name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-white/60 font-mono">{{ tenant.slug }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-blue-600/20 text-blue-400 border border-blue-500/30': tenant.plan === 'trial',
                                        'bg-purple-600/20 text-purple-400 border border-purple-500/30': tenant.plan === 'pro',
                                        'bg-green-600/20 text-green-400 border border-green-500/30': tenant.plan === 'enterprise',
                                    }"
                                >
                                    {{ tenant.plan.charAt(0).toUpperCase() + tenant.plan.slice(1) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-white/80">{{ tenant.users_count }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-white/60">{{ formatDate(tenant.created_at) }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button class="text-purple-400 hover:text-purple-300 transition">
                                    View Details →
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!recent_signups || recent_signups.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-white/40">
                                    <svg class="w-12 h-12 mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 01-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 011-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 011.52 0C14.51 3.81 17 5 19 5a1 1 0 011 1z"/>
                                    </svg>
                                    <p class="text-sm font-medium">No recent signups</p>
                                    <p class="text-xs mt-1">New organizations will appear here</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <Card class="card-neo">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-600/20 to-red-600/20 border border-orange-500/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-bold text-white mb-1">System Health</h3>
                        <p class="text-xs text-white/50">All systems operational</p>
                    </div>
                    <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                </div>
            </Card>

            <Card class="card-neo">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-600/20 to-blue-600/20 border border-cyan-500/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-bold text-white mb-1">Revenue Insights</h3>
                        <p class="text-xs text-white/50">View detailed analytics</p>
                    </div>
                    <button class="text-xs text-purple-400 hover:text-purple-300 transition font-medium">
                        View →
                    </button>
                </div>
            </Card>
        </div>
    </PlatformLayout>
</template>

<style scoped>
/* Card styling from theme.css */
.card-neo {
    @apply border-white/10;
}
</style>
