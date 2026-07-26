<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/invitation', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Activer mon compte" />

    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <h1 class="mb-1 text-center text-2xl font-semibold tracking-tight">Nonalix&nbsp;IA</h1>
            <p class="mb-8 text-center text-sm text-slate-500">Activer mon compte</p>

            <form class="card space-y-4" @submit.prevent="submit">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Choisissez votre mot de passe pour activer votre accès.
                </p>

                <div>
                    <label class="label" for="email">Adresse e-mail</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="input"
                        required
                        autocomplete="username"
                    />
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
                        autofocus
                        autocomplete="new-password"
                    />
                    <p class="mt-1 text-sm text-slate-500">
                        12 caractères minimum, avec majuscules, minuscules, chiffres et symboles.
                        Les mots de passe connus des fuites publiques sont refusés.
                    </p>
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label class="label" for="password_confirmation">Confirmer</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="input"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    {{ form.processing ? 'Activation…' : 'Activer mon compte' }}
                </button>

                <p class="text-center text-sm text-slate-500">
                    Une double authentification vous sera demandée à la première connexion.
                </p>
            </form>
        </div>
    </div>
</template>
