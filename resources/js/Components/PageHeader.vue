<script setup>
import Icon from '@/Components/Icon.vue';

/**
 * En-tête de page : intitulé, phrase d'explication, actions.
 *
 * Chaque page réécrivait son propre titre, avec une taille et une marge
 * légèrement différentes à chaque fois. L'écart ne se voit pas page à page,
 * mais il se sent en naviguant — c'est ce qui donnait l'impression de passer
 * d'un outil à l'autre plutôt que de circuler dans un seul.
 *
 * La phrase d'explication n'est pas décorative : elle dit à quoi sert l'écran.
 * Un intitulé seul (« Prospects ») laisse deviner.
 */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: null },
    tone: { type: String, default: 'slate' },
});

/*
 * Table explicite plutôt qu'une classe composée (`tile-${tone}`) : la classe
 * assemblée fonctionnerait ici, mais rien ne le dirait au prochain lecteur,
 * et la même construction appliquée à un utilitaire Tailwind échouerait
 * silencieusement. Une seule règle pour tout le projet vaut mieux qu'une
 * exception à retenir.
 */
const TONES = {
    brand:   'tile-brand',
    emerald: 'tile-emerald',
    amber:   'tile-amber',
    violet:  'tile-violet',
    rose:    'tile-rose',
    slate:   'tile-slate',
};
</script>

<template>
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <span v-if="icon" :class="TONES[tone] ?? TONES.slate">
                <Icon :name="icon" />
            </span>

            <div>
                <h1 class="page-title">{{ title }}</h1>
                <p v-if="description" class="page-subtitle max-w-2xl">{{ description }}</p>
                <!-- Complément libre : compteur, filtre actif, fil d'Ariane. -->
                <slot name="meta" />
            </div>
        </div>

        <!-- Les actions restent alignées en haut : sur une page dont
             l'explication tient sur deux lignes, un bouton centré
             verticalement flotterait sans repère. -->
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
