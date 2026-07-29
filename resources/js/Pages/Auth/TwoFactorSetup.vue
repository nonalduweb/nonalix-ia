<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    enabled: Boolean,
    required: Boolean,
    qrCode: { type: String, default: null },
    secret: { type: String, default: null },
    method: { type: String, default: 'totp' },
    email: { type: String, default: '' },
});

const page = usePage();

// Affichés une seule fois, au moment de la confirmation : ils ne sont jamais
// relus depuis la base (ils y sont chiffrés et destinés à être consommés).
const recoveryCodes = computed(() => page.props.flash?.recoveryCodes ?? null);

const confirmForm = useForm({ code: '' });
const disableForm = useForm({ password: '' });

const showSecret = ref(false);

const start = () => router.post('/two-factor/enable', {}, { preserveScroll: true });

// Second facteur par e-mail : aucun secret ni application à installer, un
// code part immédiatement pour vérifier que l'adresse est bien accessible.
const startEmail = () => router.post('/two-factor/enable-email', {}, { preserveScroll: true });

const status = computed(() => page.props.flash?.status ?? null);

// Une configuration par e-mail est en cours dès que la methode est `email`
// et que la 2FA n'est pas encore confirmee.
const awaitingEmailCode = computed(() => props.method === 'email' && !props.enabled);

const confirm = () =>
    confirmForm.post('/two-factor/confirm', {
        onFinish: () => confirmForm.reset('code'),
    });

const disable = () =>
    disableForm.delete('/two-factor/disable', {
        onFinish: () => disableForm.reset('password'),
    });

const copyCodes = () => {
    if (recoveryCodes.value) {
        navigator.clipboard?.writeText(recoveryCodes.value.join('\n'));
    }
};
</script>

