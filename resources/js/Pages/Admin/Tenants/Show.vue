<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    tenant: Object,
    users: Array,
    usage: Object,
    plans: Array,
});

const suspending = ref(false);
const impersonating = ref(false);

const suspendForm = useForm({ reason: '' });
const impersonateForm = useForm({ reason: '', user_id: '' });
const planForm = useForm({ plan_id: props.tenant.plan_id });
const quotaForm = useForm({
    quota_overrides: { ...(props.tenant.quota_overrides ?? {}) },
});

const suspend = () =>
    suspendForm.post(`/tenants/${props.tenant.id}/suspend`, {
        preserveScroll: true,
        onSuccess: () => {
            suspendForm.reset();
            suspending.value = false;
        },
    });

const reactivate = () =>
    router.post(`/tenants/${props.tenant.id}/reactivate`, {}, { preserveScroll: true });

// Une invitation se perd ou expire : sans ce renvoi, un propriétaire qui n'a
// jamais reçu son lien ne peut plus entrer du tout.
const resendInvitation = () =>
    router.post(`/tenants/${props.tenant.id}/resend-invitation`, {}, { preserveScroll: true });

const changePlan = () =>
    planForm.put(`/tenants/${props.tenant.id}/plan`, { preserveScroll: true });

const saveQuotas = () =>
    quotaForm.put(`/tenants/${props.tenant.id}/quotas`, { preserveScroll: true });

const impersonate = () => impersonateForm.post(`/tenants/${props.tenant.id}/impersonate`);

const setOverride = (metric, event) => {
    const value = event.target.value;

    // Champ vidé = suppression de la dérogation, retour au quota du plan.
    if (value === '') {
        delete quotaForm.quota_overrides[metric];
    } else {
        quotaForm.quota_overrides[metric] = Number(value);
    }
};

const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR') : '—');
</script>

