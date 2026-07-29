<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';

defineProps({
    method: { type: String, default: 'totp' },
    maskedEmail: { type: String, default: '' },
    status: { type: String, default: null },
});

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

    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <h1 class="mb-1 text-center text-2xl font-semibold tracking-tight">Vérification</h1>
            <p class="mb-8 text-center text-sm text-slate-500">
                <template v-if="useRecovery">Saisissez l'un de vos codes de récupération.</template>
                <template v-else-if="method === 'email'">
                    Nous avons envoyé un code à {{ maskedEmail }}.
                </template>
                <template v-else>
                    Saisissez le code affiché par votre application d'authentification.
                </template>
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
        </div>
    </div>
</template>
