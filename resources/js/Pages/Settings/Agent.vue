<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    agent: Object,
    hasApiKey: Boolean,
    providers: Array,
    tools: Array,
    defaults: Object,
});

const page = usePage();

const form = useForm({
    name: props.agent.name,
    provider: props.agent.provider,
    model: props.agent.model,
    api_key: '',
    temperature: Number(props.agent.temperature),
    max_tokens: props.agent.max_tokens,
    system_prompt: props.agent.system_prompt ?? '',
    persona: props.agent.persona ?? '',
    tone: props.agent.tone ?? 'professionnel',
    language: props.agent.language ?? 'fr',
    greeting_message: props.agent.greeting_message ?? '',
    fallback_message: props.agent.fallback_message ?? '',
    memory_window: props.agent.memory_window,
    rag_enabled: props.agent.rag_enabled,
    rag_top_k: props.agent.rag_top_k,
    rag_min_score: Number(props.agent.rag_min_score),
    handover_keywords: [...(props.agent.handover_keywords ?? [])],
    enabled_tools: [...(props.agent.enabled_tools ?? [])],
    active_hours_only: props.agent.active_hours_only,
    is_active: props.agent.is_active,
});

// Seuil d'avertissement, pas de blocage : le plafond reste 20 000. Au-delà
// d'environ 2 000 jetons renvoyés à chaque message, le coût par conversation
// devient sensible et mérite d'être signalé.
const promptTooLong = computed(() => (form.system_prompt?.length ?? 0) > 8000);

// Suggestions par fournisseur. Le champ reste libre : un nouveau modèle
// sorti après ce déploiement doit pouvoir être saisi sans mise à jour.
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

const previewing = ref(false);
const prompt = computed(() => page.props.flash?.promptPreview ?? null);

const preview = () =>
    router.post('/settings/agent/preview', {}, {
        preserveScroll: true,
        onSuccess: () => (previewing.value = true),
    });

const submit = () =>
    form
        // Champ vide = on conserve la clé déjà enregistrée côté serveur.
        .transform((data) => (data.api_key === '' ? { ...data, api_key: undefined } : data))
        .put('/settings/agent', { preserveScroll: true });
</script>

