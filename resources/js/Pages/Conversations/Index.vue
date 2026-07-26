<script setup>
import { onMounted, onUnmounted, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    conversations: Object,
    filters: Object,
    counts: Object,
});

const page = usePage();

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    mine: Boolean(props.filters.mine),
    awaiting: Boolean(props.filters.awaiting),
});

const applyFilters = () => {
    router.get('/conversations', filters, {
        preserveState: true,
        replace: true,
    });
};

/*
 * Temps réel : la boîte de réception se réordonne dès qu'un message arrive,
 * sans rafraîchissement. `only` limite le rechargement à la seule liste, pour
 * ne pas refaire tous les comptages à chaque message.
 */
let channel = null;

onMounted(() => {
    const tenantId = page.props.tenant?.id;

    if (!tenantId || !window.Echo) return;

    channel = window.Echo.private(`tenant.${tenantId}.conversations`)
        .listen('.message.created', () => {
            router.reload({ only: ['conversations', 'counts'] });
        })
        .listen('.conversation.updated', () => {
            router.reload({ only: ['conversations', 'counts'] });
        });
});

onUnmounted(() => {
    const tenantId = page.props.tenant?.id;

    if (tenantId && window.Echo) {
        window.Echo.leave(`tenant.${tenantId}.conversations`);
    }
});

const relative = (iso) => {
    if (!iso) return '';

    const minutes = Math.round((Date.now() - new Date(iso)) / 60000);

    if (minutes < 1) return "à l'instant";
    if (minutes < 60) return `il y a ${minutes} min`;
    if (minutes < 1440) return `il y a ${Math.floor(minutes / 60)} h`;

    return new Date(iso).toLocaleDateString('fr-FR');
};
</script>

<template>
    <Head title="Conversations" />

    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">Conversations</h1>
            <div class="flex gap-2 text-sm">
                <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-slate-800">
                    {{ counts.open }} ouvertes
                </span>
                <span v-if="counts.awaiting" class="rounded-full bg-amber-100 px-3 py-1 text-amber-800">
                    {{ counts.awaiting }} en attente
                </span>
            </div>
        </div>

        <div class="card mb-4 flex flex-wrap items-center gap-3">
            <input
                v-model="filters.q"
                type="search"
                placeholder="Rechercher un contact…"
                class="input max-w-xs"
                @keyup.enter="applyFilters"
            />

            <select v-model="filters.status" class="input max-w-40" @change="applyFilters">
                <option value="">Tous les statuts</option>
                <option value="open">Ouvertes</option>
                <option value="pending">En attente</option>
                <option value="closed">Fermées</option>
            </select>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="filters.mine" type="checkbox" @change="applyFilters" />
                Les miennes
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="filters.awaiting" type="checkbox" @change="applyFilters" />
                En attente d'un humain
            </label>
        </div>

        <div class="card divide-y divide-slate-100 p-0 dark:divide-slate-800">
            <Link
                v-for="conversation in conversations.data"
                :key="conversation.id"
                :href="`/conversations/${conversation.id}`"
                class="flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">
                        {{ conversation.contact?.name || conversation.contact?.profile_name || '+' + conversation.contact?.wa_id }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ relative(conversation.last_message_at) }}
                        <span v-if="conversation.assigned_user"> · {{ conversation.assigned_user.name }}</span>
                    </p>
                </div>

                <span
                    v-if="conversation.handover_at"
                    class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800"
                >
                    humain requis
                </span>

                <span
                    v-else-if="conversation.ai_enabled"
                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800"
                >
                    IA active
                </span>

                <span
                    v-if="conversation.unread_count"
                    class="rounded-full bg-brand-600 px-2 py-0.5 text-xs text-white"
                >
                    {{ conversation.unread_count }}
                </span>
            </Link>

            <p v-if="!conversations.data.length" class="px-5 py-12 text-center text-sm text-slate-500">
                Aucune conversation pour ces critères.
            </p>
        </div>
    </AppLayout>
</template>
