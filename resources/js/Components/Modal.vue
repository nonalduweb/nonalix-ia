<script setup>
import { onMounted, onUnmounted, watch } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['close']);

const onKeydown = (event) => {
    if (event.key === 'Escape' && props.open) emit('close');
};

// Le fond ne doit pas défiler derrière la modale : sur un formulaire long,
// c'est une source constante de désorientation.
watch(
    () => props.open,
    (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
    },
);

onMounted(() => document.addEventListener('keydown', onKeydown));

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Voile légèrement flouté : la page reste reconnaissable
                     derrière la modale, ce qui garde le contexte, mais cesse
                     d'être lisible et donc de disputer l'attention. -->
                <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="emit('close')" />

                <div class="flex min-h-full items-center justify-center p-4">
                    <div
                        class="relative w-full rounded-2xl bg-white p-6 shadow-lift sm:p-7 dark:bg-slate-900"
                        :class="maxWidth"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div v-if="title" class="mb-5 flex items-start justify-between gap-4">
                            <h2 class="text-base font-semibold tracking-tight text-slate-900 dark:text-white">
                                {{ title }}
                            </h2>
                            <button
                                type="button"
                                class="-m-1.5 cursor-pointer rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                aria-label="Fermer"
                                @click="emit('close')"
                            >
                                <Icon name="close" size="sm" />
                            </button>
                        </div>

                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
