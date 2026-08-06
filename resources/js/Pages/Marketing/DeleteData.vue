<script setup>
import { Head } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

const year = new Date().getFullYear();
</script>

<template>
    <Head title="Suppression des données" />

    <MarketingLayout>
        <article class="mx-auto max-w-3xl px-4 py-16">
            <h1 class="mb-8 text-3xl font-semibold tracking-tight">Instructions de suppression des données</h1>

            <div class="mb-8 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                <p class="mb-4">
                    Conformément au Règlement Général sur la Protection des Données (RGPD) et aux exigences de Meta Platforms, 
                    les utilisateurs de nos services disposent d'un droit de suppression, de rectification et d'accès à leurs données personnelles.
                </p>
                <p>
                    Cette page décrit les instructions étape par étape pour demander la suppression des informations vous concernant 
                    détenues par <strong>Nonalix IA</strong>.
                </p>
            </div>

            <!-- Section pour les clients directs (Entreprises) -->
            <section class="mb-10">
                <h2 class="mb-3 text-lg font-semibold text-slate-900">1. Pour les clients de Nonalix IA (Utilisateurs de la plateforme)</h2>
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Si vous possédez un compte sur la plateforme Nonalix IA (espace client) et que vous souhaitez supprimer votre compte, 
                    vos informations de facturation, les accès de vos collaborateurs et toutes vos configurations :
                </p>
                <ol class="list-decimal pl-5 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li>
                        Envoyez un e-mail à notre support à l'adresse : 
                        <a href="mailto:support@nonalixia.com" class="text-brand-600 hover:underline font-medium">support@nonalixia.com</a>.
                    </li>
                    <li>
                        Précisez dans l'objet de votre mail : <strong>« Demande de suppression de compte - [Nom de votre entreprise] »</strong>.
                    </li>
                    <li>
                        Afin de valider la légitimité de la demande, celle-ci doit être envoyée par l'administrateur ou le propriétaire principal de l'espace de travail.
                    </li>
                </ol>
                <p class="mt-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Dès réception, notre équipe procédera à la désactivation immédiate des services. L'intégralité de vos bases de données, 
                    documents de connaissances vectorisés et données de compte seront définitivement purgés de nos serveurs de production 
                    sous 30 jours, à l'exception des données légalement requises pour la comptabilité et la facturation.
                </p>
            </section>

            <!-- Section pour les contacts finaux (WhatsApp) -->
            <section class="mb-10">
                <h2 class="mb-3 text-lg font-semibold text-slate-900">2. Pour les utilisateurs finaux (Discussions WhatsApp)</h2>
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Nonalix IA agit en tant que <strong>sous-traitant</strong> pour le compte d'entreprises clientes. Si vous êtes un utilisateur final 
                    qui a échangé avec un assistant virtuel sur le compte WhatsApp d'une entreprise utilisant nos services, vous pouvez supprimer 
                    vos données de deux manières :
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li>
                        <strong>Désinscription immédiate (Opt-out) :</strong> Répondez simplement par le mot-clé <strong>STOP</strong> 
                        dans la discussion WhatsApp de l'entreprise. L'agent virtuel cessera instantanément toute interaction et vos informations 
                        seront marquées comme exclues des traitements futurs de façon automatique.
                    </li>
                    <li>
                        <strong>Demande d'effacement de l'historique :</strong> Adressez directement votre demande de suppression à l'entreprise 
                        (le responsable de traitement) avec laquelle vous avez communiqué. L'entreprise peut à tout moment supprimer l'historique 
                        de vos échanges directement depuis son interface Nonalix IA ou sur demande à notre support.
                    </li>
                </ul>
            </section>

            <!-- Section Contact direct DPO -->
            <section class="mb-10 border-t border-slate-100 pt-8">
                <h2 class="mb-3 text-lg font-semibold text-slate-900">3. Contact et Assistance</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    Pour toute question relative à la confidentialité de vos données ou pour toute réclamation concernant le traitement de vos informations personnelles, 
                    vous pouvez également nous écrire par courriel à : 
                    <a href="mailto:support@nonalixia.com" class="text-brand-600 hover:underline font-medium">support@nonalixia.com</a>.
                </p>
            </section>
        </article>
    </MarketingLayout>
</template>
