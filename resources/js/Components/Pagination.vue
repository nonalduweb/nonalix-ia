<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * Pagination Inertia.
 *
 * Consomme directement le format des paginateurs Laravel (`links`, `meta`),
 * qu'ils soient sérialisés en resource ou renvoyés bruts — d'où le double
 * chemin d'accès ci-dessous.
 */
const props = defineProps({
    paginator: { type: Object, required: true },
});

const links = computed(() => props.paginator.links ?? props.paginator.meta?.links ?? []);
const from = computed(() => props.paginator.from ?? props.paginator.meta?.from ?? 0);
const to = computed(() => props.paginator.to ?? props.paginator.meta?.to ?? 0);
const total = computed(() => props.paginator.total ?? props.paginator.meta?.total ?? 0);

// Une seule page : afficher une barre de pagination serait du bruit.
const isVisible = computed(() => links.value.length > 3);
</script>

<template>
    <div v-if="isVisible" class="mt-4 flex items-center justify-between text-sm">
        <p class="text-slate-500">
            {{ from }}–{{ to }} sur {{ total }}
        </p>

        <nav class="flex gap-1">
            <template v-for="(link, index) in links" :key="index">
                <span
                    v-if="!link.url"
                    class="rounded-lg px-3 py-1.5 text-slate-400"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="rounded-lg px-3 py-1.5 transition"
                    :class="
                        link.active
                            ? 'bg-brand-600 text-white'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                    "
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
