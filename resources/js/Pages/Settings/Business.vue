<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    profile: { type: Object, default: null },
    hours: Array,
    days: Object,
    timezoneGroups: Object,
    currencies: Object,
    defaults: Object,
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
    // Valeurs par défaut fournies par le serveur (config/nonalix.php) et non
    // codées en dur ici : elles doivent suivre le marché servi.
    country: props.profile?.country ?? props.defaults.country,
    timezone: props.profile?.timezone ?? props.defaults.timezone,
    currency: props.profile?.currency ?? props.defaults.currency,
});

// Secteurs proposés en suggestion, sans contraindre : le champ reste libre
// pour les activités qui n'entrent dans aucune case.
const SECTORS = [
    'Restauration', 'Coiffure et beauté', 'Santé et bien-être', 'Immobilier',
    'Commerce de détail', 'Artisanat et BTP', 'Automobile', 'Hôtellerie',
    'Sport et remise en forme', 'Formation', 'Services aux entreprises',
];

const WEEKEND = [0, 6];

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

// Un compte neuf arrive avec les sept jours fermés. Sans avertissement,
// l'agent répondrait « nous sommes fermés » à chaque client, en permanence,
// et rien à l'écran n'expliquerait pourquoi.
const allClosed = computed(() => slots.value.every((s) => s.is_closed));

const applyTypicalWeek = () => {
    slots.value = DAY_ORDER.map((day) => ({
        day_of_week: day,
        opens_at: WEEKEND.includes(day) ? '' : '09:00',
        closes_at: WEEKEND.includes(day) ? '' : '18:00',
        is_closed: WEEKEND.includes(day),
    }));
};

const setOpen = (slot, isOpen) => {
    slot.is_closed = !isOpen;

    // Ouvrir un jour vierge sans horaire donnerait une plage vide, refusée
    // par le serveur : on propose des valeurs plausibles à corriger.
    if (isOpen && !slot.opens_at && !slot.closes_at) {
        slot.opens_at = '09:00';
        slot.closes_at = '18:00';
    }
};

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

const isLastSlotOfDay = (slot, index) =>
    index === slots.value.findLastIndex((s) => s.day_of_week === slot.day_of_week);

const daySlotCount = (day) => slots.value.filter((s) => s.day_of_week === day).length;

// Les erreurs de plage arrivent indexées (`hours.3.closes_at`) : les afficher
// telles quelles imposerait au client de compter les lignes.
const hasSlotError = computed(() =>
    Object.keys(hoursForm.errors).some((key) => key.startsWith('hours.')),
);

const saveProfile = () => profileForm.put('/settings/business', { preserveScroll: true });

const saveHours = () => {
    hoursForm.hours = slots.value;
    hoursForm.put('/settings/hours', { preserveScroll: true });
};
</script>

