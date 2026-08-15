<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    conversations: Object,
    filters: Object,
    counts: Object,
    conversation: Object, // nullable
    messages: Array,
    notes: Array,
    lead: Object, // nullable
    operators: Array,
    agents: Array,
    windowOpen: Boolean,
    windowExpires: String,
    templates: Array,
});

const page = usePage();
const thread = ref(null);
const live = ref([]);
/*
 * Volet de détails : ouvert d'office sur grand écran, fermé sur mobile.
 *
 * Il y recouvre le fil au lieu de se poser à côté — 320 px de plus ne tiennent
 * pas sur un téléphone. L'ouvrir par défaut masquerait donc la conversation
 * qu'on vient tout juste d'ouvrir.
 */
const showSidebar = ref(typeof window === 'undefined' || window.innerWidth >= 1024);

/*
| Actualisation de la liste
|------------------------------------------------------------------------------
| Trois mecanismes complementaires, tous silencieux : aucun ne doit faire
| clignoter la barre de progression, sauter le defilement, ni interrompre une
| saisie en cours.
|
|   1. une horloge reactive, pour que « il y a 2 min » vieillisse tout seul ;
|   2. les evenements Reverb, regroupes pour ne pas declencher une requete par
|      message entrant sur une boite chargee ;
|   3. un rafraichissement periodique de securite, seul filet si le socket
|      tombe — ce qui arrive a chaque redemarrage du serveur.
*/
const now = ref(Date.now());

const CLOCK_TICK_MS = 30_000;   // granularite affichee : la minute
const POLL_MS = 60_000;         // filet de securite, pas le canal principal
const COALESCE_MS = 800;        // regroupe les rafales d'evenements

let clockTimer = null;
let pollTimer = null;
let coalesceTimer = null;
let refreshing = false;

/** Recharge la seule liste, sans rien perturber a l'ecran. */
const refreshList = () => {
    // Onglet en arriere-plan : inutile de consommer, on rattrapera au retour.
    if (refreshing || document.hidden) return;

    refreshing = true;

    router.reload({
        // `messages` est volontairement absent : recharger le fil ouvert le
        // ferait sauter au bas de la conversation pendant la lecture.
        only: ['conversations', 'counts'],
        showProgress: false,
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            refreshing = false;
            now.value = Date.now();
        },
    });
};

/** Regroupe les rafales : dix messages d'affilee ne font qu'un rechargement. */
const scheduleRefresh = () => {
    if (coalesceTimer) return;

    coalesceTimer = setTimeout(() => {
        coalesceTimer = null;
        refreshList();
    }, COALESCE_MS);
};

let socketEverConnected = false;

/** Ne resynchronise que sur une RE-connexion, jamais sur la premiere. */
const onSocketConnected = () => {
    if (!socketEverConnected) {
        socketEverConnected = true;

        return;
    }

    refreshList();
};

const onVisibilityChange = () => {
    if (document.hidden) return;

    // Retour sur l'onglet : c'est la que l'obsolescence se voit le plus.
    now.value = Date.now();
    refreshList();
};

const form = useForm({
    body: '',
    type: 'text',
    template_id: '',
    template_parameters: [],
});

const noteForm = useForm({
    body: '',
});

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    mine: Boolean(props.filters.mine),
    awaiting: Boolean(props.filters.awaiting),
});

// Templates support
const selectedTemplate = ref(null);
const templateParams = ref([]);

const selectTemplate = (template) => {
    selectedTemplate.value = template;
    form.template_id = template.id;
    form.type = 'template';

    const bodyComponent = template.components?.find(c => c.type === 'BODY');
    const text = bodyComponent?.text || '';
    form.body = text;

    // Match place holders like {{1}}, {{2}} to find the number of parameters needed
    const matches = text.match(/\{\{\s*(\d+)\s*\}\}/g) || [];
    let count = 0;
    if (matches.length > 0) {
        const nums = matches.map(m => parseInt(m.replace(/[\{\}]/g, ''), 10));
        count = Math.max(...nums);
    }
    templateParams.value = Array.from({ length: count }, () => '');
};

const cancelTemplate = () => {
    selectedTemplate.value = null;
    templateParams.value = [];
    form.template_id = '';
    form.type = 'text';
    form.body = '';
};

const templatePreview = computed(() => {
    if (!selectedTemplate.value) return '';
    const bodyComponent = selectedTemplate.value.components?.find(c => c.type === 'BODY');
    let text = bodyComponent?.text || '';
    templateParams.value.forEach((val, index) => {
        const regex = new RegExp(`\\{\\{\\s*${index + 1}\\s*\\}\\}`, 'g');
        text = text.replace(regex, val || `[Variable ${index + 1}]`);
    });
    return text;
});

// Scroll utility
const scrollToBottom = () =>
    nextTick(() => {
        if (thread.value) thread.value.scrollTop = thread.value.scrollHeight;
        hasNewBelow.value = false;
    });

/** L'operateur est-il au bas du fil, ou en train de lire plus haut ? */
const isNearBottom = () => {
    const el = thread.value;
    if (!el) return true;

    // Marge de tolerance : on considere « au bas » a une centaine de pixels
    // pres, sinon le moindre pixel de defilement couperait le suivi.
    return el.scrollHeight - el.scrollTop - el.clientHeight < 120;
};

/** Des messages sont arrives pendant la lecture, plus bas dans le fil. */
const hasNewBelow = ref(false);

