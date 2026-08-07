<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    agent: Object, // nullable
    hasApiKey: Boolean,
    providers: Array,
    tools: Array,
    defaults: Object,
});

const page = usePage();

const form = useForm({
    name: props.agent?.name ?? 'Nouvel Agent',
    provider: props.agent?.provider ?? 'openai',
    model: props.agent?.model ?? 'gpt-4.1-mini',
    api_key: '',
    temperature: Number(props.agent?.temperature ?? 0.3),
    max_tokens: props.agent?.max_tokens ?? 1024,
    system_prompt: props.agent?.system_prompt ?? '',
    persona: props.agent?.persona ?? '',
    tone: props.agent?.tone ?? 'professionnel',
    language: props.agent?.language ?? 'fr',
    greeting_message: props.agent?.greeting_message ?? '',
    fallback_message: props.agent?.fallback_message ?? '',
    memory_window: props.agent?.memory_window ?? 12,
    rag_enabled: props.agent?.rag_enabled ?? true,
    rag_top_k: props.agent?.rag_top_k ?? 5,
    rag_min_score: Number(props.agent?.rag_min_score ?? 0.75),
    handover_keywords: [...(props.agent?.handover_keywords ?? ['humain', 'conseiller', 'agent', 'quelqu\'un'])],
    enabled_tools: [...(props.agent?.enabled_tools ?? ['request_human_handover', 'list_services', 'get_business_hours'])],
    active_hours_only: props.agent?.active_hours_only ?? false,
    is_active: props.agent?.is_active ?? true,
    settings: {
        n8n_webhook_url: props.agent?.settings?.n8n_webhook_url ?? '',
    },
});

const isNew = computed(() => !props.agent);

// Seuil d'avertissement
const promptTooLong = computed(() => (form.system_prompt?.length ?? 0) > 8000);

const MODELS = {
    openai: ['gpt-4.1-mini', 'gpt-4.1'],
    anthropic: ['claude-sonnet-5', 'claude-haiku-4-5'],
    gemini: ['gemini-2.5-flash'],
};

const suggestedModels = computed(() => MODELS[form.provider] ?? []);

const keywordInput = ref('');

const addKeyword = () => {
    const value = keywordInput.value.trim().toLowerCase();
    if (value && !form.handover_keywords.includes(value)) {
        form.handover_keywords.push(value);
    }
    keywordInput.value = '';
};

const removeKeyword = (index) => form.handover_keywords.splice(index, 1);

const toggleTool = (name) => {
    const index = form.enabled_tools.indexOf(name);
    index === -1 ? form.enabled_tools.push(name) : form.enabled_tools.splice(index, 1);
};

const toolCategory = (name) => {
    const n8nTools = ['create_prospect', 'book_appointment', 'send_email', 'generate_quote', 'check_order_status', 'send_document'];
    return n8nTools.includes(name) ? 'n8n' : 'standard';
};

const toolLabel = (name) => {
    return {
        request_human_handover: "Transférer la main à un conseiller humain",
        qualify_lead: "Qualifier et noter les prospects",
        list_services: "Lister les prestations/services de l'entreprise",
        get_business_hours: "Donner les horaires d'ouverture",
        create_prospect: "Créer un prospect dans le CRM",
        book_appointment: "Prendre un rendez-vous (Google Calendar, etc.)",
        send_email: "Envoyer un e-mail automatiquement",
        generate_quote: "Générer et envoyer un devis commercial",
        check_order_status: "Vérifier le statut d'une commande",
        send_document: "Transmettre un document (PDF, tarifs, catalogue)",
    }[name] || name;
};

const previewing = ref(false);
const prompt = computed(() => page.props.flash?.promptPreview ?? null);

const preview = () =>
    router.post(`/settings/agent/${props.agent.id}/preview`, {}, {
        preserveScroll: true,
        onSuccess: () => (previewing.value = true),
    });

const submit = () => {
    const action = isNew.value ? '/settings/agent' : `/settings/agent/${props.agent.id}`;
    form
        .transform((data) => (data.api_key === '' ? { ...data, api_key: undefined } : data))
        .submit(isNew.value ? 'post' : 'put', action, { preserveScroll: true });
};
</script>

