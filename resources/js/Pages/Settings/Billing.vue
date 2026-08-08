<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';

const props = defineProps({
    plans: Array,
    subscription: Object, // nullable
    usage: Object,
    tenantStatus: String,
    trialEndsAt: String,
});

const form = useForm({
    code: '',
});

const submit = () => {
    form.post('/settings/billing/redeem', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('code');
        },
    });
};

const formatDate = (iso) => {
    return iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';
};

const formatPrice = (cents) => {
    return (cents / 100).toLocaleString('fr-FR') + ' F CFA';
};

const getStatusBadgeClass = (status) => {
    return {
        active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
        trial: 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
        suspended: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
        past_due: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
    }[status] || 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300';
};

const getStatusLabel = (status) => {
    return {
        active: 'Abonnement Actif',
        trial: 'Période d\'essai',
        suspended: 'Compte Suspendu',
        past_due: 'Paiement en retard',
    }[status] || status;
};
</script>

<template>
    <Head title="Facturation et Abonnements" />

    <AppLayout>
        <h1 class="mb-6 text-xl font-semibold">Configuration</h1>
        <SettingsNav />

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- État Actuel & Quotas -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Statut de l'abonnement -->
                <section class="card space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                        <h2 class="text-sm font-semibold">Votre Offre Actuelle</h2>
                        <span 
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="getStatusBadgeClass(tenantStatus)"
                        >
                            {{ getStatusLabel(tenantStatus) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <div>
                            <span class="block text-slate-400">Pack actif</span>
                            <span class="font-bold text-slate-800 dark:text-white mt-1 block">
                                {{ subscription?.plan_name || 'Essai Gratuit' }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-slate-400">
                                {{ tenantStatus === 'trial' ? 'Fin d\'essai' : 'Échéance' }}
                            </span>
                            <span class="font-bold text-slate-800 dark:text-white mt-1 block">
                                {{ tenantStatus === 'trial' ? formatDate(trialEndsAt) : formatDate(subscription?.ends_at) }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Suivi des Quotas Mensuels -->
                <section class="card space-y-4">
                    <h2 class="text-sm font-semibold">Consommation des Quotas</h2>
                    
                    <div class="space-y-4 pt-1">
                        <!-- Messages Envoyés -->
                        <div>
                            <div class="flex justify-between text-xs font-medium mb-1">
                                <span>Messages envoyés</span>
                                <span class="text-slate-500">
                                    {{ usage.messages_sent.used.toLocaleString('fr-FR') }} / 
                                    {{ usage.messages_sent.limit ? usage.messages_sent.limit.toLocaleString('fr-FR') : 'Illimité' }}
                                </span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2.5 dark:bg-slate-800">
                                <div 
                                    class="bg-brand-600 h-2.5 rounded-full transition-all" 
                                    :style="`width: ${usage.messages_sent.limit ? Math.min(100, (usage.messages_sent.used / usage.messages_sent.limit * 100)) : 0}%`"
                                />
                            </div>
                        </div>

                        <!-- Requêtes IA -->
                        <div>
                            <div class="flex justify-between text-xs font-medium mb-1">
                                <span>Requêtes Agent IA</span>
                                <span class="text-slate-500">
                                    {{ usage.ai_requests.used.toLocaleString('fr-FR') }} / 
                                    {{ usage.ai_requests.limit ? usage.ai_requests.limit.toLocaleString('fr-FR') : 'Illimité' }}
                                </span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2.5 dark:bg-slate-800">
                                <div 
                                    class="bg-violet-600 h-2.5 rounded-full transition-all" 
                                    :style="`width: ${usage.ai_requests.limit ? Math.min(100, (usage.ai_requests.used / usage.ai_requests.limit * 100)) : 0}%`"
                                />
                            </div>
                        </div>

                        <!-- Documents Stockés -->
                        <div>
                            <div class="flex justify-between text-xs font-medium mb-1">
                                <span>Documents dans la Base de Connaissances</span>
                                <span class="text-slate-500">
                                    {{ usage.documents_stored.used.toLocaleString('fr-FR') }} / 
                                    {{ usage.documents_stored.limit ? usage.documents_stored.limit.toLocaleString('fr-FR') : 'Illimité' }}
                                </span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2.5 dark:bg-slate-800">
                                <div 
                                    class="bg-emerald-600 h-2.5 rounded-full transition-all" 
                                    :style="`width: ${usage.documents_stored.limit ? Math.min(100, (usage.documents_stored.used / usage.documents_stored.limit * 100)) : 0}%`"
                                />
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Rédemption de Code & Instructions Mobiles -->
            <div class="space-y-6">
                <!-- Rédemption -->
                <section class="card space-y-4">
                    <h2 class="text-sm font-semibold">Activer un Code d'accès</h2>
                    <p class="text-xs text-slate-500 leading-normal">
                        Entrez le code d'accès reçu après validation de votre virement pour recharger votre compte.
                    </p>

                    <form @submit.prevent="submit" class="space-y-3">
                        <div>
                            <input 
                                v-model="form.code" 
                                type="text" 
                                class="input py-2 px-3 text-xs uppercase text-center font-mono tracking-widest font-bold" 
                                placeholder="EX: AA11-BB22-CC33"
                                required
                            />
                            <p v-if="form.errors.code" class="mt-1 text-[11px] text-red-600">{{ form.errors.code }}</p>
                        </div>

                        <button 
                            type="submit" 
                            class="btn-primary w-full py-2 text-xs font-semibold cursor-pointer animate-none" 
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Activation…' : 'Valider le code' }}
                        </button>
                    </form>
                </section>

                <!-- Consignes Wave, MTN, Moov -->
                <section class="card space-y-4 border border-brand-100 bg-brand-50/20 dark:border-slate-800 dark:bg-slate-900/50">
                    <h2 class="text-xs font-bold text-brand-800 dark:text-brand-300 uppercase tracking-wider">Comment s'abonner / renouveler ?</h2>
                    
                    <div class="space-y-3 text-xs leading-relaxed text-slate-700 dark:text-slate-300">
                        <p>
                            1. Choisissez votre offre ci-dessous et effectuez le virement (Wave, MTN MoMo, Moov) au numéro support :
                        </p>
                        <div class="rounded-lg bg-white dark:bg-slate-800 p-2.5 text-center font-bold text-slate-800 dark:text-white border border-brand-200/50 font-mono">
                            +225 05 66 36 03 03
                        </div>
                        <p>
                            2. Envoyez la capture d'écran du reçu de paiement ainsi que le nom de votre entreprise par :
                        </p>
                        <ul class="list-disc list-inside pl-1 space-y-1 font-medium">
                            <li>WhatsApp : <a href="https://wa.me/2250566360303" target="_blank" class="text-emerald-600 hover:underline">+225 05 66 36 03 03</a></li>
                            <li>E-mail : <a href="mailto:contact@nonalixia.com" class="text-brand-600 hover:underline">contact@nonalixia.com</a></li>
                        </ul>
                        <p>
                            3. Un code d'accès unique vous sera transmis immédiatement pour être renseigné ci-dessus.
                        </p>
                    </div>
                </section>
            </div>

            <!-- Grille des Offres Tarifs -->
            <div class="lg:col-span-3 space-y-4">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Détails des Offres</span>
                
                <div class="grid gap-6 md:grid-cols-3">
                    <div 
                        v-for="plan in plans" 
                        :key="plan.id" 
                        class="card flex flex-col justify-between border transition duration-200"
                        :class="subscription?.plan_name === plan.name 
                            ? 'border-brand-500 shadow-md ring-2 ring-brand-100 dark:ring-slate-800' 
                            : 'border-slate-200 dark:border-slate-800'"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ plan.name }}</h3>
                                <span 
                                    v-if="subscription?.plan_name === plan.name" 
                                    class="rounded bg-brand-50 px-2 py-0.5 text-[9px] font-bold text-brand-700 uppercase tracking-wider dark:bg-slate-800 dark:text-brand-300"
                                >
                                    Actif
                                </span>
                            </div>

                            <div class="flex items-baseline gap-1 py-1">
                                <span class="text-xl font-black text-slate-900 dark:text-white">
                                    {{ plan.price_cents === 0 ? 'Gratuit' : formatPrice(plan.price_cents) }}
                                </span>
                                <span v-if="plan.price_cents > 0" class="text-[10px] text-slate-400">/ mois</span>
                            </div>

                            <p class="text-xs text-slate-500 leading-normal">{{ plan.description }}</p>

                            <hr class="border-slate-100 dark:border-slate-800" />

                            <!-- Limites -->
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Messages/mois :</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ plan.quotas.messages_sent ? plan.quotas.messages_sent.toLocaleString('fr-FR') : 'Illimité' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Requêtes IA/mois :</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ plan.quotas.ai_requests ? plan.quotas.ai_requests.toLocaleString('fr-FR') : 'Illimité' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Documents base :</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ plan.quotas.documents_stored ? plan.quotas.documents_stored.toLocaleString('fr-FR') : 'Illimité' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
