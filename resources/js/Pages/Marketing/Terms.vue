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

            <div class="mb-10 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900/50">
                Les présentes Conditions Générales d'Utilisation décrivent le cadre technique et contractuel du service NONALIX IA, édité et exploité par NONALIX CI SARL.
            </div>

            <div class="space-y-8 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">1. Objet</h2>
                    <p>
                        NONALIX CI SARL met à disposition par l'intermédiaire de sa plateforme NONALIX IA un service permettant à une
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
                        peut révoquer l'accès de la plateforme à tout moment en retirant l'URL
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
                        fournisseurs de modèles d'IA — sur lesquels NONALIX CI SARL n'a pas de
                        maîtrise. En cas d'indisponibilité d'un fournisseur d'IA, un
                        mécanisme de repli est appliqué et, à défaut, la conversation est
                        transférée à un opérateur humain.
                    </p>
                    <p class="mt-2">
                        NONALIX CI SARL met en œuvre les moyens techniques adéquats pour assurer une disponibilité de la plateforme 24 heures sur 24 et 7 jours sur 7. Les interventions planifiées de maintenance font l'objet d'une information préalable dans la mesure du possible.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        6. Données et confidentialité
                    </h2>
                    <p>
                        Les données de chaque entreprise sont strictement cloisonnées.
                        Elles ne sont utilisées ni pour entraîner de modèle, ni à des fins
                        publicitaires. Les modalités détaillées figurent dans la
                        <Link href="/confidentialite" class="underline">politique de confidentialité</Link>.
                    </p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        7. Suspension
                    </h2>
                    <p>
                        NONALIX CI SARL peut suspendre un compte en cas d'usage manifestement
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
                    <p>
                        La responsabilité de NONALIX CI SARL est limitée aux dommages directs et prévisibles résultant de l'exécution du service, dans la limite des montants perçus au titre de l'abonnement en cours. Les présentes CGU sont régies par le droit ivoirien. Tout litige relatif à leur validité, leur interprétation ou leur exécution sera soumis à la juridiction compétente d'Abidjan, Côte d'Ivoire.
                    </p>
                </section>
            </div>
        </article>
    </MarketingLayout>
</template>