<template>
    <Head title="Authentification à deux facteurs" />

    <div class="mx-auto max-w-xl px-4 py-12">
        <h1 class="mb-2 text-xl font-semibold">Authentification à deux facteurs</h1>

        <p v-if="required" class="mb-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Votre rôle impose la 2FA. Elle doit être activée avant d'accéder à la plateforme.
        </p>
        <p v-else class="mb-6 text-sm text-slate-500">
            Ajoute une deuxième vérification à la connexion, en plus du mot de passe.
        </p>

        <p
            v-if="status"
            class="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
        >
            {{ status }}
        </p>

        <!-- Codes de récupération : affichés une seule fois. -->
        <section v-if="recoveryCodes" class="card mb-6 border-amber-300 bg-amber-50 dark:bg-amber-950/30">
            <h2 class="mb-2 text-sm font-semibold">Codes de récupération</h2>
            <p class="mb-3 text-sm text-amber-800 dark:text-amber-200">
                Conservez-les hors de votre téléphone. Ils sont votre seul recours si
                vous perdez l'accès à votre application d'authentification —
                <strong>ils ne seront plus jamais affichés</strong>.
            </p>
            <ul class="grid grid-cols-2 gap-1 font-mono text-sm">
                <li v-for="code in recoveryCodes" :key="code">{{ code }}</li>
            </ul>
            <button class="btn-secondary mt-4 text-sm" @click="copyCodes">Copier</button>
        </section>

        <!-- 2FA active -->
        <section v-if="enabled" class="card">
            <p class="mb-4 flex items-center gap-2 text-sm">
                <span class="text-emerald-600">✓</span>
                L'authentification à deux facteurs est active sur ce compte.
            </p>

            <form v-if="!required" class="space-y-3" @submit.prevent="disable">
                <p class="text-sm text-slate-500">
                    Pour la désactiver, confirmez votre mot de passe.
                </p>
                <div>
                    <label class="label" for="password">Mot de passe</label>
                    <input
                        id="password"
                        v-model="disableForm.password"
                        type="password"
                        class="input"
                        autocomplete="current-password"
                        required
                    />
                    <p v-if="disableForm.errors.password" class="mt-1 text-sm text-red-600">
                        {{ disableForm.errors.password }}
                    </p>
                </div>
                <button type="submit" class="btn-secondary" :disabled="disableForm.processing">
                    Désactiver
                </button>
            </form>

            <p v-else class="text-sm text-slate-500">
                Elle ne peut pas être désactivée tant que votre rôle l'exige.
            </p>
        </section>

        <!-- Configuration en cours : secret généré, en attente de confirmation -->
        <section v-else-if="qrCode" class="card space-y-5">
            <div>
                <h2 class="mb-1 text-sm font-semibold">1. Scannez le QR code</h2>
                <p class="mb-3 text-sm text-slate-500">
                    Avec Google Authenticator, 1Password, Authy ou équivalent.
                </p>
                <div class="inline-block rounded-lg bg-white p-3" v-html="qrCode" />
            </div>

            <div>
                <button class="text-sm text-slate-500 underline" @click="showSecret = !showSecret">
                    {{ showSecret ? 'Masquer' : 'Saisir la clé manuellement' }}
                </button>
                <p v-if="showSecret" class="mt-2 rounded bg-slate-100 p-2 font-mono text-sm dark:bg-slate-800">
                    {{ secret }}
                </p>
            </div>

            <form class="space-y-3" @submit.prevent="confirm">
                <h2 class="text-sm font-semibold">2. Confirmez avec un premier code</h2>
                <p class="text-sm text-slate-500">
                    Cette étape évite d'être verrouillé hors de votre compte si le
                    QR code a été mal scanné.
                </p>
                <div>
                    <input
                        v-model="confirmForm.code"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        placeholder="123456"
                        class="input max-w-40 text-center font-mono text-lg tracking-widest"
                        required
                    />
                    <p v-if="confirmForm.errors.code" class="mt-1 text-sm text-red-600">
                        {{ confirmForm.errors.code }}
                    </p>
                </div>
                <button type="submit" class="btn-primary" :disabled="confirmForm.processing">
                    Activer
                </button>
            </form>
        </section>

        <!-- Confirmation d'un code reçu par e-mail -->
        <section v-else-if="awaitingEmailCode" class="card space-y-4">
            <div>
                <h2 class="text-sm font-semibold">Confirmez avec le code reçu</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Un code à 6 chiffres vient d'être envoyé à {{ email }}. Cette étape
                    vérifie que vous accédez bien à cette adresse.
                </p>
            </div>

            <form class="space-y-3" @submit.prevent="confirm">
                <input
                    v-model="confirmForm.code"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    autocomplete="one-time-code"
                    placeholder="123456"
                    class="input max-w-40 text-center font-mono text-lg tracking-widest"
                    required
                />
                <p v-if="confirmForm.errors.code" class="text-sm text-red-600">
                    {{ confirmForm.errors.code }}
                </p>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="confirmForm.processing">
                        Activer
                    </button>
                    <button type="button" class="text-sm text-slate-500 underline" @click="startEmail">
                        Renvoyer le code
                    </button>
                </div>
            </form>
        </section>

        <!-- Rien de configuré : choix de la méthode -->
        <section v-else class="space-y-4">
            <div class="card">
                <h2 class="text-sm font-semibold">Application d'authentification</h2>
                <p class="mt-1 mb-4 text-sm text-slate-500">
                    Google Authenticator, 1Password, Authy ou équivalent. Le plus sûr :
                    le code est produit sur votre téléphone, sans passer par un réseau.
                </p>
                <button class="btn-primary" @click="start">Utiliser une application</button>
            </div>

            <div class="card">
                <h2 class="text-sm font-semibold">Code par e-mail</h2>
                <p class="mt-1 mb-4 text-sm text-slate-500">
                    Un code à 6 chiffres envoyé à {{ email }} à chaque connexion. Rien à
                    installer, mais votre boîte mail devient la clé de votre compte :
                    protégez-la au moins aussi bien.
                </p>
                <button class="btn-secondary" @click="startEmail">Recevoir par e-mail</button>
            </div>
        </section>
    </div>
</template>
