<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    period: String,
    counters: Object,
    byModel: Array,
    periods: Array,
});

const euros = (micros) =>
    (micros / 100_000_000).toLocaleString('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 2,
    });

const changePeriod = (event) =>
    router.get('/usage', { period: event.target.value }, { preserveState: true });

const totalCost = computed(() =>
    props.byModel.reduce((sum, row) => sum + Number(row.cost_micros), 0),
);

// Les lignes sont groupées par tenant côté serveur ; on les aplatit pour
// afficher un tableau par entreprise.
const tenantRows = computed(() =>
    Object.entries(props.counters).map(([tenantId, rows]) => ({
        tenantId,
        name: rows[0]?.tenant?.name ?? 'Entreprise supprimée',
        metrics: Object.fromEntries(rows.map((row) => [row.metric, Number(row.value)])),
    })),
);
</script>

<template>
    <Head title="Consommation" />

    <AdminLayout>
        <PageHeader
            title="Consommation"
            description="Compteurs consolidés depuis Redis toutes les quinze minutes."
            icon="trending"
            tone="violet"
        >
            <template #actions>
                <select class="input max-w-40" :value="period" @change="changePeriod">
                    <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                </select>
            </template>
        </PageHeader>

        <section class="card mb-6 p-0">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold">Coût IA par modèle — mois en cours</h2>
                <p class="text-sm">
                    Total <strong>{{ euros(totalCost) }}</strong>
                </p>
            </div>

            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Fournisseur</th>
                        <th class="px-5 py-3 font-medium">Modèle</th>
                        <th class="px-5 py-3 font-medium">Appels</th>
                        <th class="px-5 py-3 font-medium">Tokens entrée</th>
                        <th class="px-5 py-3 font-medium">Tokens sortie</th>
                        <th class="px-5 py-3 font-medium">Latence moy.</th>
                        <th class="px-5 py-3 font-medium">Coût</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="row in byModel" :key="row.provider + row.model">
                        <td class="px-5 py-3">{{ row.provider }}</td>
                        <td class="px-5 py-3 font-mono text-xs">{{ row.model }}</td>
                        <td class="px-5 py-3 tabular-nums">{{ Number(row.calls).toLocaleString('fr-FR') }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-500">
                            {{ Number(row.input_tokens).toLocaleString('fr-FR') }}
                        </td>
                        <td class="px-5 py-3 tabular-nums text-slate-500">
                            {{ Number(row.output_tokens).toLocaleString('fr-FR') }}
                        </td>
                        <td class="px-5 py-3 tabular-nums text-slate-500">{{ row.avg_latency_ms }} ms</td>
                        <td class="px-5 py-3 font-medium tabular-nums">{{ euros(row.cost_micros) }}</td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!byModel.length" class="px-5 py-10 text-center text-sm text-slate-500">
                Aucun appel IA sur la période.
            </p>
        </section>

        <section class="card p-0">
            <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">
                Compteurs par entreprise — {{ period }}
            </h2>

            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Entreprise</th>
                        <th class="px-5 py-3 font-medium">Msg. envoyés</th>
                        <th class="px-5 py-3 font-medium">Msg. reçus</th>
                        <th class="px-5 py-3 font-medium">Requêtes IA</th>
                        <th class="px-5 py-3 font-medium">Documents</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="row in tenantRows" :key="row.tenantId">
                        <td class="px-5 py-3">
                            <Link :href="`/tenants/${row.tenantId}`" class="hover:underline">{{ row.name }}</Link>
                        </td>
                        <td class="px-5 py-3 tabular-nums">{{ (row.metrics.messages_sent ?? 0).toLocaleString('fr-FR') }}</td>
                        <td class="px-5 py-3 tabular-nums">{{ (row.metrics.messages_received ?? 0).toLocaleString('fr-FR') }}</td>
                        <td class="px-5 py-3 tabular-nums">{{ (row.metrics.ai_requests ?? 0).toLocaleString('fr-FR') }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-500">{{ row.metrics.documents_stored ?? 0 }}</td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!tenantRows.length" class="px-5 py-10 text-center text-sm text-slate-500">
                Aucun compteur consolidé pour cette période.
            </p>
        </section>
    </AdminLayout>
</template>
