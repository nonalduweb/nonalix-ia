<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

// Date de mise à jour réelle
const lastUpdated = "8 août 2026";

// Sommaire pour la navigation rapide
const indexItems = [
    { id: 'publisher', label: '1. Éditeur de la plateforme' },
    { id: 'product', label: '2. Le service NONALIX IA' },
    { id: 'director', label: '3. Directeur de la publication' },
    { id: 'hoster', label: '4. Hébergement' },
    { id: 'ip', label: '5. Propriété intellectuelle' },
    { id: 'user-data', label: '6. Données et contenus utilisateurs' },
    { id: 'ai', label: '7. Intelligence artificielle' },
    { id: 'third-party', label: '8. Services et technologies tiers' },
    { id: 'responsibility', label: '9. Responsabilité' },
    { id: 'availability', label: '10. Disponibilité du service' },
    { id: 'security', label: '11. Sécurité' },
    { id: 'personal-data', label: '12. Protection des données' },
    { id: 'cookies', label: '13. Cookies et traceurs' },
    { id: 'external-links', label: '14. Liens externes' },
    { id: 'fair-use', label: '15. Utilisation licite' },
    { id: 'law', label: '16. Droit applicable' },
    { id: 'contact', label: '17. Contact' }
];

const scrollTo = (id) => {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
};
</script>

