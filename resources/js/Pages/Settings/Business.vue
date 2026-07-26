<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';

const props = defineProps({
    profile: { type: Object, default: null },
    hours: Array,
    days: Object,
    timezones: Array,
});

const profileForm = useForm({
    legal_name: props.profile?.legal_name ?? '',
    description: props.profile?.description ?? '',
    industry: props.profile?.industry ?? '',
    website: props.profile?.website ?? '',
    email: props.profile?.email ?? '',
    phone: props.profile?.phone ?? '',
    address_line1: props.profile?.address_line1 ?? '',
    address_line2: props.profile?.address_line2 ?? '',
    postal_code: props.profile?.postal_code ?? '',
    city: props.profile?.city ?? '',
    country: props.profile?.country ?? 'FR',
    timezone: props.profile?.timezone ?? 'Europe/Paris',
    currency: props.profile?.currency ?? 'EUR',
});

// Ordre d'affichage : lundi en premier, comme dans un agenda français.
const DAY_ORDER = [1, 2, 3, 4, 5, 6, 0];

// Le formulaire d'horaires est reconstruit à plat : le serveur remplace
// l'intégralité des plages, une plage supprimée ici doit disparaître en base.
const slots = ref(
    DAY_ORDER.flatMap((day) => {
        const existing = props.hours.filter((h) => h.day_of_week === day);

        return existing.length
            ? existing.map((h) => ({
                  day_of_week: day,
                  opens_at: h.opens_at?.slice(0, 5) ?? '',
                  closes_at: h.closes_at?.slice(0, 5) ?? '',
                  is_closed: h.is_closed,
              }))
            : [{ day_of_week: day, opens_at: '', closes_at: '', is_closed: true }];
    }),
);

const hoursForm = useForm({ hours: slots.value });

const addSlot = (day) => {
    const index = slots.value.findLastIndex((s) => s.day_of_week === day);
    slots.value.splice(index + 1, 0, {
        day_of_week: day,
        opens_at: '14:00',
        closes_at: '18:00',
        is_closed: false,
    });
};

const removeSlot = (index) => slots.value.splice(index, 1);

const saveProfile = () => profileForm.put('/settings/business', { preserveScroll: true });

const saveHours = () => {
    hoursForm.hours = slots.value;
    hoursForm.put('/settings/hours', { preserveScroll: true });
};
</script>

<template>
    <Head title="Entreprise" />

    <AppLayout>
        <h1 class="mb-6 text-xl font-semibold">Configuration</h1>
        <SettingsNav />

        <div class="grid gap-6 lg:grid-cols-2">
            <form class="card space-y-4" @submit.prevent="saveProfile">
                <div>
                    <h2 class="text-sm font-semibold">Identité</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Ces informations sont transmises à l'agent IA à chaque conversation.
                    </p>
                </div>

                <div>
                    <label class="label" for="legal_name">Raison sociale</label>
                    <input id="legal_name" v-model="profileForm.legal_name" type="text" class="input" required />
                    <p v-if="profileForm.errors.legal_name" class="mt-1 text-sm text-red-600">
                        {{ profileForm.errors.legal_name }}
                    </p>
                </div>

                <div>
                    <label class="label" for="description">Activité</label>
                    <textarea
                        id="description"
                        v-model="profileForm.description"
                        rows="3"
                        class="input resize-none"
                        maxlength="2000"
                        placeholder="Décrivez en quelques phrases ce que fait votre entreprise."
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="industry">Secteur</label>
                        <input id="industry" v-model="profileForm.industry" type="text" class="input" />
                    </div>
                    <div>
                        <label class="label" for="phone">Téléphone</label>
                        <input id="phone" v-model="profileForm.phone" type="tel" class="input" />
                    </div>
                    <div>
                        <label class="label" for="email">E-mail</label>
                        <input id="email" v-model="profileForm.email" type="email" class="input" />
                    </div>
                    <div>
                        <label class="label" for="website">Site web</label>
                        <input id="website" v-model="profileForm.website" type="url" class="input" />
                    </div>
                </div>

                <div>
                    <label class="label" for="address_line1">Adresse</label>
                    <input id="address_line1" v-model="profileForm.address_line1" type="text" class="input" />
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="label" for="postal_code">Code postal</label>
                        <input id="postal_code" v-model="profileForm.postal_code" type="text" class="input" />
                    </div>
                    <div class="col-span-2">
                        <label class="label" for="city">Ville</label>
                        <input id="city" v-model="profileForm.city" type="text" class="input" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="timezone">Fuseau horaire</label>
                        <select id="timezone" v-model="profileForm.timezone" class="input">
                            <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                        </select>
                        <!-- Le fuseau détermine si l'agent considère l'entreprise
                             ouverte ou fermée au moment où il répond. -->
                        <p class="mt-1 text-xs text-slate-500">Sert au calcul des horaires d'ouverture.</p>
                    </div>
                    <div>
                        <label class="label" for="currency">Devise</label>
                        <select id="currency" v-model="profileForm.currency" class="input">
                            <option value="EUR">EUR (€)</option>
                            <option value="CHF">CHF</option>
                            <option value="XOF">XOF</option>
                            <option value="MAD">MAD</option>
                            <option value="USD">USD ($)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-primary" :disabled="profileForm.processing">
                    Enregistrer
                </button>
            </form>

            <form class="card space-y-4" @submit.prevent="saveHours">
                <div>
                    <h2 class="text-sm font-semibold">Horaires d'ouverture</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        L'agent indique s'il est possible de vous joindre maintenant. Ajoutez
                        une seconde plage pour gérer une coupure du midi.
                    </p>
                </div>

                <div v-for="(slot, index) in slots" :key="index" class="flex items-center gap-2 text-sm">
                    <span
                        class="w-24 shrink-0 capitalize"
                        :class="index > 0 && slots[index - 1].day_of_week === slot.day_of_week && 'invisible'"
                    >
                        {{ days[slot.day_of_week] }}
                    </span>

                    <label class="flex items-center gap-1.5 text-xs text-slate-500">
                        <input v-model="slot.is_closed" type="checkbox" />
                        Fermé
                    </label>

                    <template v-if="!slot.is_closed">
                        <input v-model="slot.opens_at" type="time" class="input w-28 py-1" />
                        <span class="text-slate-400">–</span>
                        <input v-model="slot.closes_at" type="time" class="input w-28 py-1" />
                    </template>

                    <div class="ml-auto flex gap-2">
                        <button
                            v-if="index === slots.findLastIndex((s) => s.day_of_week === slot.day_of_week)"
                            type="button"
                            class="text-xs text-slate-400 hover:text-slate-700"
                            title="Ajouter une plage"
                            @click="addSlot(slot.day_of_week)"
                        >
                            +
                        </button>
                        <button
                            v-if="slots.filter((s) => s.day_of_week === slot.day_of_week).length > 1"
                            type="button"
                            class="text-xs text-red-500 hover:text-red-700"
                            @click="removeSlot(index)"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" :disabled="hoursForm.processing">
                    Enregistrer les horaires
                </button>
            </form>
        </div>
    </AppLayout>
</template>
