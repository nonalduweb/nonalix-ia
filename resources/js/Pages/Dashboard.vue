<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import Icon from '@/Components/Icon.vue';

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

const remainingSteps = computed(() => setupSteps.value.filter((s) => !s.done).length);

const quotaPercent = (quota) =>
    quota.limit ? Math.min(100, Math.round((quota.used / quota.limit) * 100)) : 0;

// Intitulés lisibles : les clés brutes (`messages_sent`) venaient de la base
// et s'affichaient telles quelles.
const QUOTA_LABELS = {
    messages_sent: 'Messages envoyés',
    ai_requests: 'Requêtes IA',
    documents_stored: 'Documents stockés',
};
</script>

<template>
    <Head title="Tableau de bord" />

    <AppLayout>
        <PageHeader
            title="Tableau de bord"
            description="L'activité de votre agent sur les trente derniers jours."
            icon="home"
            tone="brand"
        />

        <!-- Tant que la configuration est incomplète, c'est la seule chose qui
             compte : un agent non connecté ne répondra à personne. D'où la
             carte en tête, et l'accent ambre plutôt qu'un gris discret. -->
        <section v-if="!setupComplete" class="card mb-8 border-amber-200/70 bg-amber-50/40 dark:border-amber-900/60 dark:bg-amber-950/20">
            <div class="flex items-start gap-4">
                <span class="tile-amber">
                    <Icon name="alert" />
                </span>

                <div class="flex-1">
                    <h2 class="section-title">Terminer la configuration</h2>
                    <p class="page-subtitle">
                        {{ remainingSteps }} étape{{ remainingSteps > 1 ? 's' : '' }} avant que votre agent
                        puisse répondre à vos clients.
                    </p>

                    <ul class="mt-4 space-y-1">
                        <li v-for="step in setupSteps" :key="step.label">
                            <Link
                                :href="step.href"
                                class="group flex items-center gap-3 rounded-lg px-2 py-2 text-sm transition hover:bg-white/70 dark:hover:bg-slate-900/50"
                            >
                                <Icon
                                    :name="step.done ? 'checkCircle' : 'circle'"
                                    size="sm"
                                    :class="step.done ? 'text-emerald-600' : 'text-slate-400'"
                                />
                                <span :class="step.done ? 'text-slate-400 line-through' : 'font-medium text-slate-700 dark:text-slate-200'">
                                    {{ step.label }}
                                </span>
                                <Icon
                                    v-if="!step.done"
                                    name="chevronRight"
                                    size="sm"
                                    class="ml-auto text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500"
                                />
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                label="Conversations"
                :value="metrics.conversations_30d"
                icon="chat"
                tone="brand"
                hint="30 derniers jours"
            />
            <StatCard
                label="Messages reçus / envoyés"
                :value="`${metrics.messages_in_30d} / ${metrics.messages_out_30d}`"
                icon="inbox"
                tone="violet"
                hint="30 derniers jours"
            />
            <StatCard
                label="Prospects qualifiés"
                :value="metrics.leads_qualified_30d"
                icon="target"
                tone="emerald"
                hint="30 derniers jours"
                href="/leads"
            />
            <StatCard
                label="Coût IA"
                :value="aiCost"
                icon="sparkles"
                tone="amber"
                hint="30 derniers jours"
            />
        </section>

        <section class="grid gap-5 lg:grid-cols-3">
            <div class="card lg:col-span-2">
                <h2 class="section-title mb-5">Boîte de réception</h2>

                <div class="grid gap-3 sm:grid-cols-3">
                    <Link
                        href="/conversations?status=open"
                        class="card-link rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-800/60"
                    >
                        <p class="text-2xl font-semibold tabular-nums">{{ inbox.open }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">ouvertes</p>
                    </Link>

                    <Link
                        href="/conversations?awaiting=1"
                        class="card-link rounded-xl bg-amber-50 p-4 text-center dark:bg-amber-950/25"
                    >
                        <p class="text-2xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">
                            {{ inbox.awaiting }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">en attente d'un humain</p>
                    </Link>

                    <!-- Inerte : aucun filtre ne correspond aux conversations
                         non attribuées, un lien mènerait à une liste complète. -->
                    <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-slate-800/60">
                        <p class="text-2xl font-semibold tabular-nums">{{ inbox.unassigned }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">non attribuées</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-slate-100 pt-5 text-sm dark:border-slate-800">
                    <span class="text-slate-500">Taux de reprise humaine :</span>
                    <strong :class="metrics.handover_rate > 30 ? 'text-amber-600' : 'text-emerald-600'">
                        {{ metrics.handover_rate }} %
                    </strong>
                    <!-- Au-delà de 30 %, l'agent ne tient pas son rôle. -->
                    <span v-if="metrics.handover_rate > 30" class="text-slate-500">
                        — <Link href="/settings/agent" class="underline underline-offset-2">revoyez les instructions de l'agent</Link>.
                    </span>
                </div>
            </div>

            <div class="card">
                <h2 class="section-title mb-5">Consommation</h2>

                <div v-for="(quota, metric) in quotas" :key="metric" class="mb-4 last:mb-0">
                    <div class="flex justify-between text-xs">
                        <span class="font-medium text-slate-600 dark:text-slate-300">
                            {{ QUOTA_LABELS[metric] ?? metric }}
                        </span>
                        <span class="tabular-nums text-slate-500">
                            {{ quota.used.toLocaleString('fr-FR') }} /
                            {{ quota.limit ? quota.limit.toLocaleString('fr-FR') : '∞' }}
                        </span>
                    </div>

                    <div v-if="quota.limit" class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="quotaPercent(quota) >= 80 ? 'bg-amber-500' : 'bg-brand-500'"
                            :style="{ width: quotaPercent(quota) + '%' }"
                        />
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
