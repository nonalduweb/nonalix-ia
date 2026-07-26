<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const navigation = [
    { label: 'Vue d\'ensemble', href: '/' },
    { label: 'Entreprises', href: '/tenants' },
    { label: 'Codes d\'accès', href: '/access-codes' },
    { label: 'Plans', href: '/plans' },
    { label: 'Consommation', href: '/usage' },
    { label: 'Incidents', href: '/incidents' },
    { label: 'Audit', href: '/audit-logs' },
];

const isCurrent = (href) =>
    href === '/' ? page.url === '/' : page.url.startsWith(href);

const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-full">
        <!--
          Bandeau sombre volontairement distinct de l'espace client : un
          administrateur NONALIX doit voir d'un coup d'œil qu'il agit sur la
          plateforme entière, pas sur un compte.
        -->
        <header class="bg-slate-900 text-slate-100">
            <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3">
                <span class="text-sm font-semibold tracking-tight">
                    Nonalix&nbsp;IA
                    <span class="ml-1 rounded bg-slate-700 px-1.5 py-0.5 text-[10px] uppercase tracking-wider">
                        administration
                    </span>
                </span>

                <nav class="flex flex-1 gap-1">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-lg px-3 py-2 text-sm transition"
                        :class="
                            isCurrent(item.href)
                                ? 'bg-slate-700 font-medium text-white'
                                : 'text-slate-300 hover:bg-slate-800'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="flex items-center gap-3 text-sm">
                    <span class="text-slate-300">{{ user?.name }}</span>
                    <button class="text-slate-400 hover:text-white" @click="logout">
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
    </div>
</template>
