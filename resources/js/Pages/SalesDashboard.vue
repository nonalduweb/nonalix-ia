<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    metrics: Object,
    templates: Object,
});

const activeTab = ref('dashboard');

const installTemplate = (key) => {
    if (confirm(`Voulez-vous installer ce modèle sur votre agent actif ? Cela écrasera ses instructions, son nom et ses capacités actuelles.`)) {
        // Implémentation unique, partagée avec Configuration › Agent IA : la
        // résolution de l'agent cible porte une vérification d'autorisation.
        router.post('/settings/agent/install-template', { template_key: key }, {
            onSuccess: () => {
                // Flash success will be handled by Layout
            }
        });
    }
};

const formatMetric = (val) => val.toLocaleString('fr-FR');
</script>

<template>
    <Head title="Tableau de bord Commercial" />

    <AppLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Ventes & Automation</h1>
                <p class="text-xs text-slate-500 mt-0.5">Suivez le retour financier de vos agents IA et installez des scénarios clés en main.</p>
            </div>
            
            <!-- Commutateur d'onglets -->
            <div class="flex rounded-lg bg-slate-100 p-0.5 dark:bg-slate-800 self-start">
                <button
                    @click="activeTab = 'dashboard'"
                    class="rounded-md px-3.5 py-1.5 text-xs font-semibold transition cursor-pointer"
                    :class="activeTab === 'dashboard' ? 'bg-white shadow-xs text-slate-900 dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                >
                    Tableau de bord Ventes
                </button>
                <button
                    @click="activeTab = 'library'"
                    class="rounded-md px-3.5 py-1.5 text-xs font-semibold transition cursor-pointer"
                    :class="activeTab === 'library' ? 'bg-white shadow-xs text-slate-900 dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                >
                    Bibliothèque d'automatisations
                </button>
            </div>
        </div>

        <!-- 1. ONGLET TABLEAU DE BORD COMMERCIAL -->
        <div v-if="activeTab === 'dashboard'" class="space-y-6">
            
            <!-- Grille de Métriques ROI -->
            <div class="grid gap-4 grid-cols-2 lg:grid-cols-6">
                <!-- Prospects du jour -->
                <div class="card p-4 flex flex-col justify-between border-l-4 border-blue-500 bg-white dark:bg-slate-900 shadow-xs relative overflow-hidden group hover:scale-[1.02] transition duration-200">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Prospects (Auj.)</span>
                    <div class="flex items-baseline gap-1.5 mt-2">
                        <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ formatMetric(metrics.leads_today) }}</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Nouveaux contacts captés</p>
                </div>

                <!-- Qualifiés -->
                <div class="card p-4 flex flex-col justify-between border-l-4 border-indigo-500 bg-white dark:bg-slate-900 shadow-xs relative overflow-hidden group hover:scale-[1.02] transition duration-200">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Qualifiés</span>
                    <div class="flex items-baseline gap-1.5 mt-2">
                        <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ formatMetric(metrics.qualified) }}</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Besoins clairs identifiés</p>
                </div>

                <!-- Chauds -->
                <div class="card p-4 flex flex-col justify-between border-l-4 border-amber-500 bg-white dark:bg-slate-900 shadow-xs relative overflow-hidden group hover:scale-[1.02] transition duration-200">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Chauds (Score >= 75)</span>
                    <div class="flex items-baseline gap-1.5 mt-2">
                        <span class="text-2xl font-extrabold text-amber-600 dark:text-amber-400">{{ formatMetric(metrics.hot) }}</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Maturité d'achat élevée</p>
                </div>

                <!-- RDV Obtenus -->
                <div class="card p-4 flex flex-col justify-between border-l-4 border-violet-500 bg-white dark:bg-slate-900 shadow-xs relative overflow-hidden group hover:scale-[1.02] transition duration-200">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">RDV Obtenus</span>
                    <div class="flex items-baseline gap-1.5 mt-2">
                        <span class="text-2xl font-extrabold text-violet-600 dark:text-violet-400">{{ formatMetric(metrics.appointments) }}</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Planifiés automatiquement</p>
                </div>

                <!-- Devis envoyés -->
                <div class="card p-4 flex flex-col justify-between border-l-4 border-pink-500 bg-white dark:bg-slate-900 shadow-xs relative overflow-hidden group hover:scale-[1.02] transition duration-200">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Devis Envoyés</span>
                    <div class="flex items-baseline gap-1.5 mt-2">
                        <span class="text-2xl font-extrabold text-pink-600 dark:text-pink-400">{{ formatMetric(metrics.quotes) }}</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Chiffrages générés via n8n</p>
                </div>

                <!-- Conversions (Won) -->
                <div class="card p-4 flex flex-col justify-between border-l-4 border-emerald-500 bg-white dark:bg-slate-900 shadow-xs relative overflow-hidden group hover:scale-[1.02] transition duration-200">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Conversions</span>
                    <div class="flex items-baseline gap-1.5 mt-2">
                        <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ formatMetric(metrics.conversions) }}</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Prospects convertis / gagnés</p>
                </div>
            </div>

            <!-- Entonnoir graphique des conversions -->
            <div class="card p-5 space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Entonnoir de Conversion Commercial</h3>
                <div class="space-y-4 pt-2">
                    <!-- Prospects qualifiés -->
                    <div>
                        <div class="flex justify-between text-xs font-semibold mb-1">
                            <span>1. Prospects Qualifiés (IA)</span>
                            <span>{{ metrics.qualified }}</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-3 dark:bg-slate-800">
                            <div class="bg-indigo-500 h-3 rounded-full transition-all duration-500" :style="`width: ${metrics.leads_today > 0 ? (metrics.qualified / metrics.leads_today * 100) : 100}%`" />
                        </div>
                    </div>

                    <!-- RDV Obtenus -->
                    <div>
                        <div class="flex justify-between text-xs font-semibold mb-1">
                            <span>2. Actions clés : Rendez-vous obtenus</span>
                            <span>{{ metrics.appointments }}</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-3 dark:bg-slate-800">
                            <div class="bg-violet-500 h-3 rounded-full transition-all duration-500" :style="`width: ${metrics.qualified > 0 ? (metrics.appointments / metrics.qualified * 100) : 0}%`" />
                        </div>
                    </div>

                    <!-- Devis envoyés -->
                    <div>
                        <div class="flex justify-between text-xs font-semibold mb-1">
                            <span>3. Actions clés : Devis envoyés</span>
                            <span>{{ metrics.quotes }}</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-3 dark:bg-slate-800">
                            <div class="bg-pink-500 h-3 rounded-full transition-all duration-500" :style="`width: ${metrics.qualified > 0 ? (metrics.quotes / metrics.qualified * 100) : 0}%`" />
                        </div>
                    </div>

                    <!-- Conversions -->
                    <div>
                        <div class="flex justify-between text-xs font-semibold mb-1">
                            <span>4. Prospects convertis (Gagnés)</span>
                            <span>{{ metrics.conversions }}</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-3 dark:bg-slate-800">
                            <div class="bg-emerald-500 h-3 rounded-full transition-all duration-500" :style="`width: ${metrics.qualified > 0 ? (metrics.conversions / metrics.qualified * 100) : 0}%`" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. ONGLET BIBLIOTHEQUE D'AUTOMATISATIONS -->
        <div v-else class="space-y-6">
            <div class="bg-brand-50 border border-brand-200 rounded-xl p-4 dark:bg-slate-900/50 dark:border-slate-800 flex gap-3">
                <span class="text-xl">💡</span>
                <div class="text-xs text-brand-800 dark:text-slate-300">
                    <p class="font-semibold">À quoi servent les modèles d'automatisation ?</p>
                    <p class="mt-1 leading-relaxed">
                        Ils configurent instantanément votre agent avec des consignes, un profil (nom, rôle), des messages types et des outils pré-ciblés pour votre secteur d'activité. Il vous suffira de relier l'URL du webhook n8n pour faire fonctionner les intégrations !
                    </p>
                </div>
            </div>

            <!-- Grille des modèles d'automatisations -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div 
                    v-for="(template, key) in templates" 
                    :key="key" 
                    class="card flex flex-col justify-between border border-slate-100 dark:border-slate-800 hover:shadow-md transition duration-200"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="rounded bg-brand-50 px-2.5 py-0.5 text-[10px] font-bold text-brand-700 uppercase tracking-wider dark:bg-slate-800 dark:text-brand-300">
                                {{ template.industry }}
                            </span>
                            <span class="text-xs text-slate-400">Agent : {{ template.name }}</span>
                        </div>
                        
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white">{{ template.title }}</h3>
                        <p class="text-xs text-slate-500 leading-normal">{{ template.description }}</p>
                        
                        <!-- Liste des outils prévus -->
                        <div class="pt-2">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                                Capacités incluses :
                            </span>
                            <div class="flex flex-wrap gap-1">
                                <span 
                                    v-for="tool in template.enabled_tools" 
                                    :key="tool"
                                    class="rounded bg-slate-50 border border-slate-100 px-1.5 py-0.5 font-mono text-[9px] dark:bg-slate-800 dark:border-slate-700 text-slate-600 dark:text-slate-300"
                                >
                                    {{ tool }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-5 border-t border-slate-100 dark:border-slate-800/80 mt-4">
                        <button 
                            @click="installTemplate(key)"
                            class="btn-primary w-full py-2 text-xs font-semibold cursor-pointer text-center"
                        >
                            Installer sur mon agent
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
