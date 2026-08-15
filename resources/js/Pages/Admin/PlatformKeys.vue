<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({
    providers: Array,
    defaultProvider: String,
    fallbackProvider: String,
    embeddingModel: String,
});

// Une saisie par fournisseur : un seul champ partagé enverrait la clé au
// mauvais fournisseur au premier clic distrait.
const saisies = ref({});
const testing = ref(null);

const form = useForm({ provider: '', api_key: '' });

const enregistrer = (provider) => {
    form.provider = provider;
    form.api_key = saisies.value[provider] ?? '';
    form.post('/platform-keys', {
        preserveScroll: true,
        onSuccess: () => (saisies.value[provider] = ''),
    });
};

const tester = (provider) => {
    testing.value = provider;
    router.post('/platform-keys/test', { provider }, {
        preserveScroll: true,
        onFinish: () => (testing.value = null),
    });
};

const effacer = (provider) => {
    form.provider = provider;
    form.api_key = '';
    form.post('/platform-keys', { preserveScroll: true });
};
</script>

<template>
    <Head title="Clés IA" />

    <AdminLayout>
        <PageHeader
            title="Clés IA de la plateforme"
            description="Socle utilisé par toutes les entreprises qui n'ont pas fourni la leur. Une entreprise qui saisit sa propre clé dans ses réglages d'agent consomme son quota et prime sur celle-ci."
            icon="sparkles"
            tone="violet"
        />

        <div class="mb-6 card text-sm">
            <dl class="grid gap-3 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-slate-500">Fournisseur par défaut</dt>
                    <dd class="mt-0.5 font-medium">{{ defaultProvider }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Repli</dt>
                    <dd class="mt-0.5 font-medium">{{ fallbackProvider || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Modèle d'embeddings</dt>
                    <dd class="mt-0.5 font-mono text-xs">{{ embeddingModel }}</dd>
                </div>
            </dl>
        </div>

        <div class="space-y-4">
            <div v-for="p in providers" :key="p.value" class="card">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <h2 class="font-semibold">{{ p.label }}</h2>

                    <span v-if="p.configured" class="text-xs text-emerald-600">clé enregistrée</span>
                    <span v-else-if="p.fromEnv" class="text-xs text-slate-500">
                        héritée du serveur (.env)
                    </span>
                    <span v-else class="text-xs text-amber-600">aucune clé</span>

                    <span
                        v-if="p.embeddings"
                        class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] dark:bg-slate-800"
                    >
                        sert aux embeddings
                    </span>
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-64 flex-1">
                        <label class="label" :for="`k_${p.value}`">
                            {{ p.configured ? 'Remplacer la clé' : 'Clé API' }}
                        </label>
                        <input
                            :id="`k_${p.value}`"
                            v-model="saisies[p.value]"
                            type="password"
                            class="input"
                            autocomplete="off"
                            :placeholder="p.configured ? '•••••••• (enregistrée)' : 'sk-…'"
                        />
                    </div>

                    <button
                        type="button"
                        class="btn-primary"
                        :disabled="form.processing || !saisies[p.value]"
                        @click="enregistrer(p.value)"
                    >
                        Enregistrer
                    </button>

                    <button
                        v-if="p.configured || p.fromEnv"
                        type="button"
                        class="btn-secondary"
                        :disabled="testing === p.value"
                        @click="tester(p.value)"
                    >
                        {{ testing === p.value ? 'Test…' : 'Tester' }}
                    </button>

                    <!-- Effacer redonne la main au .env : c'est le seul moyen
                         de revenir à la configuration du serveur. -->
                    <button
                        v-if="p.configured"
                        type="button"
                        class="text-xs text-red-600 hover:underline"
                        @click="effacer(p.value)"
                    >
                        Effacer
                    </button>
                </div>

                <p v-if="form.errors.api_key && form.provider === p.value" class="mt-2 text-sm text-red-600">
                    {{ form.errors.api_key }}
                </p>
            </div>
        </div>

        <p class="mt-6 max-w-3xl text-xs text-slate-500">
            Les clés sont chiffrées en base et ne sont jamais renvoyées au navigateur,
            même tronquées. Le journal d'audit enregistre la modification, jamais la
            valeur. Effacer une clé redonne la main à celle du serveur, si elle existe.
        </p>
    </AdminLayout>
</template>
