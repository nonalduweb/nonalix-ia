<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    codes: Array,
    plans: Array,
});

const creating = ref(false);
const revoking = ref(null);
const copied = ref(null);

const form = useForm({
    plan_id: props.plans[0]?.id ?? '',
    label: '',
    max_uses: 1,
    trial_days: 14,
    expires_at: '',
    quantity: 1,
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    creating.value = true;
};

const submit = () =>
    form.post('/access-codes', {
        preserveScroll: true,
        onSuccess: () => (creating.value = false),
    });

const revoke = () =>
    router.post(
        `/access-codes/${revoking.value.code}/revoke`,
        {},
        { preserveScroll: true, onFinish: () => (revoking.value = null) },
    );

const copy = async (code) => {
    try {
        await navigator.clipboard.writeText(code.shareUrl);
        copied.value = code.id;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        // Le presse-papiers est refusé hors contexte sécurisé : le lien reste
        // sélectionnable à la main dans le tableau.
    }
};

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const usesLabel = (code) => (code.maxUses === 0 ? `${code.usedCount} / ∞` : `${code.usedCount} / ${code.maxUses}`);
</script>

<template>
    <Head title="Codes d'accès" />

    <AdminLayout>
        <PageHeader
            title="Codes d'accès"
            description="L'inscription est fermée : seul un code émis ici permet de créer une entreprise."
            icon="target"
            tone="emerald"
        >
            <template #actions>
                <button class="btn-primary" @click="openCreate">Générer des codes</button>
            </template>
        </PageHeader>

        <div v-if="!codes.length" class="card text-sm text-slate-500">
            Aucun code émis. Générez-en un pour ouvrir un compte à un client.
        </div>

        <div v-else class="card overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Pack</th>
                        <th class="px-4 py-3">Libellé</th>
                        <th class="px-4 py-3">Usages</th>
                        <th class="px-4 py-3">Essai</th>
                        <th class="px-4 py-3">Expire</th>
                        <th class="px-4 py-3">État</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="code in codes"
                        :key="code.id"
                        class="border-b border-slate-100 last:border-0 dark:border-slate-800"
                    >
                        <td class="px-4 py-3 font-mono tracking-wider">{{ code.code }}</td>
                        <td class="px-4 py-3">{{ code.plan }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ code.label || '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ usesLabel(code) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ code.trialDays }} j</td>
                        <td class="px-4 py-3 text-slate-500">{{ formatDate(code.expiresAt) }}</td>
                        <td class="px-4 py-3">
                            <span v-if="code.usable" class="text-emerald-600">actif</span>
                            <span v-else class="text-slate-400">{{ code.reason }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3 text-xs">
                                <button class="text-slate-500 hover:underline" @click="copy(code)">
                                    {{ copied === code.id ? 'Copié' : 'Copier le lien' }}
                                </button>
                                <button
                                    v-if="!code.revokedAt"
                                    class="text-red-600 hover:underline"
                                    @click="revoking = code"
                                >
                                    Révoquer
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :open="creating" title="Générer des codes d'accès" max-width="max-w-xl" @close="creating = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="label" for="plan_id">Pack ouvert par le code</label>
                    <select id="plan_id" v-model="form.plan_id" class="input" required>
                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                    </select>
                    <p v-if="form.errors.plan_id" class="mt-1 text-sm text-red-600">{{ form.errors.plan_id }}</p>
                </div>

                <div>
                    <label class="label" for="label">Libellé interne</label>
                    <input id="label" v-model="form.label" type="text" class="input" maxlength="160" placeholder="Salon Pro 2026" />
                    <p class="mt-1 text-sm text-slate-500">Jamais montré au client. Sert à retrouver l'opération.</p>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="label" for="quantity">Nombre de codes</label>
                        <input id="quantity" v-model.number="form.quantity" type="number" min="1" max="50" class="input" required />
                    </div>
                    <div>
                        <label class="label" for="max_uses">Usages par code</label>
                        <input id="max_uses" v-model.number="form.max_uses" type="number" min="0" max="1000" class="input" required />
                        <p class="mt-1 text-xs text-slate-500">0 = illimité</p>
                    </div>
                    <div>
                        <label class="label" for="trial_days">Essai (jours)</label>
                        <input id="trial_days" v-model.number="form.trial_days" type="number" min="0" max="365" class="input" required />
                    </div>
                </div>
                <p v-if="form.errors.max_uses" class="text-sm text-red-600">{{ form.errors.max_uses }}</p>

                <div>
                    <label class="label" for="expires_at">Expiration (facultatif)</label>
                    <input id="expires_at" v-model="form.expires_at" type="datetime-local" class="input" />
                    <p class="mt-1 text-sm text-slate-500">
                        Un code sans expiration reste valable indéfiniment tant qu'il n'est ni épuisé ni révoqué.
                    </p>
                    <p v-if="form.errors.expires_at" class="mt-1 text-sm text-red-600">{{ form.errors.expires_at }}</p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary" @click="creating = false">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Générer</button>
                </div>
            </form>
        </Modal>

        <Modal :open="!!revoking" title="Révoquer ce code ?" @close="revoking = null">
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-300">
                <span class="font-mono">{{ revoking?.code }}</span> ne pourra plus servir à créer
                d'entreprise. Les comptes déjà créés avec lui ne sont pas affectés.
            </p>
            <div class="flex justify-end gap-3">
                <button class="btn-secondary" @click="revoking = null">Annuler</button>
                <button class="btn-primary bg-red-600 hover:bg-red-700" @click="revoke">Révoquer</button>
            </div>
        </Modal>
    </AdminLayout>
</template>