<template>
    <Head :title="isNew ? 'Créer un Agent IA' : 'Configurer l\'Agent IA'" />

    <AppLayout>
        <div class="flex items-center gap-3 mb-6">
            <Link href="/settings/agent" class="text-slate-400 hover:text-slate-600 transition text-sm">
                ← Retour
            </Link>
            <h1 class="text-xl font-semibold">
                {{ isNew ? 'Créer un nouvel Agent IA' : `Configurer l'Agent : ${agent.name}` }}
            </h1>
        </div>

        <form class="grid gap-6 lg:grid-cols-2 pb-12" @submit.prevent="submit">
            <!-- Identité -->
            <section class="card space-y-4">
                <h2 class="text-sm font-semibold">Identité de l'agent</h2>

                <div>
                    <label class="label" for="name">Nom de l'agent</label>
                    <input id="name" v-model="form.name" type="text" class="input" required />
                    <p class="mt-1 text-xs text-slate-500">Le nom sous lequel il se présente aux clients.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="persona">Rôle / Métier</label>
                        <input id="persona" v-model="form.persona" type="text" class="input" placeholder="assistante commerciale" />
                    </div>
                    <div>
                        <label class="label" for="tone">Ton de voix</label>
                        <input id="tone" v-model="form.tone" type="text" class="input" placeholder="professionnel" />
                    </div>
                </div>

                <div>
                    <label class="label" for="fallback">Message de repli</label>
                    <textarea id="fallback" v-model="form.fallback_message" rows="2" class="input resize-none" />
                    <p class="mt-1 text-xs text-slate-500">
                        Envoyé si l'IA ne peut pas répondre. La conversation bascule alors vers un humain.
                    </p>
                </div>

                <div class="flex flex-col gap-2 pt-2">
                    <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                        <input v-model="form.is_active" type="checkbox" />
                        <span><strong>Agent par défaut</strong> (Activer pour ce canal)</span>
                    </label>

                    <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                        <input v-model="form.active_hours_only" type="checkbox" />
                        <span>Ne répondre que pendant les horaires d'ouverture</span>
                    </label>
                </div>
            </section>

            <!-- Modèle -->
            <section class="card space-y-4">
                <h2 class="text-sm font-semibold">Fournisseur & Modèle LLM</h2>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="provider">Fournisseur</label>
                        <select id="provider" v-model="form.provider" class="input">
                            <option v-for="provider in providers" :key="provider.value" :value="provider.value">
                                {{ provider.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="model">Modèle</label>
                        <input id="model" v-model="form.model" type="text" class="input" list="models" required />
                        <datalist id="models">
                            <option v-for="model in suggestedModels" :key="model" :value="model" />
                        </datalist>
                    </div>
                </div>
                <p v-if="form.errors.model" class="text-sm text-red-600">{{ form.errors.model }}</p>

                <div>
                    <label class="label" for="api_key">Clé API personnelle (optionnelle)</label>
                    <input
                        id="api_key"
                        v-model="form.api_key"
                        type="password"
                        class="input"
                        autocomplete="off"
                        :placeholder="hasApiKey ? '•••••••• (enregistrée)' : 'Laisser vide pour utiliser les clés Nonalix'"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Si vide, l'application utilise la clé générale de la plateforme (crédit facturé par Nonalix).
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="temperature">Créativité ({{ form.temperature }})</label>
                        <input id="temperature" v-model.number="form.temperature" type="range" min="0" max="1" step="0.05" class="w-full" />
                    </div>
                    <div>
                        <label class="label" for="max_tokens">Longueur max (Tokens)</label>
                        <input id="max_tokens" v-model.number="form.max_tokens" type="number" min="64" max="8192" class="input" />
                    </div>
                </div>

                <div>
                    <label class="label" for="memory">Fenêtre mémoire ({{ form.memory_window }} messages)</label>
                    <input id="memory" v-model.number="form.memory_window" type="range" min="2" max="30" class="w-full" />
                    <p class="text-xs text-slate-500">
                        Historique de discussion réinjecté au modèle à chaque message.
                    </p>
                </div>
            </section>

            <!-- Instructions -->
            <section class="card space-y-4 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Instructions Systèmes (Prompt)</h2>
                    <button v-if="!isNew" type="button" class="text-xs text-brand-600 hover:underline cursor-pointer" @click="preview">
                        Voir le contexte envoyé au modèle
                    </button>
                </div>

                <textarea
                    v-model="form.system_prompt"
                    rows="10"
                    class="input resize-y font-mono text-sm"
                    maxlength="20000"
                    placeholder="Décrivez précisément les consignes de l'agent, ses objectifs et ses limites de réponses."
                />
                <div class="flex items-start justify-between gap-4">
                    <p class="text-xs text-slate-500 font-medium">
                        ⚠️ Les informations dynamiques de l'entreprise (FAQ, Prestations, Horaires) sont injectées automatiquement par le système, inutile de les réécrire ici.
                    </p>
                    <span
                        class="shrink-0 text-xs font-mono"
                        :class="promptTooLong ? 'text-amber-600' : 'text-slate-400'"
                    >
                        {{ form.system_prompt.length.toLocaleString('fr-FR') }} / 20 000
                    </span>
                </div>
            </section>

            <!-- Transfert humain -->
            <section class="card space-y-4">
                <h2 class="text-sm font-semibold">Escalade & Transfert Humain</h2>

                <div>
                    <label class="label" for="keyword">Mots-clés déclencheurs</label>
                    <div class="flex gap-2">
                        <input
                            id="keyword"
                            v-model="keywordInput"
                            type="text"
                            class="input"
                            placeholder="conseiller, humain, responsable"
                            @keydown.enter.prevent="addKeyword"
                        />
                        <button type="button" class="btn-secondary py-2" @click="addKeyword">Ajouter</button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        La détection de ces mots-clés dans les messages clients coupe immédiatement l'IA et alerte votre équipe.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="(keyword, index) in form.handover_keywords"
                            :key="keyword"
                            class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-semibold"
                        >
                            {{ keyword }}
                            <button type="button" class="text-slate-400 hover:text-red-600 transition" @click="removeKeyword(index)">
                                ✕
                            </button>
                        </span>
                    </div>
                </div>
            </section>

            <!-- Outils & n8n -->
            <section class="card space-y-5">
                <h2 class="text-sm font-semibold">Capacités & Actions Automatisées</h2>

                <!-- Actions standard -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Capacités standards</h3>
                    <div class="space-y-3">
                        <label v-for="tool in tools.filter(t => toolCategory(t.name) === 'standard')" :key="tool.name" class="flex gap-3 text-sm cursor-pointer select-none">
                            <input
                                type="checkbox"
                                class="mt-1 rounded"
                                :checked="form.enabled_tools.includes(tool.name)"
                                @change="toggleTool(tool.name)"
                            />
                            <span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ toolLabel(tool.name) }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ tool.description }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <hr class="border-slate-100 dark:border-slate-800" />

                <!-- Actions n8n -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Actions Automatisées (via n8n)</h3>
                    
                    <div class="mb-3">
                        <label class="label text-[11px] font-medium text-slate-500" for="n8n_webhook_url">URL de Webhook n8n d'entreprise</label>
                        <input
                            id="n8n_webhook_url"
                            v-model="form.settings.n8n_webhook_url"
                            type="url"
                            class="input py-1.5 px-3 text-xs"
                            placeholder="https://n8n.votre-serveur.com/webhook/..."
                        />
                        <p class="mt-1 text-[10px] text-slate-400 leading-normal">
                            Renseignez cette URL pour débloquer l'exécution d'actions de l'agent.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <label v-for="tool in tools.filter(t => toolCategory(t.name) === 'n8n')" :key="tool.name" class="flex gap-3 text-sm" :class="form.settings.n8n_webhook_url ? 'cursor-pointer select-none' : 'opacity-40'">
                            <input
                                type="checkbox"
                                class="mt-1 rounded"
                                :checked="form.enabled_tools.includes(tool.name)"
                                :disabled="!form.settings.n8n_webhook_url"
                                @change="toggleTool(tool.name)"
                            />
                            <span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ toolLabel(tool.name) }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ tool.description }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <hr class="border-slate-100 dark:border-slate-800" />

                <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                    <input v-model="form.rag_enabled" type="checkbox" />
                    <span>Utiliser la base de connaissances (RAG)</span>
                </label>

                <div v-if="form.rag_enabled" class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="label text-xs" for="top_k">Extraits maximum ({{ form.rag_top_k }})</label>
                        <input id="top_k" v-model.number="form.rag_top_k" type="range" min="1" max="20" class="w-full" />
                    </div>
                    <div>
                        <label class="label text-xs" for="min_score">Seuil de similarité ({{ form.rag_min_score }})</label>
                        <input id="min_score" v-model.number="form.rag_min_score" type="range" min="0" max="1" step="0.05" class="w-full" />
                    </div>
                </div>
            </section>

            <!-- Actions de validation -->
            <div class="lg:col-span-2 flex justify-end gap-3 pt-4">
                <Link href="/settings/agent" class="btn-secondary py-2 px-4 text-xs font-semibold cursor-pointer">
                    Annuler
                </Link>
                <button type="submit" class="btn-primary py-2 px-5 text-xs font-semibold cursor-pointer animate-none" :disabled="form.processing">
                    {{ form.processing ? 'Enregistrement…' : isNew ? 'Créer l\'agent' : 'Enregistrer les modifications' }}
                </button>
            </div>
        </form>

        <Modal :open="previewing" title="Contexte système envoyé au modèle" max-width="max-w-3xl" @close="previewing = false">
            <p class="mb-3 text-xs text-slate-500">
                Aperçu des instructions combinées de l'entreprise (FAQ, Prestations, Horaires) envoyées au LLM à chaque message.
            </p>
            <pre class="max-h-[60vh] overflow-auto rounded-lg bg-slate-100 p-4 text-[10px] font-mono whitespace-pre-wrap dark:bg-slate-800 text-slate-700 dark:text-slate-300">{{ prompt }}</pre>
        </Modal>
    </AppLayout>
</template>
