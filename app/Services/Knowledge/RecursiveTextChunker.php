<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Data\Knowledge\Chunk;

/**
 * Découpage récursif du texte en fragments.
 *
 * Le découpage tente d'abord de couper aux frontières les plus naturelles
 * (paragraphes, puis phrases, puis mots) et ne tombe sur une coupe brutale
 * qu'en dernier recours. Un fragment qui s'arrête au milieu d'une phrase
 * produit un embedding de mauvaise qualité et une citation illisible pour le
 * contact.
 *
 * Le chevauchement conserve le contexte aux coutures : sans lui, une
 * information à cheval sur deux fragments devient introuvable par la recherche.
 */
class RecursiveTextChunker
{
    /** Du séparateur le plus structurant au moins structurant. */
    private const SEPARATORS = ["\n\n", "\n", '. ', ' ', ''];

    public function __construct(
        private readonly int $chunkSize = 900,
        private readonly int $overlap = 150,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('nonalix.knowledge.chunk_size', 900),
            (int) config('nonalix.knowledge.chunk_overlap', 150),
        );
    }

    /**
     * @param  array<string, mixed>  $metadata  propagé à chaque fragment
     * @return array<int, Chunk>
     */
    public function chunk(string $text, array $metadata = []): array
    {
        $text = $this->normalize($text);

        if ($text === '') {
            return [];
        }

        $pieces = $this->split($text, self::SEPARATORS);
        $chunks = [];
        $buffer = '';

        foreach ($pieces as $piece) {
            // Ajouter cette pièce ferait déborder : on ferme le fragment.
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($piece) > $this->chunkSize) {
                $chunks[] = $this->makeChunk($buffer, count($chunks), $metadata);
                $buffer   = $this->tail($buffer);
            }

            $buffer .= $piece;
        }

        if (trim($buffer) !== '') {
            $chunks[] = $this->makeChunk($buffer, count($chunks), $metadata);
        }

        return $chunks;
    }

    /**
     * Découpe récursivement jusqu'à ce que chaque pièce tienne dans la taille
     * cible, en descendant dans la liste des séparateurs.
     *
     * @param  array<int, string>  $separators
     * @return array<int, string>
     */
    private function split(string $text, array $separators): array
    {
        if (mb_strlen($text) <= $this->chunkSize) {
            return [$text];
        }

        $separator = array_shift($separators);

        // Plus aucun séparateur : coupe brutale à la taille cible. Cas
        // dégradé, atteint uniquement sur des textes sans espace (données
        // encodées, tableaux compactés).
        if ($separator === null || $separator === '') {
            return mb_str_split($text, $this->chunkSize);
        }

        $parts  = explode($separator, $text);
        $result = [];

        foreach ($parts as $index => $part) {
            // Le séparateur est réattaché : sans cela, la ponctuation et les
            // sauts de ligne disparaîtraient du texte reconstitué.
            $part = $index < count($parts) - 1 ? $part.$separator : $part;

            if (mb_strlen($part) > $this->chunkSize) {
                array_push($result, ...$this->split($part, $separators));

                continue;
            }

            $result[] = $part;
        }

        return array_values(array_filter($result, static fn (string $p) => $p !== ''));
    }

    /**
     * Fin du fragment précédent, reprise en tête du suivant.
     *
     * On tente de repartir d'une frontière de mot pour ne pas introduire un
     * fragment de mot au début du fragment suivant.
     */
    private function tail(string $buffer): string
    {
        if ($this->overlap <= 0) {
            return '';
        }

        $tail  = mb_substr($buffer, -$this->overlap);
        $space = mb_strpos($tail, ' ');

        return $space === false ? $tail : mb_substr($tail, $space + 1);
    }

    /** @param array<string, mixed> $metadata */
    private function makeChunk(string $content, int $position, array $metadata): Chunk
    {
        $content = trim($content);

        return new Chunk(
            content: $content,
            position: $position,
            // Estimation : ~4 caractères par token en français. Suffisant pour
            // dimensionner les lots, un décompte exact exigerait un tokenizer
            // par modèle et n'apporterait rien ici.
            tokens: (int) ceil(mb_strlen($content) / 4),
            metadata: $metadata,
        );
    }

    /** Normalise les fins de ligne et supprime les espaces superflus. */
    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
