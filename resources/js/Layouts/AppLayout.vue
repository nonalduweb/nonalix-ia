<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ConfigCopilot from '@/Components/ConfigCopilot.vue';
import Icon from '@/Components/Icon.vue';

const page = usePage();

const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
const impersonating = computed(() => page.props.impersonating);

const navigation = computed(() => [
    { label: 'Tableau de bord', href: '/', icon: 'home', permission: null, show: true },
    // « Conversations » et non « WhatsApp » : la boîte réunit désormais le
    // WhatsApp et le widget web. Elle s'affiche dès qu'un canal l'alimente,
    // sans quoi une entreprise sans numéro connecté ne verrait jamais les
    // messages recus depuis son site.
    { label: 'Conversations', href: '/conversations', icon: 'chat', permission: 'conversations.view', show: !!tenant.value?.whatsapp_connected || !!tenant.value?.has_conversations },
    { label: 'Ventes & Automation', href: '/sales', icon: 'trending', permission: 'leads.view', show: true },
    { label: 'Contacts', href: '/contacts', icon: 'users', permission: 'contacts.view', show: true },
    { label: 'Prospects', href: '/leads', icon: 'target', permission: 'leads.view', show: true },
    { label: 'Connaissances', href: '/knowledge', icon: 'book', permission: 'knowledge.view', show: true },
    { label: 'Configuration', href: '/settings/business', icon: 'cog', permission: 'settings.update', show: true },
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

// Initiales pour la pastille d'identité : deux lettres au plus, sinon la
// pastille cesse d'être ronde sur les noms composés.
const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join(''),
);

const mobileNavOpen = ref(false);

// Referme le tiroir à chaque navigation : sans cela, il reste ouvert
// par-dessus la page qu'on vient d'ouvrir.
watch(() => page.url, () => (mobileNavOpen.value = false));

const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-full">
        <!-- L'opérateur doit toujours savoir qu'il agit au nom d'un client. -->
        <div
            v-if="impersonating"
            class="flex items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950"
        >
            <Icon name="alert" size="sm" />
            Session d'assistance en cours sur « {{ impersonating }} ».
            <button class="cursor-pointer underline underline-offset-2" @click="router.delete('/impersonate')">
                Revenir à mon compte
            </button>
        </div>

        <div
            v-if="tenant?.status === 'trial'"
            class="flex items-center justify-center gap-2 bg-brand-50 px-4 py-2 text-center text-sm text-brand-700"
        >
            <Icon name="clock" size="sm" />
            {{ tenant.plan || "Période d'essai" }} — se termine le
            {{ new Date(tenant.trial_ends_at).toLocaleDateString('fr-FR') }}.
        </div>

        <!--
            En-tête collant : sur une conversation longue ou un tableau de
            contacts, la navigation restait hors d'atteinte sans revenir en
            haut de page.
        -->
        <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/85 backdrop-blur dark:border-slate-800 dark:bg-slate-900/85">
            <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 lg:gap-6">
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

                <!-- Navigation principale, masquée sous 1024 px : sept entrées
                     avec icône ne tiennent pas sur la largeur d'un téléphone. -->
                <nav class="hidden flex-1 gap-0.5 lg:flex">
                    <Link
                        v-for="item in visibleNavigation"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="
                            isCurrent(item.href)
                                ? 'bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-brand-100'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                        "
                        :aria-current="isCurrent(item.href) ? 'page' : undefined"
                    >
                        <Icon :name="item.icon" size="sm" />
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-3">
                    <div class="hidden text-right leading-tight sm:block">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ user?.name }}</p>
                        <p class="text-xs text-slate-500">{{ tenant?.name }}</p>
                    </div>

                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900"
                        :title="user?.name"
                    >
                        {{ initials }}
                    </span>

                    <button
                        class="btn-ghost hidden lg:inline-flex"
                        aria-label="Se déconnecter"
                        @click="logout"
                    >
                        <Icon name="logout" size="sm" />
                    </button>

                    <button
                        class="btn-ghost lg:hidden"
                        :aria-expanded="mobileNavOpen"
                        aria-label="Ouvrir la navigation"
                        @click="mobileNavOpen = !mobileNavOpen"
                    >
                        <Icon :name="mobileNavOpen ? 'close' : 'menu'" />
                    </button>
                </div>
            </div>

            <!-- Tiroir de navigation mobile -->
            <nav
                v-if="mobileNavOpen"
                class="border-t border-slate-200/70 bg-white px-4 py-3 lg:hidden dark:border-slate-800 dark:bg-slate-900"
            >
                <Link
                    v-for="item in visibleNavigation"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                    :class="
                        isCurrent(item.href)
                            ? 'bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-brand-100'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                    "
                >
                    <Icon :name="item.icon" size="sm" />
                    {{ item.label }}
                </Link>

                <button
                    class="mt-1 flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    @click="logout"
                >
                    <Icon name="logout" size="sm" />
                    Déconnexion
                </button>
            </nav>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:py-10">
            <div v-if="page.props.flash?.success" class="alert-success mb-6 flex items-center gap-2">
                <Icon name="checkCircle" size="sm" />
                {{ page.props.flash.success }}
            </div>

            <div v-if="page.props.flash?.error" class="alert-error mb-6 flex items-center gap-2">
                <Icon name="alert" size="sm" />
                {{ page.props.flash.error }}
            </div>

            <slot />
        </main>

        <!-- Copilote d'assistance à la configuration -->
        <ConfigCopilot />
    </div>
</template>
