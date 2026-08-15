<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    prefilledCode: { type: String, default: null },
    googleEnabled: { type: Boolean, default: false },
});

const form = useForm({
    code: props.prefilledCode ?? '',
    company: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

// État du code saisi : null tant qu'aucune vérification n'a abouti.
const codeStatus = ref(null);
const checking = ref(false);

let debounce = null;

// Le projet n'embarque pas axios : le jeton CSRF n'est donc pas ajouté
// automatiquement. Laravel le dépose dans le cookie XSRF-TOKEN, encodé.
const xsrfToken = () =>
    decodeURIComponent(
        document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
    );

const checkCode = async (value) => {
    const cleaned = (value ?? '').replace(/[^A-Za-z0-9]/g, '');

    if (cleaned.length < 12) {
        codeStatus.value = null;
        return;
    }

    checking.value = true;

    try {
        const response = await fetch('/register/check-code', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ code: value }),
        });

        codeStatus.value = response.ok ? await response.json() : null;
    } catch {
        // Une vérification indisponible ne doit pas empêcher d'envoyer le
        // formulaire : le serveur revalidera de toute façon.
        codeStatus.value = null;
    } finally {
        checking.value = false;
    }
};

watch(
    () => form.code,
    (value) => {
        clearTimeout(debounce);
        codeStatus.value = null;
        debounce = setTimeout(() => checkCode(value), 400);
    },
    { immediate: true },
);

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Créer mon entreprise" />

    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg">
            <h1 class="mb-1 flex justify-center">
                <BrandLogo size="lg" />
            </h1>
            <p class="mb-8 text-center text-sm text-slate-500">Créer mon entreprise</p>

            <form class="card space-y-5" @submit.prevent="submit">
                <!-- Affiche seulement si Google est configure : un bouton qui
                     mene a une erreur vaut moins que pas de bouton du tout. -->
                <template v-if="googleEnabled">
                    <a href="/auth/google" class="btn-secondary w-full">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.65l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.11a6.6 6.6 0 0 1 0-4.22V7.05H2.18a11 11 0 0 0 0 9.9l3.66-2.84z"/>
                            <path fill="#EA4335" d="M12 4.75c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 1.46 14.97.5 12 .5A11 11 0 0 0 2.18 7.05l3.66 2.84c.87-2.6 3.3-4.14 6.16-4.14z"/>
                        </svg>
                        Continuer avec Google
                    </a>

                    <div class="flex items-center gap-3 text-xs text-slate-400">
                        <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700" />
                        ou
                        <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700" />
                    </div>
                </template>

                <!-- Code d'accès -->
                <div>
                    <label class="label" for="code">Code d'accès</label>
                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        class="input font-mono uppercase tracking-widest"
                        placeholder="XXXX-XXXX-XXXX"
                        required
                        autofocus
                        autocomplete="off"
                        spellcheck="false"
                    />

                    <p v-if="checking" class="mt-1 text-sm text-slate-500">Vérification…</p>
                    <p v-else-if="codeStatus?.valid" class="mt-1 text-sm text-emerald-600">
                        Pack {{ codeStatus.plan }} — {{ codeStatus.trialDays }} jours d'essai.
                    </p>
                    <p v-else-if="codeStatus" class="mt-1 text-sm text-red-600">
                        Ce code n'est pas valide.
                    </p>
                    <p v-else class="mt-1 text-sm text-slate-500">
                        Communiqué par Nonalix. Il détermine votre pack.
                    </p>

                    <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
                </div>

                <hr class="border-slate-200 dark:border-slate-800" />

                <!-- Entreprise -->
                <div>
                    <label class="label" for="company">Nom de l'entreprise</label>
                    <input id="company" v-model="form.company" type="text" class="input" required autocomplete="organization" />
                    <p v-if="form.errors.company" class="mt-1 text-sm text-red-600">{{ form.errors.company }}</p>
                </div>

                <!-- Propriétaire -->
                <div>
                    <label class="label" for="name">Votre nom</label>
                    <input id="name" v-model="form.name" type="text" class="input" required autocomplete="name" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="label" for="email">Adresse e-mail</label>
                    <input id="email" v-model="form.email" type="email" class="input" required autocomplete="username" />
                    <p class="mt-1 text-sm text-slate-500">
                        Elle servira à confirmer votre compte et à le récupérer : vérifiez-la.
                    </p>
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="label" for="password">Mot de passe</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="input"
                        required
                        autocomplete="new-password"
                    />
                    <p class="mt-1 text-sm text-slate-500">
                        12 caractères minimum, avec majuscules, minuscules, chiffres et symboles.
                        Les mots de passe connus des fuites publiques sont refusés.
                    </p>
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label class="label" for="password_confirmation">Confirmer le mot de passe</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="input"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="form.terms" type="checkbox" class="mt-1 rounded border-slate-300" />
                    <span>
                        J'accepte les
                        <a href="/conditions-utilisation" target="_blank" class="underline">conditions d'utilisation</a>
                        et la
                        <a href="/confidentialite" target="_blank" class="underline">politique de confidentialité</a>.
                    </span>
                </label>
                <p v-if="form.errors.terms" class="-mt-3 text-sm text-red-600">{{ form.errors.terms }}</p>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    {{ form.processing ? 'Création…' : 'Créer mon entreprise' }}
                </button>

                <p class="text-center text-sm text-slate-500">
                    Déjà un compte ?
                    <Link href="/login" class="underline">Se connecter</Link>
                </p>
            </form>
        </div>
    </div>
</template>
