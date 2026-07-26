<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';

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
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">Prospects</h1>
            <p class="text-sm text-slate-500">{{ leads.total }} au total</p>
        </div>

        <div class="card mb-4 flex flex-wrap items-center gap-4">
            <select v-model="filters.status" class="input max-w-48">
                <option value="">Tous les statuts</option>
                <option v-for="status in statuses" :key="status.value" :value="status.value">
                    {{ status.label }}
                </option>
            </select>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="filters.mine" type="checkbox" />
                Les miens
            </label>
        </div>

        <div class="card overflow-hidden p-0">
            <table v-if="leads.data.length" class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Contact</th>
                        <th class="px-5 py-3 font-medium">Besoin</th>
                        <th class="px-5 py-3 font-medium">Score</th>
                        <th class="px-5 py-3 font-medium">Statut</th>
                        <th class="px-5 py-3 font-medium">Attribué à</th>
                        <th class="px-5 py-3 font-medium">Créé</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="lead in leads.data"
                        :key="lead.id"
                        class="transition hover:bg-slate-50 dark:hover:bg-slate-800"
                    >
                        <td class="px-5 py-3">
                            <Link :href="`/leads/${lead.id}`" class="font-medium hover:underline">
                                {{ lead.contact?.name || lead.contact?.profile_name || '+' + lead.contact?.wa_id }}
                            </Link>
                        </td>
                        <td class="max-w-xs truncate px-5 py-3 text-slate-600 dark:text-slate-300">
                            {{ lead.qualification?.need || '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <span class="font-medium" :class="scoreClass(lead.score)">{{ lead.score }}</span>
                            <span class="text-xs text-slate-400">/100</span>
                            <span v-if="lead.qualified_by === 'ai'" class="ml-1 text-xs text-slate-400">IA</span>
                        </td>
                        <td class="px-5 py-3">
                            <StatusBadge :status="lead.status" :label="statusLabel(lead.status)" />
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ lead.assigned_user?.name || '—' }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ formatDate(lead.created_at) }}</td>
                    </tr>
                </tbody>
            </table>

            <EmptyState
                v-else
                title="Aucun prospect"
                description="L'agent IA crée un prospect dès qu'un contact exprime un besoin concret, si l'outil de qualification est activé."
            >
                <Link href="/settings/agent" class="btn-secondary text-sm">Configurer l'agent</Link>
            </EmptyState>
        </div>

        <Pagination :paginator="leads" />
    </AppLayout>
</template>
