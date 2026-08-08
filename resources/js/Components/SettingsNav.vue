<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

// `key` rattache chaque onglet à l'état calculé côté serveur. Un onglet sans
// clé ne porte aucun indicateur : il n'y a rien à y « terminer ».
const items = [
    { label: 'Entreprise', href: '/settings/business', key: 'business' },
    { label: 'Agent IA', href: '/settings/agent', key: 'agent' },
    { label: 'WhatsApp', href: '/settings/whatsapp', key: 'whatsapp' },
    { label: 'Widget Site Web', href: '/settings/widget', key: null },
    { label: 'Canal e-mail', href: '/settings/email', key: null },
    { label: 'Facturation', href: '/settings/billing', key: null },
    { label: 'Prestations', href: '/settings/services', key: 'services' },
    { label: 'Questions fréquentes', href: '/settings/faqs', key: 'faqs' },
    { label: 'Utilisateurs', href: '/settings/users', key: null },
];

const setup = computed(() => page.props.setup ?? null);

const stateFor = (key) => (key && setup.value ? (setup.value[key] ?? null) : null);

const isCurrent = (href) => page.url.startsWith(href);

// Ce qui empêche réellement l'agent de fonctionner.
const blocking = computed(() =>
    setup.value === null
        ? []
        : items
              .filter((i) => stateFor(i.key)?.required && !stateFor(i.key)?.done)
              .map((i) => ({ ...i, hint: stateFor(i.key).hint })),
);
</script>

<template>
    <nav class="mb-4 flex flex-wrap gap-1 border-b border-slate-200 dark:border-slate-800">
        <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            class="-mb-px flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm transition"
            :class="
                isCurrent(item.href)
                    ? 'border-brand-600 font-medium text-brand-700 dark:text-brand-100'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:hover:text-slate-200'
            "
        >
            {{ item.label }}

            <!-- Pastille pleine = requis et manquant, creuse = facultatif non
                 renseigné, coche = terminé. L'état se lit d'un coup d'œil. -->
            <span
                v-if="stateFor(item.key)"
                class="shrink-0 text-xs leading-none"
                :class="
                    stateFor(item.key).done
                        ? 'text-emerald-600'
                        : stateFor(item.key).required
                          ? 'text-amber-500'
                          : 'text-slate-300 dark:text-slate-600'
                "
                :title="stateFor(item.key).done ? 'Configuré' : (stateFor(item.key).hint ?? 'Facultatif')"
            >
                {{ stateFor(item.key).done ? '✓' : stateFor(item.key).required ? '●' : '○' }}
            </span>
        </Link>
    </nav>

    <!-- Rappel visible depuis n'importe quel onglet : sans lui, on peut
         peaufiner ses prestations pendant une heure sans voir que le numéro
         WhatsApp n'est pas connecté. -->
    <div
        v-if="blocking.length"
        class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/40"
    >
        <p class="text-xs font-medium text-slate-700 dark:text-slate-200">
            Votre agent n'est pas encore opérationnel
        </p>
        <ul class="mt-1.5 space-y-1">
            <li v-for="item in blocking" :key="item.href" class="text-xs text-slate-600 dark:text-slate-300">
                <Link :href="item.href" class="font-medium underline">{{ item.label }}</Link>
                <span v-if="item.hint"> — {{ item.hint }}</span>
            </li>
        </ul>
    </div>
</template>
