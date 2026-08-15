<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
});

const filters = reactive({
    tenant_id: props.filters.tenant_id ?? '',
    action: props.filters.action ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let debounce = null;

watch(
    filters,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get('/audit-logs', filters, { preserveState: true, replace: true });
        }, 300);
    },
    { deep: true },
);

const inspecting = ref(null);

// Les actions les plus sensibles sont mises en avant : ce sont celles qu'on
// vient chercher ici après un incident de sécurité.
const CRITICAL_PREFIXES = ['platform.impersonation', 'platform.cross_tenant', 'admin.access_denied', 'auth.two_factor_failed'];

const isCritical = (action) => CRITICAL_PREFIXES.some((prefix) => action.startsWith(prefix));

const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR') : '—');
</script>

<template>
    <Head title="Journal d'audit" />

    <AdminLayout>
        <!-- Table insert-only : aucune route de modification ni de
             suppression n'existe, y compris pour un super-admin. -->
        <PageHeader
            title="Journal d'audit"
            description="Insert-only. Ces entrées ne peuvent être ni modifiées ni supprimées."
            icon="document"
            tone="slate"
        />

        <div class="card mb-4 flex flex-wrap gap-3">
            <input v-model="filters.action" type="search" placeholder="Action (ex. whatsapp.)" class="input max-w-xs" />
            <input v-model="filters.tenant_id" type="search" placeholder="ID d'entreprise" class="input max-w-xs font-mono text-xs" />
            <input v-model="filters.from" type="date" class="input max-w-40" />
            <input v-model="filters.to" type="date" class="input max-w-40" />
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Date</th>
                        <th class="px-5 py-3 font-medium">Action</th>
                        <th class="px-5 py-3 font-medium">Auteur</th>
                        <th class="px-5 py-3 font-medium">Entreprise</th>
                        <th class="px-5 py-3 font-medium">IP</th>
                        <th class="px-5 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="log in logs.data" :key="log.id">
                        <td class="px-5 py-2.5 text-xs whitespace-nowrap text-slate-500">
                            {{ formatDateTime(log.created_at) }}
                        </td>
                        <td class="px-5 py-2.5">
                            <span
                                class="font-mono text-xs"
                                :class="isCritical(log.action) && 'font-semibold text-red-600'"
                            >
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-5 py-2.5">
                            {{ log.user?.name ?? 'Système' }}
                            <span v-if="log.user?.email" class="block text-xs text-slate-500">{{ log.user.email }}</span>
                        </td>
                        <td class="px-5 py-2.5 text-slate-500">{{ log.tenant?.name ?? '—' }}</td>
                        <td class="px-5 py-2.5 font-mono text-xs text-slate-500">{{ log.ip_address ?? '—' }}</td>
                        <td class="px-5 py-2.5 text-right">
                            <button
                                v-if="log.changes || log.context"
                                class="text-xs text-slate-500 hover:underline"
                                @click="inspecting = log"
                            >
                                Détail
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!logs.data.length" class="px-5 py-12 text-center text-sm text-slate-500">
                Aucune entrée pour ces critères.
            </p>
        </div>

        <Pagination :paginator="logs" />

        <Modal :open="!!inspecting" :title="inspecting?.action" max-width="max-w-2xl" @close="inspecting = null">
            <p class="mb-4 text-xs text-slate-500">
                {{ formatDateTime(inspecting?.created_at) }} ·
                {{ inspecting?.user?.email ?? 'système' }} ·
                {{ inspecting?.ip_address ?? 'IP inconnue' }}
            </p>

            <div v-if="inspecting?.changes" class="mb-4">
                <h3 class="mb-2 text-xs uppercase tracking-wide text-slate-500">Modifications</h3>
                <!-- Les secrets sont remplacés par [masqué] à l'écriture :
                     le journal ne doit jamais devenir une source de fuite. -->
                <pre class="max-h-64 overflow-auto rounded-lg bg-slate-100 p-4 text-xs dark:bg-slate-800">{{
                    JSON.stringify(inspecting.changes, null, 2)
                }}</pre>
            </div>

            <div v-if="inspecting?.context">
                <h3 class="mb-2 text-xs uppercase tracking-wide text-slate-500">Contexte</h3>
                <pre class="max-h-64 overflow-auto rounded-lg bg-slate-100 p-4 text-xs dark:bg-slate-800">{{
                    JSON.stringify(inspecting.context, null, 2)
                }}</pre>
            </div>

            <div class="mt-5 flex justify-end">
                <button class="btn-secondary" @click="inspecting = null">Fermer</button>
            </div>
        </Modal>
    </AdminLayout>
</template>