const onThreadScroll = () => {
    if (isNearBottom()) hasNewBelow.value = false;
};

/*
| Synchronisation du fil ouvert
|------------------------------------------------------------------------------
| Ce `watch` reinitialisait `live` et sautait au bas a CHAQUE changement de
| reference du tableau, sans regarder son contenu. Il suffisait donc qu'Inertia
| renvoie un nouveau tableau — ce qu'aucune garantie publique n'interdit, la
| preservation des references egales etant derriere un drapeau `future`
| desactive — pour arracher l'operateur a la ligne qu'il lisait.
|
| Trois regles desormais : on ne touche a rien si le contenu est identique, on
| ne suit le bas que si l'operateur s'y trouve, et on ne perd jamais un message
| arrive par WebSocket que le serveur n'a pas encore renvoye.
*/
let lastConversationId = null;

watch(() => props.messages, (newMessages) => {
    const incoming = newMessages ?? [];
    const conversationId = props.conversation?.id ?? null;
    const switched = conversationId !== lastConversationId;

    lastConversationId = conversationId;

    // Changement de conversation : on repart du bas, c'est ce qu'on attend.
    if (switched) {
        live.value = [...incoming];
        scrollToBottom();

        return;
    }

    const sameThread = incoming.length === live.value.length
        && incoming.every((message, index) => message.id === live.value[index]?.id);

    if (sameThread) return;

    const known = new Set(incoming.map((message) => message.id));
    const pending = live.value.filter((message) => ! known.has(message.id));
    const stick = isNearBottom();

    live.value = [...incoming, ...pending];

    if (stick) {
        scrollToBottom();
    } else {
        hasNewBelow.value = true;
    }
}, { immediate: true });

// Listen for global and active conversation Echo channels
let globalChannel = null;
let activeChannel = null;

const subscribeToActiveConversation = () => {
    if (activeChannel && window.Echo) {
        window.Echo.leave(activeChannel);
        activeChannel = null;
    }

    const tenantId = page.props.tenant?.id;
    if (!tenantId || !props.conversation?.id || !window.Echo) return;

    activeChannel = `tenant.${tenantId}.conversation.${props.conversation.id}`;

    window.Echo.private(activeChannel)
        .listen('.message.created', (event) => {
            if (live.value.some((m) => m.id === event.id)) return;

            // On ne suit le bas du fil QUE si l'operateur s'y trouve deja.
            // Sinon, un message entrant l'arrachait a la ligne qu'il etait en
            // train de lire — le defaut le plus agacant d'une boite active.
            const stick = isNearBottom();

            live.value.push(event);

            if (stick) {
                scrollToBottom();
            } else {
                hasNewBelow.value = true;
            }
        })
        .listen('.message.status', (event) => {
            const message = live.value.find((m) => m.id === event.id);
            if (message) {
                message.status = event.status;
                message.error = event.error;
            }
        });
};

// --- Notifications Bureau HTML5 ---------------------------------------------
const notificationState = ref('default');

const checkNotificationPermission = () => {
    if (!('Notification' in window)) {
        notificationState.value = 'unsupported';
        return;
    }
    notificationState.value = Notification.permission;
};

const toggleNotifications = () => {
    if (!('Notification' in window)) return;
    
    if (Notification.permission === 'default') {
        Notification.requestPermission().then((permission) => {
            notificationState.value = permission;
        });
    } else if (Notification.permission === 'denied') {
        alert("Les notifications ont été bloquées dans votre navigateur. Veuillez les réactiver dans les paramètres de votre navigateur pour ce site.");
    } else {
        alert("Les notifications de bureau sont déjà activées !");
    }
};

const showNotification = (event) => {
    if (event.direction !== 'in') return;
    if (Notification.permission !== 'granted') return;

    // Ne pas notifier si l'utilisateur regarde déjà cette conversation
    if (document.visibilityState === 'visible' && props.conversation?.id === event.conversation_id) {
        return;
    }

    const conv = props.conversations?.data?.find(c => c.id === event.conversation_id);
    const senderName = conv 
        ? (conv.contact?.name || conv.contact?.profile_name || '+' + conv.contact?.wa_id) 
        : 'Nouveau contact';

    const title = `Message de ${senderName}`;
    const bodyText = event.type === 'text' 
        ? (event.body?.length > 80 ? event.body.substring(0, 80) + '...' : event.body)
        : `[Média/Fichier : ${event.type}]`;

    const options = {
        body: bodyText,
        icon: '/pwa-icon-192.png',
        tag: event.conversation_id
    };

    try {
        const notification = new Notification(title, options);
        notification.onclick = () => {
            window.focus();
            selectConversation(event.conversation_id);
        };
    } catch (e) {
        console.error('Failed to display browser notification:', e);
    }
};

