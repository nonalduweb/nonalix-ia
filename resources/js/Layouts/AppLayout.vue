<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ConfigCopilot from '@/Components/ConfigCopilot.vue';

const page = usePage();

const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
const impersonating = computed(() => page.props.impersonating);

const navigation = computed(() => [
    { label: 'Tableau de bord', href: '/', permission: null, show: true },
    // « Conversations » et non « WhatsApp » : la boîte réunit désormais le
    // WhatsApp et le widget web. Elle s'affiche dès qu'un canal l'alimente,
    // sans quoi une entreprise sans numéro connecté ne verrait jamais les
    // messages recus depuis son site.
    { label: 'Conversations', href: '/conversations', permission: 'conversations.view', show: !!tenant.value?.whatsapp_connected || !!tenant.value?.has_conversations },
    { label: 'Ventes & Automation', href: '/sales', permission: 'leads.view', show: true },
    { label: 'Contacts', href: '/contacts', permission: 'contacts.view', show: true },
    { label: 'Prospects', href: '/leads', permission: 'leads.view', show: true },
    { label: 'Connaissances', href: '/knowledge', permission: 'knowledge.view', show: true },
    { label: 'Configuration', href: '/settings/business', permission: 'settings.update', show: true },
]);

// Les permissions ne servent qu'à masquer ce qui est inutile : l'autorisation
// réelle est vérifiée côté serveur par les policies. Cacher un lien n'a
// jamais protégé une route.
const visibleNavigation = computed(() =>
    navigation.value.filter(
        (item) => (!item.permission || user.value?.permissions?.includes(item.permission)) && item.show,
    ),
);

const isCurrent = (href) =>
    href === '/' ? page.url === '/' : page.url.startsWith(href);

const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-full">
        <!-- L'opérateur doit toujours savoir qu'il agit au nom d'un client. -->
        <div
            v-if="impersonating"
            class="bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950"
        >
            Session d'assistance en cours sur « {{ impersonating }} ».
            <button class="underline" @click="router.delete('/impersonate')">
                Revenir à mon compte
            </button>
        </div>

        <div
            v-if="tenant?.status === 'trial'"
            class="bg-brand-50 px-4 py-2 text-center text-sm text-brand-700"
        >
            Période d'essai — se termine le
            {{ new Date(tenant.trial_ends_at).toLocaleDateString('fr-FR') }}.
        </div>

        <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3">
                <!-- `invert` seul retournerait aussi la teinte du N : le
                     `hue-rotate` la ramène, si bien que le noir devient blanc
                     et que le bleu de la marque reste bleu. -->
                <Link href="/" class="shrink-0" aria-label="Nonalix IA — accueil">
                    <img
                        src="/logo-nonalixia.png"
                        alt="Nonalix IA"
                        class="h-7 w-auto dark:invert dark:hue-rotate-180"
                        width="500"
                        height="105"
                    />
                </Link>

                <nav class="flex flex-1 gap-1">
                    <Link
                        v-for="item in visibleNavigation"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="
                            isCurrent(item.href)
                                ? 'bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-brand-100'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="flex items-center gap-3 text-sm">
                    <span class="text-slate-500">{{ tenant?.name }}</span>
                    <span class="font-medium">{{ user?.name }}</span>
                    <button class="text-slate-500 hover:text-slate-900" @click="logout">
                        Déconnexion
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8">
            <div
                v-if="page.props.flash?.success"
                class="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ page.props.flash.success }}
            </div>

            <div
                v-if="page.props.flash?.error"
                class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800"
            >
                {{ page.props.flash.error }}
            </div>

            <slot />
        </main>

        <!-- Copilote d'assistance à la configuration -->
        <ConfigCopilot />
    </div>
</template>