<template>
    <!-- Métadonnées de la page pour le SEO -->
    <Head>
        <title>Mentions légales | NONALIX IA</title>
        <meta name="description" content="Consultez les mentions légales de NONALIX IA, plateforme SaaS éditée et exploitée par NONALIX CI SARL en Côte d’Ivoire." />
        <link rel="canonical" href="https://nonalixia.com/mentions-legales" />
        <meta name="robots" content="index, follow" />
        
        <!-- Open Graph -->
        <meta property="og:title" content="Mentions légales | NONALIX IA" />
        <meta property="og:description" content="Consultez les mentions légales de NONALIX IA, plateforme SaaS éditée et exploitée par NONALIX CI SARL." />
        <meta property="og:url" content="https://nonalixia.com/mentions-legales" />
        <meta property="og:type" content="website" />
    </Head>

    <MarketingLayout>
        <div class="mx-auto max-w-5xl px-6 py-16">
            <!-- En-tête de la page -->
            <header class="mb-12 border-b border-slate-100 pb-8 dark:border-slate-800 pb-8">
                <span class="text-xs font-bold uppercase tracking-wider text-brand-600 dark:text-brand-400">Mentions légales</span>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                    Mentions légales
                </h1>
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    Informations légales relatives à la plateforme NONALIX IA.
                </p>
                <p class="mt-1 text-xs text-slate-400">
                    Dernière mise à jour : {{ lastUpdated }}
                </p>
            </header>

            <!-- Layout principal à deux colonnes -->
            <div class="grid grid-cols-1 gap-12 lg:grid-cols-4">
                
                <!-- Sommaire sticky à gauche pour desktop -->
                <aside class="hidden lg:block lg:col-span-1">
                    <div class="sticky top-24 space-y-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Sommaire</h3>
                        <nav class="flex flex-col space-y-1.5 text-xs text-slate-500">
                            <button 
                                v-for="item in indexItems" 
                                :key="item.id"
                                @click="scrollTo(item.id)"
                                class="text-left font-medium hover:text-slate-900 dark:hover:text-white transition cursor-pointer"
                            >
                                {{ item.label }}
                            </button>
                        </nav>
                    </div>
                </aside>

                <!-- Corps de texte éditorial à droite -->
                <main class="lg:col-span-3 space-y-10 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    
                    <!-- 1. Éditeur de la plateforme -->
                    <section id="publisher" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">1. Éditeur de la plateforme</h2>
                        <p>
                            NONALIX IA est une plateforme SaaS d’intelligence artificielle et d’automatisation éditée et exploitée par NONALIX CI SARL, Société à Responsabilité Limitée au capital social de 1 000 000 FCFA, immatriculée au Registre du Commerce et du Crédit Mobilier sous le numéro CI-ABJ-03-2026-B13-10152.
                        </p>
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50 space-y-1.5 text-xs">
                            <p><strong>Raison sociale :</strong> NONALIX CI SARL</p>
                            <p><strong>Forme juridique :</strong> Société à Responsabilité Limitée (SARL)</p>
                            <p><strong>Capital social :</strong> 1 000 000 FCFA</p>
                            <p><strong>RCCM :</strong> CI-ABJ-03-2026-B13-10152</p>
                            <p><strong>Siège social :</strong> Abidjan, Cocody Angré, Pétro Ivoire, près de BAOBAB Microfinance, Îlot 332, Lot 4172, Côte d’Ivoire</p>
                            <p><strong>Adresse e-mail de contact :</strong> <a href="mailto:contact@nonalixia.com" class="text-brand-600 hover:underline">contact@nonalixia.com</a> (Privilégiée)</p>
                            <p><strong>Téléphone :</strong> +225 05 66 36 03 03</p>
                            <p><strong>Site officiel :</strong> <a href="https://nonalix-ci.com" target="_blank" class="text-brand-600 hover:underline">https://nonalix-ci.com</a></p>
                        </div>
                    </section>

                    <!-- 2. Le service NONALIX IA -->
                    <section id="product" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">2. Le service NONALIX IA</h2>
                        <p>
                            NONALIX IA est un produit numérique développé, édité et exploité par NONALIX CI SARL. L’utilisation de la dénomination « NONALIX IA » sur la plateforme, ses interfaces, ses communications ou ses services ne constitue pas la désignation d’une entité juridique distincte de NONALIX CI SARL.
                        </p>
                        <p>
                            La plateforme fournit des services SaaS liés notamment à :
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2">
                            <li>la configuration et l'exploitation d'agents conversationnels intelligents ;</li>
                            <li>l'automatisation des communications multicanales (WhatsApp Business, Widget Web, E-mail) ;</li>
                            <li>la gestion centralisée de conversations clients via une boîte de réception partagée ;</li>
                            <li>la structuration de bases de connaissances pour l'IA à partir de documents importés ;</li>
                            <li>l'automatisation de tâches commerciales et l'interfaçage avec des flux de travail n8n.</li>
                        </ul>
                    </section>

                    <!-- 3. Directeur de la publication -->
                    <section id="director" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">3. Directeur de la publication</h2>
                        <p>
                            Le directeur de la publication est le représentant légal de la société NONALIX CI SARL.
                        </p>
                    </section>

                    <!-- 4. Hébergement -->
                    <section id="hoster" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">4. Hébergement</h2>
                        <p>
                            Les serveurs applicatifs, les bases de données et les flux SMTP de NONALIX IA sont hébergés et acheminés par les infrastructures de la société Hostinger :
                        </p>
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50 space-y-1 text-xs">
                            <p><strong>Hébergeur :</strong> Hostinger International Ltd.</p>
                            <p><strong>Adresse :</strong> 61 Lordou Vironos Street, 6023 Larnaca, Chypre</p>
                            <p><strong>Site web :</strong> <a href="https://www.hostinger.com" target="_blank" class="text-brand-600 hover:underline">https://www.hostinger.com</a></p>
                        </div>
                    </section>

                    <!-- 5. Propriété intellectuelle -->
                    <section id="ip" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">5. Propriété intellectuelle</h2>
                        <p>
                            Sauf mention contraire, les éléments propriétaires de NONALIX IA appartiennent à NONALIX CI SARL. Cela comprend notamment :
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2">
                            <li>les marques, logos et dénominations « NONALIX IA » et « NONALIX CI » ;</li>
                            <li>l'architecture graphique, les designs, le logo et les interfaces de la plateforme ;</li>
                            <li>les textes rédactionnels, documentations et composants graphiques originaux ;</li>
                            <li>les codes sources, développements logiciels et architectures logicielles propriétaires.</li>
                        </ul>
                        <p>
                            Toute reproduction, représentation ou exploitation non autorisée de ces éléments peut constituer une contrefaçon et est interdite sans notre accord préalable écrit.
                        </p>
                        <p class="text-xs text-slate-400 italic">
                            Note : Les technologies tierces intégrées (telles que Meta, WhatsApp, OpenAI, ElevenLabs, n8n, Google et Microsoft) ainsi que les librairies open source restent la propriété exclusive de leurs éditeurs et détenteurs respectifs.
                        </p>
                    </section>

                    <!-- 6. Données et contenus utilisateurs -->
                    <section id="user-data" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">6. Contenus et données des utilisateurs</h2>
                        <p>
                            NONALIX IA étant une plateforme SaaS B2B, les entreprises utilisatrices importent sous leur responsabilité exclusive les contenus nécessaires à l'apprentissage et à l'exploitation de leurs agents (documents textuels, listes de prestations, FAQs, informations clients, etc.).
                        </p>
                        <p>
                            L'utilisation du service ne transfère pas à NONALIX CI SARL la propriété des données ou contenus appartenant aux clients. L'utilisateur certifie qu'il détient tous les droits et autorisations nécessaires pour importer et faire traiter ses documents par la plateforme.
                        </p>
                        <p>
                            Pour en savoir plus sur le traitement des données et les responsabilités, veuillez consulter notre 
                            <Link href="/confidentialite" class="text-brand-600 hover:underline">Politique de confidentialité</Link> 
                            et nos 
                            <Link href="/conditions-utilisation" class="text-brand-600 hover:underline">Conditions Générales d'Utilisation</Link>.
                        </p>
                    </section>

                    <!-- 7. Intelligence artificielle -->
                    <section id="ai" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">7. Intelligence artificielle et automatisation</h2>
                        <p>
                            La plateforme NONALIX IA intègre des modèles de langage avancés pour automatiser les interactions clients (analyse de messages, extraction de prospects, rédaction et classification).
                        </p>
                        <blockquote class="border-l-4 border-amber-300 bg-amber-50/40 p-3 text-xs text-slate-700 dark:border-amber-700 dark:bg-amber-950/10 dark:text-slate-300">
                            <strong>Avertissement important :</strong> Les résultats et rédactions générés automatiquement par des systèmes d'intelligence artificielle peuvent contenir des inexactitudes ou des erreurs. L'utilisateur est invité à conserver un contrôle humain ou à utiliser les modes de réponses supervisés (Mode Assisté) lorsqu'un niveau de fiabilité absolu est requis.
                        </blockquote>
                        <p>
                            Les réponses fournies par l'agent IA n'ont pas vocation à se substituer à une expertise humaine qualifiée dans des secteurs spécialisés ou réglementés, notamment les domaines juridique, médical, financier ou fiscal.
                        </p>
                    </section>

                    <!-- 8. Services et technologies tiers -->
                    <section id="third-party" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">8. Services et technologies tiers</h2>
                        <p>
                            Pour exécuter ses fonctionnalités, NONALIX IA interagit avec plusieurs API de tiers externes, notamment :
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2">
                            <li><strong>OpenAI / Google / Anthropic</strong> : Pour les moteurs de génération linguistique et les calculs d'embeddings vectoriels ;</li>
                            <li><strong>Meta Platforms</strong> : Pour l'envoi et la réception de messages via l'API Cloud WhatsApp Business ;</li>
                            <li><strong>n8n</strong> : Pour la coordination et l'exécution d'automatisations de workflows.</li>
                        </ul>
                        <p>
                            Les intégrations et marques tierces demeurent la propriété exclusive de leurs créateurs. Leur liaison n'implique aucune approbation ou affiliation contractuelle directe de Nonalix CI avec ces entités en dehors des abonnements standards d'API.
                        </p>
                    </section>

                    <!-- 9. Responsabilité -->
                    <section id="responsibility" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">9. Responsabilité</h2>
                        <p>
                            NONALIX CI SARL s'engage à fournir un service de qualité et à sécuriser au mieux les accès à la plateforme. Toutefois, la responsabilité de NONALIX CI SARL est limitée au cadre contractuel convenu et ne saurait être engagée pour des dysfonctionnements résultant de pannes réseau globales, de suspensions de services tiers (ex: pannes des serveurs OpenAI ou WhatsApp Meta), ou d'erreurs de configuration imputables à l'utilisateur.
                        </p>
                        <p>
                            Toute clause de limitation ou d'exclusion de responsabilité est rédigée et applicable dans les limites autorisées par la législation en vigueur.
                        </p>
                    </section>

                    <!-- 10. Disponibilité du service -->
                    <section id="availability" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">10. Disponibilité du service</h2>
                        <p>
                            Bien que nous nous efforions de maintenir une plateforme opérationnelle en continu (24h/24 et 7j/7), la disponibilité de NONALIX IA peut être suspendue temporairement pour des raisons de maintenance technique, de déploiement de mises à jour de sécurité, ou d'indisponibilité de dépendances externes.
                        </p>
                    </section>

                    <!-- 11. Sécurité -->
                    <section id="security" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">11. Sécurité</h2>
                        <p>
                            NONALIX CI SARL met en place des mesures techniques et organisationnelles conformes aux standards professionnels pour préserver l'intégrité de vos comptes, le chiffrement des clés d'API configurées, et l'étanchéité des données entre les différents comptes clients (tenants).
                        </p>
                    </section>

                    <!-- 12. Protection des données -->
                    <section id="personal-data" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">12. Protection des données personnelles</h2>
                        <p>
                            NONALIX CI SARL applique les règles nationales de protection de la vie privée, notamment la <strong>Loi n° 2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel</strong> en Côte d'Ivoire.
                        </p>
                        <p>
                            Toutes les finalités de collecte, les durées de stockage de vos historiques de chat et les droits d'accès/de modification de vos contacts sont détaillés dans notre
                            <Link href="/confidentialite" class="text-brand-600 hover:underline">Politique de confidentialité</Link>.
                        </p>
                    </section>

                    <!-- 13. Cookies et traceurs -->
                    <section id="cookies" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">13. Cookies et traceurs</h2>
                        <p>
                            La plateforme utilise des cookies fonctionnels strictement nécessaires pour assurer l'authentification sécurisée des utilisateurs, maintenir les sessions actives et mémoriser les préférences d'affichage (ex: choix de l'agent actif). Aucun cookie de ciblage publicitaire tiers n'est utilisé.
                        </p>
                    </section>

                    <!-- 14. Liens externes -->
                    <section id="external-links" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">14. Liens externes</h2>
                        <p>
                            La plateforme peut contenir des liens vers des sites ou des outils édités par des tiers. Nous ne saurions être tenus responsables des politiques de confidentialité ou du contenu édité sur ces plateformes extérieures.
                        </p>
                    </section>

                    <!-- 15. Utilisation licite -->
                    <section id="fair-use" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">15. Utilisation de la plateforme</h2>
                        <p>
                            Les utilisateurs s'engagent à faire un usage licite de NONALIX IA, respectueux des dispositions des CGU. Sont formellement interdites les activités de fraude, d'usurpation d'identité, de spamming commercial non consenti, d'intrusion réseau, ou d'hébergement de données illégales au sein de la base de connaissances.
                        </p>
                    </section>

                    <!-- 16. Droit applicable -->
                    <section id="law" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">16. Droit applicable</h2>
                        <p>
                            Les présentes mentions légales sont régies par le droit ivoirien, sous réserve des règles et conventions internationales impératives applicables selon la localisation géographique de l'utilisateur.
                        </p>
                    </section>

                    <!-- 17. Contact -->
                    <section id="contact" class="scroll-mt-24 space-y-3">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">17. Contact</h2>
                        <p>
                            Pour toute question ou demande de renseignements juridiques, écrivez à :
                        </p>
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50 space-y-1 text-xs">
                            <p><strong>NONALIX CI SARL</strong></p>
                            <p>Abidjan, Cocody Angré, Pétro Ivoire</p>
                            <p>Près de BAOBAB Microfinance, Îlot 332, Lot 4172</p>
                            <p>Côte d’Ivoire</p>
                            <p class="pt-1"><strong>E-mail :</strong> <a href="mailto:contact@nonalixia.com" class="text-brand-600 hover:underline">contact@nonalixia.com</a> (Privilégiée)</p>
                            <p><strong>Téléphone :</strong> +225 05 66 36 03 03</p>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </MarketingLayout>
</template>
