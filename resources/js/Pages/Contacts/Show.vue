<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    contact: Object,
    conversations: Array,
    leads: Array,
    consentLogs: Array,
});

const form = useForm({
    name: props.contact.name ?? '',
    email: props.contact.email ?? '',
});

const confirmingOptOut = ref(false);

const save = () => form.put(`/contacts/${props.contact.id}`, { preserveScroll: true });

const optOut = () => {
    router.post(`/contacts/${props.contact.id}/opt-out`, {}, {
        preserveScroll: true,
        onFinish: () => (confirmingOptOut.value = false),
    });
};

const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR') : '—');
</script>

<template>
    <Head :title="contact.name || 'Contact'" />

    <AppLayout>
        <Link href="/contacts" class="mb-4 inline-block text-sm text-slate-500 hover:underline">
            ← Contacts
        </Link>

        <PageHeader
            :title="contact.name || contact.profile_name || 'Contact sans nom'"
            icon="users"
            tone="brand"
        >
            <template #meta>
                <p class="mt-1.5 font-mono text-sm text-slate-500">+{{ contact.wa_id }}</p>
            </template>
            <template #actions>
                <StatusBadge :status="contact.opt_in_status" />
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <form class="card space-y-4" @submit.prevent="save">
                    <h2 class="text-sm font-semibold">Fiche</h2>

                    <div>
                        <label class="label" for="name">Nom</label>
                        <input id="name" v-model="form.name" type="text" class="input" />
                        <!-- Le nom saisi ici prime sur le nom de profil WhatsApp,
                             qui continue d'être rafraîchi à chaque message. -->
                        <p class="mt-1 text-xs text-slate-500">
                            Nom du profil WhatsApp : {{ contact.profile_name || 'inconnu' }}
                        </p>
                    </div>

                    <div>
                        <label class="label" for="email">E-mail</label>
                        <input id="email" v-model="form.email" type="email" class="input" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <button type="submit" class="btn-primary" :disabled="form.processing">Enregistrer</button>
                </form>

                <section class="card p-0">
                    <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">
                        Conversations
                    </h2>
                    <Link
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        :href="`/conversations/${conversation.id}`"
                        class="flex items-center justify-between border-b border-slate-100 px-5 py-3 text-sm transition last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"
                    >
                        <span>{{ formatDateTime(conversation.last_message_at) }}</span>
                        <StatusBadge :status="conversation.status" />
                    </Link>
                    <p v-if="!conversations.length" class="px-5 py-6 text-sm text-slate-500">
                        Aucune conversation.
                    </p>
                </section>

                <section v-if="leads.length" class="card p-0">
                    <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold dark:border-slate-800">
                        Prospects
                    </h2>
                    <Link
                        v-for="lead in leads"
                        :key="lead.id"
                        :href="`/leads/${lead.id}`"
                        class="flex items-center justify-between border-b border-slate-100 px-5 py-3 text-sm transition last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"
                    >
                        <span>Score {{ lead.score }}/100</span>
                        <StatusBadge :status="lead.status" />
                    </Link>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card">
                    <h2 class="mb-3 text-sm font-semibold">Consentement</h2>

                    <p class="mb-4 text-sm text-slate-500">
                        Un contact désabonné ne reçoit plus aucun message, ni de l'agent
                        IA ni d'un opérateur.
                    </p>

                    <button
                        v-if="contact.opt_in_status !== 'opted_out'"
                        class="btn-secondary w-full text-sm"
                        @click="confirmingOptOut = true"
                    >
                        Désinscrire ce contact
                    </button>
                    <p v-else class="text-sm text-red-600">
                        Désinscrit le {{ formatDateTime(contact.opt_out_at) }}.
                    </p>
                </section>

                <section class="card">
                    <h2 class="mb-3 text-sm font-semibold">Historique du consentement</h2>
                    <ul class="space-y-2 text-xs">
                        <li v-for="log in consentLogs" :key="log.id" class="flex justify-between gap-3">
                            <span :class="log.action === 'opt_out' ? 'text-red-600' : 'text-emerald-600'">
                                {{ log.action === 'opt_out' ? 'Désinscription' : 'Inscription' }}
                            </span>
                            <span class="text-slate-500">{{ log.source }} · {{ formatDateTime(log.created_at) }}</span>
                        </li>
                    </ul>
                    <!-- Cette table est insert-only : c'est la preuve à produire
                         en cas de réclamation ou de contrôle. -->
                    <p v-if="!consentLogs.length" class="text-xs text-slate-500">
                        Aucun événement enregistré.
                    </p>
                </section>
            </aside>
        </div>

        <Modal :open="confirmingOptOut" title="Désinscrire ce contact ?" @close="confirmingOptOut = false">
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-300">
                Plus aucun message ne pourra lui être envoyé, et sa conversation en
                cours sera fermée. L'opération est tracée. Le contact peut se
                réabonner en répondant <strong>START</strong>.
            </p>
            <div class="flex justify-end gap-3">
                <button class="btn-secondary" @click="confirmingOptOut = false">Annuler</button>
                <button class="btn-primary bg-red-600 hover:bg-red-700" @click="optOut">Désinscrire</button>
            </div>
        </Modal>
    </AppLayout>
</template>
