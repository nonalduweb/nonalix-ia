<script setup>
import { ref, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const page = usePage();
const isOpen = ref(false);

const setup = computed(() => page.props.setup ?? null);
const currentUrl = computed(() => page.url);

// Étapes d'onboarding suivies par le copilote
const steps = computed(() => {
    if (!setup.value) return [];
    
    return [
        { 
            name: 'Informations Entreprise', 
            path: '/settings/business', 
            done: setup.value.business?.done ?? false, 
            required: setup.value.business?.required ?? true,
            hint: 'Saisir les horaires et les détails de base.'
        },
        { 
            name: 'Configuration de l\'Agent IA', 
            path: '/settings/agent', 
            done: setup.value.agent?.done ?? false, 
            required: setup.value.agent?.required ?? true,
            hint: 'Créer un agent et définir ses instructions.'
        },
        { 
            name: 'Connexion WhatsApp', 
            path: '/settings/whatsapp', 
            done: setup.value.whatsapp?.done ?? false, 
            required: setup.value.whatsapp?.required ?? true,
            hint: 'Lier votre numéro de téléphone professionnel.'
        },
        { 
            name: 'Prestations & Services', 
            path: '/settings/services', 
            done: setup.value.services?.done ?? false, 
            required: setup.value.services?.required ?? false,
            hint: 'Lister vos offres et prix.'
        },
        { 
            name: 'Questions Fréquentes (FAQ)', 
            path: '/settings/faqs', 
            done: setup.value.faqs?.done ?? false, 
            required: setup.value.faqs?.required ?? false,
            hint: 'Ajouter les réponses aux questions répétitives.'
        },
        { 
            name: 'Widget Site Web', 
            path: '/settings/widget', 
            done: !!page.props.tenant?.active_agent?.settings?.theme_color, 
            required: false,
            hint: 'Personnaliser et intégrer la bulle de chat.'
        }
    ];
});

// Calcul du pourcentage d'onboarding
const completionPercentage = computed(() => {
    if (steps.value.length === 0) return 0;
    const completed = steps.value.filter(s => s.done).length;
    return Math.round((completed / steps.value.length) * 100);
});

// Conseils contextuels selon la page actuelle
const contextAdvice = computed(() => {
    const url = currentUrl.value;
    
    if (url.startsWith('/settings/business')) {
        return {
            title: '🏢 Conseils du Copilote : Profil & Horaires',
            text: 'Remplissez soigneusement votre description d\'activité. C\'est le socle sur lequel l\'IA s\'appuie pour savoir qui vous êtes. Définissez également des horaires réels : si l\'agent répond hors horaires, il informera le client de votre fermeture et proposera d\'enregistrer sa demande.'
        };
    }
    if (url.startsWith('/settings/agent')) {
        return {
            title: '🤖 Conseils du Copilote : Personnalité & Outils',
            text: 'Donnez un prénom humain à votre agent pour créer de la proximité. Choisissez une température faible (ex: 0.2) pour éviter les inventions de prix. Cochez les capacités nécessaires (Horaires, Services) et activez l\'escalade vers un humain sur mots-clés.'
        };
    }
    if (url.startsWith('/settings/whatsapp')) {
        return {
            title: '💬 Conseils du Copilote : Canal WhatsApp',
            text: 'WhatsApp est le canal roi. Suivez les étapes de connexion de votre Meta Business Suite. Une fois configuré, utilisez le bouton d\'envoi de test en bas pour vérifier le bon fonctionnement de votre webhook.'
        };
    }
    if (url.startsWith('/settings/widget')) {
        return {
            title: '⚙️ Conseils du Copilote : Widget Web',
            text: 'Choisissez une couleur de thème cohérente avec votre logo. Copiez le code HTML fourni et collez-le dans le footer de votre site web pour activer la messagerie instantanée instantanément. Les conversations apparaîtront dans votre Inbox comme pour WhatsApp !'
        };
    }
    if (url.startsWith('/settings/billing')) {
        return {
            title: '💳 Conseils du Copilote : Facturation',
            text: 'Suivez vos limites mensuelles de messages. Si vos quotas approchent de 100%, effectuez un paiement mobile Wave, MTN ou Moov et insérez le code d\'accès reçu par WhatsApp/mail pour recharger immédiatement votre compte sans interruption.'
        };
    }
    if (url.startsWith('/settings/services')) {
        return {
            title: '📁 Conseils du Copilote : Prestations',
            text: 'Listez ici vos produits ou services avec leur nom, description et tarif. L\'agent IA consultera cette liste pour renseigner les clients sur ce que vous proposez et générer des propositions précises.'
        };
    }
    if (url.startsWith('/settings/faqs')) {
        return {
            title: '❓ Conseils du Copilote : Questions fréquentes',
            text: 'Saisissez les questions récurrentes (délais de livraison, retours, moyens de paiement acceptés). Cela constitue la base de connaissances directe de l\'agent pour répondre de manière fiable sans surcharger vos conseillers.'
        };
    }
    if (url.startsWith('/sales')) {
        return {
            title: '📈 Conseils du Copilote : Ventes & ROI',
            text: 'Suivez ici les performances commerciales de votre agent. Si vous manquez de rendez-vous ou de devis, vérifiez que les outils correspondants sont activés dans la configuration de votre agent et connectez votre webhook n8n.'
        };
    }

    return {
        title: '👋 Bienvenue sur Nonalix IA !',
        text: 'Je suis votre copilote de configuration. Suivez la check-list ci-dessous pour rendre votre agent IA opérationnel et le connecter à vos canaux (WhatsApp, site web).'
    };
});
</script>

<template>
    <div class="nonalix-copilot-container">
        <!-- Bouton flottant -->
        <button 
            @click="isOpen = !isOpen"
            class="fixed bottom-6 left-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-lg hover:bg-indigo-700 hover:scale-105 transition active:scale-95 cursor-pointer"
            :class="isOpen ? 'bg-slate-800 hover:bg-slate-900' : ''"
            title="Copilote de configuration"
        >
            <!-- Icône IA Bot -->
            <svg v-if="!isOpen" viewBox="0 0 24 24" class="h-6 w-6 fill-current animate-pulse">
                <path d="M12 2a10 10 0 0 1 10 10c0 5.523-4.477 10-10 10S2 17.523 2 12A10 10 0 0 1 12 2zm0 2a8 8 0 0 0-8 8c0 4.418 3.582 8 8 8s8-3.582 8-8a8 8 0 0 0-8-8zm1 11h-2v-2h2v2zm0-4h-2V7h2v4z"/>
            </svg>
            <span v-else class="text-xl font-bold">&times;</span>

            <!-- Badge pourcentage -->
            <span 
                v-if="!isOpen && completionPercentage < 100" 
                class="absolute -top-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-white border-2 border-white dark:border-slate-950"
            >
                {{ completionPercentage }}%
            </span>
        </button>

        <!-- Sidebar d'aide Copilote -->
        <div 
            class="fixed inset-y-0 left-0 z-40 w-80 transform bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 shadow-2xl transition duration-300 flex flex-col justify-between"
            :class="isOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex-1 overflow-y-auto p-5 space-y-6">
                <!-- En-tête -->
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">Copilote de Configuration</h2>
                    <p class="text-[10px] text-slate-400 mt-0.5">Votre assistant pas-à-pas pour paramétrer Nonalix.</p>
                </div>

                <!-- Barre d'avancement -->
                <div class="bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-lg border border-slate-100 dark:border-slate-800/60">
                    <div class="flex justify-between text-xs font-semibold mb-1.5">
                        <span>Avancement</span>
                        <span class="text-indigo-600 dark:text-indigo-400">{{ completionPercentage }}%</span>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-2">
                        <div 
                            class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                            :style="`width: ${completionPercentage}%`"
                        />
                    </div>
                    <span v-if="completionPercentage === 100" class="text-[9px] text-emerald-600 font-bold mt-1.5 block">
                        🎉 Configuration terminée et opérationnelle !
                    </span>
                </div>

                <!-- Conseil Contextuel -->
                <div class="bg-indigo-50/50 border border-indigo-100 rounded-lg p-3.5 dark:bg-indigo-950/20 dark:border-indigo-900/40 space-y-1.5">
                    <h4 class="text-xs font-bold text-indigo-950 dark:text-indigo-200">{{ contextAdvice.title }}</h4>
                    <p class="text-[10.5px] leading-relaxed text-indigo-900/80 dark:text-indigo-300/80">{{ contextAdvice.text }}</p>
                </div>

                <!-- Checklist des étapes -->
                <div class="space-y-3">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Étapes de configuration</h4>
                    <div class="space-y-2">
                        <div 
                            v-for="step in steps" 
                            :key="step.path"
                            class="flex items-start gap-2.5 p-2 rounded-lg transition"
                            :class="currentUrl.startsWith(step.path) ? 'bg-slate-50 dark:bg-slate-800/40' : ''"
                        >
                            <!-- Statut Checkbox -->
                            <span 
                                class="h-4 w-4 shrink-0 rounded border flex items-center justify-center text-[10px] font-bold"
                                :class="step.done 
                                    ? 'bg-emerald-500 border-emerald-500 text-white' 
                                    : 'border-slate-300 text-transparent dark:border-slate-700'"
                            >
                                ✓
                            </span>
                            
                            <div class="text-[11px]">
                                <Link 
                                    :href="step.path" 
                                    class="font-semibold block hover:underline"
                                    :class="step.done 
                                        ? 'text-slate-700 dark:text-slate-300' 
                                        : 'text-indigo-600 dark:text-indigo-400'"
                                >
                                    {{ step.name }}
                                    <span v-if="step.required && !step.done" class="text-red-500 ml-0.5">*</span>
                                </Link>
                                <span class="text-[9.5px] text-slate-400 block mt-0.5 leading-normal">{{ step.hint }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/20 text-center">
                <p class="text-[9px] text-slate-400">Besoin d'aide ? Contactez notre support commercial.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.nonalix-copilot-container {
    --indigo-600: oklch(0.55 0.20 255);
}
</style>
