<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { formatMoney, formatPlanPrice } from '@/money';

defineProps({
    plans: Array,
});

const page = usePage();

const appUrl = computed(() => `https://${page.props.domains?.app ?? 'app.nonalixia.com'}`);

const QUOTA_LABELS = {
    messages_sent: 'Messages envoyés',
    messages_received: 'Messages reçus',
    ai_requests: 'Réponses IA',
    documents_stored: 'Documents indexés',
};

const FEATURE_LABELS = {
    rag: 'Base de connaissances',
    api_access: 'Accès API',
    templates: 'Modèles de messages',
};

const formatPrice = (plan) =>
    plan.price_cents === 0 ? 'Gratuit' : formatMoney(plan.price_cents, plan.currency);

// Les quotas affichés sont ceux réellement appliqués : la page lit la table
// `plans`, elle ne peut pas dériver de ce qui est facturé.
const displayedQuotas = (plan) =>
    Object.entries(QUOTA_LABELS)
        .filter(([key]) => plan.quotas?.[key] !== undefined)
        .map(([key, label]) => ({
            label,
            value: plan.quotas[key].toLocaleString('fr-FR'),
        }));
</script>

<template>
    <Head title="Tarifs" />

    <MarketingLayout>
        <section class="mx-auto max-w-6xl px-4 py-20">
            <div class="mb-14 text-center">
                <h1 class="text-3xl font-semibold tracking-tight">Tarifs</h1>
                <p class="mx-auto mt-4 max-w-2xl text-slate-600 dark:text-slate-300">
                    Un abonnement mensuel, sans engagement. Les quotas correspondent à
                    l'usage réel : vous voyez votre consommation en temps réel depuis
                    votre tableau de bord.
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="card flex flex-col"
                    :class="plan.slug === 'business' && 'border-brand-500 ring-1 ring-brand-500'"
                >
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold">{{ plan.name }}</h2>
                        <p v-if="plan.description" class="mt-1 text-sm text-slate-500">
                            {{ plan.description }}
                        </p>
                    </div>

                    <p class="mb-6">
                        <span class="text-3xl font-semibold">{{ formatPrice(plan) }}</span>
                        <span v-if="plan.price_cents > 0" class="text-sm text-slate-500">
                            / {{ plan.interval === 'year' ? 'an' : 'mois' }}
                        </span>
                    </p>

                    <dl class="mb-6 space-y-2 text-sm">
                        <div v-for="quota in displayedQuotas(plan)" :key="quota.label" class="flex justify-between gap-3">
                            <dt class="text-slate-600 dark:text-slate-300">{{ quota.label }}</dt>
                            <dd class="font-medium tabular-nums">{{ quota.value }}</dd>
                        </div>
                    </dl>

                    <ul class="mb-8 space-y-1.5 text-sm">
                        <li
                            v-for="(label, key) in FEATURE_LABELS"
                            :key="key"
                            class="flex items-center gap-2"
                            :class="!plan.features?.[key] && 'text-slate-400'"
                        >
                            <span :class="plan.features?.[key] ? 'text-emerald-600' : 'text-slate-300'">
                                {{ plan.features?.[key] ? '✓' : '○' }}
                            </span>
                            {{ label }}
                        </li>
                    </ul>

                    <a :href="appUrl" class="btn-primary mt-auto w-full">Choisir</a>
                </div>
            </div>

            <div class="mt-16 grid gap-8 md:grid-cols-2">
                <div>
                    <h3 class="font-medium">Que se passe-t-il si je dépasse un quota ?</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Sur les plans standards, l'envoi est bloqué et vous êtes prévenu
                        dès 80 % de consommation. Aucun dépassement n'est facturé sans
                        que vous l'ayez choisi.
                    </p>
                </div>
                <div>
                    <h3 class="font-medium">Les coûts WhatsApp de Meta sont-ils inclus ?</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Non. Meta facture directement les conversations sur votre compte
                        WhatsApp Business, selon sa propre grille. Nous n'intervenons pas
                        dans cette relation.
                    </p>
                </div>
                <div>
                    <h3 class="font-medium">Puis-je utiliser ma propre clé IA ?</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Oui. Vous pouvez fournir votre clé OpenAI, Anthropic ou Google :
                        la consommation est alors facturée directement par le fournisseur.
                    </p>
                </div>
                <div>
                    <h3 class="font-medium">Que devient mon numéro si je pars ?</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Il vous appartient. Le compte WhatsApp Business est le vôtre :
                        il suffit de retirer notre URL de webhook.
                    </p>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
