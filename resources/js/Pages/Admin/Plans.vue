<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import { formatPlanPrice, toMajor, toMinor } from '@/money';

const props = defineProps({
    plans: Array,
    metrics: Array,
});

const editing = ref(null);
const deleting = ref(null);
const priceInEuros = ref('');

const emptyQuotas = () => Object.fromEntries(props.metrics.map((metric) => [metric, 0]));

const form = useForm({
    name: '',
    slug: '',
    description: '',
    price_cents: 0,
    currency: 'EUR',
    interval: 'month',
    quotas: emptyQuotas(),
    features: { rag: true, api_access: false, templates: false },
    overage_policy: 'block',
    is_active: true,
    is_public: true,
    position: 0,
});

const openCreate = () => {
    form.defaults({
        name: '', slug: '', description: '', price_cents: 0, currency: 'EUR',
        interval: 'month', quotas: emptyQuotas(),
        features: { rag: true, api_access: false, templates: false },
        overage_policy: 'block', is_active: true, is_public: true, position: 0,
    });
    form.reset();
    form.clearErrors();
    priceInEuros.value = '0';
    editing.value = 'new';
};

const openEdit = (plan) => {
    form.defaults({
        ...plan,
        quotas: { ...emptyQuotas(), ...(plan.quotas ?? {}) },
        features: { rag: false, api_access: false, templates: false, ...(plan.features ?? {}) },
    });
    form.reset();
    form.clearErrors();
    priceInEuros.value = toMajor(plan.price_cents, plan.currency).toString();
    editing.value = plan;
};

const submit = () => {
    // Converti selon la devise : le franc CFA n'a pas de sous-unite, un
    // facteur cent y multiplierait le tarif par cent.
    form.price_cents = toMinor(priceInEuros.value, form.currency);

    const options = { preserveScroll: true, onSuccess: () => (editing.value = null) };

    editing.value === 'new'
        ? form.post('/plans', options)
        : form.put(`/plans/${editing.value.slug}`, options);
};

const remove = () =>
    router.delete(`/plans/${deleting.value.slug}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });

const formatPrice = (plan) => formatPlanPrice(plan);
</script>

<template>
    <Head title="Plans" />

    <AdminLayout>
        <PageHeader
            title="Plans"
            description="Le catalogue commercial : tarifs, quotas et fonctionnalités de chaque formule."
            icon="money"
            tone="amber"
        >
            <template #actions>
                <button class="btn-primary" @click="openCreate">Créer un plan</button>
            </template>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div v-for="plan in plans" :key="plan.id" class="card flex flex-col">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h2 class="font-semibold">{{ plan.name }}</h2>
                        <p class="text-xs text-slate-500">{{ plan.slug }}</p>
                    </div>
                    <span v-if="!plan.is_public" class="text-xs text-slate-400">privé</span>
                </div>

                <p class="mb-3 text-2xl font-semibold">{{ formatPrice(plan) }}</p>

                <p v-if="plan.description" class="mb-3 text-sm text-slate-500">{{ plan.description }}</p>

                <dl class="mb-4 space-y-1 text-xs">
                    <div v-for="(value, metric) in plan.quotas" :key="metric" class="flex justify-between gap-2">
                        <dt class="truncate text-slate-500">{{ metric }}</dt>
                        <dd class="tabular-nums">{{ value.toLocaleString('fr-FR') }}</dd>
                    </div>
                </dl>

                <p class="mb-4 text-xs" :class="plan.overage_policy === 'soft' ? 'text-amber-600' : 'text-slate-500'">
                    Dépassement : {{ plan.overage_policy === 'soft' ? 'autorisé et facturé' : 'bloqué' }}
                </p>

                <p class="mt-auto text-xs text-slate-500">{{ plan.tenants_count }} entreprise(s)</p>

                <div class="mt-3 flex gap-3 text-xs">
                    <button class="text-slate-500 hover:underline" @click="openEdit(plan)">Modifier</button>
                    <Link :href="`/plans/${plan.slug}`" class="text-slate-500 hover:underline">Détail</Link>
                    <button
                        v-if="!plan.tenants_count"
                        class="text-red-600 hover:underline"
                        @click="deleting = plan"
                    >
                        Supprimer
                    </button>
                </div>
            </div>
        </div>

        <Modal
            :open="!!editing"
            :title="editing === 'new' ? 'Nouveau plan' : 'Modifier le plan'"
            max-width="max-w-2xl"
            @close="editing = null"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="plan_name">Nom</label>
                        <input id="plan_name" v-model="form.name" type="text" class="input" required maxlength="80" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="label" for="plan_slug">Identifiant</label>
                        <input id="plan_slug" v-model="form.slug" type="text" class="input font-mono text-sm" required />
                        <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">{{ form.errors.slug }}</p>
                    </div>
                </div>

                <div>
                    <label class="label" for="plan_description">Description</label>
                    <input id="plan_description" v-model="form.description" type="text" class="input" maxlength="1000" />
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="label" for="plan_price">Prix ({{ form.currency }})</label>
                        <input id="plan_price" v-model="priceInEuros" type="number" step="0.01" min="0" class="input" />
                    </div>
                    <div>
                        <label class="label" for="plan_interval">Période</label>
                        <select id="plan_interval" v-model="form.interval" class="input">
                            <option value="month">Mensuel</option>
                            <option value="year">Annuel</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="plan_overage">Dépassement</label>
                        <select id="plan_overage" v-model="form.overage_policy" class="input">
                            <option value="block">Bloqué</option>
                            <option value="soft">Autorisé et facturé</option>
                        </select>
                    </div>
                </div>

                <div>
                    <p class="label">Quotas mensuels</p>
                    <!-- Une valeur à 0 est une limite réelle, pas une absence de
                         limite : retirer la métrique du plan supprime le plafond. -->
                    <div class="grid grid-cols-2 gap-2">
                        <div v-for="metric in metrics" :key="metric" class="flex items-center gap-2">
                            <label class="w-40 shrink-0 font-mono text-xs" :for="`quota_${metric}`">{{ metric }}</label>
                            <input
                                :id="`quota_${metric}`"
                                v-model.number="form.quotas[metric]"
                                type="number"
                                min="0"
                                class="input py-1 text-sm"
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <p class="label">Fonctionnalités</p>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input v-model="form.features.rag" type="checkbox" /> Base de connaissances
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.features.api_access" type="checkbox" /> Accès API
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.features.templates" type="checkbox" /> Modèles de messages
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2">
                        <input v-model="form.is_active" type="checkbox" /> Actif
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="form.is_public" type="checkbox" /> Affiché sur le site commercial
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary" @click="editing = null">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Enregistrer</button>
                </div>
            </form>
        </Modal>

        <Modal :open="!!deleting" title="Supprimer ce plan ?" @close="deleting = null">
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-300">
                « {{ deleting?.name }} » sera supprimé. Cette opération est impossible si
                une entreprise l'utilise encore.
            </p>
            <div class="flex justify-end gap-3">
                <button class="btn-secondary" @click="deleting = null">Annuler</button>
                <button class="btn-primary bg-red-600 hover:bg-red-700" @click="remove">Supprimer</button>
            </div>
        </Modal>
    </AdminLayout>
</template>
