<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';

defineProps({
    method: { type: String, default: 'totp' },
    maskedEmail: { type: String, default: '' },
    status: { type: String, default: null },
});

/*
 * Porte de sortie.
 *
 * Cet écran s'atteint sans repasser par la connexion : la case « Rester
 * connecté » pose un cookie valable des années, alors que la session, elle,
 * expire au bout de deux heures. Passé ce délai, le cookie ré-authentifie
 * silencieusement, mais la nouvelle session n'a pas encore franchi le second
 * facteur — d'où ce défi, sur un compte qu'on croyait déconnecté.
 *
 * Le comportement est sain (le second facteur se revalide à chaque session),
 * mais il enfermait : sans application d'authentification sous la main, aucune
 * navigation ne ramenait à la page de connexion. `/logout` n'est protégé que
 * par `auth`, jamais par le second facteur : la sortie reste donc joignable
 * depuis ici, et elle efface le cookie « Rester connecté » au passage.
 */
const leaving = ref(false);

const logout = () => {
    leaving.value = true;
    router.post('/logout');
};

const useRecovery = ref(false);
const sending = ref(false);

// Un compte en TOTP peut demander un code par e-mail : c'est le recours de
// celui qui a perdu son téléphone mais garde sa boîte mail.
const sendEmailCode = () => {
    sending.value = true;
    router.post('/two-factor-challenge/email', {}, {
        preserveScroll: true,
        onFinish: () => (sending.value = false),
    });
};
const codeInput = ref(null);

const form = useForm({
    code: '',
    recovery_code: '',
});

const toggleRecovery = async () => {
    useRecovery.value = !useRecovery.value;

    // Un seul des deux champs est envoyé : laisser l'autre rempli
    // déclencherait la mauvaise branche de validation côté serveur.
    form.reset('code', 'recovery_code');
    form.clearErrors();

    await nextTick();
    codeInput.value?.focus();
};

const submit = () =>
    form
        .transform((data) =>
            useRecovery.value
                ? { recovery_code: data.recovery_code }
                : { code: data.code },
        )
        .post('/two-factor-challenge', {
            onFinish: () => form.reset('code', 'recovery_code'),
        });
</script>

<template>
    <Head title="Vérification" />

    <div class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex justify-center">
                <BrandLogo size="lg" />
            </div>

            <h1 class="mb-1 text-center text-2xl font-semibold tracking-tight">Vérification</h1>
            <p class="mb-2 text-center text-sm text-slate-500">
                <template v-if="useRecovery">Saisissez l'un de vos codes de récupération.</template>
                <template v-else-if="method === 'email'">
                    Nous avons envoyé un code à {{ maskedEmail }}.
                </template>
                <template v-else>
                    Saisissez le code affiché par votre application d'authentification.
                </template>
            </p>

            <!-- Le compte concerné est rappelé même en TOTP : on arrive ici
                 sans être repassé par la connexion, et sans ce rappel on ne
                 sait pas quelle identité on est en train de confirmer. -->
            <p v-if="maskedEmail" class="mb-8 text-center text-xs text-slate-400">
                Compte : {{ maskedEmail }}
            </p>

            <p
                v-if="status"
                class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-center text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
            >
                {{ status }}
            </p>

            <form class="card space-y-4" @submit.prevent="submit">
                <div v-if="!useRecovery">
                    <label class="label" for="code">Code à 6 chiffres</label>
                    <input
                        id="code"
                        ref="codeInput"
                        v-model="form.code"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        autocomplete="one-time-code"
                        placeholder="123456"
                        class="input text-center font-mono text-lg tracking-widest"
                        autofocus
                        required
                    />
                </div>

                <div v-else>
                    <label class="label" for="recovery">Code de récupération</label>
                    <input
                        id="recovery"
                        ref="codeInput"
                        v-model="form.recovery_code"
                        type="text"
                        placeholder="ABCDE-FGHIJ"
                        class="input text-center font-mono"
                        autofocus
                        required
                    />
                    <!-- Un code de récupération est à usage unique : il est
                         retiré de la liste dès qu'il est accepté. -->
                    <p class="mt-1 text-xs text-slate-500">
                        Ce code sera consommé et ne pourra plus servir.
                    </p>
                </div>

                <p v-if="form.errors.code || form.errors.recovery_code" class="text-sm text-red-600">
                    {{ form.errors.code || form.errors.recovery_code }}
                </p>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    {{ form.processing ? 'Vérification…' : 'Valider' }}
                </button>

                <div class="space-y-2 pt-1 text-center text-sm text-slate-500">
                    <button
                        v-if="!useRecovery"
                        type="button"
                        class="w-full underline"
                        :disabled="sending"
                        @click="sendEmailCode"
                    >
                        {{ sending ? 'Envoi…' : (method === 'email' ? 'Renvoyer le code par e-mail' : 'Recevoir un code par e-mail') }}
                    </button>

                    <button type="button" class="w-full underline" @click="toggleRecovery">
                        {{
                            useRecovery
                                ? 'Revenir à la saisie du code'
                                : 'Utiliser un code de récupération'
                        }}
                    </button>
                </div>
            </form>

            <!-- Sortie de secours : sans elle, un compte « Rester connecté »
                 dont le second facteur est hors d'atteinte n'a plus aucun
                 moyen de revenir à la page de connexion. -->
            <div class="mt-6 border-t border-slate-200 pt-5 text-center dark:border-slate-800">
                <p class="text-xs leading-relaxed text-slate-500">
                    Vous n'avez pas accès à votre second facteur ?
                </p>
                <button
                    type="button"
                    class="mt-1.5 cursor-pointer text-sm text-slate-600 underline underline-offset-2 hover:text-slate-900 disabled:opacity-50 dark:text-slate-400 dark:hover:text-white"
                    :disabled="leaving"
                    @click="logout"
                >
                    {{ leaving ? 'Déconnexion…' : 'Se déconnecter et changer de compte' }}
                </button>
            </div>
        </div>
    </div>
</template>
