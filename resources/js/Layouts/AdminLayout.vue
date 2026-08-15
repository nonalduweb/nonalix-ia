<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const navigation = [
    { label: 'Vue d\'ensemble', href: '/', icon: 'home' },
    { label: 'Entreprises', href: '/tenants', icon: 'users' },
    { label: 'Codes d\'accès', href: '/access-codes', icon: 'target' },
    { label: 'Plans', href: '/plans', icon: 'money' },
    { label: 'Clés IA', href: '/platform-keys', icon: 'sparkles' },
    { label: 'Consommation', href: '/usage', icon: 'trending' },
    { label: 'Incidents', href: '/incidents', icon: 'alert' },
    { label: 'Audit', href: '/audit-logs', icon: 'document' },
];

const isCurrent = (href) =>
    href === '/' ? page.url === '/' : page.url.startsWith(href);

const mobileNavOpen = ref(false);
watch(() => page.url, () => (mobileNavOpen.value = false));

const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-full">
        <!--
          Bandeau sombre volontairement distinct de l'espace client : un
          administrateur NONALIX doit voir d'un coup d'œil qu'il agit sur la
          plateforme entière, pas sur un compte.

          C'est la seule surface de l'application qui reste sombre en thème
          clair, et c'est délibéré : cette distinction prime sur l'uniformité.
        -->
        <header class="sticky top-0 z-30 bg-slate-900 text-slate-100">
            <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 lg:gap-6">
                <span class="flex shrink-0 items-center gap-2 text-sm font-semibold tracking-tight">
                    <img src="/logo-nonalixia.png" alt="Nonalix IA" width="500" height="105" class="h-5 w-auto invert hue-rotate-180" />
                    <span class="ml-1 rounded bg-slate-700 px-1.5 py-0.5 text-[10px] tracking-wider uppercase">
                        administration
                    </span>
                </span>

                <nav class="hidden flex-1 gap-0.5 lg:flex">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition"
                        :class="
                            isCurrent(item.href)
                                ? 'bg-slate-700 font-medium text-white'
                                : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                        "
                        :aria-current="isCurrent(item.href) ? 'page' : undefined"
                    >
                        <Icon :name="item.icon" size="sm" />
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-3 text-sm">
                    <span class="hidden text-slate-300 sm:block">{{ user?.name }}</span>

                    <button
                        class="hidden cursor-pointer rounded-lg px-3 py-2 text-slate-400 transition hover:bg-slate-800 hover:text-white lg:inline-flex"
                        aria-label="Se déconnecter"
                        @click="logout"
                    >
                        <Icon name="logout" size="sm" />
                    </button>

                    <button
                        class="cursor-pointer rounded-lg px-2 py-2 text-slate-300 transition hover:bg-slate-800 hover:text-white lg:hidden"
                        :aria-expanded="mobileNavOpen"
                        aria-label="Ouvrir la navigation"
                        @click="mobileNavOpen = !mobileNavOpen"
                    >
                        <Icon :name="mobileNavOpen ? 'close' : 'menu'" />
                    </button>
                </div>
            </div>

            <nav v-if="mobileNavOpen" class="border-t border-slate-800 px-4 py-3 lg:hidden">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition"
                    :class="
                        isCurrent(item.href)
                            ? 'bg-slate-700 font-medium text-white'
                            : 'text-slate-300 hover:bg-slate-800'
                    "
                >
                    <Icon :name="item.icon" size="sm" />
                    {{ item.label }}
                </Link>

                <button
                    class="mt-1 flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800"
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
    </div>
</template>
