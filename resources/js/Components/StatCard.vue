<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

/**
 * Carte de chiffre clé : tuile d'icône, intitulé, valeur.
 *
 * La valeur est le seul élément en gros corps. L'intitulé passe au-dessus en
 * petit gris — on lit d'abord le chiffre, on cherche ensuite ce qu'il compte.
 * L'ordre inverse, courant, oblige à relire chaque carte d'une rangée pour
 * retrouver celle qu'on cherchait.
 *
 * `href` rend la carte cliquable ; sans lui, elle reste inerte et ne prend
 * aucun effet de survol — un décollement au survol sur un bloc qui ne mène
 * nulle part est une promesse non tenue.
 */
const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: null },
    tone: { type: String, default: 'slate' },
    /** Précision sous la valeur : période, comparaison, unité. */
    hint: { type: String, default: '' },
    href: { type: String, default: null },
});

const TONES = {
    brand:   'tile-brand',
    emerald: 'tile-emerald',
    amber:   'tile-amber',
    violet:  'tile-violet',
    rose:    'tile-rose',
    slate:   'tile-slate',
};

const tile = computed(() => TONES[props.tone] ?? TONES.slate);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="card"
        :class="href && 'card-link block'"
    >
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ label }}</p>
            <span v-if="icon" :class="tile">
                <Icon :name="icon" />
            </span>
        </div>

        <!-- tabular-nums : sans lui, une rangée de cartes dont les chiffres
             changent en direct voit sa valeur bouger latéralement à chaque
             mise à jour. -->
        <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums text-slate-900 dark:text-white">
            {{ value }}
        </p>

        <p v-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</p>

        <slot />
    </component>
</template>
