<?php

declare(strict_types=1);

use App\Services\Knowledge\RecursiveTextChunker;

it('retourne un seul fragment pour un texte court', function () {
    $chunks = (new RecursiveTextChunker(900, 150))->chunk('Un texte court.');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->content)->toBe('Un texte court.')
        ->and($chunks[0]->position)->toBe(0);
});

it('découpe un texte long en plusieurs fragments ordonnés', function () {
    $text   = str_repeat('Phrase de test suffisamment longue pour remplir. ', 60);
    $chunks = (new RecursiveTextChunker(300, 50))->chunk($text);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $index => $chunk) {
        expect($chunk->position)->toBe($index)
            ->and($chunk->content)->not->toBe('');
    }
});

it('coupe en priorité aux frontières de paragraphe', function () {
    $text = str_repeat('a', 200)."\n\n".str_repeat('b', 200);

    $chunks = (new RecursiveTextChunker(250, 0))->chunk($text);

    // Un fragment ne doit pas mélanger les deux blocs : couper au milieu
    // d'une phrase dégrade l'embedding et rend la citation illisible.
    expect($chunks[0]->content)->not->toContain('b');
});

it('conserve un chevauchement entre fragments consécutifs', function () {
    $text   = implode(' ', array_map(static fn (int $i) => "mot{$i}", range(1, 400)));
    $chunks = (new RecursiveTextChunker(200, 60))->chunk($text);

    expect(count($chunks))->toBeGreaterThan(1);

    // Sans chevauchement, une information à cheval sur deux fragments devient
    // introuvable par la recherche vectorielle.
    $endOfFirst = mb_substr($chunks[0]->content, -30);
    $words      = array_filter(explode(' ', $endOfFirst));

    expect($chunks[1]->content)->toContain((string) array_shift($words));
});

it('propage les métadonnées à tous les fragments', function () {
    $chunks = (new RecursiveTextChunker(100, 10))
        ->chunk(str_repeat('texte de test. ', 40), ['source' => 'contrat.pdf', 'pages' => 3]);

    foreach ($chunks as $chunk) {
        expect($chunk->metadata['source'])->toBe('contrat.pdf')
            ->and($chunk->metadata['pages'])->toBe(3);
    }
});

it('retourne un tableau vide pour un texte vide ou blanc', function () {
    $chunker = new RecursiveTextChunker;

    expect($chunker->chunk(''))->toBe([])
        ->and($chunker->chunk("   \n\n  \t "))->toBe([]);
});

it('gère un texte sans aucun séparateur', function () {
    // Cas dégradé : données encodées, tableau compacté. La coupe brutale est
    // le seul recours, mais elle ne doit pas provoquer de boucle infinie.
    $chunks = (new RecursiveTextChunker(100, 0))->chunk(str_repeat('x', 550));

    expect(count($chunks))->toBeGreaterThanOrEqual(5);
});
