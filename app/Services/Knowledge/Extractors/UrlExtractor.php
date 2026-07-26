<?php

declare(strict_types=1);

namespace App\Services\Knowledge\Extractors;

use App\Contracts\Knowledge\DocumentExtractor;
use App\Data\Knowledge\ExtractedDocument;
use App\Models\Document;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Extraction du contenu textuel d'une page web.
 *
 * Sécurité : l'URL est fournie par le client, donc potentiellement hostile.
 * Le schéma est restreint à http/https et les adresses privées sont refusées,
 * afin que cette fonctionnalité ne devienne pas un SSRF permettant d'atteindre
 * les services internes de l'infrastructure.
 */
class UrlExtractor implements DocumentExtractor
{
    /** Balises dont le contenu n'a aucune valeur informative. */
    private const NOISE = 'script, style, nav, header, footer, aside, noscript, iframe, form, svg';

    public function supports(Document $document): bool
    {
        return $document->source_type === 'url';
    }

    public function extract(Document $document): ExtractedDocument
    {
        $url = $document->source_url;

        if ($url === null || ! $this->isSafe($url)) {
            throw new RuntimeException('URL absente, invalide ou non autorisée.');
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'NonalixIA/1.0 (+https://nonalixia.com)'])
                ->withOptions([
                    'allow_redirects' => ['max' => 3, 'strict' => true],
                ])
                ->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException('Page inaccessible : '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("La page a répondu avec le code {$response->status()}.");
        }

        $crawler = new Crawler($response->body());

        $title = $this->firstText($crawler, 'title') ?? $document->title;

        // On retire le bruit avant extraction : menus et pieds de page
        // pollueraient chaque fragment et dégraderaient la recherche.
        $crawler->filter(self::NOISE)->each(static function (Crawler $node) {
            $dom = $node->getNode(0);
            $dom?->parentNode?->removeChild($dom);
        });

        $body = $crawler->filter('main, article, body')->first();
        $text = $body->count() > 0 ? $body->text('', true) : $crawler->text('', true);

        $text = trim(preg_replace('/\s{2,}/u', ' ', $text) ?? $text);

        if ($text === '') {
            throw new RuntimeException(
                'Aucun texte extractible. La page est probablement rendue en JavaScript.'
            );
        }

        return new ExtractedDocument($text, [
            'source' => $title,
            'url'    => $url,
        ]);
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector)->first();

        return $node->count() > 0 ? trim($node->text()) : null;
    }

    /**
     * Refuse les URL pointant vers le réseau interne.
     *
     * Sans ce contrôle, un client pourrait faire lire à la plateforme
     * http://169.254.169.254/ (métadonnées cloud) ou n'importe quel service
     * interne, et en récupérer le contenu via sa base de connaissances.
     */
    private function isSafe(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'] ?? '';

        if ($host === '') {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        // La résolution a échoué : gethostbyname renvoie le nom d'origine.
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
