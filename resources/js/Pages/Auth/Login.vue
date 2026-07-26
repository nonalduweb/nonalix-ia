<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

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
            <h1 class="mb-1 text-center text-2xl font-semibold tracking-tight">Nonalix&nbsp;IA</h1>
            <p class="mb-8 text-center text-sm text-slate-500">Espace client</p>

            <form class="card space-y-4" @submit.prevent="submit">
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
