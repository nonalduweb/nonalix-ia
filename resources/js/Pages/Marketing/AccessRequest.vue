<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { formatMoney } from '@/money';

const props = defineProps({
    plans: Array,
    preselectedPlan: { type: String, default: null },
    // Posé en session par le contrôleur après un envoi réussi : `back()`
    // repasse par la page, qui affiche alors la confirmation.
    submitted: { type: Boolean, default: false },
});

const form = useForm({
    company: '',
    contact_name: '',
    email: '',
    phone: '',
    plan_id: props.plans.find((p) => p.slug === props.preselectedPlan)?.id ?? '',
    message: '',
    // Leurre anti-robots : invisible et jamais rempli par un humain.
    website: '',
});

const submit = () => form.post('/demande', { preserveScroll: true });
</script>

<template>
    <Head title="Demander un accès" />

    <MarketingLayout>
        <section class="mx-auto max-w-2xl px-6 pt-20 pb-24 sm:pt-28">
            <div v-if="submitted" class="rounded-2xl border border-slate-200 p-10 text-center">
                <p class="text-2xl font-semibold tracking-tight">Demande enregistrée</p>
                <p class="mx-auto mt-4 max-w-md leading-relaxed text-slate-600">
                    Nous revenons vers vous très vite, à l'adresse indiquée, avec votre
                    code d'accès. Il vous permettra de créer votre espace en quelques
                    minutes.
                </p>
            </div>

            <template v-else>
                <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">
                    Demander un accès
                </h1>
                <p class="mt-5 text-lg leading-relaxed text-slate-600">
                    Nous ouvrons les comptes un par un, pour accompagner chaque
                    entreprise à la mise en route. Décrivez-nous votre activité : vous
                    recevrez un code d'accès par e-mail.
                </p>

                <form class="mt-12 space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="label" for="company">Nom de l'entreprise</label>
                        <input
                            id="company"
                            v-model="form.company"
                            type="text"
                            class="input"
                            required
                            placeholder="Boulangerie Kouassi"
                        />
                        <p v-if="form.errors.company" class="mt-1 text-sm text-red-600">{{ form.errors.company }}</p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="label" for="contact_name">Votre nom</label>
                            <input id="contact_name" v-model="form.contact_name" type="text" class="input" required />
                            <p v-if="form.errors.contact_name" class="mt-1 text-sm text-red-600">
                                {{ form.errors.contact_name }}
                            </p>
                        </div>
                        <div>
                            <label class="label" for="phone">Téléphone</label>
                            <input
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                class="input"
                                placeholder="+225 07 00 00 00 00"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="label" for="email">Adresse e-mail</label>
                        <input id="email" v-model="form.email" type="email" class="input" required />
                        <p class="mt-1 text-xs text-slate-500">Votre code d'accès y sera envoyé.</p>
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="label" for="plan_id">Pack envisagé</label>
                        <select id="plan_id" v-model="form.plan_id" class="input">
                            <option value="">Je ne sais pas encore</option>
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }} — {{ formatMoney(plan.price_cents, plan.currency) }} / mois
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Indicatif : vous pourrez en changer.</p>
                    </div>

                    <div>
                        <label class="label" for="message">Votre activité</label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="4"
                            class="input resize-none"
                            maxlength="2000"
                            placeholder="Ce que vous vendez, le volume de messages que vous recevez, ce que vous attendez de l'agent."
                        />
                    </div>

                    <!-- Leurre : masqué à l'écran et retiré de la navigation au
                         clavier. Un robot qui remplit tous les champs se signale. -->
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Site web</label>
                        <input id="website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
                    </div>

                    <p v-if="form.errors.website" class="text-sm text-red-600">{{ form.errors.website }}</p>

                    <button type="submit" class="btn-ink w-full px-6 py-3 text-base" :disabled="form.processing">
                        {{ form.processing ? 'Envoi…' : 'Envoyer ma demande' }}
                    </button>

                    <p class="text-center text-sm text-slate-500">
                        Réponse sous 24 h ouvrées. Aucune carte bancaire n'est demandée.
                    </p>
                </form>
            </template>
        </section>
    </MarketingLayout>
</template>
