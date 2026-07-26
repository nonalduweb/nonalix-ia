<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    conversation: Object,
    messages: Array,
    notes: Array,
    lead: Object,
    operators: Array,
    windowOpen: Boolean,
    windowExpires: String,
});

const page = usePage();
const thread = ref(null);
const live = ref([...props.messages]);

const form = useForm({ body: '' });
const noteForm = useForm({ body: '' });

const scrollToBottom = () =>
    nextTick(() => {
        if (thread.value) thread.value.scrollTop = thread.value.scrollHeight;
    });

const send = () => {
    if (!form.body.trim()) return;

    form.post(`/conversations/${props.conversation.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body');
            router.reload({ only: ['messages', 'conversation'] });
        },
    });
};

/*
 * Le fil est alimenté en direct : la réponse de l'agent IA arrive quelques
 * secondes après le message du contact, sans action de l'opérateur.
 */
let channelName = null;

onMounted(() => {
    scrollToBottom();

    const tenantId = page.props.tenant?.id;

    if (!tenantId || !window.Echo) return;

    channelName = `tenant.${tenantId}.conversation.${props.conversation.id}`;

    window.Echo.private(channelName)
        .listen('.message.created', (event) => {
            // Un message envoyé depuis cet onglet est déjà affiché.
            if (live.value.some((m) => m.id === event.id)) return;

            live.value.push(event);
            scrollToBottom();
        })
        .listen('.message.status', (event) => {
            const message = live.value.find((m) => m.id === event.id);

            if (message) {
                message.status = event.status;
                message.error = event.error;
            }
        });
});

onUnmounted(() => {
    if (channelName && window.Echo) window.Echo.leave(channelName);
});

const toggleAi = () => {
    const action = props.conversation.ai_enabled ? 'handover' : 'resume-ai';

    router.post(`/conversations/${props.conversation.id}/${action}`, {}, { preserveScroll: true });
};

const assign = (event) =>
    router.post(
        `/conversations/${props.conversation.id}/assign`,
        { user_id: event.target.value || null },
        { preserveScroll: true },
    );

const addNote = () =>
    noteForm.post(`/conversations/${props.conversation.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset('body'),
    });

const alignment = (message) => (message.direction === 'in' ? 'justify-start' : 'justify-end');

const bubble = (message) => {
    if (message.direction === 'in') return 'bg-white dark:bg-slate-800';
    if (message.sender_type === 'ai') return 'bg-brand-50 dark:bg-slate-700';
    if (message.sender_type === 'system') return 'bg-slate-100 italic dark:bg-slate-800';

    return 'bg-brand-600 text-white';
};

const statusIcon = (status) =>
    ({ queued: '🕓', sent: '✓', delivered: '✓✓', read: '✓✓', failed: '⚠' })[status] ?? '';
</script>

<template>
    <Head :title="conversation.contact?.name || 'Conversation'" />

    <AppLayout>
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="card mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="font-semibold">
                            {{ conversation.contact?.name || conversation.contact?.profile_name || '+' + conversation.contact?.wa_id }}
                        </h1>
                        <p class="text-xs text-slate-500">+{{ conversation.contact?.wa_id }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <select class="input max-w-44 text-sm" :value="conversation.assigned_user_id ?? ''" @change="assign">
                            <option value="">Non attribuée</option>
                            <option v-for="op in operators" :key="op.id" :value="op.id">{{ op.name }}</option>
                        </select>

                        <button class="btn-secondary text-sm" @click="toggleAi">
                            {{ conversation.ai_enabled ? "Reprendre la main" : "Réactiver l'IA" }}
                        </button>
                    </div>
                </div>

                <!-- Hors fenêtre de 24 h, Meta n'accepte plus qu'un template
                     approuvé : l'opérateur doit le savoir AVANT d'écrire. -->
                <div v-if="!windowOpen" class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    La fenêtre de 24 h est fermée. Seul un modèle de message approuvé peut être envoyé.
                </div>

                <div ref="thread" class="card mb-4 h-[28rem] space-y-3 overflow-y-auto">
                    <div v-for="message in live" :key="message.id" class="flex" :class="alignment(message)">
                        <div class="max-w-[75%] rounded-2xl px-4 py-2 text-sm shadow-sm" :class="bubble(message)">
                            <p class="whitespace-pre-wrap">{{ message.body }}</p>
                            <p class="mt-1 flex items-center justify-end gap-1 text-[11px] opacity-70">
                                <span v-if="message.sender_type === 'ai'">IA ·</span>
                                {{ new Date(message.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                                <span v-if="message.direction === 'out'">{{ statusIcon(message.status) }}</span>
                            </p>
                            <p v-if="message.error" class="mt-1 text-[11px] text-red-600">{{ message.error }}</p>
                        </div>
                    </div>
                </div>

                <form class="card flex gap-3" @submit.prevent="send">
                    <textarea
                        v-model="form.body"
                        rows="2"
                        class="input flex-1 resize-none"
                        :disabled="!windowOpen"
                        placeholder="Votre réponse…"
                        @keydown.enter.exact.prevent="send"
                    />
                    <button type="submit" class="btn-primary self-end" :disabled="form.processing || !windowOpen">
                        Envoyer
                    </button>
                </form>
                <p v-if="form.errors.body" class="mt-2 text-sm text-red-600">{{ form.errors.body }}</p>
            </div>

            <aside class="space-y-4">
                <div v-if="lead" class="card">
                    <h2 class="mb-2 text-sm font-semibold">Prospect</h2>
                    <p class="text-sm">Statut : {{ lead.status }}</p>
                    <p class="text-sm">Score : {{ lead.score }}/100</p>
                    <dl class="mt-2 space-y-1 text-xs text-slate-600">
                        <div v-for="(value, key) in lead.qualification" :key="key">
                            <dt class="inline font-medium">{{ key }} :</dt>
                            <dd class="inline"> {{ value }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="card">
                    <h2 class="mb-3 text-sm font-semibold">Notes internes</h2>
                    <p class="mb-3 text-xs text-slate-500">
                        Visibles uniquement par votre équipe. Jamais transmises au contact ni à l'IA.
                    </p>

                    <form class="mb-3 space-y-2" @submit.prevent="addNote">
                        <textarea v-model="noteForm.body" rows="2" class="input resize-none" placeholder="Ajouter une note…" />
                        <button type="submit" class="btn-secondary w-full text-sm" :disabled="noteForm.processing">
                            Ajouter
                        </button>
                    </form>

                    <div v-for="note in notes" :key="note.id" class="border-t border-slate-100 py-2 text-sm dark:border-slate-800">
                        <p class="whitespace-pre-wrap">{{ note.body }}</p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{ note.author?.name }} · {{ new Date(note.created_at).toLocaleString('fr-FR') }}
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
