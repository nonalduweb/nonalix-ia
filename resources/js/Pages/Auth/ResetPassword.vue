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
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Nouveau mot de passe" />

    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <h1 class="mb-1 flex justify-center">
                <img src="/logo-nonalixia.png" alt="Nonalix IA" width="500" height="105" class="h-9 w-auto dark:invert dark:hue-rotate-180" />
            </h1>
            <p class="mb-8 text-center text-sm text-slate-500">Nouveau mot de passe</p>

            <form class="card space-y-4" @submit.prevent="submit">
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
                    <label class="label" for="password">Nouveau mot de passe</label>
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

                <p class="text-sm text-slate-500">
                    Toute autre session « rester connecté » sera fermée.
                </p>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    {{ form.processing ? 'Enregistrement…' : 'Changer mon mot de passe' }}
                </button>
            </form>
        </div>
    </div>
</template>
