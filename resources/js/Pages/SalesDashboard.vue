<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    metrics: Object,
    templates: Object,
});

const activeTab = ref('dashboard');

const TABS = [
    { key: 'dashboard', label: 'Tableau de bord' },
    { key: 'library', label: 'Automatisations' },
];

const installTemplate = (key) => {
    if (confirm('Voulez-vous installer ce modèle sur votre agent actif ? Cela écrasera ses instructions, son nom et ses capacités actuelles.')) {
        // Implémentation unique, partagée avec Configuration › Agent IA : la
        // résolution de l'agent cible porte une vérification d'autorisation.
        router.post('/settings/agent/install-template', { template_key: key });
    }
};

const formatMetric = (val) => val.toLocaleString('fr-FR');

/*
 * Largeur d'une étape de l'entonnoir, bornée à 100 %.
 *
 * La borne n'est pas cosmétique : `leads_today` ne compte que depuis minuit
 * alors que les autres compteurs sont cumulés depuis l'origine. Le rapport
 * dépasse donc 100 % sur tout compte ayant un historique, et la barre sortait
 * de sa gouttière. Borner corrige l'affichage ; l'incohérence des périodes
 * reste à trancher côté contrôleur.
 */
const funnelWidth = (value, base) =>
    `${base > 0 ? Math.min(100, Math.round((value / base) * 100)) : 0}%`;

const funnel = computed(() => [
    { label: 'Prospects qualifiés par l\'IA', value: props.metrics.qualified, base: props.metrics.leads_today, bar: 'bg-brand-500' },
    { label: 'Rendez-vous obtenus', value: props.metrics.appointments, base: props.metrics.qualified, bar: 'bg-violet-500' },
    { label: 'Devis envoyés', value: props.metrics.quotes, base: props.metrics.qualified, bar: 'bg-rose-500' },
    { label: 'Prospects convertis', value: props.metrics.conversions, base: props.metrics.qualified, bar: 'bg-emerald-500' },
]);
</script>

<template>
    <Head title="Ventes & Automation" />

    <AppLayout>
        <PageHeader
            title="Ventes & Automation"
            description="Le retour commercial de vos agents IA, et des scénarios prêts à installer."
            icon="trending"
            tone="emerald"
        >
            <template #actions>
                <!-- Commutateur d'onglets : deux vues d'un même sujet, pas deux
                     pages — un onglet évite de perdre le contexte. -->
                <div class="flex rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                    <button
                        v-for="tab in TABS"
                        :key="tab.key"
                        class="cursor-pointer rounded-md px-3.5 py-1.5 text-sm font-medium transition"
                        :class="
                            activeTab === tab.key
                                ? 'bg-white text-slate-900 shadow-card dark:bg-slate-700 dark:text-white'
                                : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'
                        "
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </template>
        </PageHeader>

        <!-- 1. TABLEAU DE BORD COMMERCIAL -->
        <div v-if="activeTab === 'dashboard'" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    label="Prospects du jour"
                    :value="formatMetric(metrics.leads_today)"
                    icon="users"
                    tone="brand"
                    hint="Nouveaux contacts captés depuis minuit"
                />
                <StatCard
                    label="Qualifiés"
                    :value="formatMetric(metrics.qualified)"
                    icon="checkCircle"
                    tone="brand"
                    hint="Besoin clair identifié par l'agent"
                />
                <StatCard
                    label="Chauds"
                    :value="formatMetric(metrics.hot)"
                    icon="alert"
                    tone="amber"
                    hint="Score d'intérêt supérieur à 75"
                    href="/leads"
                />
                <StatCard
                    label="Rendez-vous obtenus"
                    :value="formatMetric(metrics.appointments)"
                    icon="clock"
                    tone="violet"
                    hint="Planifiés automatiquement"
                />
                <StatCard
                    label="Devis envoyés"
                    :value="formatMetric(metrics.quotes)"
                    icon="document"
                    tone="rose"
                    hint="Chiffrages générés via n8n"
                />
                <StatCard
                    label="Conversions"
                    :value="formatMetric(metrics.conversions)"
                    icon="money"
                    tone="emerald"
                    hint="Prospects gagnés"
                />
            </div>

            <div class="card">
                <h2 class="section-title">Entonnoir de conversion</h2>
                <p class="page-subtitle">
                    Chaque étape est rapportée à la précédente : c'est la déperdition qui se lit, pas le volume.
                </p>

                <div class="mt-6 space-y-5">
                    <div v-for="(step, index) in funnel" :key="step.label">
                        <div class="mb-2 flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">
                                <span class="mr-1.5 text-slate-400 tabular-nums">{{ index + 1 }}.</span>
                                {{ step.label }}
                            </span>
                            <span class="font-semibold tabular-nums text-slate-900 dark:text-white">
                                {{ formatMetric(step.value) }}
                            </span>
                        </div>

                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="step.bar"
                                :style="{ width: funnelWidth(step.value, step.base) }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. BIBLIOTHÈQUE D'AUTOMATISATIONS -->
        <div v-else class="space-y-6">
            <div class="alert-info flex items-start gap-3">
                <span class="tile-brand shrink-0">
                    <Icon name="sparkles" />
                </span>
                <div>
                    <p class="font-semibold text-slate-900 dark:text-white">
                        À quoi servent les modèles d'automatisation ?
                    </p>
                    <p class="mt-1 leading-relaxed">
                        Ils configurent votre agent d'un coup : consignes, profil, messages types et outils
                        pré-ciblés pour votre secteur. Il ne reste qu'à relier l'URL du webhook n8n pour
                        activer les intégrations.
                    </p>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(template, key) in templates"
                    :key="key"
                    class="card flex flex-col justify-between"
                >
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="rounded-md bg-brand-50 px-2 py-0.5 text-[11px] font-semibold tracking-wide text-brand-700 uppercase dark:bg-slate-800 dark:text-brand-100">
                                {{ template.industry }}
                            </span>
                            <span class="truncate text-xs text-slate-400">{{ template.name }}</span>
                        </div>

                        <h3 class="mt-4 text-base font-semibold tracking-tight text-slate-900 dark:text-white">
                            {{ template.title }}
                        </h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ template.description }}</p>

                        <div class="mt-5">
                            <span class="eyebrow">Capacités incluses</span>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span
                                    v-for="tool in template.enabled_tools"
                                    :key="tool"
                                    class="rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ tool }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-slate-100 pt-5 dark:border-slate-800">
                        <button class="btn-primary w-full" @click="installTemplate(key)">
                            Installer sur mon agent
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
