<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    leads: Object,
    filters: Object,
    statuses: Array,
    operators: Array,
});

const filters = reactive({
    status: props.filters.status ?? '',
    mine: Boolean(props.filters.mine),
});

watch(filters, () => {
    router.get('/leads', filters, { preserveState: true, replace: true });
});

const statusLabel = (value) =>
    props.statuses.find((s) => s.value === value)?.label ?? value;

// Le score vient d'un modèle probabiliste : on l'affiche comme une estimation,
// pas comme une vérité. La couleur reste sobre en dessous du seuil de 50.
const scoreClass = (score) =>
    score >= 75 ? 'text-emerald-600' : score >= 50 ? 'text-blue-600' : 'text-slate-500';

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—';
</script>

<template>
    <Head title="Prospects" />

    <AppLayout>
        <PageHeader
            title="Prospects"
            description="Les contacts dont l'agent a identifié un besoin concret, classés par maturité d'achat."
            icon="target"
            tone="emerald"
        >
            <template #meta>
                <p class="mt-2 text-sm text-slate-500 tabular-nums">{{ leads.total }} au total</p>
            </template>
        </PageHeader>

        <div class="card mb-5 flex flex-wrap items-center gap-4">
            <select v-model="filters.status" class="input max-w-52">
                <option value="">Tous les statuts</option>
                <option v-for="status in statuses" :key="status.value" :value="status.value">
                    {{ status.label }}
                </option>
            </select>

            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 select-none dark:text-slate-300">
                <input v-model="filters.mine" type="checkbox" class="rounded border-slate-300" />
                Les miens
            </label>
        </div>

        <div class="card-flush">
            <div v-if="leads.data.length" class="overflow-x-auto">
                <table class="w-full">
                    <thead class="table-head">
                        <tr>
                            <th class="th">Contact</th>
                            <th class="th">Besoin</th>
                            <th class="th">Score</th>
                            <th class="th">Statut</th>
                            <th class="th">Attribué à</th>
                            <th class="th">Créé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lead in leads.data" :key="lead.id" class="table-row">
                            <td class="td whitespace-nowrap">
                                <Link
                                    :href="`/leads/${lead.id}`"
                                    class="font-medium text-slate-900 hover:underline dark:text-white"
                                >
                                    {{ lead.contact?.name || lead.contact?.profile_name || '+' + lead.contact?.wa_id }}
                                </Link>
                            </td>
                            <td class="td max-w-xs truncate">{{ lead.qualification?.need || '—' }}</td>
                            <td class="td whitespace-nowrap">
                                <span class="font-semibold tabular-nums" :class="scoreClass(lead.score)">
                                    {{ lead.score }}
                                </span>
                                <span class="text-xs text-slate-400">/100</span>
                                <!-- Distingue un score calculé par l'agent d'une
                                     appréciation saisie par un opérateur. -->
                                <span v-if="lead.qualified_by === 'ai'" class="ml-1.5 text-[11px] text-slate-400">IA</span>
                            </td>
                            <td class="td">
                                <StatusBadge :status="lead.status" :label="statusLabel(lead.status)" />
                            </td>
                            <td class="td whitespace-nowrap text-slate-500">{{ lead.assigned_user?.name || '—' }}</td>
                            <td class="td whitespace-nowrap text-slate-500 tabular-nums">
                                {{ formatDate(lead.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                icon="target"
                tone="emerald"
                title="Aucun prospect"
                description="L'agent IA crée un prospect dès qu'un contact exprime un besoin concret, si l'outil de qualification est activé."
            >
                <Link href="/settings/agent" class="btn-secondary">Configurer l'agent</Link>
            </EmptyState>
        </div>

        <Pagination :paginator="leads" />
    </AppLayout>
</template>
