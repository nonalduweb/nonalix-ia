<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatMoney, formatPlanPrice } from '@/money';

const props = defineProps({
    plan: Object,
    metrics: Array,
});

const formatPrice = () => formatPlanPrice(props.plan);

const FEATURE_LABELS = {
    rag: 'Base de connaissances',
    api_access: 'Accès API',
    templates: 'Modèles de messages',
};
</script>

<template>
    <Head :title="plan.name" />

    <AdminLayout>
        <Link href="/plans" class="mb-4 inline-block text-sm text-slate-400 hover:underline">
            ← Plans
        </Link>

        <div class="mb-6">
            <h1 class="text-xl font-semibold">{{ plan.name }}</h1>
            <p class="text-sm text-slate-500">{{ plan.slug }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card">
                <p class="text-sm text-slate-500">Tarif</p>
                <p class="mt-1 text-2xl font-semibold">{{ formatPrice() }}</p>
                <p class="mt-2 text-xs" :class="plan.overage_policy === 'soft' ? 'text-amber-600' : 'text-slate-500'">
                    Dépassement {{ plan.overage_policy === 'soft' ? 'autorisé et facturé' : 'bloqué' }}
                </p>
            </div>

            <div class="card">
                <p class="text-sm text-slate-500">Entreprises abonnées</p>
                <p class="mt-1 text-2xl font-semibold">{{ plan.tenants_count }}</p>
                <Link href="/tenants" class="mt-2 inline-block text-xs text-slate-500 hover:underline">
                    Voir les entreprises →
                </Link>
            </div>

            <div class="card">
                <p class="text-sm text-slate-500">Visibilité</p>
                <p class="mt-1 text-sm">
                    {{ plan.is_active ? 'Actif' : 'Inactif' }} ·
                    {{ plan.is_public ? 'public' : 'privé' }}
                </p>
                <p class="mt-2 text-xs text-slate-500">
                    Un plan privé n'apparaît pas sur le site commercial mais reste
                    attribuable manuellement.
                </p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="card">
                <h2 class="mb-4 text-sm font-semibold">Quotas mensuels</h2>
                <dl class="space-y-2 text-sm">
                    <div v-for="metric in metrics" :key="metric" class="flex justify-between gap-4">
                        <dt class="font-mono text-xs text-slate-500">{{ metric }}</dt>
                        <dd class="tabular-nums">
                            <!-- Métrique absente du plan = pas de plafond. Une
                                 limite nulle se déclare explicitement à 0. -->
                            {{
                                plan.quotas?.[metric] !== undefined
                                    ? plan.quotas[metric].toLocaleString('fr-FR')
                                    : 'illimité'
                            }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <h2 class="mb-4 text-sm font-semibold">Fonctionnalités</h2>
                <ul class="space-y-2 text-sm">
                    <li v-for="(label, key) in FEATURE_LABELS" :key="key" class="flex items-center gap-2">
                        <span :class="plan.features?.[key] ? 'text-emerald-600' : 'text-slate-400'">
                            {{ plan.features?.[key] ? '✓' : '○' }}
                        </span>
                        {{ label }}
                    </li>
                </ul>

                <p v-if="plan.description" class="mt-4 text-sm text-slate-500">{{ plan.description }}</p>
            </section>
        </div>
    </AdminLayout>
</template>
