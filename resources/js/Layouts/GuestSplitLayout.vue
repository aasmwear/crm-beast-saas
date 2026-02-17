<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="flex min-h-screen flex-col lg:flex-row">
            <slot />
        </div>
    </div>
</template>
