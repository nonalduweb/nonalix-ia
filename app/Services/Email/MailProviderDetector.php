<?php

declare(strict_types=1);

namespace App\Services\Email;

/**
 * Reconnaît le fournisseur de messagerie d'un domaine, d'après ses MX.
 *
 * Sert à une seule chose, mais elle compte : afficher au client le chemin de
 * clics de SON fournisseur plutôt qu'une notice générique. Un restaurateur ne
 * lira jamais « consultez la documentation de votre hébergeur » ; il suivra
 * « Gmail › Paramètres › Transfert › Ajouter une adresse ».
 *
 * L'échec est prévu et sans gravité : un domaine injoignable ou un fournisseur
 * inconnu renvoient des consignes génériques, et la vérification par message
 * sonde reste le juge de paix.
 */
class MailProviderDetector
{
    /**
     * Empreintes cherchées dans les serveurs MX, du plus précis au plus large.
     *
     * @var array<string, array{name: string, steps: array<int, string>, doc: ?string}>
     */
    private const PROVIDERS = [
        'google' => [
            'name'  => 'Gmail / Google Workspace',
            'steps' => [
                'Ouvrez Gmail, puis la roue dentée en haut à droite › « Voir tous les paramètres ».',
                'Onglet « Transfert et POP/IMAP » › bouton « Ajouter une adresse de transfert ».',
                'Collez l\'adresse ci-dessus, puis validez.',
                'Google envoie un code de confirmation à cette adresse : revenez ici et cliquez sur « Vérifier », nous le recevrons pour vous.',
                'De retour dans Gmail, cochez « Transférer une copie des messages reçus ».',
            ],
            'doc' => 'https://support.google.com/mail/answer/10957',
        ],
        'outlook' => [
            'name'  => 'Microsoft 365 / Outlook',
            'steps' => [
                'Ouvrez Outlook sur le web › roue dentée › « Courrier » › « Transfert ».',
                'Cochez « Activer le transfert » et collez l\'adresse ci-dessus.',
                'Cochez « Conserver une copie des messages transférés », puis enregistrez.',
            ],
            'doc' => 'https://support.microsoft.com/fr-fr/office/f30b2f2f-1b1c-4b13-8b0d-8e0ba6a0a0e6',
        ],
        'ovh' => [
            'name'  => 'OVH',
            'steps' => [
                'Espace client OVH › « Web Cloud » › « E-mails » › votre domaine.',
                'Onglet « Redirections » › « Ajouter une redirection ».',
                'Indiquez votre adresse en source et collez l\'adresse ci-dessus en destination.',
                'Cochez « Conserver une copie » si vous voulez garder les messages dans votre boîte.',
            ],
            'doc' => null,
        ],
        'titan' => [
            'name'  => 'Hostinger / Titan Mail',
            'steps' => [
                'hPanel Hostinger › « E-mails » › votre domaine › « Transfert d\'e-mails ».',
                'Ajoutez une redirection vers l\'adresse ci-dessus.',
            ],
            'doc' => null,
        ],
        'zoho' => [
            'name'  => 'Zoho Mail',
            'steps' => [
                'Zoho Mail › Paramètres › « Transfert et POP/IMAP ».',
                'Ajoutez l\'adresse ci-dessus comme destination de transfert.',
            ],
            'doc' => null,
        ],
        'ionos' => [
            'name'  => 'IONOS',
            'steps' => [
                'Espace client IONOS › « E-mail » › votre adresse › « Transfert ».',
                'Collez l\'adresse ci-dessus et enregistrez.',
            ],
            'doc' => null,
        ],
        'gandi' => [
            'name'  => 'Gandi',
            'steps' => [
                'Admin Gandi › votre domaine › « Boîtes e-mail » › « Transferts ».',
                'Créez un transfert vers l\'adresse ci-dessus.',
            ],
            'doc' => null,
        ],
        'infomaniak' => [
            'name'  => 'Infomaniak',
            'steps' => [
                'Manager Infomaniak › « Service Mail » › votre adresse › « Redirections ».',
                'Ajoutez l\'adresse ci-dessus.',
            ],
            'doc' => null,
        ],
    ];

    /** @return array{provider: ?string, name: string, steps: array<int, string>, doc: ?string, mx: array<int, string>} */
    public function detect(string $email): array
    {
        $domain = $this->domainOf($email);
        $mx     = $domain === null ? [] : $this->mxRecords($domain);
        $joined = mb_strtolower(implode(' ', $mx));

        foreach (self::PROVIDERS as $needle => $provider) {
            if ($joined !== '' && str_contains($joined, $needle)) {
                return [
                    'provider' => $needle,
                    'name'     => $provider['name'],
                    'steps'    => $provider['steps'],
                    'doc'      => $provider['doc'],
                    'mx'       => $mx,
                ];
            }
        }

        return [
            'provider' => null,
            'name'     => 'Votre fournisseur de messagerie',
            'steps'    => [
                'Ouvrez l\'administration de votre messagerie, là où vous gérez vos adresses.',
                'Cherchez « Redirection », « Transfert » ou « Forwarding ».',
                'Créez une règle qui envoie une copie de vos messages vers l\'adresse ci-dessus.',
            ],
            'doc' => null,
            'mx'  => $mx,
        ];
    }

    private function domainOf(string $email): ?string
    {
        $at = mb_strrpos($email, '@');

        return $at === false ? null : mb_strtolower(trim(mb_substr($email, $at + 1)));
    }

    /** @return array<int, string> */
    private function mxRecords(string $domain): array
    {
        // Un domaine sans MX, un DNS lent ou coupé : ce n'est pas une erreur,
        // c'est un cas ordinaire. Les consignes génériques prennent le relais.
        try {
            $records = @dns_get_record($domain, DNS_MX);
        } catch (\Throwable) {
            return [];
        }

        if ($records === false || $records === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $record) => $record['target'] ?? null,
            $records,
        )));
    }
}
