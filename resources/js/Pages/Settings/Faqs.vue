<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    faqs: Array,
});

const editing = ref(null);
const deleting = ref(null);

const form = useForm({
    question: '',
    answer: '',
    category: '',
    is_active: true,
    position: 0,
});

const openCreate = () => {
    form.defaults({ question: '', answer: '', category: '', is_active: true, position: 0 });
    form.reset();
    form.clearErrors();
    editing.value = 'new';
};

const openEdit = (faq) => {
    form.defaults({ ...faq });
    form.reset();
    form.clearErrors();
    editing.value = faq;
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => (editing.value = null) };

    editing.value === 'new'
        ? form.post('/settings/faqs', options)
        : form.put(`/settings/faqs/${editing.value.id}`, options);
};

const remove = () =>
    router.delete(`/settings/faqs/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
</script>

<template>
    <Head title="Questions fréquentes" />

    <AppLayout>
        <PageHeader
            title="Questions fréquentes"
            description="Les réponses toutes faites que l'agent réutilise mot pour mot, sans les reformuler."
            icon="book"
            tone="violet"
        >
            <template #actions>
                <button class="btn-primary" @click="openCreate">Ajouter</button>
            </template>
        </PageHeader>

        <SettingsNav />

        <!-- Avertissement conservé à part de la description : c'est une
             conséquence financière, pas une explication de l'écran. -->
        <p class="alert-info mt-5">
            Les questions fréquentes sont injectées intégralement dans le contexte de l'agent.
            Privilégiez des réponses courtes et factuelles : chaque réponse pèse sur le coût de
            <em>chaque</em> conversation.
        </p>

        <div class="card-flush mt-5 divide-y divide-slate-100 dark:divide-slate-800">
            <div v-for="faq in faqs" :key="faq.id" class="flex items-start gap-4 px-5 py-4">
                <div class="min-w-0 flex-1">
                    <p class="font-medium">{{ faq.question }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ faq.answer }}</p>
                    <p v-if="faq.category" class="mt-1 text-xs text-slate-400">{{ faq.category }}</p>
                </div>

                <span v-if="!faq.is_active" class="shrink-0 text-xs text-slate-400">masquée</span>

                <div class="shrink-0 whitespace-nowrap">
                    <button class="text-xs text-slate-500 hover:underline" @click="openEdit(faq)">
                        Modifier
                    </button>
                    <button class="ml-3 text-xs text-red-600 hover:underline" @click="deleting = faq">
                        Supprimer
                    </button>
                </div>
            </div>

            <EmptyState
                v-if="!faqs.length"
                title="Aucune question enregistrée"
                description="Reprenez les questions que vos clients posent le plus souvent par téléphone ou par message."
            >
                <button class="btn-primary" @click="openCreate">Ajouter une question</button>
            </EmptyState>
        </div>

        <Modal
            :open="!!editing"
            :title="editing === 'new' ? 'Nouvelle question' : 'Modifier la question'"
            @close="editing = null"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="label" for="question">Question</label>
                    <input id="question" v-model="form.question" type="text" class="input" required maxlength="500" />
                    <p v-if="form.errors.question" class="mt-1 text-sm text-red-600">{{ form.errors.question }}</p>
                </div>

                <div>
                    <label class="label" for="answer">Réponse</label>
                    <textarea id="answer" v-model="form.answer" rows="4" class="input resize-none" required maxlength="3000" />
                    <p class="mt-1 text-xs text-slate-500">{{ form.answer.length }} / 3000 caractères</p>
                    <p v-if="form.errors.answer" class="mt-1 text-sm text-red-600">{{ form.errors.answer }}</p>
                </div>

                <div>
                    <label class="label" for="category">Catégorie</label>
                    <input id="category" v-model="form.category" type="text" class="input" maxlength="80" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" />
                    Visible par l'agent IA
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary" @click="editing = null">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Enregistrer</button>
                </div>
            </form>
        </Modal>

        <Modal :open="!!deleting" title="Supprimer cette question ?" @close="deleting = null">
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-300">
                L'agent ne disposera plus de cette réponse.
            </p>
            <div class="flex justify-end gap-3">
                <button class="btn-secondary" @click="deleting = null">Annuler</button>
                <button class="btn-primary bg-red-600 hover:bg-red-700" @click="remove">Supprimer</button>
            </div>
        </Modal>
    </AppLayout>
</template>
