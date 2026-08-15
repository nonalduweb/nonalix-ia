<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    lead: Object,
});

const form = useForm({
    status: props.lead.status,
    score: props.lead.score,
    lost_reason: props.lead.lost_reason ?? '',
    next_action_at: props.lead.next_action_at?.slice(0, 10) ?? '',
});

const STATUSES = [
    { value: 'new', label: 'Nouveau' },
    { value: 'contacted', label: 'Contacté' },
    { value: 'qualified', label: 'Qualifié' },
    { value: 'unqualified', label: 'Non qualifié' },
    { value: 'won', label: 'Gagné' },
    { value: 'lost', label: 'Perdu' },
];

const LABELS = {
    need: 'Besoin',
    budget: 'Budget',
    timeframe: 'Échéance',
    contact_name: 'Nom donné',
};

const save = () => form.put(`/leads/${props.lead.id}`, { preserveScroll: true });

const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR') : '—');
</script>

<template>
    <Head title="Prospect" />

    <AppLayout>
        <Link href="/leads" class="mb-4 inline-block text-sm text-slate-500 hover:underline">
            ← Prospects
        </Link>

        <PageHeader
            :title="lead.contact?.name || lead.contact?.profile_name || '+' + lead.contact?.wa_id"
            :description="`Créé le ${formatDateTime(lead.created_at)} · source ${lead.source}`"
            icon="target"
            tone="emerald"
        >
            <template #actions>
                <StatusBadge :status="lead.status" />
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="card">
                    <h2 class="mb-4 text-sm font-semibold">Qualification</h2>

                    <!-- Distinction assumée : une équipe commerciale ne traite pas
                         de la même façon un score calculé par un LLM et une
                         qualification validée par un collègue. -->
                    <p v-if="lead.qualified_by" class="mb-4 rounded-lg px-3 py-2 text-xs"
                       :class="lead.qualified_by === 'ai'
                           ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                           : 'bg-emerald-50 text-emerald-800'">
                        {{
                            lead.qualified_by === 'ai'
                                ? "Qualification estimée par l'agent IA — à confirmer."
                                : 'Qualification validée par un membre de l\'équipe.'
                        }}
                    </p>

                    <dl v-if="Object.keys(lead.qualification || {}).length" class="space-y-3 text-sm">
                        <div v-for="(value, key) in lead.qualification" :key="key">
                            <dt class="text-xs uppercase tracking-wide text-slate-500">
                                {{ LABELS[key] ?? key }}
                            </dt>
                            <dd class="mt-0.5">{{ value }}</dd>
                        </div>
                    </dl>
                    <p v-else class="text-sm text-slate-500">Aucune information recueillie.</p>

                    <p v-if="lead.intent" class="mt-4 text-sm">
                        <span class="text-xs uppercase tracking-wide text-slate-500">Intention</span><br />
                        {{ lead.intent }}
                    </p>
                </section>

                <section v-if="lead.conversation" class="card">
                    <h2 class="mb-2 text-sm font-semibold">Conversation d'origine</h2>
                    <Link :href="`/conversations/${lead.conversation.id}`" class="text-sm text-brand-600 hover:underline">
                        Ouvrir le fil de discussion →
                    </Link>
                </section>
            </div>

            <aside>
                <form class="card space-y-4" @submit.prevent="save">
                    <h2 class="text-sm font-semibold">Suivi commercial</h2>

                    <div>
                        <label class="label" for="status">Statut</label>
                        <select id="status" v-model="form.status" class="input">
                            <option v-for="status in STATUSES" :key="status.value" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="label" for="score">Score ({{ form.score }}/100)</label>
                        <input id="score" v-model.number="form.score" type="range" min="0" max="100" class="w-full" />
                    </div>

                    <div v-if="form.status === 'lost'">
                        <label class="label" for="lost_reason">Motif de la perte</label>
                        <input id="lost_reason" v-model="form.lost_reason" type="text" class="input" maxlength="160" />
                    </div>

                    <div>
                        <label class="label" for="next_action_at">Prochaine action</label>
                        <input id="next_action_at" v-model="form.next_action_at" type="date" class="input" />
                    </div>

                    <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                        Enregistrer
                    </button>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
