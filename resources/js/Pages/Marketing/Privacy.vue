<script setup>
import { Head } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

/*
 * GABARIT À FAIRE VALIDER PAR UN JURISTE AVANT MISE EN LIGNE.
 *
 * Les traitements décrits ci-dessous correspondent à ce que la plateforme fait
 * RÉELLEMENT (voir ARCHITECTURE.md §9 et DATABASE.md §8). Ce qui reste à
 * compléter est signalé entre crochets : responsable de traitement, base
 * légale retenue, sous-traitants effectivement contractualisés.
 */

const dataCategories = [
    {
        category: 'Compte utilisateur',
        data: 'Nom, adresse e-mail, mot de passe chiffré, secret 2FA chiffré, journal des connexions.',
        retention: "Durée du contrat, puis 30 jours.",
    },
    {
        category: 'Contacts WhatsApp',
        data: 'Numéro de téléphone, nom de profil WhatsApp, état du consentement, historique des messages.',
        retention: "Paramétrable par l'entreprise cliente. Illimité par défaut.",
    },
    {
        category: 'Contenu des conversations',
        data: 'Messages entrants et sortants, médias échangés, notes internes.',
        retention: 'Médias : 90 jours. Messages : selon le paramétrage du client.',
    },
    {
        category: 'Base de connaissances',
        data: "Documents déposés par l'entreprise cliente et leurs représentations vectorielles.",
        retention: "Jusqu'à suppression par le client.",
    },
    {
        category: 'Exploitation',
        data: 'Journal des webhooks, journal d\'audit, consommation IA, incidents.',
        retention: 'Webhooks : 30 jours. Usage IA : 13 mois. Audit : 24 mois.',
    },
];

const subprocessors = [
    ['Meta Platforms Inc.', 'Acheminement des messages WhatsApp', 'États-Unis / Union Européenne'],
    ['OpenAI / Anthropic / Google', 'Génération des réponses et vectorisation', 'États-Unis / Union Européenne'],
    ['Hostinger International Ltd.', 'Hébergement de l\'infrastructure et des bases de données', 'Chypre / Union Européenne'],
];
</script>

<template>
    <Head title="Politique de confidentialité" />

    <MarketingLayout>
        <article class="mx-auto max-w-3xl px-4 py-16">
            <h1 class="mb-8 text-3xl font-semibold tracking-tight">Politique de confidentialité</h1>

            <div class="mb-10 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900/50">
                La protection de vos données personnelles est une priorité. Les traitements décrits ci-dessous correspondent aux activités réelles de la plateforme exploitée par NONALIX CI SARL.
            </div>

            <section class="mb-10">
                <h2 class="mb-3 text-lg font-semibold">Rôles</h2>
                <p class="mb-3 text-sm text-slate-600 dark:text-slate-300">
                    NONALIX CI SARL (pour son produit NONALIX IA) agit à deux titres distincts :
                </p>
                <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li>
                        <strong>Responsable de traitement</strong> pour les données des
                        comptes utilisateurs de la plateforme (nos clients directs).
                    </li>
                    <li>
                        <strong>Sous-traitant</strong> pour les données des contacts
                        WhatsApp traitées pour le compte de l'entreprise cliente. Celle-ci
                        reste responsable de la licéité de la collecte et du recueil du
                        consentement.
                    </li>
                </ul>
            </section>

            <section class="mb-10">
                <h2 class="mb-4 text-lg font-semibold">Données traitées et durées de conservation</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Catégorie</th>
                                <th class="py-2 pr-4 font-medium">Données</th>
                                <th class="py-2 font-medium">Conservation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="row in dataCategories" :key="row.category" class="align-top">
                                <td class="py-3 pr-4 font-medium">{{ row.category }}</td>
                                <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ row.data }}</td>
                                <td class="py-3 text-slate-600 dark:text-slate-300">{{ row.retention }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mb-10">
                <h2 class="mb-3 text-lg font-semibold">Sous-traitants</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="[name, purpose, zone] in subprocessors" :key="name" class="align-top">
                            <td class="py-3 pr-4 font-medium">{{ name }}</td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ purpose }}</td>
                            <td class="py-3 font-mono text-xs text-slate-400">{{ zone }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="mb-10">
                <h2 class="mb-3 text-lg font-semibold">Ce que nous ne faisons pas</h2>
                <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li>
                        Les conversations de nos clients ne servent pas à entraîner de
                        modèle d'intelligence artificielle, ni le nôtre ni celui d'un tiers.
                    </li>
                    <li>Aucune donnée n'est revendue ni cédée à des fins publicitaires.</li>
                    <li>
                        Les équipes de NONALIX CI SARL n'accèdent pas librement aux données d'un
                        client : toute consultation passe par une session d'assistance
                        limitée dans le temps et intégralement journalisée.
                    </li>
                </ul>
            </section>

            <section class="mb-10">
                <h2 class="mb-3 text-lg font-semibold">Sécurité</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Cloisonnement strict des données par entreprise, chiffrement au repos
                    des jetons d'accès et des secrets d'authentification, vérification
                    cryptographique des webhooks entrants, authentification à deux
                    facteurs obligatoire pour les rôles à privilèges, journal d'audit
                    immuable, sauvegardes chiffrées quotidiennes.
                </p>
            </section>

            <section>
                <h2 class="mb-3 text-lg font-semibold">Exercice des droits</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Les personnes concernées disposent d'un droit d'accès, de
                    rectification, d'effacement, de limitation, d'opposition et de
                    portabilité. Les contacts WhatsApp peuvent également se désinscrire à
                    tout moment en répondant <strong>STOP</strong> ; la désinscription est
                    immédiate et inconditionnelle.
                </p>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                    Toute demande relative aux contacts d'une entreprise cliente doit être
                    adressée à cette entreprise, qui en est responsable. Pour les autres
                    demandes concernant vos données personnelles : <strong><a href="mailto:contact@nonalix-ci.com" class="text-brand-600 hover:underline">contact@nonalix-ci.com</a></strong>.
                </p>
            </section>
        </article>
    </MarketingLayout>
</template>