onMounted(() => {
    scrollToBottom();
    checkNotificationPermission();

    // 1. L'horloge : aucun reseau, elle ne fait que vieillir l'affichage.
    clockTimer = setInterval(() => (now.value = Date.now()), CLOCK_TICK_MS);

    // 3. Le filet de securite, indispensable : sans lui, une coupure du
    //    WebSocket fige la liste sans que l'operateur en sache rien.
    pollTimer = setInterval(refreshList, POLL_MS);

    document.addEventListener('visibilitychange', onVisibilityChange);

    const tenantId = page.props.tenant?.id;
    if (!tenantId || !window.Echo) return;

    // 2. Les evenements, regroupes.
    globalChannel = window.Echo.private(`tenant.${tenantId}.conversations`)
        .listen('.message.created', (event) => {
            showNotification(event);
            scheduleRefresh();
        })
        .listen('.conversation.updated', scheduleRefresh);

    // Une RE-connexion signifie que des evenements ont ete manques pendant la
    // coupure : on resynchronise sans attendre le prochain tour de scrutation.
    // La premiere connexion est ignoree — la page vient d'etre servie avec des
    // donnees fraiches, une requete de plus n'apporterait rien.
    //
    // L'etat est releve AVANT l'ecoute : Echo se connecte au chargement de
    // l'application, souvent avant le montage de cette page. Sans ce releve,
    // l'evenement initial nous echappait, et c'est la premiere vraie
    // reconnexion qui aurait ete prise pour elle — donc ignoree.
    const connection = window.Echo.connector?.pusher?.connection;

    socketEverConnected = connection?.state === 'connected';
    connection?.bind('connected', onSocketConnected);

    subscribeToActiveConversation();
});

watch(() => props.conversation?.id, () => {
    subscribeToActiveConversation();
});

onUnmounted(() => {
    clearInterval(clockTimer);
    clearInterval(pollTimer);
    clearTimeout(coalesceTimer);

    document.removeEventListener('visibilitychange', onVisibilityChange);

    const tenantId = page.props.tenant?.id;
    if (tenantId && window.Echo) {
        window.Echo.connector?.pusher?.connection?.unbind('connected', onSocketConnected);
        if (globalChannel) window.Echo.leave(`tenant.${tenantId}.conversations`);
        if (activeChannel) window.Echo.leave(activeChannel);
    }
});

// Filters application
const applyFilters = () => {
    const url = props.conversation ? `/conversations/${props.conversation.id}` : '/conversations';
    router.get(url, filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Select a conversation from list
const selectConversation = (id) => {
    router.visit(`/conversations/${id}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['conversation', 'messages', 'notes', 'lead', 'operators', 'agents', 'windowOpen', 'windowExpires', 'templates'],
    });
};

/*
 * Retour à la liste — mobile uniquement.
 *
 * Sous 1024 px, les trois volets ne tiennent pas côte à côte : l'écran affiche
 * soit la liste, soit le fil. C'est le va-et-vient maître/détail habituel d'une
 * messagerie sur téléphone, et il lui faut une sortie explicite, sinon on ne
 * peut plus changer de conversation sans le bouton « précédent » du navigateur.
 */
const backToList = () => {
    router.visit('/conversations', { preserveState: true, preserveScroll: true });
};

// Toggle AI mode
const toggleAi = () => {
    const action = props.conversation.ai_enabled ? 'handover' : 'resume-ai';
    router.post(`/conversations/${props.conversation.id}/${action}`, {}, { 
        preserveScroll: true,
        preserveState: true,
    });
};

// Assign conversation to operator
const assign = (event) => {
    router.post(
        `/conversations/${props.conversation.id}/assign`,
        { user_id: event.target.value || null },
        { 
            preserveScroll: true,
            preserveState: true,
        },
    );
};

// Assign conversation to AI Agent
const changeAgent = (event) => {
    router.post(
        `/conversations/${props.conversation.id}/assign-agent`,
        { agent_id: event.target.value || null },
        { 
            preserveScroll: true,
            preserveState: true,
        },
    );
};

// Change conversation status
const changeStatus = (event) => {
    router.patch(
        `/conversations/${props.conversation.id}`,
        { status: event.target.value },
        { 
            preserveScroll: true,
            preserveState: true,
        },
    );
};

// Add internal note
const addNote = () => {
    if (!noteForm.body.trim()) return;
    noteForm.post(`/conversations/${props.conversation.id}/notes`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => noteForm.reset('body'),
    });
};

// Send message
const send = () => {
    if (form.type === 'text' && !form.body.trim()) return;

    if (form.type === 'template') {
        form.template_parameters = templateParams.value;
        form.body = templatePreview.value;
    }

    form.post(`/conversations/${props.conversation.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body', 'template_id', 'template_parameters');
            selectedTemplate.value = null;
            templateParams.value = [];
            form.type = 'text';
            router.reload({ only: ['messages', 'conversation'] });
        },
    });
};

// Draft validation and sending
const editingDraftId = ref(null);
const editDraftBody = ref('');

const sendDraftMessage = (msg) => {
    router.post(`/conversations/${props.conversation.id}/messages/${msg.id}/send-draft`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['messages', 'conversation'] });
        }
    });
};

const startEditDraft = (msg) => {
    editingDraftId.value = msg.id;
    editDraftBody.value = msg.body;
};

const cancelEditDraft = () => {
    editingDraftId.value = null;
    editDraftBody.value = '';
};

const saveAndSendDraft = (msg) => {
    router.post(`/conversations/${props.conversation.id}/messages/${msg.id}/send-draft`, {
        body: editDraftBody.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editingDraftId.value = null;
            editDraftBody.value = '';
            router.reload({ only: ['messages', 'conversation'] });
        }
    });
};

// Bubble colors and alignments
const alignment = (message) => (message.direction === 'in' ? 'justify-start' : 'justify-end');

