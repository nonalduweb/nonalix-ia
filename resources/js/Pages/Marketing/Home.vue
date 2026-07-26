<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

defineProps({
    appUrl: String,
});

const steps = [
    {
        title: 'Connectez votre numéro',
        text: "Votre propre numéro WhatsApp Business, sur votre propre compte Meta. Vous en gardez la maîtrise, y compris si vous nous quittez.",
    },
    {
        title: 'Décrivez votre activité',
        text: "Horaires, prestations, tarifs, questions fréquentes. Vos documents commerciaux complètent l'ensemble.",
    },
    {
        title: "L'agent prend les demandes",
        text: "Il répond avec vos informations, qualifie les demandes et passe la main à votre équipe dès que c'est nécessaire.",
    },
];

const guarantees = [
    {
        title: 'Aucun tarif inventé',
        text: "L'agent ne peut annoncer que les prix que vous avez saisis. Face à une question hors périmètre, il transfère plutôt que d'improviser.",
    },
    {
        title: 'Transfert humain à tout moment',
        text: "Un mot-clé, une réclamation, un doute : la conversation bascule immédiatement vers un opérateur, sans passer par l'IA.",
    },
    {
        title: 'Vos données restent les vôtres',
        text: 'Cloisonnement strict par entreprise, jetons chiffrés, journal des accès. Aucune donnée client ne sert à entraîner un modèle.',
    },
    {
        title: 'Coûts visibles',
        text: 'Consommation IA et WhatsApp affichée en temps réel, quotas par plan, aucune surprise en fin de mois.',
    },
];
</script>

<template>
    <Head title="Un agent IA sur votre WhatsApp" />

    <MarketingLayout>
        <section class="mx-auto max-w-4xl px-4 py-24 text-center">
            <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">
                Un agent IA qui répond sur
                <span class="text-brand-600">votre WhatsApp</span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-lg text-slate-600 dark:text-slate-300">
                Vos clients écrivent sur votre numéro habituel. L'agent répond avec vos
                informations, jour et nuit, et passe la main à votre équipe dès que la
                demande le mérite.
            </p>

            <div class="mt-10 flex flex-wrap justify-center gap-4">
                <a :href="appUrl" class="btn-primary px-6 py-3 text-base">Démarrer l'essai</a>
                <Link href="/tarifs" class="btn-secondary px-6 py-3 text-base">Voir les tarifs</Link>
            </div>

            <p class="mt-4 text-sm text-slate-500">14 jours d'essai · sans carte bancaire</p>
        </section>

        <section class="border-y border-slate-200 bg-slate-50 py-20 dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto max-w-5xl px-4">
                <h2 class="mb-12 text-center text-2xl font-semibold">Trois étapes, un après-midi</h2>

                <ol class="grid gap-8 md:grid-cols-3">
                    <li v-for="(step, index) in steps" :key="step.title">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">
                            {{ index + 1 }}
                        </span>
                        <h3 class="mt-4 font-medium">{{ step.title }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ step.text }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="py-20">
            <div class="mx-auto max-w-5xl px-4">
                <h2 class="mb-4 text-center text-2xl font-semibold">Ce que nous garantissons</h2>
                <p class="mx-auto mb-12 max-w-2xl text-center text-slate-600 dark:text-slate-300">
                    Un agent qui parle à vos clients engage votre réputation. Voici les
                    garde-fous que nous avons mis en place.
                </p>

                <div class="grid gap-6 md:grid-cols-2">
                    <div v-for="item in guarantees" :key="item.title" class="card">
                        <h3 class="font-medium">{{ item.title }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ item.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200 py-20 text-center dark:border-slate-800">
            <div class="mx-auto max-w-2xl px-4">
                <h2 class="text-2xl font-semibold">Essayez sur votre propre numéro</h2>
                <p class="mt-3 text-slate-600 dark:text-slate-300">
                    La configuration prend une quinzaine de minutes. Vous gardez le
                    contrôle de votre compte Meta du début à la fin.
                </p>
                <a :href="appUrl" class="btn-primary mt-8 inline-flex px-6 py-3 text-base">
                    Créer un compte
                </a>
            </div>
        </section>
    </MarketingLayout>
</template>
