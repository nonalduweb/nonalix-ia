<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const items = [
    { label: 'Entreprise', href: '/settings/business' },
    { label: 'Agent IA', href: '/settings/agent' },
    { label: 'WhatsApp', href: '/settings/whatsapp' },
    { label: 'Prestations', href: '/settings/services' },
    { label: 'Questions fréquentes', href: '/settings/faqs' },
    { label: 'Utilisateurs', href: '/settings/users' },
];

const isCurrent = (href) => page.url.startsWith(href);
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-1 border-b border-slate-200 dark:border-slate-800">
        <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            class="-mb-px border-b-2 px-4 py-2.5 text-sm transition"
            :class="
                isCurrent(item.href)
                    ? 'border-brand-600 font-medium text-brand-700 dark:text-brand-100'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:hover:text-slate-200'
            "
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
