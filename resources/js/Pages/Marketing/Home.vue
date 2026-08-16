<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import AgentOrbit from '@/Components/Marketing/AgentOrbit.vue';

defineProps({
    appUrl: String,
    registerUrl: String,
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
        <!-- Héros aligné à gauche et non centré : une colonne de texte se lit
             plus vite, et la promesse tient en une ligne. -->
        <section class="mx-auto max-w-5xl px-6 pt-20 pb-20 sm:pt-28">
            <div class="grid items-center gap-8 lg:grid-cols-[1fr_auto] lg:gap-4">
                <div>
                    <h1
                        v-reveal
                        class="max-w-3xl text-[2.75rem] font-semibold leading-[1.08] tracking-tight sm:text-6xl"
                    >
                        Vos clients écrivent.<br />
                        L'agent répond.
                    </h1>

                    <p v-reveal="80" class="mt-7 max-w-xl text-lg leading-relaxed text-slate-600">
                        Sur votre numéro WhatsApp habituel, jour et nuit, avec vos propres
                        informations. Et il passe la main à votre équipe dès que la demande
                        le mérite.
                    </p>

                    <div v-reveal="160" class="mt-10 flex flex-wrap items-center gap-3">
                        <Link href="/demande" class="btn-ink px-6 py-3 text-base">Demander un accès</Link>
                        <Link href="/tarifs" class="btn-secondary px-6 py-3 text-base">Voir les tarifs</Link>
                    </div>

                    <p v-reveal="220" class="mt-5 text-sm text-slate-500">
                        14 jours d'essai · sans carte bancaire · à partir de 15 000 F CFA par mois
                    </p>
                </div>

                <AgentOrbit v-reveal="120" class="mx-auto hidden sm:block" />
            </div>
        </section>

        <!-- Séparation par la couleur de fond plutôt que par un trait : c'est
             l'espace qui structure la page, pas la bordure. -->
        <section class="bg-slate-50 py-24">
            <div class="mx-auto max-w-5xl px-6">
                <h2 v-reveal class="text-3xl font-semibold tracking-tight">Trois étapes, un après-midi</h2>

                <ol class="mt-14 grid gap-12 md:grid-cols-3">
                    <li v-for="(step, index) in steps" :key="step.title" v-reveal="index * 100">
                        <span class="text-sm font-medium tabular-nums text-slate-400">
                            0{{ index + 1 }}
                        </span>
                        <h3 class="mt-3 text-lg font-medium">{{ step.title }}</h3>
                        <p class="mt-2.5 leading-relaxed text-slate-600">{{ step.text }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="py-24">
            <div class="mx-auto max-w-5xl px-6">
                <h2 v-reveal class="text-3xl font-semibold tracking-tight">Ce que nous garantissons</h2>
                <p v-reveal="80" class="mt-4 max-w-xl leading-relaxed text-slate-600">
                    Un agent qui parle à vos clients engage votre réputation. Voici les
                    garde-fous que nous avons mis en place.
                </p>

                <div class="mt-14 grid gap-x-12 gap-y-10 md:grid-cols-2">
                    <div v-for="(item, index) in guarantees" :key="item.title" v-reveal="index * 80">
                        <h3 class="text-lg font-medium">{{ item.title }}</h3>
                        <p class="mt-2.5 leading-relaxed text-slate-600">{{ item.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-5xl px-6 pb-8">
            <div v-reveal class="rounded-3xl bg-slate-900 px-8 py-16 text-center transition duration-300 hover:shadow-lift sm:px-16">
                <h2 class="text-3xl font-semibold tracking-tight text-white">
                    Essayez sur votre propre numéro
                </h2>
                <p class="mx-auto mt-4 max-w-lg leading-relaxed text-slate-300">
                    La configuration prend une quinzaine de minutes. Vous gardez le
                    contrôle de votre compte Meta du début à la fin.
                </p>
                <Link
                    href="/demande"
                    class="btn mt-9 bg-white px-6 py-3 text-base text-slate-900 transition hover:scale-[1.03] hover:bg-slate-100"
                >
                    Demander un accès
                </Link>
                <p class="mt-4 text-sm text-slate-400">
                    Réponse sous 24 h ouvrées. Aucune carte bancaire n'est demandée.
                </p>
            </div>
        </section>
    </MarketingLayout>
</template>
