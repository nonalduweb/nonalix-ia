<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    incidents: Object,
    filters: Object,
});

const filters = reactive({
    level: props.filters.level ?? '',
    source: props.filters.source ?? '',
    resolved: Boolean(props.filters.resolved),
});

watch(filters, () => {
    router.get('/incidents', filters, { preserveState: true, replace: true });
});

const inspecting = ref(null);

const resolve = (incident) =>
    router.post(`/incidents/${incident.id}/resolve`, {}, {
        preserveScroll: true,
        onFinish: () => (inspecting.value = null),
    });

const LEVEL_LABELS = {
    info: 'Information',
    warning: 'Avertissement',
    error: 'Erreur',
    critical: 'Critique',
};

const SOURCES = ['whatsapp', 'ai', 'quota', 'webhook', 'system'];

const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR') : '—');
</script>

<template>
    <Head title="Incidents" />

    <AdminLayout>
        <h1 class="mb-6 text-xl font-semibold">Incidents</h1>

        <div class="card mb-4 flex flex-wrap items-center gap-4">
            <select v-model="filters.level" class="input max-w-44">
                <option value="">Tous les niveaux</option>
                <option v-for="(label, level) in LEVEL_LABELS" :key="level" :value="level">
                    {{ label }}
                </option>
            </select>

            <select v-model="filters.source" class="input max-w-44">
                <option value="">Toutes les sources</option>
                <option v-for="source in SOURCES" :key="source" :value="source">{{ source }}</option>
            </select>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="filters.resolved" type="checkbox" />
                Afficher les incidents résolus
            </label>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Incident</th>
                        <th class="px-5 py-3 font-medium">Entreprise</th>
                        <th class="px-5 py-3 font-medium">Occurrences</th>
                        <th class="px-5 py-3 font-medium">Dernière</th>
                        <th class="px-5 py-3 font-medium">Niveau</th>
                        <th class="px-5 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="incident in incidents.data"
                        :key="incident.id"
                        class="transition hover:bg-slate-50 dark:hover:bg-slate-800"
                        :class="incident.resolved_at && 'opacity-50'"
                    >
                        <td class="px-5 py-3">
                            <button class="text-left font-medium hover:underline" @click="inspecting = incident">
                                {{ incident.title }}
                            </button>
                            <p class="font-mono text-xs text-slate-500">{{ incident.source }} · {{ incident.code }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <Link
                                v-if="incident.tenant"
                                :href="`/tenants/${incident.tenant.id}`"
                                class="hover:underline"
                            >
                                {{ incident.tenant.name }}
                            </Link>
                            <span v-else class="text-slate-400">Plateforme</span>
                        </td>
                        <!-- Les occurrences identiques sont agrégées : une panne
                             de fournisseur produit une ligne, pas des milliers. -->
                        <td class="px-5 py-3 tabular-nums">{{ incident.occurrences }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ formatDateTime(incident.last_seen_at) }}</td>
                        <td class="px-5 py-3">
                            <StatusBadge :status="incident.level" :label="LEVEL_LABELS[incident.level]" />
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button
                                v-if="!incident.resolved_at"
                                class="text-xs text-slate-500 hover:underline"
                                @click="resolve(incident)"
                            >
                                Résoudre
                            </button>
                            <span v-else class="text-xs text-slate-400">
                                Résolu par {{ incident.resolver?.name ?? '—' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!incidents.data.length" class="px-5 py-12 text-center text-sm text-slate-500">
                Aucun incident pour ces critères.
            </p>
        </div>

        <Pagination :paginator="incidents" />

        <Modal :open="!!inspecting" :title="inspecting?.title" max-width="max-w-2xl" @close="inspecting = null">
            <dl class="mb-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Code</dt>
                    <dd class="font-mono text-xs">{{ inspecting?.code }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Source</dt>
                    <dd>{{ inspecting?.source }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Première occurrence</dt>
                    <dd>{{ formatDateTime(inspecting?.first_seen_at) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Dernière occurrence</dt>
                    <dd>{{ formatDateTime(inspecting?.last_seen_at) }}</dd>
                </div>
            </dl>

            <pre class="max-h-80 overflow-auto rounded-lg bg-slate-100 p-4 text-xs dark:bg-slate-800">{{
                JSON.stringify(inspecting?.context ?? {}, null, 2)
            }}</pre>

            <div class="mt-5 flex justify-end gap-3">
                <button class="btn-secondary" @click="inspecting = null">Fermer</button>
                <button v-if="!inspecting?.resolved_at" class="btn-primary" @click="resolve(inspecting)">
                    Marquer comme résolu
                </button>
            </div>

            <p class="mt-3 text-xs text-slate-500">
                Un incident résolu se rouvre automatiquement à la prochaine occurrence.
            </p>
        </Modal>
    </AdminLayout>
</template>
