<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    tenantId: String,
    agent: Object, // nullable
    baseUrl: String,
});

const form = useForm({
    theme_color: props.agent?.settings?.theme_color ?? '#2563eb',
});

const embedCode = computed(() => {
    return `<script src="${props.baseUrl}/widget.js" data-tenant="${props.tenantId}"><\/script>`;
});

const copied = ref(false);

const copyEmbedCode = () => {
    navigator.clipboard.writeText(embedCode.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const submit = () => {
    form.put('/settings/widget', {
        preserveScroll: true,
    });
};

const PRESET_COLORS = [
    { name: 'Bleu Royal', value: '#2563eb' },
    { name: 'Vert Forêt', value: '#059669' },
    { name: 'Vert WhatsApp', value: '#075e54' },
    { name: 'Violet Électrique', value: '#7c3aed' },
    { name: 'Rouge Corail', value: '#e11d48' },
    { name: 'Orange Solaire', value: '#ea580c' },
    { name: 'Slate Neutre', value: '#475569' },
    { name: 'Noir Premium', value: '#0f172a' },
];
</script>

<template>
    <Head title="Configuration Widget Chat" />

    <AppLayout>
        <PageHeader
            title="Widget site web"
            description="La bulle de discussion installée sur votre site, et son apparence."
            icon="chat"
            tone="brand"
        />

        <SettingsNav />

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Formulaire de réglage & Code d'intégration -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Code d'intégration -->
                <section class="card space-y-4">
                    <h2 class="text-sm font-semibold">Intégration du Widget</h2>
                    <p class="text-xs text-slate-500 leading-normal">
                        Copiez-collez ce code juste avant la balise <code>&lt;/body&gt;</code> de votre site web (WordPress, Shopify, Webflow ou site sur-mesure) pour y afficher le chat en direct.
                    </p>

                    <div class="relative bg-slate-950 p-4 rounded-lg font-mono text-xs text-slate-300 break-all select-all dark:bg-slate-900 border border-slate-800">
                        <code>{{ embedCode }}</code>
                        
                        <button 
                            @click="copyEmbedCode"
                            class="absolute top-2 right-2 bg-slate-800 hover:bg-slate-700 text-white rounded px-2.5 py-1 text-[10px] font-semibold transition cursor-pointer"
                        >
                            {{ copied ? 'Copié !' : 'Copier' }}
                        </button>
                    </div>
                </section>

                <!-- Paramètres graphiques -->
                <section class="card space-y-5">
                    <h2 class="text-sm font-semibold">Paramètres visuels</h2>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <span class="label mb-2 block">Couleur du thème</span>
                            
                            <!-- Couleurs prédéfinies -->
                            <div class="grid grid-cols-4 gap-2 mb-4">
                                <button
                                    v-for="color in PRESET_COLORS"
                                    :key="color.value"
                                    type="button"
                                    @click="form.theme_color = color.value"
                                    class="flex items-center gap-1.5 rounded-lg border p-2 text-left text-xs transition hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer"
                                    :class="form.theme_color === color.value ? 'border-brand-600 ring-2 ring-brand-100 dark:border-brand-500' : 'border-slate-200 dark:border-slate-800'"
                                >
                                    <span 
                                        class="h-3 w-3 shrink-0 rounded-full border border-black/10"
                                        :style="`background-color: ${color.value}`"
                                    />
                                    <span class="truncate font-medium text-slate-700 dark:text-slate-300">{{ color.name }}</span>
                                </button>
                            </div>

                            <!-- Sélecteur personnalisé -->
                            <div class="flex items-center gap-3">
                                <input 
                                    v-model="form.theme_color" 
                                    type="color" 
                                    class="h-8 w-12 cursor-pointer rounded border border-slate-200 bg-transparent p-0"
                                />
                                <input 
                                    v-model="form.theme_color" 
                                    type="text" 
                                    class="input py-1.5 px-3 max-w-32 font-mono uppercase text-xs" 
                                    pattern="^#[a-fA-F0-9]{6}$"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button 
                                type="submit" 
                                class="btn-primary py-2 px-5 text-xs font-semibold cursor-pointer" 
                                :disabled="form.processing || !agent"
                            >
                                {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                            </button>
                        </div>

                        <p v-if="!agent" class="text-xs text-red-500 font-medium">
                            ⚠️ Aucun Agent IA actif n'a été détecté. Créez un agent à l'onglet "Agent IA" pour débloquer l'enregistrement.
                        </p>
                    </form>
                </section>
            </div>

            <!-- Preview Sandbox (Simulateur) -->
            <div class="space-y-4">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Aperçu en direct</span>
                
                <div class="rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/50 p-4 h-[500px] flex flex-col justify-between relative overflow-hidden">
                    <span class="text-[10px] text-slate-400 uppercase font-semibold absolute top-3 left-4">Simulateur Site Web</span>
                    
                    <!-- Simuler la bulle de chat ouverte -->
                    <div class="rounded-xl bg-white border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col h-[400px] overflow-hidden mt-6">
                        <!-- En-tête -->
                        <div class="p-3 text-white flex items-center justify-between transition-colors duration-300" :style="`background-color: ${form.theme_color}`">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold">{{ agent?.name || 'Léon - Maître d\'Hôtel' }}</span>
                                <span class="text-[9px] opacity-90 flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" /> en ligne
                                </span>
                            </div>
                            <span class="text-xs font-bold">&times;</span>
                        </div>

                        <!-- Boîte de messages -->
                        <div class="flex-1 bg-slate-50 dark:bg-slate-950 p-3 space-y-3 overflow-y-auto text-[11px]">
                            <!-- Message Agent -->
                            <div class="max-w-[85%] bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 rounded-lg p-2.5 text-slate-700 dark:text-slate-300 rounded-bl-none shadow-xs">
                                {{ agent?.greeting_message || 'Bonjour ! Prêt pour votre réservation ? Comment puis-je vous aider ?' }}
                            </div>

                            <!-- Message Visiteur -->
                            <div class="max-w-[85%] text-white rounded-lg p-2.5 rounded-br-none ml-auto text-right shadow-xs transition-colors duration-300" :style="`background-color: ${form.theme_color}`">
                                Je voudrais réserver une table pour ce soir.
                            </div>
                        </div>

                        <!-- Pied de page -->
                        <div class="p-2 border-t border-slate-100 dark:border-slate-800/80 bg-white dark:bg-slate-900 flex gap-2">
                            <input type="text" class="flex-1 border rounded-full px-3 py-1.5 text-[10px] outline-none" placeholder="Écrire..." disabled />
                            <button class="h-7 w-7 rounded-full text-white flex items-center justify-center flex-shrink-0 transition-colors duration-300" :style="`background-color: ${form.theme_color}`">
                                <svg viewBox="0 0 24 24" class="h-3 w-3 fill-current"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Bulle flottante -->
                    <div class="h-12 w-12 rounded-full shadow-md text-white flex items-center justify-center self-end transition-colors duration-300" :style="`background-color: ${form.theme_color}`">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