const bubble = (message) => {
    if (message.status === 'draft') {
        return 'bg-amber-50/60 text-slate-800 dark:bg-amber-950/20 dark:text-amber-100 border-2 border-dashed border-amber-300 dark:border-amber-700 rounded-tr-none';
    }
    if (message.direction === 'in') {
        return 'bg-white text-slate-800 dark:bg-slate-800 dark:text-slate-100 rounded-tl-none border-l-2 border-slate-300 dark:border-slate-600';
    }
    if (message.sender_type === 'ai') {
        return 'bg-teal-50 text-slate-800 dark:bg-teal-950/40 dark:text-teal-100 rounded-tr-none border-r-2 border-teal-400 dark:border-teal-500';
    }
    return 'bg-[#d9fdd3] text-slate-800 dark:bg-[#005c4b] dark:text-slate-100 rounded-tr-none border-r-2 border-[#58b368] dark:border-[#00a884]';
};

// `now` est une horloge REACTIVE. Lire Date.now() directement figeait les
// horodatages : Vue n'a aucune raison de re-rendre quand le temps passe, et
// « il y a 2 min » restait affiché indéfiniment sur une boîte laissée ouverte.
const relative = (iso) => {
    if (!iso) return '';
    const minutes = Math.round((now.value - new Date(iso)) / 60000);
    if (minutes < 1) return "à l'instant";
    if (minutes < 60) return `il y a ${minutes} min`;
    if (minutes < 1440) return `il y a ${Math.floor(minutes / 60)} h`;
    return new Date(iso).toLocaleDateString('fr-FR');
};

const toggleSidebar = () => {
    showSidebar.value = !showSidebar.value;
};

// -- Messages vocaux ----------------------------------------------------------
// Un message est vocal des lors qu'il porte un audio : le type suffit, mais on
// verifie aussi la presence du fichier pour ne pas afficher un lecteur vide.
const isVoice = (msg) => msg.type === 'audio' && !!msg.media?.storage_path;