<template>
    <Head title="Entreprise" />

    <AppLayout>
        <PageHeader
            title="Entreprise"
            description="Les informations que l'agent cite quand un client demande vos horaires, votre adresse ou votre secteur."
            icon="cog"
            tone="brand"
        />

        <SettingsNav />

        <div class="grid gap-6 lg:grid-cols-2">
            <form class="card space-y-5" @submit.prevent="saveProfile">
                <div>
                    <h2 class="text-sm font-semibold">Identité</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Ces informations sont transmises à l'agent IA à chaque conversation.
                        Plus elles sont précises, plus ses réponses le sont.
                    </p>
                </div>

                <div>
                    <label class="label flex items-center gap-1" for="legal_name">
                        Raison sociale
                        <span class="text-red-500" aria-hidden="true">*</span>
                        <span class="group relative cursor-help text-slate-400 hover:text-slate-600">
                            ⓘ
                            <span class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] font-normal p-2 rounded shadow-md z-50 w-48 leading-normal normal-case">
                                Le nom légal ou commercial sous lequel l'agent se présente aux clients.
                            </span>
                        </span>
                    </label>
                    <input
                        id="legal_name"
                        v-model="profileForm.legal_name"
                        type="text"
                        class="input"
                        required
                        placeholder="Boulangerie Kouassi"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Le nom sous lequel l'agent se présentera à vos clients.
                    </p>
                    <p v-if="profileForm.errors.legal_name" class="mt-1 text-sm text-red-600">
                        {{ profileForm.errors.legal_name }}
                    </p>
                </div>

                <div>
                    <label class="label flex items-center gap-1" for="description">
                        Activité
                        <span class="text-red-500" aria-hidden="true">*</span>
                        <span class="group relative cursor-help text-slate-400 hover:text-slate-600">
                            ⓘ
                            <span class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] font-normal p-2 rounded shadow-md z-50 w-48 leading-normal normal-case">
                                Décrivez précisément votre activité, ce que vous vendez et vos règles. L'agent s'appuiera dessus.
                            </span>
                        </span>
                    </label>
                    <textarea
                        id="description"
                        v-model="profileForm.description"
                        rows="4"
                        class="input resize-none"
                        maxlength="2000"
                        placeholder="Boulangerie artisanale à Cocody. Pains, viennoiseries et sandwichs le midi. Commandes de pièces montées sur demande, 48 h à l'avance."
                    />
                    <div class="mt-1 flex items-start justify-between gap-3">
                        <p class="text-xs text-slate-500">
                            Ce que vous vendez, à qui, et vos particularités. C'est la base
                            de toutes les réponses de l'agent.
                        </p>
                        <span
                            class="shrink-0 text-xs tabular-nums"
                            :class="profileForm.description.length < 40 ? 'text-amber-600' : 'text-slate-400'"
                        >
                            {{ profileForm.description.length }} / 2000
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label flex items-center gap-1" for="industry">
                            Secteur
                            <span class="group relative cursor-help text-slate-400 hover:text-slate-600">
                                ⓘ
                                <span class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] font-normal p-2 rounded shadow-md z-50 w-48 leading-normal normal-case">
                                    Le secteur d'activité pour adapter le comportement de l'agent.
                                </span>
                            </span>
                        </label>
                        <input
                            id="industry"
                            v-model="profileForm.industry"
                            type="text"
                            class="input"
                            list="sector-suggestions"
                            placeholder="Restauration"
                        />
                        <!-- datalist et non select : les suggestions guident sans
                             enfermer les activités qui n'entrent dans aucune case. -->
                        <datalist id="sector-suggestions">
                            <option v-for="sector in SECTORS" :key="sector" :value="sector" />
                        </datalist>
                    </div>
                    <div>
                        <label class="label flex items-center gap-1" for="phone">
                            Téléphone
                            <span class="group relative cursor-help text-slate-400 hover:text-slate-600">
                                ⓘ
                                <span class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] font-normal p-2 rounded shadow-md z-50 w-48 leading-normal normal-case">
                                    Le numéro de téléphone officiel de l'entreprise.
                                </span>
                            </span>
                        </label>
                        <input
                            id="phone"
                            v-model="profileForm.phone"
                            type="tel"
                            class="input"
                            placeholder="+225 07 00 00 00 00"
                        />
                        <p class="mt-1 text-xs text-slate-500">Communiqué sur demande.</p>
                    </div>
                    <div>
                        <label class="label flex items-center gap-1" for="email">
                            E-mail
                            <span class="group relative cursor-help text-slate-400 hover:text-slate-600">
                                ⓘ
                                <span class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] font-normal p-2 rounded shadow-md z-50 w-48 leading-normal normal-case">
                                    L'adresse de contact principale de votre entreprise.
                                </span>
                            </span>
                        </label>
                        <input
                            id="email"
                            v-model="profileForm.email"
                            type="email"
                            class="input"
                            placeholder="contact@exemple.ci"
                        />
                        <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">
                            {{ profileForm.errors.email }}
                        </p>
                    </div>
                    <div>
                        <label class="label flex items-center gap-1" for="website">
                            Site web
                            <span class="group relative cursor-help text-slate-400 hover:text-slate-600">
                                ⓘ
                                <span class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] font-normal p-2 rounded shadow-md z-50 w-48 leading-normal normal-case">
                                    L'URL de votre site internet institutionnel ou e-commerce.
                                </span>
                            </span>
                        </label>
                        <input
                            id="website"
                            v-model="profileForm.website"
                            type="url"
                            class="input"
                            placeholder="https://exemple.ci"
                        />
                        <p v-if="profileForm.errors.website" class="mt-1 text-sm text-red-600">
                            {{ profileForm.errors.website }}
                        </p>
                    </div>
                </div>

                <div>
                    <label class="label flex items-center gap-1" for="address_line1">
                        Adresse
                        <span class="group relative cursor-help text-slate-400 hover:text-slate-600">
                            ⓘ
                            <span class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] font-normal p-2 rounded shadow-md z-50 w-48 leading-normal normal-case">
                                L'adresse géographique complète de vos locaux.
                            </span>
                        </span>
                    </label>
                    <input
                        id="address_line1"
                        v-model="profileForm.address_line1"
                        type="text"
                        class="input"
                        placeholder="Rue des Jardins, Cocody"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Facultatif, mais l'agent pourra indiquer où vous trouver.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="label" for="postal_code">Code postal</label>
                        <input
                            id="postal_code"
                            v-model="profileForm.postal_code"
                            type="text"
                            class="input"
                            placeholder="01 BP 1234"
                        />
                    </div>
                    <div class="col-span-2">
                        <label class="label" for="city">Ville</label>
                        <input
                            id="city"
                            v-model="profileForm.city"
                            type="text"
                            class="input"
                            placeholder="Abidjan"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label flex items-center gap-1" for="timezone">
                            Fuseau horaire
                            <span class="group relative cursor-help text-slate-400 hover:text-slate-600">
                                ⓘ
                                <span class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] font-normal p-2 rounded shadow-md z-50 w-48 leading-normal normal-case">
                                    Détermine l'heure locale de l'entreprise pour gérer les horaires d'ouverture.
                                </span>
                            </span>
                        </label>
                        <select id="timezone" v-model="profileForm.timezone" class="input">
                            <optgroup v-for="(zones, continent) in timezoneGroups" :key="continent" :label="continent">
                                <option v-for="tz in zones" :key="tz" :value="tz">{{ tz }}</option>
                            </optgroup>
                        </select>
                        <!-- Le fuseau détermine si l'agent considère l'entreprise
                             ouverte ou fermée au moment où il répond. -->
                        <p class="mt-1 text-xs text-slate-500">
                            Détermine si l'agent vous considère ouvert au moment où il répond.
                        </p>
                    </div>
                    <div>
                        <label class="label flex items-center gap-1" for="currency">
                            Devise
                            <span class="group relative cursor-help text-slate-400 hover:text-slate-600">
                                ⓘ
                                <span class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[10px] font-normal p-2 rounded shadow-md z-50 w-48 leading-normal normal-case">
                                    La devise monétaire par défaut pour l'affichage de vos tarifs.
                                </span>
                            </span>
                        </label>
                        <select id="currency" v-model="profileForm.currency" class="input">
                            <option v-for="(label, code) in currencies" :key="code" :value="code">
                                {{ label }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Utilisée pour annoncer vos tarifs.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <button type="submit" class="btn-primary" :disabled="profileForm.processing || !profileForm.isDirty">
                        {{ profileForm.processing ? 'Enregistrement…' : 'Enregistrer' }}
                    </button>
                    <!-- isDirty : sans ce repère, on ne sait pas si le bouton a
                         déjà été cliqué ni s'il reste des modifications en attente. -->
                    <span v-if="profileForm.isDirty" class="text-xs text-amber-600">
                        Modifications non enregistrées
                    </span>
                    <span v-else-if="profileForm.recentlySuccessful" class="text-xs text-emerald-600">
                        Enregistré
                    </span>
                </div>
            </form>

            <form class="card space-y-4" @submit.prevent="saveHours">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">Horaires d'ouverture</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            L'agent indique s'il est possible de vous joindre maintenant.
                            Ajoutez une seconde plage pour gérer une coupure du midi.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn-secondary shrink-0 text-xs"
                        @click="applyTypicalWeek"
                    >
                        Semaine type
                    </button>
                </div>

                <p
                    v-if="allClosed"
                    class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                >
                    Aucun jour n'est ouvert : l'agent répondra à tous vos clients que vous
                    êtes fermé. Utilisez « Semaine type » pour partir de lundi–vendredi, 9 h–18 h.
                </p>

                <div class="space-y-1">
                    <div
                        v-for="(slot, index) in slots"
                        :key="index"
                        class="flex items-center gap-3 rounded-md px-2 py-1.5 text-sm"
                        :class="slot.is_closed ? 'opacity-60' : 'bg-slate-50 dark:bg-slate-800/40'"
                    >
                        <span
                            class="w-24 shrink-0 capitalize"
                            :class="index > 0 && slots[index - 1].day_of_week === slot.day_of_week && 'invisible'"
                        >
                            {{ days[slot.day_of_week] }}
                        </span>

                        <!-- Libellé positif : « Ouvert » coché se lit directement,
                             là où « Fermé » coché imposait une double négation. -->
                        <label class="flex w-20 shrink-0 items-center gap-1.5 text-xs">
                            <input
                                type="checkbox"
                                :checked="!slot.is_closed"
                                @change="setOpen(slot, $event.target.checked)"
                            />
                            <span :class="slot.is_closed ? 'text-slate-400' : 'text-slate-700 dark:text-slate-200'">
                                {{ slot.is_closed ? 'Fermé' : 'Ouvert' }}
                            </span>
                        </label>

                        <template v-if="!slot.is_closed">
                            <input v-model="slot.opens_at" type="time" class="input w-28 py-1" required />
                            <span class="text-slate-400">–</span>
                            <input v-model="slot.closes_at" type="time" class="input w-28 py-1" required />
                        </template>

                        <div class="ml-auto flex items-center gap-2">
                            <button
                                v-if="isLastSlotOfDay(slot, index) && !slot.is_closed"
                                type="button"
                                class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                                @click="addSlot(slot.day_of_week)"
                            >
                                + plage
                            </button>
                            <button
                                v-if="daySlotCount(slot.day_of_week) > 1"
                                type="button"
                                class="text-xs text-red-500 hover:text-red-700"
                                title="Supprimer cette plage"
                                @click="removeSlot(index)"
                            >
                                Retirer
                            </button>
                        </div>
                    </div>
                </div>

                <p v-if="hoursForm.errors.hours" class="text-sm text-red-600">
                    {{ hoursForm.errors.hours }}
                </p>
                <!-- L'heure de fermeture doit suivre l'ouverture : la règle est
                     posée côté serveur, mais son message par défaut désigne un
                     index de tableau incompréhensible pour le client. -->
                <p v-else-if="hasSlotError" class="text-sm text-red-600">
                    Vérifiez vos plages : l'heure de fermeture doit être postérieure à celle d'ouverture.
                </p>

                <div class="flex items-center gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <button type="submit" class="btn-primary" :disabled="hoursForm.processing">
                        {{ hoursForm.processing ? 'Enregistrement…' : 'Enregistrer les horaires' }}
                    </button>
                    <span v-if="hoursForm.recentlySuccessful" class="text-xs text-emerald-600">
                        Enregistré
                    </span>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
