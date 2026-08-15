<script setup>
import { computed } from 'vue';

/**
 * Marque Nonalix, posée sur un aplat noir.
 *
 * Le fichier logo est un PNG à texte sombre et N bleu, sans transparence
 * utile : il n'est lisible que sur fond clair. Les pages qui le voulaient en
 * blanc le retournaient donc au filtre CSS — `invert hue-rotate-180`, le
 * `hue-rotate` servant à ramener le bleu du N que l'`invert` avait viré au
 * orange.
 *
 * Cette astuce a produit sept écrans au logo invisible : l'en-tête commercial
 * l'appliquait sur un fond blanc (texte blanc sur blanc), et six pages
 * d'authentification l'appliquaient à l'envers — retourné en thème clair,
 * laissé sombre en thème sombre, donc illisible dans les deux cas.
 *
 * D'où ce composant : le fond noir est porté par la marque elle-même, et non
 * hérité de la page. Le rendu ne dépend plus du thème, et il n'y a plus qu'un
 * seul endroit où le filtre est écrit.
 */
const props = defineProps({
    size: { type: String, default: 'md' },
});

// Classes littérales : Tailwind ne génère que ce qu'il trouve écrit en toutes
// lettres dans les sources.
const SIZES = {
    sm: { box: 'rounded-lg px-2.5 py-1.5', img: 'h-4' },
    md: { box: 'rounded-xl px-3 py-2',     img: 'h-6' },
    lg: { box: 'rounded-2xl px-4 py-3',    img: 'h-8' },
};

const variant = computed(() => SIZES[props.size] ?? SIZES.md);
</script>

<template>
    <span class="inline-flex items-center bg-slate-950" :class="variant.box">
        <img
            src="/logo-nonalixia.png"
            alt="Nonalix IA"
            width="500"
            height="105"
            class="w-auto invert hue-rotate-180"
            :class="variant.img"
        />
    </span>
</template>
