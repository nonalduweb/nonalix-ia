<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <!--
      Un écran vide doit expliquer pourquoi il est vide et quoi faire ensuite.
      Un simple « aucun résultat » laisse l'utilisateur se demander si la
      plateforme fonctionne.
    -->
    <div class="px-6 py-16 text-center">
        <p class="font-medium text-slate-700 dark:text-slate-200">{{ title }}</p>
        <p v-if="description" class="mx-auto mt-2 max-w-md text-sm text-slate-500">
            {{ description }}
        </p>
        <div class="mt-5">
            <slot />
        </div>
    </div>
</template>
