<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Icon from '@/Components/Icon.vue';

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
        <PageHeader
            title="Contacts"
            description="Toutes les personnes qui ont écrit à votre entreprise, tous canaux confondus."
            icon="users"
            tone="brand"
        >
            <template #meta>
                <p class="mt-2 text-sm text-slate-500 tabular-nums">{{ contacts.total }} au total</p>
            </template>
        </PageHeader>

        <div class="card mb-5 flex flex-wrap gap-3">
            <!-- L'icône est posée dans le champ plutôt qu'à côté : un champ de
                 recherche doit s'identifier avant d'être lu. -->
            <div class="relative max-w-xs flex-1">
                <Icon name="search" size="sm" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="filters.q"
                    type="search"
                    placeholder="Nom ou numéro…"
                    class="input pl-9"
                />
            </div>

            <select v-model="filters.status" class="input max-w-52">
                <option value="">Tous les consentements</option>
                <option value="opted_in">Abonnés</option>
                <option value="opted_out">Désabonnés</option>
                <option value="unknown">Non renseignés</option>
            </select>
        </div>

        <div class="card-flush">
            <!-- Le tableau déborde horizontalement plutôt que de comprimer ses
                 colonnes : un numéro coupé sur téléphone n'est plus un numéro. -->
            <div v-if="contacts.data.length" class="overflow-x-auto">
                <table class="w-full">
                    <thead class="table-head">
                        <tr>
                            <th class="th">Contact</th>
                            <th class="th">Numéro</th>
                            <th class="th">Consentement</th>
                            <th class="th">Dernier message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="contact in contacts.data" :key="contact.id" class="table-row">
                            <td class="td">
                                <Link
                                    :href="`/contacts/${contact.id}`"
                                    class="font-medium text-slate-900 hover:underline dark:text-white"
                                >
                                    {{ contact.name || contact.profile_name || 'Sans nom' }}
                                </Link>
                            </td>
                            <td class="td font-mono text-xs whitespace-nowrap text-slate-500">+{{ contact.wa_id }}</td>
                            <td class="td">
                                <StatusBadge
                                    :status="contact.opt_in_status"
                                    :label="OPT_IN_LABELS[contact.opt_in_status]"
                                />
                            </td>
                            <td class="td whitespace-nowrap text-slate-500 tabular-nums">
                                {{ formatDate(contact.last_message_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                icon="users"
                title="Aucun contact"
                description="Les contacts sont créés automatiquement à la réception du premier message WhatsApp."
            />
        </div>

        <Pagination :paginator="contacts" />
    </AppLayout>
</template>
