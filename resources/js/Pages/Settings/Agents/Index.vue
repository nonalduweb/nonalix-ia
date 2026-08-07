<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';

const props = defineProps({
    agents: Array,
    templates: Object,
    hasActiveAgent: Boolean,
});

const destroy = (id) => {
    if (confirm("Êtes-vous sûr de vouloir supprimer cet agent ? Cela réorientera les conversations vers un autre agent actif.")) {
        router.delete(`/settings/agent/${id}`);
    }
};

// Tant qu'aucun agent n'est actif, la galerie de métiers passe devant : c'est le
// seul écran qu'un client non technique sait remplir tout seul.
const showTemplates = ref(false);
const onboarding = computed(() => !props.hasActiveAgent || showTemplates.value);

const installing = ref(null);

const install = (key, title) => {
    if (!confirm(`Installer le modèle « ${title} » ? Le nom, les instructions et les capacités de votre agent seront remplacés.`)) {
        return;
    }

    installing.value = key;

    router.post('/settings/agent/install-template', { template_key: key }, {
        onFinish: () => (installing.value = null),
    });
};
</script>

<template>
    <Head title="Configuration Agents IA" />

    <AppLayout>
        <h1 class="mb-6 text-xl font-semibold">Configuration</h1>
        <SettingsNav />

        <!-- Galerie de métiers : premier écran tant qu'aucun agent n'est actif -->
        <div v-if="onboarding" class="card mb-6 space-y-5 border-brand-200 dark:border-brand-900">
            <div>
                <h2 class="text-sm font-semibold">Quel est votre métier ?</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Choisissez le profil le plus proche : votre agent sera configuré d'un clic, avec ses instructions,
                    son message d'accueil et ses capacités. Vous pourrez tout relire et l'essayer juste après.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="(template, key) in templates"
                    :key="key"
                    type="button"
                    class="flex h-full flex-col rounded-xl border border-slate-200 p-4 text-left transition hover:border-brand-500 hover:shadow-sm disabled:opacity-50 dark:border-slate-800 cursor-pointer"
                    :disabled="installing !== null"
                    @click="install(key, template.title)"
                >
                    <span class="text-[10px] font-bold uppercase tracking-wider text-brand-600">{{ template.industry }}</span>
                    <span class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ template.title }}</span>
                    <span class="mt-1.5 flex-1 text-xs leading-normal text-slate-500">{{ template.description }}</span>
                    <span class="mt-3 text-xs font-semibold text-brand-600">
                        {{ installing === key ? 'Installation…' : 'Choisir ce profil →' }}
                    </span>
                </button>

                <!-- Aucun profil ne correspond : la page blanche reste accessible -->
                <Link
                    href="/settings/agent/create"
                    class="flex h-full flex-col justify-center rounded-xl border border-dashed border-slate-300 p-4 text-left transition hover:border-slate-400 dark:border-slate-700 cursor-pointer"
                >
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Autre métier</span>
                    <span class="mt-1.5 text-xs leading-normal text-slate-500">
                        Partir d'une page blanche et rédiger vous-même les instructions de votre agent.
                    </span>
                </Link>
            </div>
        </div>

        <div class="card space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                <div>
                    <h2 class="text-sm font-semibold">Agents IA de l'entreprise</h2>
                    <p class="text-xs text-slate-500 mt-1">Créez et configurez différents profils d'agents (SAV, commercial, accueil) à affecter à vos conversations.</p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <button
                        v-if="hasActiveAgent && !showTemplates"
                        type="button"
                        class="text-xs font-semibold text-slate-500 hover:text-slate-700 transition cursor-pointer"
                        @click="showTemplates = true"
                    >
                        Partir d'un modèle métier
                    </button>
                    <Link href="/settings/agent/create" class="btn-primary py-1.5 px-3 text-xs font-semibold cursor-pointer">
                        + Ajouter un agent
                    </Link>
                </div>
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
