<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    documents: Object,
    stats: Object,
    limits: Object,
});

const adding = ref(false);
const deleting = ref(null);

const form = useForm({
    source_type: 'pdf',
    title: '',
    file: null,
    source_url: '',
});

const isUrl = computed(() => form.source_type === 'url');

const maxMegabytes = computed(() => Math.floor(props.limits.max_bytes / 1024 / 1024));

const STATUS_LABELS = {
    pending: 'En attente',
    extracting: 'Extraction du texte',
    chunking: 'Découpage',
    embedding: 'Vectorisation',
    ready: 'Prêt',
    failed: 'Échec',
};

const PROCESSING = ['pending', 'extracting', 'chunking', 'embedding'];

/*
 * L'ingestion est asynchrone et peut durer plusieurs minutes sur un gros PDF.
 * Tant qu'un document est en cours, on rafraîchit la liste : une barre figée
 * sans explication est perçue comme une panne.
 */
let poller = null;

const startPolling = () => {
    if (poller || !props.stats.processing) return;

    poller = setInterval(() => {
        router.reload({ only: ['documents', 'stats'] });
    }, 5000);
};

const stopPolling = () => {
    clearInterval(poller);
    poller = null;
};

watch(
    () => props.stats.processing,
    (processing) => (processing ? startPolling() : stopPolling()),
);

onMounted(startPolling);
onUnmounted(stopPolling);

const onFileChange = (event) => {
    const file = event.target.files[0];
    form.file = file;

    // Pré-remplit le titre avec le nom du fichier : dans la majorité des cas
    // c'est déjà le bon libellé.
    if (file && !form.title) {
        form.title = file.name.replace(/\.[^.]+$/, '');
    }
};

const submit = () =>
    form.post('/knowledge', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            adding.value = false;
        },
    });

const remove = () => {
    router.delete(`/knowledge/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
};

const reprocess = (document) =>
    router.post(`/knowledge/${document.id}/reprocess`, {}, { preserveScroll: true });

const formatSize = (bytes) =>
    bytes ? `${(bytes / 1024 / 1024).toFixed(1)} Mo` : '—';
</script>

<template>
    <Head title="Base de connaissances" />

    <AppLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Base de connaissances</h1>
                <p class="text-sm text-slate-500">
                    Documents consultés par l'agent IA pour répondre aux clients.
                </p>
            </div>
            <button class="btn-primary" @click="adding = true">Ajouter un document</button>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-4">
            <div class="card">
                <p class="text-sm text-slate-500">Documents prêts</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ stats.ready }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">En traitement</p>
                <p class="mt-1 text-2xl font-semibold">{{ stats.processing }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">En échec</p>
                <p class="mt-1 text-2xl font-semibold" :class="stats.failed && 'text-red-600'">
                    {{ stats.failed }}
                </p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Fragments indexés</p>
                <p class="mt-1 text-2xl font-semibold">{{ stats.chunks }}</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table v-if="documents.data.length" class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Document</th>
                        <th class="px-5 py-3 font-medium">Type</th>
                        <th class="px-5 py-3 font-medium">Taille</th>
                        <th class="px-5 py-3 font-medium">Fragments</th>
                        <th class="px-5 py-3 font-medium">Statut</th>
                        <th class="px-5 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="document in documents.data" :key="document.id">
                        <td class="px-5 py-3">
                            <p class="font-medium">{{ document.title }}</p>
                            <p v-if="document.error" class="mt-0.5 text-xs text-red-600">
                                {{ document.error }}
                            </p>
                            <p v-else-if="document.uploader" class="mt-0.5 text-xs text-slate-500">
                                Ajouté par {{ document.uploader.name }}
                            </p>
                        </td>
                        <td class="px-5 py-3 uppercase text-slate-500">{{ document.source_type }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ formatSize(document.size_bytes) }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ document.chunks_count || '—' }}</td>
                        <td class="px-5 py-3">
                            <span class="flex items-center gap-2">
                                <StatusBadge :status="document.status" :label="STATUS_LABELS[document.status]" />
                                <span
                                    v-if="PROCESSING.includes(document.status)"
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-500"
                                />
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button
                                v-if="!PROCESSING.includes(document.status)"
                                class="text-xs text-slate-500 hover:underline"
                                @click="reprocess(document)"
                            >
                                Réindexer
                            </button>
                            <button
                                class="ml-3 text-xs text-red-600 hover:underline"
                                @click="deleting = document"
                            >
                                Supprimer
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <EmptyState
                v-else
                title="Aucun document"
                description="Ajoutez vos documents commerciaux, contrats types ou fiches produits : l'agent s'en servira pour répondre avec vos informations plutôt que d'improviser."
            >
                <button class="btn-primary" @click="adding = true">Ajouter un document</button>
            </EmptyState>
        </div>

        <Pagination :paginator="documents" />

        <!-- Ajout -->
        <Modal :open="adding" title="Ajouter un document" @close="adding = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="label" for="source_type">Source</label>
                    <select id="source_type" v-model="form.source_type" class="input">
                        <option value="pdf">Fichier PDF</option>
                        <option value="docx">Document Word (DOCX)</option>
                        <option value="txt">Fichier texte</option>
                        <option value="url">Page web</option>
                    </select>
                </div>

                <div>
                    <label class="label" for="title">Titre</label>
                    <input id="title" v-model="form.title" type="text" class="input" maxlength="250" required />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                </div>

                <div v-if="!isUrl">
                    <label class="label" for="file">Fichier</label>
                    <input
                        id="file"
                        type="file"
                        class="input"
                        accept=".pdf,.docx,.txt,.md"
                        required
                        @change="onFileChange"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        {{ maxMegabytes }} Mo maximum. Les PDF scannés sans couche texte
                        ne peuvent pas être lus.
                    </p>
                    <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">{{ form.errors.file }}</p>
                </div>

                <div v-else>
                    <label class="label" for="source_url">Adresse de la page</label>
                    <input
                        id="source_url"
                        v-model="form.source_url"
                        type="url"
                        class="input"
                        placeholder="https://exemple.fr/nos-services"
                        required
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Les pages générées entièrement en JavaScript ne peuvent pas être lues.
                    </p>
                    <p v-if="form.errors.source_url" class="mt-1 text-sm text-red-600">
                        {{ form.errors.source_url }}
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary" @click="adding = false">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Envoi…' : 'Ajouter' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Suppression -->
        <Modal :open="!!deleting" title="Supprimer ce document ?" @close="deleting = null">
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-300">
                « {{ deleting?.title }} » et ses {{ deleting?.chunks_count || 0 }} fragments
                seront supprimés définitivement. L'agent ne pourra plus s'appuyer dessus.
            </p>
            <div class="flex justify-end gap-3">
                <button class="btn-secondary" @click="deleting = null">Annuler</button>
                <button class="btn-primary bg-red-600 hover:bg-red-700" @click="remove">Supprimer</button>
            </div>
        </Modal>
    </AppLayout>
</template>
