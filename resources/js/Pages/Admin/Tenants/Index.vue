<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatMoney, formatPlanPrice } from '@/money';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    tenants: Object,
    filters: Object,
    statuses: Array,
    plans: Array,
});

const formatPrice = (plan) =>
    plan.price_cents === 0 ? 'gratuit' : `${formatMoney(plan.price_cents, plan.currency)} / mois`;

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

let debounce = null;

watch(
    filters,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get('/tenants', filters, { preserveState: true, replace: true });
        }, 300);
    },
    { deep: true },
);

const creating = ref(false);

const form = useForm({
    name: '',
    slug: '',
    plan_id: '',
    owner_name: '',
    owner_email: '',
    trial_days: 14,
});

// Slug dérivé du nom, tant que l'utilisateur ne l'a pas édité lui-même.
const slugTouched = ref(false);

watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = name
                .toLowerCase()
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
        }
    },
);

const submit = () =>
    form.post('/tenants', {
        onSuccess: () => {
            form.reset();
            slugTouched.value = false;
            creating.value = false;
        },
    });

const statusLabel = (value) => props.statuses.find((s) => s.value === value)?.label ?? value;

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';
</script>

<template>
    <Head title="Entreprises" />

    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">Entreprises</h1>
            <button class="btn-primary" @click="creating = true">Créer une entreprise</button>
        </div>

        <div class="card mb-4 flex flex-wrap gap-3">
            <input v-model="filters.q" type="search" placeholder="Nom ou identifiant…" class="input max-w-xs" />
            <select v-model="filters.status" class="input max-w-48">
                <option value="">Tous les statuts</option>
                <option v-for="status in statuses" :key="status.value" :value="status.value">
                    {{ status.label }}
                </option>
            </select>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Entreprise</th>
                        <th class="px-5 py-3 font-medium">Plan</th>
                        <th class="px-5 py-3 font-medium">Utilisateurs</th>
                        <th class="px-5 py-3 font-medium">Statut</th>
                        <th class="px-5 py-3 font-medium">Créée</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="tenant in tenants.data" :key="tenant.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="px-5 py-3">
                            <Link :href="`/tenants/${tenant.id}`" class="font-medium hover:underline">
                                {{ tenant.name }}
                            </Link>
                            <p class="text-xs text-slate-500">{{ tenant.slug }}</p>
                        </td>
                        <td class="px-5 py-3">{{ tenant.plan?.name ?? '—' }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ tenant.users_count }}</td>
                        <td class="px-5 py-3">
                            <StatusBadge :status="tenant.status" :label="statusLabel(tenant.status)" />
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ formatDate(tenant.created_at) }}</td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!tenants.data.length" class="px-5 py-12 text-center text-sm text-slate-500">
                Aucune entreprise pour ces critères.
            </p>
        </div>

        <Pagination :paginator="tenants" />

        <Modal :open="creating" title="Créer une entreprise" @close="creating = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="label" for="name">Raison sociale</label>
                    <input id="name" v-model="form.name" type="text" class="input" required maxlength="160" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="label" for="slug">Identifiant</label>
                    <input
                        id="slug"
                        v-model="form.slug"
                        type="text"
                        class="input font-mono text-sm"
                        required
                        @input="slugTouched = true"
                    />
                    <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">{{ form.errors.slug }}</p>
                </div>

                <div>
                    <label class="label" for="plan_id">Plan</label>
                    <select id="plan_id" v-model="form.plan_id" class="input" required>
                        <option value="" disabled>Choisir un plan</option>
                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                            {{ plan.name }} — {{ formatPrice(plan) }}
                        </option>
                    </select>
                    <p v-if="form.errors.plan_id" class="mt-1 text-sm text-red-600">{{ form.errors.plan_id }}</p>
                </div>

                <hr class="border-slate-100 dark:border-slate-800" />

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="owner_name">Nom du responsable</label>
                        <input id="owner_name" v-model="form.owner_name" type="text" class="input" required />
                    </div>
                    <div>
                        <label class="label" for="trial_days">Essai (jours)</label>
                        <input id="trial_days" v-model.number="form.trial_days" type="number" min="0" max="365" class="input" />
                    </div>
                </div>

                <div>
                    <label class="label" for="owner_email">E-mail du responsable</label>
                    <input id="owner_email" v-model="form.owner_email" type="email" class="input" required />
                    <p v-if="form.errors.owner_email" class="mt-1 text-sm text-red-600">{{ form.errors.owner_email }}</p>
                </div>

                <!-- Aucun mot de passe généré ni transmis : le responsable passe
                     par la réinitialisation. -->
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    Le responsable reçoit le rôle « propriétaire » et définit son mot de
                    passe via un lien de réinitialisation.
                </p>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary" @click="creating = false">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Créer</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
