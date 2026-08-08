<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    email: { type: String, required: true },
    status: { type: String, default: null },
});

const form = useForm({});
const logout = useForm({});

const resend = () => form.post('/email/verification-notification');
</script>

<template>
    <Head title="Confirmer votre adresse" />

    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-md">
            <h1 class="mb-1 flex justify-center">
                <img src="/logo-nonalixia.png" alt="Nonalix IA" width="500" height="105" class="h-9 w-auto dark:invert dark:hue-rotate-180" />
            </h1>
            <p class="mb-8 text-center text-sm text-slate-500">Confirmer votre adresse</p>

            <div class="card space-y-4">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Un lien de confirmation vient d'être envoyé à
                    <strong class="text-slate-900 dark:text-slate-100">{{ email }}</strong>.
                    Ouvrez-le pour accéder à votre espace.
                </p>

                <p class="text-sm text-slate-500">
                    Cette adresse est aussi celle par laquelle vous récupérerez votre compte
                    en cas d'oubli de mot de passe : elle doit être exacte.
                </p>

                <p v-if="status" class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                    {{ status }}
                </p>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" class="btn-primary" :disabled="form.processing" @click="resend">
                        {{ form.processing ? 'Envoi…' : 'Renvoyer le lien' }}
                    </button>

                    <button
                        type="button"
                        class="text-sm text-slate-500 underline"
                        @click="logout.post('/logout')"
                    >
                        Se déconnecter
                    </button>
                </div>

                <p class="pt-2 text-sm text-slate-500">
                    Rien reçu ? Vérifiez les indésirables. Si l'adresse est erronée,
                    déconnectez-vous et contactez-nous : elle ne peut pas être changée
                    avant confirmation.
                </p>
            </div>
        </div>
    </div>
</template>
