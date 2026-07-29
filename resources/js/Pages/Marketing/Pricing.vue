<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { formatMoney } from '@/money';

defineProps({
    plans: Array,
});

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

const FAQ = [
    {
        q: 'Que se passe-t-il si je dépasse un quota ?',
        a: "Sur les formules standards, l'envoi est bloqué et vous êtes prévenu dès 80 % de consommation. Aucun dépassement n'est facturé sans que vous l'ayez choisi.",
    },
    {
        q: 'Les coûts WhatsApp de Meta sont-ils inclus ?',
        a: "Non. Meta facture directement les conversations sur votre compte WhatsApp Business, selon sa propre grille. Nous n'intervenons pas dans cette relation.",
    },
    {
        q: 'Puis-je utiliser ma propre clé IA ?',
        a: 'Oui. Vous pouvez fournir votre clé OpenAI, Anthropic ou Google : la consommation est alors facturée directement par le fournisseur.',
    },
    {
        q: 'Que devient mon numéro si je pars ?',
        a: "Il vous appartient. Le compte WhatsApp Business est le vôtre : il suffit de retirer notre URL de webhook.",
    },
];
</script>

<template>
    <Head title="Tarifs" />

    <MarketingLayout>
        <section class="mx-auto max-w-5xl px-6 pt-20 pb-8 sm:pt-28">
            <div class="max-w-2xl">
                <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">
                    Des tarifs lisibles.
                </h1>
                <p class="mt-5 text-lg leading-relaxed text-slate-600">
                    Un abonnement mensuel, sans engagement. Les quotas correspondent à
                    l'usage réel, visible en temps réel depuis votre tableau de bord.
                </p>
            </div>
        </section>

        <section class="mx-auto max-w-5xl px-6 pb-8">
            <div class="grid gap-5 md:grid-cols-3">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="flex flex-col rounded-2xl border p-6"
                    :class="
                        plan.slug === 'business'
                            ? 'border-slate-900 bg-slate-50/60'
                            : 'border-slate-200'
                    "
                >
                    <div class="flex items-center gap-2">
                        <h2 class="font-semibold">{{ plan.name }}</h2>
                        <span
                            v-if="plan.slug === 'business'"
                            class="rounded-full bg-slate-900 px-2 py-0.5 text-[11px] font-medium text-white"
                        >
                            Le plus choisi
                        </span>
                    </div>

                    <p v-if="plan.description" class="mt-2 min-h-[2.5rem] text-sm leading-relaxed text-slate-500">
                        {{ plan.description }}
                    </p>

                    <!-- Le prix porte la hiérarchie de la carte : c'est
                         l'information qu'on vient chercher. -->
                    <p class="mt-6 flex items-baseline gap-1.5">
                        <span class="text-3xl font-semibold tracking-tight">{{ formatPrice(plan) }}</span>
                        <span v-if="plan.price_cents > 0" class="text-sm text-slate-500">
                            / {{ plan.interval === 'year' ? 'an' : 'mois' }}
                        </span>
                    </p>

                    <Link
                        :href="`/demande?plan=${plan.slug}`"
                        class="mt-6 w-full"
                        :class="plan.slug === 'business' ? 'btn-ink' : 'btn-secondary'"
                    >
                        Demander un accès
                    </Link>

                    <dl class="mt-7 space-y-2.5 border-t border-slate-100 pt-6 text-sm">
                        <div
                            v-for="quota in displayedQuotas(plan)"
                            :key="quota.label"
                            class="flex justify-between gap-3"
                        >
                            <dt class="text-slate-500">{{ quota.label }}</dt>
                            <dd class="font-medium tabular-nums">{{ quota.value }}</dd>
                        </div>
                    </dl>

                    <ul class="mt-5 space-y-2 text-sm">
                        <li
                            v-for="(label, key) in FEATURE_LABELS"
                            :key="key"
                            class="flex items-center gap-2.5"
                            :class="plan.features?.[key] ? 'text-slate-700' : 'text-slate-400'"
                        >
                            <span :class="plan.features?.[key] ? 'text-slate-900' : 'text-slate-300'">
                                {{ plan.features?.[key] ? '✓' : '—' }}
                            </span>
                            {{ label }}
                        </li>
                    </ul>
                </div>
            </div>

            <p class="mt-6 text-sm text-slate-500">
                Un code d'accès est nécessaire pour ouvrir un compte.
                <Link href="/demande" class="font-medium text-slate-900 underline">Demandez le vôtre</Link>,
                réponse sous 24 h ouvrées.
            </p>
        </section>

        <section class="mx-auto max-w-3xl px-6 pt-16">
            <h2 class="text-2xl font-semibold tracking-tight">Questions fréquentes</h2>

            <dl class="mt-8 divide-y divide-slate-100">
                <div v-for="item in FAQ" :key="item.q" class="py-6">
                    <dt class="font-medium">{{ item.q }}</dt>
                    <dd class="mt-2 leading-relaxed text-slate-600">{{ item.a }}</dd>
                </div>
            </dl>
        </section>
    </MarketingLayout>
</template>
