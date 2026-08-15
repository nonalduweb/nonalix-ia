<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({ email: '' });

const submit = () => form.post('/forgot-password');
</script>

<template>
    <Head title="Mot de passe oublié" />

    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <h1 class="mb-1 flex justify-center">
                <BrandLogo size="lg" />
            </h1>
            <p class="mb-8 text-center text-sm text-slate-500">Mot de passe oublié</p>

            <form class="card space-y-4" @submit.prevent="submit">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Indiquez l'adresse de votre compte. Vous recevrez un lien pour choisir
                    un nouveau mot de passe.
                </p>

                <!-- Message affiché quelle que soit l'existence du compte : le
                     distinguer permettrait d'énumérer les clients. -->
                <p
                    v-if="status"
                    class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                >
                    {{ status }}
                </p>

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
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    {{ form.processing ? 'Envoi…' : 'Envoyer le lien' }}
                </button>

                <p class="text-center text-sm text-slate-500">
                    <Link href="/login" class="underline">Retour à la connexion</Link>
                </p>
            </form>
        </div>
    </div>
</template>
