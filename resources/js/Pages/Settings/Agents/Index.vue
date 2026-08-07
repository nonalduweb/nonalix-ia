<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';

const props = defineProps({
    agents: Array,
});

const destroy = (id) => {
    if (confirm("Êtes-vous sûr de vouloir supprimer cet agent ? Cela réorientera les conversations vers un autre agent actif.")) {
        router.delete(`/settings/agent/${id}`);
    }
};
</script>

<template>
    <Head title="Configuration Agents IA" />

    <AppLayout>
        <h1 class="mb-6 text-xl font-semibold">Configuration</h1>
        <SettingsNav />

        <div class="card space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                <div>
                    <h2 class="text-sm font-semibold">Agents IA de l'entreprise</h2>
                    <p class="text-xs text-slate-500 mt-1">Créez et configurez différents profils d'agents (SAV, commercial, accueil) à affecter à vos conversations.</p>
                </div>
                <Link href="/settings/agent/create" class="btn-primary py-1.5 px-3 text-xs font-semibold cursor-pointer">
                    + Ajouter un agent
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-800 text-slate-400 font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Nom de l'agent</th>
                            <th class="py-3 px-4">Modèle IA</th>
                            <th class="py-3 px-4">Rôle / Persona</th>
                            <th class="py-3 px-4">État</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        <tr v-for="agent in agents" :key="agent.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition">
                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-slate-100">
                                {{ agent.name }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                    {{ agent.provider }} · {{ agent.model }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-500">
                                {{ agent.persona || 'Non défini' }}
                            </td>
                            <td class="py-3 px-4">
                                <span 
                                    class="rounded-full px-2 py-0.5 text-[9px] font-semibold cursor-default"
                                    :class="agent.is_active 
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' 
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                                    :title="agent.is_active ? 'Cet agent prendra en charge les conversations par défaut' : 'Inactif'"
                                >
                                    {{ agent.is_active ? 'Par défaut (Actif)' : 'Inactif' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right space-x-3 whitespace-nowrap">
                                <Link :href="`/settings/agent/${agent.id}/edit`" class="text-brand-600 hover:text-brand-700 font-semibold cursor-pointer">
                                    Configurer
                                </Link>
                                <button 
                                    v-if="agents.length > 1" 
                                    @click="destroy(agent.id)" 
                                    class="text-red-500 hover:text-red-600 font-semibold cursor-pointer"
                                >
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!agents.length">
                            <td colspan="5" class="py-12 text-center text-slate-400 italic">
                                Aucun agent n'est actuellement configuré.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
