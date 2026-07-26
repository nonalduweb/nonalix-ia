<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    contacts: Object,
    filters: Object,
});

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

let debounce = null;

// Recherche différée : une requête par frappe saturerait le serveur pour rien.
watch(
    filters,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get('/contacts', filters, { preserveState: true, replace: true });
        }, 300);
    },
    { deep: true },
);

const OPT_IN_LABELS = {
    unknown: 'Non renseigné',
    opted_in: 'Abonné',
    opted_out: 'Désabonné',
};

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—';
</script>

<template>
    <Head title="Contacts" />

    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">Contacts</h1>
            <p class="text-sm text-slate-500">{{ contacts.total }} au total</p>
        </div>

        <div class="card mb-4 flex flex-wrap gap-3">
            <input
                v-model="filters.q"
                type="search"
                placeholder="Nom ou numéro…"
                class="input max-w-xs"
            />
            <select v-model="filters.status" class="input max-w-48">
                <option value="">Tous les consentements</option>
                <option value="opted_in">Abonnés</option>
                <option value="opted_out">Désabonnés</option>
                <option value="unknown">Non renseignés</option>
            </select>
        </div>

        <div class="card overflow-hidden p-0">
            <table v-if="contacts.data.length" class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Contact</th>
                        <th class="px-5 py-3 font-medium">Numéro</th>
                        <th class="px-5 py-3 font-medium">Consentement</th>
                        <th class="px-5 py-3 font-medium">Dernier message</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="contact in contacts.data"
                        :key="contact.id"
                        class="transition hover:bg-slate-50 dark:hover:bg-slate-800"
                    >
                        <td class="px-5 py-3">
                            <Link :href="`/contacts/${contact.id}`" class="font-medium hover:underline">
                                {{ contact.name || contact.profile_name || 'Sans nom' }}
                            </Link>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs text-slate-500">+{{ contact.wa_id }}</td>
                        <td class="px-5 py-3">
                            <StatusBadge
                                :status="contact.opt_in_status"
                                :label="OPT_IN_LABELS[contact.opt_in_status]"
                            />
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ formatDate(contact.last_message_at) }}</td>
                    </tr>
                </tbody>
            </table>

            <EmptyState
                v-else
                title="Aucun contact"
                description="Les contacts sont créés automatiquement à la réception du premier message WhatsApp."
            />
        </div>

        <Pagination :paginator="contacts" />
    </AppLayout>
</template>