<template>
    <Head :title="tenant.name" />

    <AdminLayout>
        <Link href="/tenants" class="mb-4 inline-block text-sm text-slate-400 hover:underline">
            ← Entreprises
        </Link>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">{{ tenant.name }}</h1>
                <p class="text-sm text-slate-500">
                    {{ tenant.slug }} · créée le {{ formatDateTime(tenant.created_at) }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <StatusBadge :status="tenant.status" />
                <button class="btn-secondary text-sm" @click="impersonating = true">
                    Assistance
                </button>
                <button class="btn-secondary text-sm" @click="resendInvitation">
                    Renvoyer l'invitation
                </button>
                <button
                    v-if="tenant.status !== 'suspended'"
                    class="btn-secondary text-sm text-red-600"
                    @click="suspending = true"
                >
                    Suspendre
                </button>
                <button v-else class="btn-primary text-sm" @click="reactivate">Réactiver</button>
            </div>
        </div>

        <div
            v-if="tenant.suspension_reason"
            class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            Suspendue le {{ formatDateTime(tenant.suspended_at) }} — {{ tenant.suspension_reason }}
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- Consommation -->
                <section class="card">
                    <h2 class="mb-4 text-sm font-semibold">Consommation du mois</h2>
                    <form class="space-y-3" @submit.prevent="saveQuotas">
                        <div v-for="(quota, metric) in usage" :key="metric" class="text-sm">
                            <div class="flex items-center gap-3">
                                <span class="w-44 shrink-0 font-mono text-xs">{{ metric }}</span>
                                <span class="w-32 shrink-0 tabular-nums">
                                    {{ quota.used.toLocaleString('fr-FR') }} / {{ quota.limit?.toLocaleString('fr-FR') ?? '∞' }}
                                </span>
                                <div v-if="quota.limit" class="h-1.5 flex-1 rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div
                                        class="h-1.5 rounded-full"
                                        :class="quota.used / quota.limit >= 0.8 ? 'bg-amber-500' : 'bg-brand-500'"
                                        :style="{ width: Math.min(100, (quota.used / quota.limit) * 100) + '%' }"
                                    />
                                </div>
                                <input
                                    type="number"
                                    min="0"
                                    class="input w-32 py-1 text-xs"
                                    placeholder="dérogation"
                                    :value="quotaForm.quota_overrides[metric] ?? ''"
                                    @input="setOverride(metric, $event)"
                                />
                            </div>
                        </div>

                        <!-- Une dérogation écrase le quota du plan pour cette
                             métrique seulement, sans changer d'abonnement. -->
                        <p class="text-xs text-slate-500">
                            Une dérogation remplace la limite du plan pour cette métrique.
                            Champ vide = retour au quota du plan.
                        </p>

                        <button type="submit" class="btn-secondary text-sm" :disabled="quotaForm.processing">
                            Enregistrer les dérogations
                        </button>
                    </form>
                </section>

                <!-- Utilisateurs -->
                <section class="card p-0">
                    <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">
                        Utilisateurs ({{ users.length }})
                    </h2>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-5 py-3">
                                    <p class="font-medium">{{ user.name }}</p>
                                    <p class="text-xs text-slate-500">{{ user.email }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-500">
                                    {{ user.roles?.map((r) => r.name).join(', ') || '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <StatusBadge :status="user.status" />
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-500">
                                    {{ formatDateTime(user.last_login_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <aside class="space-y-6">
                <form class="card space-y-3" @submit.prevent="changePlan">
                    <h2 class="text-sm font-semibold">Abonnement</h2>
                    <select v-model="planForm.plan_id" class="input">
                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                    </select>
                    <p v-if="tenant.trial_ends_at" class="text-xs text-slate-500">
                        Essai jusqu'au {{ formatDateTime(tenant.trial_ends_at) }}
                    </p>
                    <button type="submit" class="btn-secondary w-full text-sm" :disabled="planForm.processing">
                        Changer de plan
                    </button>
                </form>

                <section class="card">
                    <h2 class="mb-2 text-sm font-semibold">Accès aux données</h2>
                    <!-- L'administration ne lit jamais les conversations d'un
                         client directement : l'assistance passe par une
                         impersonation tracée et limitée dans le temps. -->
                    <p class="text-xs text-slate-500">
                        Les conversations et contacts de ce client ne sont pas consultables
                        depuis l'administration. Utilisez l'assistance : elle emprunte
                        l'identité d'un utilisateur du client, est limitée dans le temps
                        et entièrement journalisée.
                    </p>
                </section>
            </aside>
        </div>

        <!-- Suspension -->
        <Modal :open="suspending" title="Suspendre cette entreprise ?" @close="suspending = false">
            <form class="space-y-4" @submit.prevent="suspend">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    L'accès sera coupé immédiatement pour tous les utilisateurs.
                    Les données sont conservées et les messages entrants continuent
                    d'être reçus.
                </p>

                <div>
                    <label class="label" for="reason">Motif</label>
                    <textarea id="reason" v-model="suspendForm.reason" rows="3" class="input resize-none" required maxlength="500" />
                    <p class="mt-1 text-xs text-slate-500">
                        Enregistré dans le journal d'audit. Une suspension sans
                        justification écrite est indéfendable auprès du client.
                    </p>
                    <p v-if="suspendForm.errors.reason" class="mt-1 text-sm text-red-600">
                        {{ suspendForm.errors.reason }}
                    </p>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="suspending = false">Annuler</button>
                    <button type="submit" class="btn-primary bg-red-600 hover:bg-red-700" :disabled="suspendForm.processing">
                        Suspendre
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Impersonation -->
        <Modal :open="impersonating" title="Démarrer une session d'assistance" @close="impersonating = false">
            <form class="space-y-4" @submit.prevent="impersonate">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Vous agirez sous l'identité d'un utilisateur de ce client, avec ses
                    permissions. La session est limitée à 60 minutes et un bandeau
                    permanent le signale.
                </p>

                <div>
                    <label class="label" for="user_id">Utilisateur</label>
                    <select id="user_id" v-model="impersonateForm.user_id" class="input">
                        <option value="">Le propriétaire du compte</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} — {{ user.email }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="label" for="impersonate_reason">Motif</label>
                    <textarea
                        id="impersonate_reason"
                        v-model="impersonateForm.reason"
                        rows="2"
                        class="input resize-none"
                        required
                        minlength="10"
                        maxlength="500"
                        placeholder="Ticket #1234 — l'agent ne répond pas depuis ce matin"
                    />
                    <p v-if="impersonateForm.errors.reason" class="mt-1 text-sm text-red-600">
                        {{ impersonateForm.errors.reason }}
                    </p>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="impersonating = false">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="impersonateForm.processing">
                        Démarrer
                    </button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
