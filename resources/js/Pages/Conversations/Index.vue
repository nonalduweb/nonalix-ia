<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    conversations: Object,
    filters: Object,
    counts: Object,
    conversation: Object, // nullable
    messages: Array,
    notes: Array,
    lead: Object, // nullable
    operators: Array,
    windowOpen: Boolean,
    windowExpires: String,
    templates: Array,
});

const page = usePage();
const thread = ref(null);
const live = ref([]);
const showSidebar = ref(true);

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
    });

// Sync local messages list when props change
watch(() => props.messages, (newMessages) => {
    live.value = [...(newMessages ?? [])];
    scrollToBottom();
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
};

onMounted(() => {
    scrollToBottom();

    const tenantId = page.props.tenant?.id;
    if (!tenantId || !window.Echo) return;

    // Sidebar list updates
    globalChannel = window.Echo.private(`tenant.${tenantId}.conversations`)
        .listen('.message.created', () => {
            router.reload({ only: ['conversations', 'counts'] });
        })
        .listen('.conversation.updated', () => {
            router.reload({ only: ['conversations', 'counts'] });
        });

    subscribeToActiveConversation();
});

watch(() => props.conversation?.id, () => {
    subscribeToActiveConversation();
});

onUnmounted(() => {
    const tenantId = page.props.tenant?.id;
    if (tenantId && window.Echo) {
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
        only: ['conversation', 'messages', 'notes', 'lead', 'operators', 'windowOpen', 'windowExpires', 'templates'],
    });
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

// Bubble colors and alignments
const alignment = (message) => (message.direction === 'in' ? 'justify-start' : 'justify-end');

const bubble = (message) => {
    if (message.direction === 'in') {
        return 'bg-white text-slate-800 dark:bg-slate-800 dark:text-slate-100 rounded-tl-none border-l-2 border-slate-300 dark:border-slate-600';
    }
    if (message.sender_type === 'ai') {
        return 'bg-teal-50 text-slate-800 dark:bg-teal-950/40 dark:text-teal-100 rounded-tr-none border-r-2 border-teal-400 dark:border-teal-500';
    }
    return 'bg-[#d9fdd3] text-slate-800 dark:bg-[#005c4b] dark:text-slate-100 rounded-tr-none border-r-2 border-[#58b368] dark:border-[#00a884]';
};

const relative = (iso) => {
    if (!iso) return '';
    const minutes = Math.round((Date.now() - new Date(iso)) / 60000);
    if (minutes < 1) return "à l'instant";
    if (minutes < 60) return `il y a ${minutes} min`;
    if (minutes < 1440) return `il y a ${Math.floor(minutes / 60)} h`;
    return new Date(iso).toLocaleDateString('fr-FR');
};

const toggleSidebar = () => {
    showSidebar.value = !showSidebar.value;
};
</script>

<template>
    <Head title="WhatsApp" />

    <AppLayout>
        <div class="card p-0 flex overflow-hidden h-[calc(100vh-11rem)] min-h-[550px]">
            
            <!-- 1. VOLET GAUCHE : LISTE DES DISCUSSIONS -->
            <div class="w-80 border-r border-slate-200 dark:border-slate-800 flex flex-col h-full shrink-0 bg-slate-50 dark:bg-slate-900/50">
                
                <!-- Outils de Recherche et Filtres -->
                <div class="p-3 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2">
                    <input
                        v-model="filters.q"
                        type="search"
                        placeholder="Rechercher un contact…"
                        class="input py-1.5 px-3"
                        @input="applyFilters"
                    />
                    
                    <div class="flex gap-2">
                        <select v-model="filters.status" class="input py-1 px-2 text-xs" @change="applyFilters">
                            <option value="">Tous les statuts</option>
                            <option value="open">Ouvertes</option>
                            <option value="pending">En attente</option>
                            <option value="closed">Fermées</option>
                        </select>
                    </div>

                    <div class="flex flex-wrap gap-x-3 gap-y-1 pt-1">
                        <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                            <input 
                                v-model="filters.mine" 
                                type="checkbox" 
                                @change="applyFilters" 
                                class="rounded border-slate-300 dark:border-slate-700" 
                            />
                            Les miennes
                        </label>
                        <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                            <input 
                                v-model="filters.awaiting" 
                                type="checkbox" 
                                @change="applyFilters" 
                                class="rounded border-slate-300 dark:border-slate-700" 
                            />
                            Humain requis
                        </label>
                    </div>
                </div>

                <!-- Liste dynamique -->
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/50">
                    <div
                        v-for="conv in conversations.data"
                        :key="conv.id"
                        @click="selectConversation(conv.id)"
                        class="flex items-start gap-3 px-4 py-3 cursor-pointer transition hover:bg-slate-100 dark:hover:bg-slate-800/40 relative"
                        :class="conversation?.id === conv.id ? 'bg-brand-50/50 dark:bg-slate-800/80 border-l-4 border-brand-500' : 'pl-5'"
                    >
                        <!-- Avatar avec initiale -->
                        <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold text-slate-600 dark:text-slate-300 shrink-0 text-sm">
                            {{ (conv.contact?.name || conv.contact?.profile_name || 'C')[0].toUpperCase() }}
                        </div>
                        
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <p class="truncate font-semibold text-xs text-slate-900 dark:text-slate-100">
                                    {{ conv.contact?.name || conv.contact?.profile_name || '+' + conv.contact?.wa_id }}
                                </p>
                                <span class="text-[9px] text-slate-400 shrink-0 whitespace-nowrap">
                                    {{ relative(conv.last_message_at) }}
                                </span>
                            </div>
                            
                            <!-- Badges de statut et attribution -->
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex gap-1 items-center">
                                    <span
                                        v-if="conv.handover_at"
                                        class="rounded-full bg-amber-100 dark:bg-amber-950/80 px-1.5 py-0.5 text-[9px] text-amber-800 dark:text-amber-300 font-medium"
                                    >
                                        humain requis
                                    </span>
                                    <span
                                        v-else-if="conv.ai_enabled"
                                        class="rounded-full bg-emerald-100 dark:bg-emerald-950/80 px-1.5 py-0.5 text-[9px] text-emerald-800 dark:text-emerald-300 font-medium"
                                    >
                                        IA active
                                    </span>
                                    <span v-if="conv.assigned_user" class="text-[9px] text-slate-400 dark:text-slate-500">
                                        · {{ conv.assigned_user.name }}
                                    </span>
                                </div>
                                
                                <!-- Badge de messages non lus -->
                                <span
                                    v-if="conv.unread_count"
                                    class="rounded-full bg-brand-600 px-1.5 py-0.5 text-[9px] font-bold text-white min-w-4 text-center shrink-0"
                                >
                                    {{ conv.unread_count }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <p v-if="!conversations.data.length" class="px-4 py-12 text-center text-xs text-slate-400 italic">
                        Aucune discussion disponible.
                    </p>
                </div>
            </div>
            
            <!-- 2. VOLET CENTRAL : FIL DE DISCUSSION / BIENVENUE -->
            <div class="flex-1 flex flex-col h-full bg-[#f0f2f5] dark:bg-slate-950 relative">
                
                <!-- A. SI CONVERSATION ACTIVE -->
                <template v-if="conversation">
                  
                    <!-- En-tête active -->
                    <div class="h-16 px-4 border-b border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900 flex items-center justify-between z-10 shrink-0 shadow-sm">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold text-slate-600 dark:text-slate-300 text-sm shrink-0">
                                {{ (conversation.contact?.name || conversation.contact?.profile_name || 'C')[0].toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <h2 class="font-semibold text-sm text-slate-900 dark:text-slate-100 truncate">
                                    {{ conversation.contact?.name || conversation.contact?.profile_name || '+' + conversation.contact?.wa_id }}
                                </h2>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">
                                    +{{ conversation.contact?.wa_id }}
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <!-- Sélecteur de statut -->
                            <select 
                                class="input py-1 px-2 text-xs max-w-28 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 cursor-pointer"
                                :value="conversation.status"
                                @change="changeStatus"
                            >
                                <option value="open">Ouverte</option>
                                <option value="pending">En attente</option>
                                <option value="closed">Fermée</option>
                            </select>

                            <!-- Attribué à -->
                            <select 
                                class="input py-1 px-2 text-xs max-w-32 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 cursor-pointer"
                                :value="conversation.assigned_user_id ?? ''" 
                                @change="assign"
                            >
                                <option value="">Non attribuée</option>
                                <option v-for="op in operators" :key="op.id" :value="op.id">{{ op.name }}</option>
                            </select>

                            <!-- Prise de main / Restauration IA -->
                            <button 
                                class="rounded-lg px-2.5 py-1.5 text-xs font-semibold border transition cursor-pointer"
                                :class="conversation.ai_enabled 
                                  ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-950/20 dark:text-amber-300'
                                  : 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-300'"
                                @click="toggleAi"
                            >
                                {{ conversation.ai_enabled ? "Prendre la main" : "Réactiver l'IA" }}
                            </button>
                            
                            <!-- Toggle Sidebar -->
                            <button 
                                @click="toggleSidebar"
                                class="p-1.5 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition cursor-pointer"
                                title="Détails"
                            >
                                <span class="text-sm">ℹ️</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Fil de discussion -->
                    <div ref="thread" class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#efeae2] dark:bg-slate-950">
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
                                <p class="whitespace-pre-wrap leading-relaxed">{{ msg.body }}</p>
                                <div class="mt-1 flex items-center justify-end gap-1 text-[9px] opacity-60">
                                    <span v-if="msg.sender_type === 'ai'" class="font-semibold text-teal-600 dark:text-teal-400">IA ·</span>
                                    <span v-else-if="msg.sender" class="font-semibold">{{ msg.sender.name }} ·</span>
                                    {{ new Date(msg.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                                    
                                    <!-- Coche de lecture -->
                                    <span v-if="msg.direction === 'out'" class="select-none">
                                        <span v-if="msg.status === 'read'" class="text-sky-500 font-bold">✓✓</span>
                                        <span v-else-if="msg.status === 'delivered'" class="text-slate-400 font-bold">✓✓</span>
                                        <span v-else-if="msg.status === 'sent'" class="text-slate-400">✓</span>
                                        <span v-else-if="msg.status === 'queued'">🕓</span>
                                        <span v-else-if="msg.status === 'failed'" class="text-red-500" title="Échec de l'envoi">⚠</span>
                                    </span>
                                </div>
                                <p v-if="msg.error" class="mt-1 text-[9px] text-red-500 font-semibold">{{ msg.error }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Barre de réponse et alertes templates -->
                    <div class="border-t border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-900 p-3 shrink-0">
                        
                        <!-- Hors fenêtre 24h -->
                        <div v-if="!windowOpen" class="mb-3 rounded-lg bg-amber-50 dark:bg-amber-950/20 p-3 border border-amber-200 dark:border-amber-900/50">
                            <p class="text-xs text-amber-800 dark:text-amber-300 font-semibold">
                                ⚠️ Session fermée (plus de 24h). Sélectionnez un modèle approuvé pour répondre.
                            </p>
                            
                            <!-- Sélecteur de modèle -->
                            <div class="mt-2" v-if="templates && templates.length">
                                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                                    Modèles approuvés :
                                </span>
                                <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto pt-1">
                                    <button
                                        v-for="tpl in templates"
                                        :key="tpl.id"
                                        type="button"
                                        class="px-2.5 py-1 text-xs border border-slate-200 hover:border-brand-500 hover:bg-brand-50 dark:border-slate-800 dark:hover:border-brand-500 dark:hover:bg-slate-800 rounded-md transition text-left font-medium cursor-pointer"
                                        @click="selectTemplate(tpl)"
                                    >
                                        {{ tpl.name }} ({{ tpl.language }})
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-slate-400 mt-1 italic">
                                Aucun modèle approuvé n'est disponible.
                            </p>

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
                        <form class="flex gap-2 items-end" @submit.prevent="send">
                            <textarea
                                v-model="form.body"
                                rows="1"
                                class="input flex-1 resize-none py-2 max-h-24 bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-800"
                                :disabled="!windowOpen && !selectedTemplate"
                                placeholder="Votre réponse… (Entrée pour envoyer)"
                                @keydown.enter.exact.prevent="send"
                            />
                            <button 
                                type="submit" 
                                class="btn-primary py-2 px-4 h-9 flex items-center justify-center text-xs shrink-0 cursor-pointer" 
                                :disabled="form.processing || (!windowOpen && !selectedTemplate)"
                            >
                                Envoyer
                            </button>
                        </form>
                        <p v-if="form.errors.body" class="mt-1 text-xs text-red-500 font-semibold">{{ form.errors.body }}</p>
                    </div>
                </template>
                
                <!-- B. SI AUCUNE DISCUSSION ACTIVE -->
                <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-8 bg-slate-50 dark:bg-slate-900/10">
                    <div class="w-16 h-16 rounded-full bg-brand-50 dark:bg-slate-800/80 flex items-center justify-center text-brand-600 dark:text-brand-400 mb-4 shadow-sm">
                        <span class="text-3xl">💬</span>
                    </div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Section WhatsApp</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mt-2 leading-relaxed">
                        Sélectionnez un contact dans le volet de gauche pour accéder à la conversation, envoyer des réponses ou prendre la main manuellement.
                    </p>
                </div>
            </div>
            
            <!-- 3. VOLET DROIT : DETAILS PROSPECT & NOTES INTERNES -->
            <aside 
                v-if="conversation && showSidebar" 
                class="w-80 border-l border-slate-200 dark:border-slate-800 flex flex-col h-full bg-white dark:bg-slate-900 shrink-0"
            >
                <div class="h-16 px-4 border-b border-slate-200 dark:border-slate-800/80 flex items-center justify-between shrink-0">
                    <h3 class="font-semibold text-xs uppercase tracking-wider text-slate-800 dark:text-slate-200">Détails</h3>
                    <button @click="showSidebar = false" class="text-[10px] font-bold text-slate-400 hover:text-slate-600 cursor-pointer">FERMER</button>
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