const voiceDuration = (msg) => {
    const s = msg.media?.duration_seconds;
    if (!s) return null;
    return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(Math.round(s % 60)).padStart(2, '0')}`;
};

// La transcription est repliee par defaut : elle encombrerait le fil.
const openTranscripts = ref(new Set());

const toggleTranscript = (id) => {
    const next = new Set(openTranscripts.value);
    next.has(id) ? next.delete(id) : next.add(id);
    openTranscripts.value = next;
};
</script>

<template>
    <Head title="WhatsApp" />

    <AppLayout>
        <PageHeader
            title="Conversations"
            description="La boîte de réception commune à WhatsApp, au widget web et à l'e-mail."
            icon="chat"
            tone="brand"
        />

        <!--
          `dvh` et non `vh` sur mobile : sur iOS et Android, `100vh` compte la
          hauteur de l'écran barre d'adresse rétractée. Le compositeur de
          message tombait donc sous le bord visible tant que la barre était
          déployée — c'est-à-dire à l'ouverture de la page.
        -->
        <div class="card-flush relative flex h-[calc(100dvh-13rem)] min-h-[440px] lg:h-[calc(100vh-14rem)] lg:min-h-[550px]">

            <!-- 1. VOLET GAUCHE : LISTE DES DISCUSSIONS
                 Sous 1024 px, elle cède la place au fil dès qu'une
                 conversation est ouverte. -->
            <div
                class="h-full w-full shrink-0 flex-col border-r border-slate-200/70 bg-slate-50/60 lg:flex lg:w-80 dark:border-slate-800 dark:bg-slate-900/50"
                :class="conversation ? 'hidden' : 'flex'"
            >

                <!-- Outils de Recherche et Filtres -->
                <div class="space-y-3 border-b border-slate-200/70 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="relative">
                        <Icon name="search" size="sm" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="filters.q"
                            type="search"
                            placeholder="Rechercher un contact…"
                            class="input pl-9"
                            @input="applyFilters"
                        />
                    </div>

                    <select v-model="filters.status" class="input" @change="applyFilters">
                        <option value="">Tous les statuts</option>
                        <option value="open">Ouvertes</option>
                        <option value="pending">En attente</option>
                        <option value="closed">Fermées</option>
                    </select>

                    <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 select-none dark:text-slate-400">
                            <input
                                v-model="filters.mine"
                                type="checkbox"
                                @change="applyFilters"
                                class="rounded border-slate-300 dark:border-slate-700"
                            />
                            Les miennes
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 select-none dark:text-slate-400">
                            <input
                                v-model="filters.awaiting"
                                type="checkbox"
                                @change="applyFilters"
                                class="rounded border-slate-300 dark:border-slate-700"
                            />
                            Humain requis
                        </label>
                    </div>

                    <!-- Notification bureau inline toggle -->
                    <div class="flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800/60">
                        <span class="eyebrow">Notifications bureau</span>
                        <button
                            @click="toggleNotifications"
                            type="button"
                            class="flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold transition"
                            :class="notificationState === 'granted'
                                ? 'border-emerald-200/60 bg-emerald-50 text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : notificationState === 'denied'
                                    ? 'border-rose-200/60 bg-rose-50 text-rose-800 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-300'
                                    : 'border-brand-200/60 bg-brand-50 text-brand-700 dark:border-slate-700 dark:bg-slate-800 dark:text-brand-100'"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="notificationState === 'granted' ? 'bg-emerald-500' : notificationState === 'denied' ? 'bg-rose-500' : 'bg-brand-500'" />
                            {{ notificationState === 'granted' ? 'Actives' : notificationState === 'denied' ? 'Bloquées' : 'Activer' }}
                        </button>
                    </div>
                </div>

                <!-- Liste dynamique -->
                <div class="flex-1 overflow-y-auto">
                    <!--
                      L'état sélectionné se marque par un liseré posé en absolu,
                      et non par une `border-l-4` : celle-ci décalait le contenu
                      de quatre pixels, si bien que toute la liste tressautait au
                      changement de conversation.
                    -->
                    <div
                        v-for="conv in conversations.data"
                        :key="conv.id"
                        @click="selectConversation(conv.id)"
                        class="relative flex cursor-pointer items-start gap-3 border-b border-slate-100 px-4 py-3.5 transition dark:border-slate-800/40"
                        :class="conversation?.id === conv.id
                            ? 'bg-brand-50/70 dark:bg-slate-800/50'
                            : 'hover:bg-slate-100/70 dark:hover:bg-slate-800/25'"
                    >
                        <span
                            v-if="conversation?.id === conv.id"
                            class="absolute inset-y-0 left-0 w-1 bg-brand-600"
                        />

                        <!-- Avatar avec initiale -->
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ (conv.contact?.name || conv.contact?.profile_name || 'C')[0].toUpperCase() }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                                    {{ conv.contact?.name || conv.contact?.profile_name || '+' + conv.contact?.wa_id }}
                                </p>
                                <span class="shrink-0 text-[11px] whitespace-nowrap text-slate-400 tabular-nums">
                                    {{ relative(conv.last_message_at) }}
                                </span>
                            </div>

                            <!-- Badges de statut et attribution -->
                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <span
                                        v-if="conv.handover_at"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950/80 dark:text-amber-300"
                                    >
                                        humain requis
                                    </span>
                                    <span
                                        v-else-if="conv.ai_enabled"
                                        class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300"
                                    >
                                        IA active
                                    </span>
                                    <span v-if="conv.assigned_user" class="truncate text-[11px] text-slate-400 dark:text-slate-500">
                                        · {{ conv.assigned_user.name }}
                                    </span>
                                </div>

                                <!-- Badge de messages non lus -->
                                <span
                                    v-if="conv.unread_count"
                                    class="min-w-5 shrink-0 rounded-full bg-brand-600 px-1.5 py-0.5 text-center text-[11px] font-semibold text-white tabular-nums"
                                >
                                    {{ conv.unread_count }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <p v-if="!conversations.data.length" class="px-4 py-12 text-center text-sm text-slate-400">
                        Aucune discussion ne correspond à ces filtres.
                    </p>
                </div>
            </div>
            
            <!-- 2. VOLET CENTRAL : FIL DE DISCUSSION / BIENVENUE -->
            <div
                class="relative h-full w-full flex-1 flex-col bg-[#f0f2f5] lg:flex dark:bg-slate-950"
                :class="conversation ? 'flex' : 'hidden'"
            >
                
                <!-- A. SI CONVERSATION ACTIVE -->
                <template v-if="conversation">
                  
                    <!-- En-tête active -->
                    <div class="z-10 flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-200/70 bg-white px-4 py-3 dark:border-slate-800/80 dark:bg-slate-900">
                        <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                            <!-- Retour à la liste : seul chemin de sortie quand
                                 le fil occupe tout l'écran. -->
                            <button
                                class="btn-ghost -ml-2 px-2 lg:hidden"
                                aria-label="Retour à la liste des conversations"
                                @click="backToList"
                            >
                                <Icon name="chevronLeft" size="sm" />
                            </button>

                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                {{ (conversation.contact?.name || conversation.contact?.profile_name || 'C')[0].toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <h2 class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                                    {{ conversation.contact?.name || conversation.contact?.profile_name || '+' + conversation.contact?.wa_id }}
                                </h2>
                                <p class="truncate font-mono text-xs text-slate-500 dark:text-slate-400">
                                    +{{ conversation.contact?.wa_id }}
                                </p>
                            </div>
                        </div>

                        <!--
                          Sur mobile ces cinq contrôles passeraient sur trois
                          lignes et mangeraient le fil. Ils tiennent sur une
                          seule ligne qui défile latéralement, et retrouvent
                          leur disposition normale à partir de 1024 px.
                        -->
                        <div class="-mx-1 flex w-full items-center gap-2 overflow-x-auto px-1 pb-1 lg:mx-0 lg:w-auto lg:flex-wrap lg:overflow-visible lg:px-0 lg:pb-0">
                            <!-- Sélecteur de statut -->
                            <select
                                class="input w-auto cursor-pointer py-1.5 text-xs"
                                :value="conversation.status"
                                @change="changeStatus"
                                aria-label="Statut de la conversation"
                            >
                                <option value="open">Ouverte</option>
                                <option value="pending">En attente</option>
                                <option value="closed">Fermée</option>
                            </select>

                            <!-- Attribué à -->
                            <select
                                class="input w-auto cursor-pointer py-1.5 text-xs"
                                :value="conversation.assigned_user_id ?? ''"
                                @change="assign"
                                aria-label="Attribuer à un opérateur"
                            >
                                <option value="">Non attribuée</option>
                                <option v-for="op in operators" :key="op.id" :value="op.id">{{ op.name }}</option>
                            </select>

                            <!-- Agent IA -->
                            <select
                                class="input w-auto cursor-pointer py-1.5 text-xs"
                                :value="conversation.agent_id ?? ''"
                                @change="changeAgent"
                                aria-label="Choisir l'agent IA"
                            >
                                <option value="">Agent par défaut</option>
                                <option v-for="ag in agents" :key="ag.id" :value="ag.id">{{ ag.name }}</option>
                            </select>

                            <!-- Prise de main / Restauration IA.
                                 Garde sa couleur : ce n'est pas une action de
                                 formulaire mais un basculement d'état, et
                                 l'opérateur doit voir qui répond sans lire. -->
                            <button
                                class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs font-semibold whitespace-nowrap transition"
                                :class="conversation.ai_enabled
                                  ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-950/20 dark:text-amber-300'
                                  : 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-300'"
                                @click="toggleAi"
                            >
                                {{ conversation.ai_enabled ? 'Prendre la main' : "Réactiver l'IA" }}
                            </button>

                            <!-- Toggle Sidebar -->
                            <button
                                @click="toggleSidebar"
                                class="btn-ghost px-2"
                                title="Détails du contact"
                                aria-label="Détails du contact"
                            >
                                <Icon name="info" size="sm" />
                            </button>
                        </div>
                    </div>
                    
                    <!-- Fil de discussion -->
                    <div
                        ref="thread"
                        class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#efeae2] dark:bg-slate-950 relative"
                        @scroll.passive="onThreadScroll"
                    >
                        <!-- Signale ce qui est arrivé pendant la lecture, plutôt
                             que d'arracher l'opérateur au bas du fil. -->
                        <button
                            v-if="hasNewBelow"
                            type="button"
                            class="sticky bottom-2 left-1/2 z-10 -translate-x-1/2 rounded-full bg-brand-600 px-3.5 py-1.5 text-[11px] font-semibold text-white shadow-lg transition hover:bg-brand-700 cursor-pointer"
                            @click="scrollToBottom"
                        >
                            Nouveaux messages ↓
                        </button>

                        <div 
                            v-for="msg in live" 
                            :key="msg.id" 
                            class="flex" 
                            :class="msg.sender_type === 'system' ? 'justify-center my-2' : alignment(msg)"
                        >
                            <!-- Message Système -->
                            <div 
                                v-if="msg.sender_type === 'system'"
                                class="bg-white/90 dark:bg-slate-900/90 text-slate-500 dark:text-slate-400 text-[10px] px-3 py-1 rounded-md shadow-xs border border-slate-100 dark:border-slate-800/80 italic text-center max-w-[80%]"
                            >
                                {{ msg.body }}
                            </div>
                            
                            <!-- Message Normal -->
                            <div 
                                v-else
                                class="max-w-[70%] rounded-xl px-3 py-2 text-xs shadow-xs relative group"
                                :class="bubble(msg)"
                            >
                                <!-- Message vocal : on écoute d'abord, on lit
                                     ensuite. La transcription reste accessible
                                     — c'est elle qui permet de comprendre un
                                     échange sans écouter chaque fichier. -->
                                <div v-if="isVoice(msg)" class="space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-semibold opacity-80">🎙 Message vocal</span>
                                        <span v-if="voiceDuration(msg)" class="text-[10px] opacity-60">{{ voiceDuration(msg) }}</span>
                                    </div>

                                    <audio
                                        :src="`/messages/${msg.id}/audio`"
                                        controls
                                        preload="none"
                                        class="h-8 w-full max-w-[240px]"
                                    />

                                    <button
                                        v-if="msg.body"
                                        type="button"
                                        class="block text-[10px] underline opacity-70 hover:opacity-100 cursor-pointer"
                                        @click="toggleTranscript(msg.id)"
                                    >
                                        {{ openTranscripts.has(msg.id) ? 'Masquer la transcription' : 'Voir la transcription' }}
                                    </button>

                                    <p v-if="openTranscripts.has(msg.id)" class="whitespace-pre-wrap leading-relaxed opacity-90">
                                        {{ msg.body }}
                                    </p>
                                </div>

                                <p v-else-if="editingDraftId !== msg.id" class="whitespace-pre-wrap leading-relaxed">{{ msg.body }}</p>

                                <!-- Éditeur de brouillon -->
                                <div v-if="msg.status === 'draft' && editingDraftId === msg.id" class="w-full space-y-2 mt-1">
                                    <textarea 
                                        v-model="editDraftBody" 
                                        rows="4" 
                                        class="w-full text-xs p-2 border rounded bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500" 
                                    />
                                    <div class="flex gap-2 justify-end">
                                        <button 
                                            type="button"
                                            @click="cancelEditDraft"
                                            class="px-2 py-1 text-[10px] font-semibold text-slate-500 hover:underline cursor-pointer"
                                        >
                                            Annuler
                                        </button>
                                        <button 
                                            type="button"
                                            @click="saveAndSendDraft(msg)"
                                            class="px-2.5 py-1 text-[10px] font-bold bg-amber-500 hover:bg-amber-600 text-white rounded transition cursor-pointer"
                                        >
                                            Enregistrer & Envoyer
                                        </button>
                                    </div>
                                </div>

                                <!-- Actions du brouillon si non en cours d'édition -->
                                <div v-if="msg.status === 'draft' && editingDraftId !== msg.id" class="mt-3 pt-2 border-t border-amber-200/50 dark:border-amber-800/40 flex items-center gap-2">
                                    <button 
                                        type="button"
                                        @click="sendDraftMessage(msg)"
                                        class="px-2 py-1 text-[10px] font-bold bg-amber-500 hover:bg-amber-600 text-white rounded transition cursor-pointer"
                                    >
                                        ✓ Valider & Envoyer
                                    </button>
                                    <button 
                                        type="button"
                                        @click="startEditDraft(msg)"
                                        class="px-2 py-1 text-[10px] font-bold bg-slate-200 hover:bg-slate-300 text-slate-700 rounded dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 transition cursor-pointer"
                                    >
                                        ✏️ Modifier
                                    </button>
                                </div>

                                <div class="mt-1 flex items-center justify-end gap-1 text-[9px] opacity-60">
                                    <span v-if="msg.sender_type === 'ai'" class="font-semibold text-teal-600 dark:text-teal-400">IA ·</span>
                                    <span v-else-if="msg.sender" class="font-semibold">{{ msg.sender.name }} ·</span>
                                    {{ new Date(msg.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                                    
                                    <!-- Coche de lecture -->
                                    <span v-if="msg.direction === 'out'" class="select-none">
                                        <span v-if="msg.status === 'read'" class="text-sky-500 font-bold">✓✓</span>
                                        <span v-else-if="msg.status === 'delivered'" class="text-slate-400 font-bold">✓✓</span>
                                        <span v-else-if="msg.status === 'sent'" class="text-slate-400">✓</span>
                                        <span v-else-if="msg.status === 'queued'">Glissant...</span>
                                        <span v-else-if="msg.status === 'failed'" class="text-red-500" title="Échec de l'envoi">⚠</span>
                                        <span v-else-if="msg.status === 'draft'" class="text-amber-500 font-semibold">Brouillon</span>
                                    </span>
                                </div>
                                <p v-if="msg.error" class="mt-1 text-[9px] text-red-500 font-semibold">{{ msg.error }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Barre de réponse et alertes templates -->
                    <div class="border-t border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900 p-3 shrink-0">
                        
                        <!--
                          Hors fenêtre de 24 h.

                          Ce n'est pas une limite de Nonalix mais une règle Meta :
                          passé 24 h sans message du contact, seul un modèle
                          approuvé peut partir. Le bloc doit donc expliquer la
                          cause ET donner la suite — sans quoi l'opérateur reste
                          devant un champ grisé sans savoir quoi faire.
                        -->
                        <div v-if="!windowOpen" class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
                            <div class="flex items-start gap-2.5">
                                <Icon name="clock" size="sm" class="mt-0.5 shrink-0 text-amber-600 dark:text-amber-400" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-300">
                                        Fenêtre de 24 h fermée
                                    </p>
                                    <p class="mt-1 text-xs leading-relaxed text-amber-800/90 dark:text-amber-300/80">
                                        Meta interdit le message libre tant que le contact n'a pas réécrit.
                                        Seul un modèle approuvé peut lui être envoyé.
                                    </p>
                                </div>
                            </div>

                            <!-- Sélecteur de modèle -->
                            <div class="mt-3" v-if="templates && templates.length">
                                <span class="eyebrow">Modèles approuvés</span>
                                <div class="mt-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                                    <button
                                        v-for="tpl in templates"
                                        :key="tpl.id"
                                        type="button"
                                        class="cursor-pointer rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-left text-xs font-medium transition hover:border-brand-500 hover:bg-brand-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-brand-500 dark:hover:bg-slate-800"
                                        @click="selectTemplate(tpl)"
                                    >
                                        {{ tpl.name }} ({{ tpl.language }})
                                    </button>
                                </div>
                            </div>

                            <!--
                              Aucun modèle : l'ancien message s'arrêtait à ce
                              constat. Il indique désormais où en créer un, parce
                              que c'est la seule action qui débloque la situation.
                            -->
                            <div v-else class="mt-3 rounded-lg border border-amber-200/70 bg-white/70 p-3 dark:border-amber-900/40 dark:bg-slate-900/40">
                                <p class="text-xs font-medium text-slate-700 dark:text-slate-200">
                                    Aucun modèle approuvé n'est encore disponible.
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">
                                    Les modèles se créent et se font approuver dans le WhatsApp Manager de Meta,
                                    puis se récupèrent ici.
                                </p>
                                <Link href="/settings/whatsapp" class="btn-secondary mt-2.5 py-1.5 text-xs">
                                    Synchroniser les modèles
                                </Link>
                            </div>

                            <!-- Édition et preview du template -->
                            <div v-if="selectedTemplate" class="mt-3 p-3 bg-slate-100 dark:bg-slate-800/40 rounded-lg border border-slate-200 dark:border-slate-700">
                                <div class="flex justify-between items-center mb-1.5">
                                    <span class="text-xs font-semibold text-brand-600 dark:text-brand-400">
                                        Modèle : {{ selectedTemplate.name }}
                                    </span>
                                    <button type="button" @click="cancelTemplate" class="text-xs text-red-500 hover:underline cursor-pointer">
                                        Annuler
                                    </button>
                                </div>
                                <div class="text-xs text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 p-2.5 rounded border border-slate-100 dark:border-slate-800 whitespace-pre-wrap mb-2">
                                    {{ templatePreview }}
                                </div>
                                
                                <!-- Paramètres dynamiques -->
                                <div v-if="templateParams.length" class="space-y-2">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Paramètres du modèle :</p>
                                    <div class="grid gap-2 grid-cols-2">
                                        <div v-for="(val, idx) in templateParams" :key="idx">
                                            <input
                                                v-model="templateParams[idx]"
                                                type="text"
                                                :placeholder="`Variable {{${idx + 1}}}`"
                                                class="input py-1 text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Formulaire de saisie -->
                        <form class="flex items-end gap-2" @submit.prevent="send">
                            <textarea
                                v-model="form.body"
                                rows="1"
                                class="input max-h-24 flex-1 resize-none"
                                :disabled="!windowOpen && !selectedTemplate"
                                placeholder="Votre réponse… (Entrée pour envoyer)"
                                @keydown.enter.exact.prevent="send"
                            />
                            <button
                                type="submit"
                                class="btn-primary shrink-0"
                                :disabled="form.processing || (!windowOpen && !selectedTemplate)"
                            >
                                Envoyer
                            </button>
                        </form>
                        <p v-if="form.errors.body" class="error">{{ form.errors.body }}</p>
                    </div>
                </template>

                <!-- B. SI AUCUNE DISCUSSION ACTIVE -->
                <div v-else class="flex flex-1 flex-col items-center justify-center bg-slate-50 p-8 text-center dark:bg-slate-900/10">
                    <span class="tile-brand mb-4 h-16 w-16">
                        <Icon name="chat" size="lg" />
                    </span>
                    <h2 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                        Aucune conversation sélectionnée
                    </h2>
                    <p class="mt-2 max-w-sm text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        Choisissez un contact à gauche pour lire le fil, répondre, ou reprendre la main sur l'agent.
                    </p>
                </div>
            </div>

            <!-- 3. VOLET DROIT : DETAILS PROSPECT & NOTES INTERNES -->
            <aside
                v-if="conversation && showSidebar"
                class="absolute inset-0 z-20 flex h-full w-full shrink-0 flex-col border-l border-slate-200/70 bg-white lg:relative lg:inset-auto lg:z-auto lg:w-80 dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex shrink-0 items-center justify-between border-b border-slate-200/70 px-4 py-3.5 dark:border-slate-800/80">
                    <h3 class="section-title">Détails</h3>
                    <button @click="showSidebar = false" class="btn-ghost px-2" aria-label="Fermer le panneau">
                        <Icon name="close" size="sm" />
                    </button>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 space-y-5">
                    
                    <!-- Informations Prospect -->
                    <div v-if="lead" class="bg-slate-50 dark:bg-slate-800/30 p-3 rounded-lg border border-slate-100 dark:border-slate-800/60">
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                            Qualification Prospect
                        </h4>
                        <div class="space-y-1.5 text-xs">
                            <p class="flex justify-between"><span class="text-slate-400">Statut :</span> <strong class="text-slate-800 dark:text-slate-200">{{ lead.status }}</strong></p>
                            <p class="flex justify-between"><span class="text-slate-400">Score d'intérêt :</span> <strong class="text-slate-800 dark:text-slate-200">{{ lead.score }}/100</strong></p>
                            
                            <div v-if="lead.qualification && Object.keys(lead.qualification).length" class="border-t border-slate-200 dark:border-slate-800/80 pt-2 mt-2">
                                <dl class="space-y-1.5">
                                    <div v-for="(val, key) in lead.qualification" :key="key" class="flex flex-col">
                                        <dt class="text-[9px] font-bold text-slate-400 uppercase">{{ key }}</dt>
                                        <dd class="text-slate-800 dark:text-slate-200 font-semibold">{{ val }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes de suivi interne -->
                    <div class="space-y-3">
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            Notes Internes
                        </h4>
                        <p class="text-[10px] text-slate-400 leading-relaxed">
                            Ces notes de suivi restent privées au sein de l'équipe et ne sont jamais montrées au client ni lues par l'IA.
                        </p>
                        
                        <form class="space-y-2" @submit.prevent="addNote">
                            <textarea 
                                v-model="noteForm.body" 
                                rows="2" 
                                class="input resize-none py-2 px-2.5 text-xs bg-slate-50 focus:bg-white" 
                                placeholder="Ajouter une note de suivi…" 
                            />
                            <button 
                                type="submit" 
                                class="btn-secondary w-full py-1.5 text-xs font-semibold flex items-center justify-center cursor-pointer" 
                                :disabled="noteForm.processing"
                            >
                                Enregistrer
                            </button>
                        </form>
                        
                        <!-- Liste des notes de suivi -->
                        <div class="space-y-2.5 max-h-64 overflow-y-auto pr-1">
                            <div 
                                v-for="note in notes" 
                                :key="note.id" 
                                class="p-2.5 bg-slate-50 dark:bg-slate-800/20 rounded-md border border-slate-100 dark:border-slate-800/60 text-xs"
                            >
                                <p class="whitespace-pre-wrap leading-relaxed text-slate-700 dark:text-slate-300">{{ note.body }}</p>
                                <p class="mt-1 text-[9px] text-slate-400 text-right">
                                    {{ note.author?.name }} · {{ new Date(note.created_at).toLocaleDateString('fr-FR', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                                </p>
                            </div>
                            <p v-if="!notes.length" class="text-center text-[10px] text-slate-400 italic py-4">
                                Aucune note enregistrée.
                            </p>
                        </div>
                    </div>
                </div>
            </aside>
            
        </div>
    </AppLayout>
</template>
