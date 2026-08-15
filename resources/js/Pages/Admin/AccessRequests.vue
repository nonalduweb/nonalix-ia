<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    requests: Array,
    plans: Array,
    pendingCount: Number,
});

const approving = ref(null);
const rejecting = ref(null);

const approveForm = useForm({ plan_id: '', trial_days: 14, expires_in: 30 });
const rejectForm = useForm({ review_note: '' });

const openApprove = (req) => {
    approveForm.reset();
    approveForm.clearErrors();
    // Le pack demandé est pré-sélectionné : c'est presque toujours celui
    // qu'on accorde, et le corriger reste possible.
    approveForm.plan_id = req.planId ?? props.plans[0]?.id ?? '';
    approving.value = req;
};

const approve = () =>
    approveForm.post(`/access-requests/${approving.value.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => (approving.value = null),
    });

const reject = () =>
    rejectForm.post(`/access-requests/${rejecting.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => (rejecting.value = null),
    });

const resend = (req) =>
    router.post(`/access-requests/${req.id}/resend`, {}, { preserveScroll: true });

const STATUS = {
    pending: { label: 'En attente', class: 'text-amber-600' },
    approved: { label: 'Approuvée', class: 'text-emerald-600' },
    rejected: { label: 'Refusée', class: 'text-slate-400' },
};

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <Head title="Demandes d'accès" />

    <AdminLayout>
        <PageHeader
            title="Demandes d'accès"
            description="Déposées depuis le site commercial. Approuver génère un code et l'envoie automatiquement au prospect."
            icon="inbox"
            tone="amber"
        />

        <div v-if="!requests.length" class="card text-sm text-slate-500">
            Aucune demande pour l'instant. Le formulaire est en ligne sur
            <span class="font-mono">nonalixia.com/demande</span>.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="req in requests"
                :key="req.id"
                class="card"
                :class="req.status === 'pending' && 'border-amber-300 dark:border-amber-800'"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold">{{ req.company }}</h2>
                            <span class="text-xs" :class="STATUS[req.status].class">
                                {{ STATUS[req.status].label }}
                            </span>
                        </div>

                        <p class="mt-1 text-sm text-slate-500">
                            {{ req.contactName }} ·
                            <a :href="`mailto:${req.email}`" class="underline">{{ req.email }}</a>
                            <span v-if="req.phone"> · {{ req.phone }}</span>
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Reçue le {{ formatDate(req.createdAt) }}
                            <span v-if="req.plan"> · pack souhaité : {{ req.plan }}</span>
                        </p>

                        <p v-if="req.message" class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            {{ req.message }}
                        </p>

                        <p v-if="req.code" class="mt-3 text-sm">
                            Code émis :
                            <span class="font-mono tracking-wider">{{ req.code }}</span>
                        </p>
                        <p v-if="req.reviewNote" class="mt-2 text-sm text-slate-500">
                            Motif : {{ req.reviewNote }}
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <template v-if="req.status === 'pending'">
                            <button class="btn-primary" @click="openApprove(req)">Approuver</button>
                            <button class="btn-secondary" @click="rejecting = req">Refuser</button>
                        </template>
                        <button
                            v-else-if="req.code"
                            class="btn-secondary"
                            @click="resend(req)"
                        >
                            Renvoyer le code
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Modal
            :open="!!approving"
            :title="`Approuver — ${approving?.company ?? ''}`"
            @close="approving = null"
        >
            <form class="space-y-4" @submit.prevent="approve">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Un code à usage unique sera généré et envoyé à
                    <strong>{{ approving?.email }}</strong>.
                </p>

                <div>
                    <label class="label" for="plan_id">Pack accordé</label>
                    <select id="plan_id" v-model="approveForm.plan_id" class="input" required>
                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                    </select>
                    <p v-if="approveForm.errors.plan_id" class="mt-1 text-sm text-red-600">
                        {{ approveForm.errors.plan_id }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="trial_days">Essai (jours)</label>
                        <input id="trial_days" v-model.number="approveForm.trial_days" type="number" min="0" max="365" class="input" required />
                    </div>
                    <div>
                        <label class="label" for="expires_in">Code valable (jours)</label>
                        <input id="expires_in" v-model.number="approveForm.expires_in" type="number" min="1" max="365" class="input" />
                        <p class="mt-1 text-xs text-slate-500">Vide = sans expiration</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary" @click="approving = null">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="approveForm.processing">
                        {{ approveForm.processing ? 'Envoi…' : 'Approuver et envoyer' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :open="!!rejecting" title="Refuser cette demande ?" @close="rejecting = null">
            <form class="space-y-4" @submit.prevent="reject">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Aucun e-mail n'est envoyé : un refus automatique serait brutal et sans
                    recours. Reprendre contact reste un geste commercial.
                </p>

                <div>
                    <label class="label" for="review_note">Motif interne</label>
                    <input id="review_note" v-model="rejectForm.review_note" type="text" class="input" maxlength="500" />
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="rejecting = null">Annuler</button>
                    <button type="submit" class="btn-primary bg-red-600 hover:bg-red-700">Refuser</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
