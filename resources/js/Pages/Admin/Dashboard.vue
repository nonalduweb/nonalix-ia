<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    tenants: Object,
    users: Object,
    volume: Object,
    incidents: Array,
    topConsumers: Array,
});

const euros = (micros) =>
    (micros / 100_000_000).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });

const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR') : '—');
</script>

<template>
    <Head title="Administration" />

    <AdminLayout>
        <PageHeader
            title="Vue d'ensemble"
            description="L'état de la plateforme entière : entreprises, volume et coût."
            icon="home"
            tone="brand"
        />

        <section class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link href="/tenants" class="card transition hover:border-brand-300">
                <p class="text-sm text-slate-500">Entreprises</p>
                <p class="mt-1 text-2xl font-semibold">{{ tenants.total }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ tenants.active }} actives · {{ tenants.trial }} en essai
                </p>
            </Link>

            <div class="card">
                <p class="text-sm text-slate-500">Nouvelles (30 j)</p>
                <p class="mt-1 text-2xl font-semibold">{{ tenants.new_30d }}</p>
                <p v-if="tenants.suspended" class="mt-1 text-xs text-red-600">
                    {{ tenants.suspended }} suspendue(s)
                </p>
            </div>

            <div class="card">
                <p class="text-sm text-slate-500">Messages (30 j)</p>
                <p class="mt-1 text-2xl font-semibold">{{ volume.messages_30d.toLocaleString('fr-FR') }}</p>
            </div>

            <Link href="/usage" class="card transition hover:border-brand-300">
                <p class="text-sm text-slate-500">Coût IA (30 j)</p>
                <p class="mt-1 text-2xl font-semibold">{{ euros(volume.ai_cost_30d_micros) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ users.total }} utilisateurs clients</p>
            </Link>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card p-0">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Incidents non résolus</h2>
                    <Link href="/incidents" class="text-xs text-slate-500 hover:underline">Tout voir</Link>
                </div>

                <div v-for="incident in incidents" :key="incident.id" class="border-b border-slate-100 px-5 py-3 last:border-0 dark:border-slate-800">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ incident.title }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ incident.tenant?.name ?? 'Plateforme' }} ·
                                {{ incident.source }} ·
                                {{ incident.occurrences }} occurrence(s) ·
                                {{ formatDateTime(incident.last_seen_at) }}
                            </p>
                        </div>
                        <StatusBadge :status="incident.level" />
                    </div>
                </div>

                <p v-if="!incidents.length" class="px-5 py-10 text-center text-sm text-slate-500">
                    Aucun incident ouvert.
                </p>
            </section>

            <section class="card p-0">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold">Clients les plus consommateurs (30 j)</h2>
                    <!-- C'est ici que la marge se joue : un client dont le coût IA
                         approche son abonnement doit être examiné. -->
                    <p class="mt-0.5 text-xs text-slate-500">Coût IA réel, hors abonnement.</p>
                </div>

                <div
                    v-for="row in topConsumers"
                    :key="row.tenant_id"
                    class="flex items-center justify-between border-b border-slate-100 px-5 py-3 text-sm last:border-0 dark:border-slate-800"
                >
                    <Link :href="`/tenants/${row.tenant_id}`" class="hover:underline">
                        {{ row.tenant?.name ?? 'Entreprise supprimée' }}
                    </Link>
                    <span class="text-right">
                        <span class="font-medium">{{ euros(row.cost_micros) }}</span>
                        <span class="ml-2 text-xs text-slate-500">{{ row.calls }} appels</span>
                    </span>
                </div>

                <p v-if="!topConsumers.length" class="px-5 py-10 text-center text-sm text-slate-500">
                    Aucune consommation enregistrée.
                </p>
            </section>
        </div>
    </AdminLayout>
</template>
