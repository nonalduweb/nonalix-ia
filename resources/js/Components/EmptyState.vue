<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: 'inbox' },
    tone: { type: String, default: 'slate' },
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
    <!--
      Un écran vide doit expliquer pourquoi il est vide et quoi faire ensuite.
      Un simple « aucun résultat » laisse l'utilisateur se demander si la
      plateforme fonctionne.

      La tuile d'icône y sert autre chose que l'ornement : elle occupe le
      centre optique d'une zone autrement déserte, et empêche le texte de
      flotter sans ancrage.
    -->
    <div class="px-6 py-16 text-center">
        <span :class="tile" class="mx-auto mb-4 h-12 w-12">
            <Icon :name="icon" size="lg" />
        </span>

        <p class="font-medium text-slate-800 dark:text-slate-100">{{ title }}</p>
        <p v-if="description" class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">
            {{ description }}
        </p>
        <div class="mt-6 flex justify-center gap-2">
            <slot />
        </div>
    </div>
</template>