<template>
    <Head title="Agent IA" />

    <AppLayout>
        <h1 class="mb-6 text-xl font-semibold">Configuration</h1>
        <SettingsNav />

        <form class="grid gap-6 lg:grid-cols-2" @submit.prevent="submit">
            <!-- Identité -->
            <section class="card space-y-4">
                <h2 class="text-sm font-semibold">Identité</h2>

                <div>
                    <label class="label" for="name">Nom de l'agent</label>
                    <input id="name" v-model="form.name" type="text" class="input" required />
                    <p class="mt-1 text-xs text-slate-500">Le nom sous lequel il se présente aux clients.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="persona">Rôle</label>
                        <input id="persona" v-model="form.persona" type="text" class="input" placeholder="assistante commerciale" />
                    </div>
                    <div>
                        <label class="label" for="tone">Ton</label>
                        <input id="tone" v-model="form.tone" type="text" class="input" placeholder="professionnel" />
                    </div>
                </div>

                <div>
                    <label class="label" for="fallback">Message de repli</label>
                    <textarea id="fallback" v-model="form.fallback_message" rows="2" class="input resize-none" />
                    <!-- Envoyé quand le fournisseur échoue ou que le quota est
                         atteint : un silence total est le pire résultat possible. -->
                    <p class="mt-1 text-xs text-slate-500">
                        Envoyé si l'IA ne peut pas répondre. La conversation bascule alors vers un humain.
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" />
                    Agent actif
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.active_hours_only" type="checkbox" />
                    Ne répondre que pendant les horaires d'ouverture
                </label>
            </section>

            <!-- Modèle -->
            <section class="card space-y-4">
                <h2 class="text-sm font-semibold">Modèle</h2>

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
                    <label class="label" for="api_key">Clé API personnelle</label>
                    <input
                        id="api_key"
                        v-model="form.api_key"
                        type="password"
                        class="input"
                        autocomplete="off"
                        :placeholder="hasApiKey ? '•••••••• (enregistrée)' : 'Laisser vide pour utiliser les clés Nonalix'"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Facultatif. Avec votre propre clé, la consommation IA est facturée
                        directement par le fournisseur. Elle est chiffrée au repos.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="temperature">Créativité ({{ form.temperature }})</label>
                        <input id="temperature" v-model.number="form.temperature" type="range" min="0" max="1" step="0.05" class="w-full" />
                        <p class="text-xs text-slate-500">Basse = réponses prévisibles.</p>
                    </div>
                    <div>
                        <label class="label" for="max_tokens">Longueur maximale</label>
                        <input id="max_tokens" v-model.number="form.max_tokens" type="number" min="64" max="8192" class="input" />
                    </div>
                </div>

                <div>
                    <label class="label" for="memory">Mémoire ({{ form.memory_window }} messages)</label>
                    <input id="memory" v-model.number="form.memory_window" type="range" min="2" max="30" class="w-full" />
                    <p class="text-xs text-slate-500">
                        Messages réinjectés à chaque tour. Plus élevé = plus de contexte, mais plus coûteux.
                    </p>
                </div>
            </section>

            <!-- Instructions -->
            <section class="card space-y-4 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Instructions</h2>
                    <button type="button" class="text-xs text-slate-500 underline" @click="preview">
                        Voir le contexte envoyé au modèle
                    </button>
                </div>

                <textarea
                    v-model="form.system_prompt"
                    rows="14"
                    class="input resize-y font-mono text-sm"
                    maxlength="20000"
                    placeholder="Décrivez le rôle de l'agent, ce qu'il doit faire et ce qu'il ne doit jamais faire."
                />
                <div class="flex items-start justify-between gap-4">
                    <p class="text-xs text-slate-500">
                        Les informations sur l'entreprise, les horaires, les tarifs et les
                        questions fréquentes sont ajoutées automatiquement — inutile de les
                        répéter ici.
                    </p>
                    <span
                        class="shrink-0 text-xs tabular-nums"
                        :class="promptTooLong ? 'text-amber-600' : 'text-slate-400'"
                    >
                        {{ form.system_prompt.length.toLocaleString('fr-FR') }} / 20 000
                    </span>
                </div>

                <!-- Ces instructions repartent au modele a CHAQUE message : leur
                     longueur se paie a chaque conversation, pas une seule fois. -->
                <p v-if="promptTooLong" class="text-xs text-amber-600">
                    Environ {{ Math.round(form.system_prompt.length / 4).toLocaleString('fr-FR') }}
                    jetons renvoyés au modèle à chaque message. Au-delà de quelques milliers,
                    le coût par conversation grimpe vite : préférez la base de connaissances,
                    qui n'envoie que les passages utiles.
                </p>
            </section>

            <!-- Transfert humain -->
            <section class="card space-y-4">
                <h2 class="text-sm font-semibold">Transfert vers un humain</h2>

                <div>
                    <label class="label" for="keyword">Mots-clés de transfert</label>
                    <div class="flex gap-2">
                        <input
                            id="keyword"
                            v-model="keywordInput"
                            type="text"
                            class="input"
                            placeholder="conseiller"
                            @keydown.enter.prevent="addKeyword"
                        />
                        <button type="button" class="btn-secondary" @click="addKeyword">Ajouter</button>
                    </div>
                    <!-- Détectés avant tout appel au LLM : un client qui demande
                         un humain ne doit pas attendre une génération. -->
                    <p class="mt-1 text-xs text-slate-500">
                        Détectés avant même d'interroger le modèle : le transfert est immédiat.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="(keyword, index) in form.handover_keywords"
                            :key="keyword"
                            class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs dark:bg-slate-800"
                        >
                            {{ keyword }}
                            <button type="button" class="text-slate-400 hover:text-red-600" @click="removeKeyword(index)">
                                ✕
                            </button>
                        </span>
                    </div>
                </div>
            </section>

            <!-- Outils et base de connaissances -->
            <section class="card space-y-4">
                <h2 class="text-sm font-semibold">Capacités</h2>

                <div class="space-y-3">
                    <label v-for="tool in tools" :key="tool.name" class="flex gap-3 text-sm">
                        <input
                            type="checkbox"
                            class="mt-1"
                            :checked="form.enabled_tools.includes(tool.name)"
                            @change="toggleTool(tool.name)"
                        />
                        <span>
                            <span class="font-mono text-xs">{{ tool.name }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ tool.description }}</span>
                        </span>
                    </label>
                </div>

                <hr class="border-slate-100 dark:border-slate-800" />

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.rag_enabled" type="checkbox" />
                    Utiliser la base de connaissances
                </label>

                <div v-if="form.rag_enabled" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="top_k">Extraits ({{ form.rag_top_k }})</label>
                        <input id="top_k" v-model.number="form.rag_top_k" type="range" min="1" max="20" class="w-full" />
                    </div>
                    <div>
                        <label class="label" for="min_score">Seuil ({{ form.rag_min_score }})</label>
                        <input id="min_score" v-model.number="form.rag_min_score" type="range" min="0" max="1" step="0.05" class="w-full" />
                        <p class="text-xs text-slate-500">Plus haut = extraits plus pertinents, mais moins nombreux.</p>
                    </div>
                </div>
            </section>

            <div class="lg:col-span-2">
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Enregistrement…' : 'Enregistrer la configuration' }}
                </button>
            </div>
        </form>

        <Modal :open="previewing" title="Contexte envoyé au modèle" max-width="max-w-3xl" @close="previewing = false">
            <p class="mb-3 text-sm text-slate-500">
                Voici exactement ce que l'agent « sait » avant de lire le message du client.
                Les extraits de la base de connaissances s'y ajoutent au cas par cas.
            </p>
            <pre class="max-h-[60vh] overflow-auto rounded-lg bg-slate-100 p-4 text-xs whitespace-pre-wrap dark:bg-slate-800">{{ prompt }}</pre>
        </Modal>
    </AppLayout>
</template>
