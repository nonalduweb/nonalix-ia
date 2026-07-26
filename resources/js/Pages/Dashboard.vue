<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    metrics: Object,
    inbox: Object,
    quotas: Object,
    setup: Object,
    recentMessages: Array,
});

// Coût stocké en micro-centimes d'euro (entier) : la division n'intervient
// qu'à l'affichage, jamais dans les calculs.
const aiCost = computed(() =>
    (props.metrics.ai_cost_30d_micros / 100_000_000).toLocaleString('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    }),
);

const setupSteps = computed(() => [
    { done: props.setup.whatsapp_connected, label: 'Connecter un numéro WhatsApp', href: '/settings/whatsapp' },
    { done: props.setup.agent_active, label: 'Activer l\'agent IA', href: '/settings/agent' },
    { done: props.setup.has_knowledge, label: 'Ajouter des documents', href: '/knowledge' },
]);

const setupComplete = computed(() => setupSteps.value.every((s) => s.done));

const quotaPercent = (quota) =>
    quota.limit ? Math.min(100, Math.round((quota.used / quota.limit) * 100)) : 0;
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout>
        <!-- Tant que la configuration est incomplète, c'est la seule chose qui
             compte : un agent non connecté ne répondra à personne. -->
        <section v-if="!setupComplete" class="card mb-8">
            <h2 class="mb-4 text-base font-semibold">Terminer la configuration</h2>
            <ul class="space-y-2">
                <li v-for="step in setupSteps" :key="step.label" class="flex items-center gap-3 text-sm">
                    <span :class="step.done ? 'text-emerald-600' : 'text-slate-400'">
                        {{ step.done ? '✓' : '○' }}
                    </span>
                    <Link :href="step.href" class="hover:underline" :class="step.done && 'text-slate-400 line-through'">
                        {{ step.label }}
                    </Link>
                </li>
            </ul>
        </section>

        <section class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card">
                <p class="text-sm text-slate-500">Conversations (30 j)</p>
                <p class="mt-1 text-2xl font-semibold">{{ metrics.conversations_30d }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Messages reçus / envoyés</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ metrics.messages_in_30d }} / {{ metrics.messages_out_30d }}
                </p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Prospects qualifiés</p>
                <p class="mt-1 text-2xl font-semibold">{{ metrics.leads_qualified_30d }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Coût IA (30 j)</p>
                <p class="mt-1 text-2xl font-semibold">{{ aiCost }}</p>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            <div class="card lg:col-span-2">
                <h2 class="mb-4 text-base font-semibold">Boîte de réception</h2>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <Link href="/conversations?status=open" class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                        <p class="text-2xl font-semibold">{{ inbox.open }}</p>
                        <p class="text-xs text-slate-500">ouvertes</p>
                    </Link>
                    <Link href="/conversations?awaiting=1" class="rounded-lg bg-amber-50 p-4 dark:bg-slate-800">
                        <p class="text-2xl font-semibold text-amber-700">{{ inbox.awaiting }}</p>
                        <p class="text-xs text-slate-500">en attente d'un humain</p>
                    </Link>
                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                        <p class="text-2xl font-semibold">{{ inbox.unassigned }}</p>
                        <p class="text-xs text-slate-500">non attribuées</p>
                    </div>
                </div>

                <p class="mt-6 text-sm text-slate-500">
                    Taux de reprise humaine :
                    <strong :class="metrics.handover_rate > 30 ? 'text-amber-600' : 'text-emerald-600'">
                        {{ metrics.handover_rate }} %
                    </strong>
                    <!-- Au-delà de 30 %, l'agent ne tient pas son rôle. -->
                    <span v-if="metrics.handover_rate > 30" class="ml-1">
                        — revoyez les instructions de l'agent.
                    </span>
                </p>
            </div>

            <div class="card">
                <h2 class="mb-4 text-base font-semibold">Consommation</h2>
                <div v-for="(quota, metric) in quotas" :key="metric" class="mb-3">
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>{{ metric }}</span>
                        <span>{{ quota.used }} / {{ quota.limit ?? '∞' }}</span>
                    </div>
                    <div v-if="quota.limit" class="mt-1 h-1.5 rounded-full bg-slate-200 dark:bg-slate-700">
                        <div
                            class="h-1.5 rounded-full"
                            :class="quotaPercent(quota) >= 80 ? 'bg-amber-500' : 'bg-brand-500'"
                            :style="{ width: quotaPercent(quota) + '%' }"
                        />
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
