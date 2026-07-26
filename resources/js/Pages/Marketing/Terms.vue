<script setup>
import { Head } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

/*
 * GABARIT À FAIRE VALIDER PAR UN JURISTE AVANT MISE EN LIGNE.
 *
 * Les clauses techniques (obligations Meta, quotas, disponibilité) reflètent
 * le fonctionnement réel de la plateforme. Les clauses contractuelles
 * (responsabilité, résiliation, droit applicable) sont à arbitrer.
 */
</script>

<template>
    <Head title="Conditions générales d'utilisation" />

    <MarketingLayout>
        <article class="mx-auto max-w-3xl px-4 py-16">
            <h1 class="mb-8 text-3xl font-semibold tracking-tight">
                Conditions générales d'utilisation
            </h1>

            <div class="mb-10 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong>Gabarit à faire valider.</strong> Les clauses techniques
                correspondent au fonctionnement réel du service. Les clauses
                contractuelles doivent être arbitrées et validées juridiquement avant
                publication.
            </div>

            <div class="space-y-8 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">1. Objet</h2>
                    <p>
                        Nonalix IA met à disposition une plateforme permettant à une
                        entreprise de connecter son propre numéro WhatsApp Business et d'y
                        déployer un agent conversationnel automatisé.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        2. Compte WhatsApp du client
                    </h2>
                    <p>
                        Le client fournit son propre compte WhatsApp Business et ses
                        propres identifiants Meta. Il en conserve la pleine propriété et
                        peut révoquer l'accès de Nonalix à tout moment en retirant l'URL
                        de webhook depuis sa console Meta.
                    </p>
                    <p class="mt-2">
                        Le client demeure seul responsable du respect des politiques de
                        Meta, notamment des règles relatives au consentement, aux modèles
                        de messages et à la fenêtre de service de 24 heures. Une
                        dégradation de la qualité du numéro imputable à l'usage qui en est
                        fait relève de sa responsabilité.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        3. Nature des réponses automatisées
                    </h2>
                    <p>
                        L'agent produit des réponses à partir des informations saisies par
                        le client. Des garde-fous limitent le risque d'erreur : les tarifs
                        communiqués proviennent exclusivement du catalogue renseigné, et
                        l'agent est instruit de transférer à un humain en cas de doute.
                    </p>
                    <p class="mt-2">
                        Ces garde-fous n'éliminent cependant pas tout risque d'erreur
                        inhérent aux modèles de langage. Le client reste responsable des
                        messages envoyés depuis son numéro et doit superviser son agent,
                        en particulier après toute modification de sa configuration.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        4. Quotas et consommation
                    </h2>
                    <p>
                        Chaque plan comporte des quotas mensuels, consultables en temps
                        réel depuis le tableau de bord. Une alerte est émise à 80 % de
                        consommation. Sur les plans à dépassement bloqué, l'envoi est
                        interrompu au-delà de la limite. Les coûts facturés par Meta au
                        titre des conversations WhatsApp ne sont pas inclus.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        5. Disponibilité et maintenance
                    </h2>
                    <p>
                        Le service dépend de la disponibilité de tiers — Meta et les
                        fournisseurs de modèles d'IA — sur lesquels Nonalix n'a pas de
                        maîtrise. En cas d'indisponibilité d'un fournisseur d'IA, un
                        mécanisme de repli est appliqué et, à défaut, la conversation est
                        transférée à un opérateur humain.
                    </p>
                    <p class="mt-2">[niveau d'engagement de disponibilité à définir]</p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        6. Données et confidentialité
                    </h2>
                    <p>
                        Les données de chaque entreprise sont strictement cloisonnées.
                        Elles ne sont utilisées ni pour entraîner de modèle, ni à des fins
                        publicitaires. Les modalités détaillées figurent dans la
                        <a href="/confidentialite" class="underline">politique de confidentialité</a>.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        7. Suspension
                    </h2>
                    <p>
                        Nonalix peut suspendre un compte en cas d'usage manifestement
                        illicite, de non-respect des politiques Meta ou d'impayé
                        persistant. Toute suspension est motivée par écrit et notifiée. Les
                        données du client sont conservées pendant la période de suspension.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        8. Résiliation et réversibilité
                    </h2>
                    <p>
                        Le client peut résilier à tout moment. Ses données sont exportables
                        avant la fermeture du compte et sont définitivement purgées trente
                        jours après celle-ci. Le numéro WhatsApp, appartenant au client,
                        n'est pas affecté par la résiliation.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        9. Responsabilité et droit applicable
                    </h2>
                    <p>[clauses de responsabilité, juridiction compétente et droit applicable à définir]</p>
                </section>
            </div>
        </article>
    </MarketingLayout>
</template>
