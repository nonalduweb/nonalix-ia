<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    googleEnabled: { type: Boolean, default: false },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        // Le mot de passe ne doit jamais rester en mémoire après un échec.
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Connexion" />

    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <h1 class="mb-1 flex justify-center">
                <img src="/logo-nonalixia.png" alt="Nonalix IA" width="500" height="105" class="h-9 w-auto invert hue-rotate-180 dark:invert-0 dark:hue-rotate-0" />
            </h1>
            <p class="mb-8 text-center text-sm text-slate-500">Espace client</p>

            <form class="card space-y-4" @submit.prevent="submit">
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

                <div>
                    <label class="label" for="email">Adresse e-mail</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="input"
                        required
                        autofocus
                        autocomplete="username"
                    />
                </div>

                <div>
                    <label class="label" for="password">Mot de passe</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="input"
                        required
                        autocomplete="current-password"
                    />
                </div>

                <!-- Message unique quelle que soit la cause de l'échec : le
                     serveur ne distingue pas e-mail inconnu et mot de passe
                     incorrect, pour ne pas permettre d'énumérer les comptes. -->
                <p v-if="form.errors.email" class="text-sm text-red-600">
                    {{ form.errors.email }}
                </p>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" />
                    Rester connecté
                </label>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    {{ form.processing ? 'Connexion…' : 'Se connecter' }}
                </button>

                <div class="space-y-1 pt-1 text-center text-sm text-slate-500">
                    <p>
                        <Link href="/forgot-password" class="underline">Mot de passe oublié ?</Link>
                    </p>
                    <p>
                        Vous avez un code d'accès ?
                        <Link href="/register" class="underline">Créer mon entreprise</Link>
                    </p>
                </div>
            </form>
        </div>
    </div>
</template>
