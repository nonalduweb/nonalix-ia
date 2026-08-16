<script setup>
import Icon from '@/Components/Icon.vue';

/*
 * Illustration du héros : le téléphone est le point fixe, les capacités de
 * l'agent flottent autour comme sur les visuels publicitaires du produit.
 * Purement décoratif — aria-hidden sur le conteneur — donc pas de logique,
 * juste la mise en scène en CSS.
 */
const capabilities = [
    { icon: 'chat', label: 'Service client', tone: 'tone-brand', position: 'pos-tl' },
    { icon: 'trending', label: 'Ventes', tone: 'tone-emerald', position: 'pos-tr' },
    { icon: 'book', label: 'Catalogue', tone: 'tone-amber', position: 'pos-bl' },
    { icon: 'sparkles', label: 'Agent IA', tone: 'tone-violet', position: 'pos-br' },
];
</script>

<template>
    <div class="agent-orbit" aria-hidden="true">
        <div class="phone-mock">
            <div class="flex items-center gap-2 border-b border-slate-100 pb-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-white">
                    <Icon name="chat" size="sm" />
                </span>
                <span>
                    <span class="block text-[11px] font-medium text-slate-900">Assistant Nonalix</span>
                    <span class="flex items-center gap-1 text-[10px] text-emerald-600">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        En ligne
                    </span>
                </span>
            </div>

            <div class="mt-3 space-y-1.5">
                <p class="bubble bubble-in">Bonjour, avez-vous le sac en cuir noir ?</p>
                <p class="bubble bubble-out">Oui, disponible — 25 000 F CFA. Je prépare la commande ?</p>
                <p class="bubble bubble-in bubble-typing">
                    <span /><span /><span />
                </p>
            </div>
        </div>

        <div
            v-for="cap in capabilities"
            :key="cap.label"
            class="float-card"
            :class="cap.position"
        >
            <span class="tile-mini" :class="cap.tone">
                <Icon :name="cap.icon" size="sm" />
            </span>
            <span class="text-xs font-medium text-slate-700">{{ cap.label }}</span>
        </div>
    </div>
</template>
