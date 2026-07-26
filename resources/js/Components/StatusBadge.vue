<script setup>
import { computed } from 'vue';

/**
 * Pastille de statut, unifiée pour toute l'application.
 *
 * Les couleurs sont centralisées ici plutôt que dispersées dans chaque page :
 * un statut « en échec » doit se lire de la même façon dans la messagerie,
 * la base de connaissances et l'administration.
 */
const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: null },
});

const PALETTE = {
    // Neutre / en attente
    queued: 'slate', pending: 'slate', unknown: 'slate', new: 'slate',
    invited: 'slate', extracting: 'slate', chunking: 'slate', embedding: 'slate',

    // En cours / positif faible
    open: 'blue', sent: 'blue', active: 'blue', trial: 'blue',
    contacted: 'blue', info: 'blue',

    // Succès
    ready: 'emerald', connected: 'emerald', delivered: 'emerald',
    read: 'emerald', qualified: 'emerald', won: 'emerald',
    opted_in: 'emerald', approved: 'emerald', processed: 'emerald',

    // Attention
    warning: 'amber', past_due: 'amber', snoozed: 'amber',
    disconnected: 'amber', paused: 'amber',

    // Problème
    failed: 'red', error: 'red', critical: 'red', suspended: 'red',
    rejected: 'red', lost: 'red', opted_out: 'red', disabled: 'red',

    // Terminé / inerte
    closed: 'zinc', unqualified: 'zinc', ignored: 'zinc',
};

const CLASSES = {
    slate:   'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    blue:    'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    amber:   'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    red:     'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    zinc:    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
};

const classes = computed(() => CLASSES[PALETTE[props.status] ?? 'slate']);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="classes"
    >
        {{ label ?? status }}
    </span>
</template>
