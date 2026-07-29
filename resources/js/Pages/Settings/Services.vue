<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import Modal from '@/Components/Modal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { decimals, formatMoney, toMajor, toMinor } from '@/money';

const props = defineProps({
    services: Array,
    defaultCurrency: String,
    currencies: Object,
});

const editing = ref(null);
const deleting = ref(null);

const PRICE_TYPES = [
    { value: 'fixed', label: 'Prix ferme' },
    { value: 'from', label: 'À partir de' },
    { value: 'hourly', label: 'Tarif horaire' },
    { value: 'quote', label: 'Sur devis' },
];

const form = useForm({
    name: '',
    description: '',
    price_cents: null,
    price_type: 'fixed',
    // Suit la devise de l'entreprise : la saisir à chaque prestation était
    // une corvée, et un oubli faisait annoncer un prix en euros à un client
    // facturé en francs CFA.
    currency: props.defaultCurrency,
    duration_minutes: null,
    category: '',
    is_active: true,
    position: 0,
});

// Les montants sont saisis dans l'unité principale de la devise et stockés
// dans sa plus petite unité (entier) : aucun flottant ne doit approcher une
// valeur monétaire. Le franc CFA n'ayant pas de sous-unité, le facteur vaut
// un — le multiplier par cent afficherait un tarif cent fois trop élevé.
const priceInput = ref('');

const openCreate = () => {
    form.reset();
    form.clearErrors();
    priceInput.value = '';
    editing.value = 'new';
};

const openEdit = (service) => {
    form.defaults({ ...service });
    form.reset();
    form.clearErrors();
    priceInput.value =
        service.price_cents !== null ? toMajor(service.price_cents, service.currency).toString() : '';
    editing.value = service;
};

const submit = () => {
    form.price_cents =
        form.price_type === 'quote' || priceInput.value === ''
            ? null
            : toMinor(priceInput.value, form.currency);

    const options = { preserveScroll: true, onSuccess: () => (editing.value = null) };

    editing.value === 'new'
        ? form.post('/settings/services', options)
        : form.put(`/settings/services/${editing.value.id}`, options);
};

const remove = () =>
    router.delete(`/settings/services/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });

const priceStep = computed(() => (decimals(form.currency) === 0 ? '100' : '0.01'));
const currencyLabel = computed(() => (decimals(form.currency) === 0 ? 'F CFA' : form.currency));
const pricePlaceholder = computed(() => (decimals(form.currency) === 0 ? '5 000' : '25.00'));

const formatPrice = (service) => {
    if (service.price_type === 'quote' || service.price_cents === null) return 'sur devis';

    const amount = formatMoney(service.price_cents, service.currency);

    return { from: `à partir de ${amount}`, hourly: `${amount} / heure` }[service.price_type] ?? amount;
};
</script>

<template>
    <Head title="Prestations" />

    <AppLayout>
        <h1 class="mb-6 text-xl font-semibold">Configuration</h1>
        <SettingsNav />

        <div class="mb-4 flex items-start justify-between gap-4">
            <p class="max-w-2xl text-sm text-slate-500">
                Ces tarifs sont la <strong>seule</strong> source de prix communiquée à l'agent
                IA. Il lui est explicitement interdit d'en annoncer d'autres : ce qui ne
                figure pas ici sera renvoyé vers un conseiller.
            </p>
            <button class="btn-primary shrink-0" @click="openCreate">Ajouter</button>
        </div>

        <div class="card overflow-hidden p-0">
            <table v-if="services.length" class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Prestation</th>
                        <th class="px-5 py-3 font-medium">Tarif</th>
                        <th class="px-5 py-3 font-medium">Durée</th>
                        <th class="px-5 py-3 font-medium">État</th>
                        <th class="px-5 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="service in services" :key="service.id">
                        <td class="px-5 py-3">
                            <p class="font-medium">{{ service.name }}</p>
                            <p v-if="service.description" class="mt-0.5 max-w-md truncate text-xs text-slate-500">
                                {{ service.description }}
                            </p>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">{{ formatPrice(service) }}</td>
                        <td class="px-5 py-3 text-slate-500">
                            {{ service.duration_minutes ? service.duration_minutes + ' min' : '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-xs" :class="service.is_active ? 'text-emerald-600' : 'text-slate-400'">
                                {{ service.is_active ? 'Active' : 'Masquée' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button class="text-xs text-slate-500 hover:underline" @click="openEdit(service)">
                                Modifier
                            </button>
                            <button class="ml-3 text-xs text-red-600 hover:underline" @click="deleting = service">
                                Supprimer
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState
                v-else
                title="Aucune prestation"
                description="Sans catalogue, l'agent ne pourra communiquer aucun tarif et transférera systématiquement à un humain."
            >
                <button class="btn-primary" @click="openCreate">Ajouter une prestation</button>
            </EmptyState>
        </div>

        <Modal
            :open="!!editing"
            :title="editing === 'new' ? 'Nouvelle prestation' : 'Modifier la prestation'"
            @close="editing = null"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="label" for="name">Nom</label>
                    <input id="name" v-model="form.name" type="text" class="input" required maxlength="160" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="label" for="description">Description</label>
                    <textarea id="description" v-model="form.description" rows="2" class="input resize-none" maxlength="2000" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="price_type">Type de tarif</label>
                        <select id="price_type" v-model="form.price_type" class="input">
                            <option v-for="type in PRICE_TYPES" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="form.price_type !== 'quote'">
                        <label class="label" for="price">Montant ({{ currencyLabel }})</label>
                        <!-- Le pas suit la devise : proposer des centimes sur
                             un montant en francs CFA n'a pas de sens. -->
                        <input
                            id="price"
                            v-model="priceInput"
                            type="number"
                            :step="priceStep"
                            min="0"
                            class="input"
                            :placeholder="pricePlaceholder"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="duration">Durée (minutes)</label>
                        <input id="duration" v-model.number="form.duration_minutes" type="number" min="1" class="input" />
                    </div>
                    <div>
                        <label class="label" for="category">Catégorie</label>
                        <input id="category" v-model="form.category" type="text" class="input" maxlength="80" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" />
                    Visible par l'agent IA
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary" @click="editing = null">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Enregistrer</button>
                </div>
            </form>
        </Modal>

        <Modal :open="!!deleting" title="Supprimer cette prestation ?" @close="deleting = null">
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-300">
                « {{ deleting?.name }} » ne sera plus proposée par l'agent IA.
            </p>
            <div class="flex justify-end gap-3">
                <button class="btn-secondary" @click="deleting = null">Annuler</button>
                <button class="btn-primary bg-red-600 hover:bg-red-700" @click="remove">Supprimer</button>
            </div>
        </Modal>
    </AppLayout>
</template>
