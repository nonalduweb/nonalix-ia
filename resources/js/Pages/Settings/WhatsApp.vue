<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    account: { type: Object, default: null },
    webhookUrl: String,
});

const form = useForm({
    waba_id: props.account?.waba_id ?? '',
    phone_number_id: props.account?.phone_number_id ?? '',
    access_token: '',
    app_secret: '',
});

const copied = ref(null);

const copy = (value, key) => {
    navigator.clipboard?.writeText(value);
    copied.value = key;
    setTimeout(() => (copied.value = null), 2000);
};

const save = () => form.put('/settings/whatsapp', { preserveScroll: true });

const testing = ref(false);

const test = () =>
    router.post('/settings/whatsapp/test', {}, {
        preserveScroll: true,
        onStart: () => (testing.value = true),
        onFinish: () => (testing.value = false),
    });

const syncTemplates = () =>
    router.post('/settings/whatsapp/sync-templates', {}, { preserveScroll: true });

const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR') : '—');
</script>

<template>
    <Head title="WhatsApp" />

    <AppLayout>
        <h1 class="mb-6 text-xl font-semibold">Configuration</h1>
        <SettingsNav />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- État de la connexion -->
                <section v-if="account" class="card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold">État de la connexion</h2>
                        <StatusBadge :status="account.status" :label="account.status_label" />
                    </div>

                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Numéro</dt>
                            <dd class="mt-0.5 font-mono">{{ account.display_phone_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Nom vérifié</dt>
                            <dd class="mt-0.5">{{ account.verified_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Qualité Meta</dt>
                            <dd class="mt-0.5">{{ account.quality_rating || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Dernière vérification</dt>
                            <dd class="mt-0.5">{{ formatDateTime(account.connected_at) }}</dd>
                        </div>
                    </dl>

                    <p v-if="account.last_error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ account.last_error }}
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button class="btn-primary text-sm" :disabled="testing" @click="test">
                            {{ testing ? 'Vérification…' : 'Tester la connexion' }}
                        </button>
                        <button
                            v-if="account.status === 'connected'"
                            class="btn-secondary text-sm"
                            @click="syncTemplates"
                        >
                            Synchroniser les modèles
                        </button>
                    </div>
                </section>

                <!-- Identifiants -->
                <form class="card space-y-4" @submit.prevent="save">
                    <div>
                        <h2 class="text-sm font-semibold">Identifiants Meta</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Récupérés depuis votre application Meta for Developers.
                            Le jeton et le secret sont chiffrés avant enregistrement et ne
                            sont jamais réaffichés.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="waba_id">WABA ID</label>
                            <input id="waba_id" v-model="form.waba_id" type="text" class="input font-mono text-sm" required />
                            <p v-if="form.errors.waba_id" class="mt-1 text-sm text-red-600">{{ form.errors.waba_id }}</p>
                        </div>
                        <div>
                            <label class="label" for="phone_number_id">Phone Number ID</label>
                            <input id="phone_number_id" v-model="form.phone_number_id" type="text" class="input font-mono text-sm" required />
                            <p v-if="form.errors.phone_number_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.phone_number_id }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="label" for="access_token">Jeton d'accès permanent</label>
                        <input
                            id="access_token"
                            v-model="form.access_token"
                            type="password"
                            class="input font-mono text-sm"
                            autocomplete="off"
                            :required="!account?.has_access_token"
                            :placeholder="account?.has_access_token ? '•••••••• (enregistré)' : 'EAAG…'"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Généré depuis un <em>System User</em>, sans expiration.
                        </p>
                    </div>

                    <div>
                        <label class="label" for="app_secret">App Secret</label>
                        <input
                            id="app_secret"
                            v-model="form.app_secret"
                            type="password"
                            class="input font-mono text-sm"
                            autocomplete="off"
                            :required="!account?.has_app_secret"
                            :placeholder="account?.has_app_secret ? '•••••••• (enregistré)' : ''"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Sert à vérifier la signature des webhooks entrants.
                        </p>
                    </div>

                    <p v-if="form.errors.connection" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ form.errors.connection }}
                    </p>

                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        Enregistrer
                    </button>
                </form>
            </div>

            <!-- Marche à suivre -->
            <aside class="space-y-6">
                <section class="card">
                    <h2 class="mb-3 text-sm font-semibold">URL de callback</h2>
                    <p class="mb-3 text-xs text-slate-500">
                        À coller dans la configuration des webhooks de votre application Meta.
                    </p>
                    <code class="block break-all rounded bg-slate-100 p-2 text-xs dark:bg-slate-800">
                        {{ account?.webhook_url ?? webhookUrl }}
                    </code>
                    <button
                        class="btn-secondary mt-3 w-full text-sm"
                        @click="copy(account?.webhook_url ?? webhookUrl, 'url')"
                    >
                        {{ copied === 'url' ? 'Copié' : 'Copier' }}
                    </button>
                    <p class="mt-3 text-xs text-slate-500">
                        N'oubliez pas de vous abonner au champ <strong>messages</strong>.
                    </p>
                </section>

                <section v-if="account?.webhook_verify_token" class="card">
                    <h2 class="mb-3 text-sm font-semibold">Jeton de vérification</h2>
                    <p class="mb-3 text-xs text-slate-500">
                        À coller dans le champ « Verify token » de Meta, juste sous l'URL
                        de callback.
                    </p>
                    <code class="block break-all rounded bg-slate-100 p-2 text-xs dark:bg-slate-800">
                        {{ account.webhook_verify_token }}
                    </code>
                    <button
                        class="btn-secondary mt-3 w-full text-sm"
                        @click="copy(account.webhook_verify_token, 'token')"
                    >
                        {{ copied === 'token' ? 'Copié' : 'Copier' }}
                    </button>
                </section>

                <section class="card">
                    <h2 class="mb-3 text-sm font-semibold">Marche à suivre</h2>
                    <ol class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
                        <li>1. Créer une application <em>Business</em> sur developers.facebook.com et y ajouter le produit WhatsApp.</li>
                        <li>2. Ajouter et vérifier un numéro dans le WhatsApp Manager. Ce numéro ne doit être lié à aucun compte WhatsApp personnel.</li>
                        <li>3. Créer un <em>System User</em>, lui accorder <code>whatsapp_business_messaging</code> et <code>whatsapp_business_management</code>, puis générer un jeton sans expiration.</li>
                        <li>4. Saisir les identifiants ci-contre.</li>
                        <li>5. Déclarer l'URL de callback et le jeton de vérification dans Meta.</li>
                        <li>6. Cliquer sur « Tester la connexion ».</li>
                    </ol>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
