<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    users: Array,
    roles: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isOwner = computed(() => currentUser.value?.roles?.includes('owner'));

const ROLE_LABELS = {
    owner: 'Propriétaire',
    admin: 'Administrateur',
    agent: 'Opérateur',
    viewer: 'Lecture seule',
};

const ROLE_HINTS = {
    owner: 'Tout, y compris la facturation et la suppression du compte.',
    admin: 'Configuration, agent IA, base de connaissances, utilisateurs.',
    agent: 'Messagerie et prospects. Pas d\'accès à la configuration.',
    viewer: 'Consultation seule.',
};

const STATUS_LABELS = {
    active: 'Actif',
    invited: 'Invitation envoyée',
    disabled: 'Désactivé',
};

const inviting = ref(false);
const editing = ref(null);
const deleting = ref(null);

const inviteForm = useForm({ name: '', email: '', role: 'agent' });
const editForm = useForm({ name: '', role: 'agent', status: 'active' });

// Seul un propriétaire peut créer ou modifier un autre propriétaire.
const assignableRoles = computed(() =>
    isOwner.value ? props.roles : props.roles.filter((role) => role !== 'owner'),
);

const invite = () =>
    inviteForm.post('/settings/users', {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            inviting.value = false;
        },
    });

const openEdit = (user) => {
    editForm.defaults({
        name: user.name,
        role: user.roles[0] ?? 'agent',
        status: user.status,
    });
    editForm.reset();
    editForm.clearErrors();
    editing.value = user;
};

const saveEdit = () =>
    editForm.put(`/settings/users/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });

const remove = () =>
    router.delete(`/settings/users/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });

const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR') : 'Jamais');
</script>

<template>
    <Head title="Utilisateurs" />

    <AppLayout>
        <h1 class="mb-6 text-xl font-semibold">Configuration</h1>
        <SettingsNav />

        <div class="mb-4 flex items-start justify-between gap-4">
            <p class="max-w-2xl text-sm text-slate-500">
                Les membres de votre équipe. Les rôles n'ont d'effet qu'à l'intérieur de
                votre entreprise.
            </p>
            <button class="btn-primary shrink-0" @click="inviting = true">Inviter</button>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Utilisateur</th>
                        <th class="px-5 py-3 font-medium">Rôle</th>
                        <th class="px-5 py-3 font-medium">2FA</th>
                        <th class="px-5 py-3 font-medium">Dernière connexion</th>
                        <th class="px-5 py-3 font-medium">État</th>
                        <th class="px-5 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="user in users" :key="user.id">
                        <td class="px-5 py-3">
                            <p class="font-medium">
                                {{ user.name }}
                                <span v-if="user.id === currentUser?.id" class="ml-1 text-xs text-slate-400">(vous)</span>
                            </p>
                            <p class="text-xs text-slate-500">{{ user.email }}</p>
                        </td>
                        <td class="px-5 py-3">
                            {{ ROLE_LABELS[user.roles[0]] ?? user.roles[0] ?? '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <span v-if="user.two_factor" class="text-emerald-600">✓</span>
                            <!-- Un compte à privilèges sans 2FA est le point de
                                 compromission le plus direct d'un tenant. -->
                            <span
                                v-else-if="['owner', 'admin'].includes(user.roles[0])"
                                class="text-xs text-amber-600"
                                title="Obligatoire pour ce rôle"
                            >
                                à activer
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ formatDateTime(user.last_login_at) }}</td>
                        <td class="px-5 py-3">
                            <StatusBadge :status="user.status" :label="STATUS_LABELS[user.status]" />
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button class="text-xs text-slate-500 hover:underline" @click="openEdit(user)">
                                Modifier
                            </button>
                            <button
                                v-if="user.id !== currentUser?.id"
                                class="ml-3 text-xs text-red-600 hover:underline"
                                @click="deleting = user"
                            >
                                Retirer
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Invitation -->
        <Modal :open="inviting" title="Inviter un utilisateur" @close="inviting = false">
            <form class="space-y-4" @submit.prevent="invite">
                <div>
                    <label class="label" for="invite_name">Nom</label>
                    <input id="invite_name" v-model="inviteForm.name" type="text" class="input" required maxlength="120" />
                    <p v-if="inviteForm.errors.name" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.name }}</p>
                </div>

                <div>
                    <label class="label" for="invite_email">E-mail</label>
                    <input id="invite_email" v-model="inviteForm.email" type="email" class="input" required />
                    <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.email }}</p>
                </div>

                <div>
                    <label class="label" for="invite_role">Rôle</label>
                    <select id="invite_role" v-model="inviteForm.role" class="input">
                        <option v-for="role in assignableRoles" :key="role" :value="role">
                            {{ ROLE_LABELS[role] }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">{{ ROLE_HINTS[inviteForm.role] }}</p>
                </div>

                <!-- Aucun mot de passe n'est transmis : l'utilisateur passe par
                     la réinitialisation, ce qui évite de faire circuler un secret. -->
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    L'utilisateur définira lui-même son mot de passe via un lien de
                    réinitialisation. Aucun mot de passe n'est envoyé par e-mail.
                </p>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary" @click="inviting = false">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="inviteForm.processing">Inviter</button>
                </div>
            </form>
        </Modal>

        <!-- Modification -->
        <Modal :open="!!editing" title="Modifier l'utilisateur" @close="editing = null">
            <form class="space-y-4" @submit.prevent="saveEdit">
                <div>
                    <label class="label" for="edit_name">Nom</label>
                    <input id="edit_name" v-model="editForm.name" type="text" class="input" required maxlength="120" />
                </div>

                <div>
                    <label class="label" for="edit_role">Rôle</label>
                    <select
                        id="edit_role"
                        v-model="editForm.role"
                        class="input"
                        :disabled="editing?.id === currentUser?.id"
                    >
                        <option v-for="role in assignableRoles" :key="role" :value="role">
                            {{ ROLE_LABELS[role] }}
                        </option>
                    </select>
                    <p v-if="editing?.id === currentUser?.id" class="mt-1 text-xs text-slate-500">
                        Vous ne pouvez pas modifier votre propre rôle.
                    </p>
                    <p v-else class="mt-1 text-xs text-slate-500">{{ ROLE_HINTS[editForm.role] }}</p>
                    <p v-if="editForm.errors.role" class="mt-1 text-sm text-red-600">{{ editForm.errors.role }}</p>
                </div>

                <div>
                    <label class="label" for="edit_status">État</label>
                    <select id="edit_status" v-model="editForm.status" class="input">
                        <option value="active">Actif</option>
                        <option value="disabled">Désactivé</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="btn-secondary" @click="editing = null">Annuler</button>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Enregistrer</button>
                </div>
            </form>
        </Modal>

        <Modal :open="!!deleting" title="Retirer cet utilisateur ?" @close="deleting = null">
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-300">
                {{ deleting?.name }} perdra l'accès immédiatement. Ses messages et notes
                restent visibles dans l'historique des conversations.
            </p>
            <div class="flex justify-end gap-3">
                <button class="btn-secondary" @click="deleting = null">Annuler</button>
                <button class="btn-primary bg-red-600 hover:bg-red-700" @click="remove">Retirer</button>
            </div>
        </Modal>
    </AppLayout>
</template>
