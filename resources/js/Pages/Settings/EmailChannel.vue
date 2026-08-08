<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';

const props = defineProps({
    inboundAddress: String,
    businessEmail: String, // nullable
    verifiedAt: String,    // nullable
    probeSentAt: String,   // nullable
    provider: Object,      // nullable
});

const copied = ref(false);

const copyAddress = () => {
    navigator.clipboard.writeText(props.inboundAddress);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const probing = ref(false);

const probe = () => {
    probing.value = true;
    router.post('/settings/email/probe', {}, {
        preserveScroll: true,
        onFinish: () => (probing.value = false),
    });
};

const formatDate = (iso) =>
    new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' });
</script>

<template>
    <Head title="Canal e-mail" />

    <AppLayout>
        <h1 class="mb-6 text-xl font-semibold">Configuration</h1>
        <SettingsNav />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- État du canal -->
                <section class="card space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-semibold">Canal e-mail</h2>
                            <p class="mt-1 text-xs leading-normal text-slate-500">
                                Votre agent peut répondre aux messages reçus sur votre adresse habituelle.
                                Pour cela, votre messagerie doit nous en envoyer une copie — c'est la seule
                                étape qui se passe chez vous.
                            </p>
                        </div>

                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider"
                            :class="verifiedAt
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                        >
                            {{ verifiedAt ? 'Actif' : 'En attente' }}
                        </span>
                    </div>

                    <p v-if="verifiedAt" class="rounded-lg bg-emerald-50 px-3.5 py-2.5 text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300">
                        Redirection constatée le {{ formatDate(verifiedAt) }}. Les messages reçus sur
                        <strong>{{ businessEmail }}</strong> arrivent bien jusqu'à votre agent.
                    </p>
                </section>

                <!-- Étape 1 : l'adresse -->
                <section class="card space-y-3">
                    <h2 class="text-sm font-semibold">1. Votre adresse de réception Nonalix</h2>
                    <p class="text-xs leading-normal text-slate-500">
                        Nous l'avons créée pour vous. C'est vers elle que votre messagerie doit rediriger.
                        Gardez-la pour vous : elle donne accès à votre canal.
                    </p>

                    <div class="relative rounded-lg border border-slate-800 bg-slate-950 p-4 font-mono text-xs break-all text-slate-300 select-all dark:bg-slate-900">
                        {{ inboundAddress }}
                        <button
                            class="absolute top-2 right-2 cursor-pointer rounded bg-slate-800 px-2.5 py-1 text-[10px] font-semibold text-white transition hover:bg-slate-700"
                            @click="copyAddress"
                        >
                            {{ copied ? 'Copié !' : 'Copier' }}
                        </button>
                    </div>
                </section>

                <!-- Étape 2 : la redirection -->
                <section class="card space-y-3">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="text-sm font-semibold">2. Rediriger votre messagerie</h2>
                        <span v-if="provider" class="text-[10px] font-bold uppercase tracking-wider text-brand-600">
                            {{ provider.name }}
                        </span>
                    </div>

                    <p v-if="!businessEmail" class="rounded-lg bg-amber-50 px-3.5 py-2.5 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                        Renseignez d'abord l'adresse e-mail de votre entreprise dans
                        <Link href="/settings/business" class="font-semibold underline">Configuration › Entreprise</Link>.
                        Nous saurons alors quel fournisseur vous utilisez.
                    </p>

                    <template v-else>
                        <p class="text-xs text-slate-500">
                            Ces étapes concernent <strong>{{ businessEmail }}</strong>.
                        </p>

                        <ol class="space-y-2 text-xs leading-normal text-slate-600 dark:text-slate-300">
                            <li v-for="(step, index) in provider.steps" :key="index" class="flex gap-2.5">
                                <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[9px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    {{ index + 1 }}
                                </span>
                                <span>{{ step }}</span>
                            </li>
                        </ol>

                        <a
                            v-if="provider.doc"
                            :href="provider.doc"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-block text-xs font-semibold text-brand-600 hover:underline"
                        >
                            Documentation de {{ provider.name }} →
                        </a>
                    </template>
                </section>

                <!-- Étape 3 : la vérification -->
                <section class="card space-y-3">
                    <h2 class="text-sm font-semibold">3. Vérifier</h2>
                    <p class="text-xs leading-normal text-slate-500">
                        Nous envoyons un message à votre adresse. S'il nous revient, c'est que la
                        redirection fonctionne — le canal s'active alors tout seul. Vous n'avez rien
                        à nous recopier.
                    </p>

                    <p v-if="probeSentAt && !verifiedAt" class="rounded-lg bg-slate-50 px-3.5 py-2.5 text-xs text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
                        Message envoyé le {{ formatDate(probeSentAt) }}, toujours pas revenu.
                        La redirection met parfois quelques minutes à s'appliquer ; certains fournisseurs
                        demandent aussi de confirmer la nouvelle destination.
                    </p>

                    <button
                        class="btn-primary cursor-pointer px-4 py-2 text-xs font-semibold"
                        :disabled="probing || !businessEmail"
                        @click="probe"
                    >
                        {{ probing ? 'Envoi…' : verifiedAt ? 'Vérifier à nouveau' : 'Vérifier la redirection' }}
                    </button>
                </section>
            </div>

            <!-- Ce qu'il faut savoir -->
            <aside class="space-y-4">
                <section class="card space-y-3 text-xs leading-normal text-slate-500">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Bon à savoir</h2>

                    <p>
                        <strong class="text-slate-700 dark:text-slate-300">Vous gardez votre adresse.</strong>
                        Vos clients continuent d'écrire à {{ businessEmail || 'votre adresse habituelle' }} ;
                        rien ne change pour eux.
                    </p>

                    <p>
                        <strong class="text-slate-700 dark:text-slate-300">Les réponses partent à votre nom.</strong>
                        Elles sont expédiées depuis nos serveurs en portant le nom de votre entreprise.
                        C'est ce qui garantit qu'elles n'atterrissent pas en indésirables.
                    </p>

                    <p>
                        <strong class="text-slate-700 dark:text-slate-300">Rien ne part sans vous, par défaut.</strong>
                        L'agent prépare un brouillon que vous validez. Le mode automatique se règle dans
                        <Link href="/settings/agent" class="font-semibold text-brand-600 underline">les réglages de l'agent</Link>.
                    </p>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
