<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    name: { type: String, required: true },
    email: { type: String, required: true },
    prefilledCode: { type: String, default: null },
});

const form = useForm({
    code: props.prefilledCode ?? '',
    company: '',
    name: props.name,
    terms: false,
});

const submit = () => form.post('/register/social');
</script>

<template>
    <Head title="Finaliser mon inscription" />

    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <h1 class="mb-1 flex justify-center">
                <img src="/logo-nonalixia.png" alt="Nonalix IA" width="500" height="105" class="h-9 w-auto invert hue-rotate-180 dark:invert-0 dark:hue-rotate-0" />
            </h1>
            <p class="mb-8 text-center text-sm text-slate-500">Finaliser mon inscription</p>

            <form class="card space-y-5" @submit.prevent="submit">
                <!-- L'adresse vient de Google et n'est pas modifiable : la
                     laisser libre permettrait de rattacher l'identité d'un
                     tiers à un compte que l'on contrôle. -->
                <div class="rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:bg-slate-800/60">
                    <p class="text-slate-500">Connecté avec Google</p>
                    <p class="mt-0.5 font-medium">{{ email }}</p>
                </div>

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
                    />
                    <p class="mt-1 text-sm text-slate-500">
                        Pas encore de code ?
                        <a href="https://nonalixia.com/demande" class="underline">Demandez le vôtre</a>.
                    </p>
                    <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
                </div>

                <div>
                    <label class="label" for="company">Nom de l'entreprise</label>
                    <input id="company" v-model="form.company" type="text" class="input" required placeholder="Boulangerie Kouassi" />
                    <p v-if="form.errors.company" class="mt-1 text-sm text-red-600">{{ form.errors.company }}</p>
                </div>

                <div>
                    <label class="label" for="name">Votre nom</label>
                    <input id="name" v-model="form.name" type="text" class="input" required />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="form.terms" type="checkbox" class="mt-1 rounded border-slate-300" />
                    <span>
                        J'accepte les
                        <a href="https://nonalixia.com/conditions-utilisation" target="_blank" class="underline">conditions d'utilisation</a>
                        et la
                        <a href="https://nonalixia.com/confidentialite" target="_blank" class="underline">politique de confidentialité</a>.
                    </span>
                </label>
                <p v-if="form.errors.terms" class="-mt-3 text-sm text-red-600">{{ form.errors.terms }}</p>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    {{ form.processing ? 'Création…' : 'Créer mon entreprise' }}
                </button>

                <p class="text-center text-sm text-slate-500">
                    <Link href="/login" class="underline">Revenir à la connexion</Link>
                </p>
            </form>
        </div>
    </div>
</template>
