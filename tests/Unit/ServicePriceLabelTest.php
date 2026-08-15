<?php

declare(strict_types=1);

use App\Models\Service;

/*
| Le libellé produit ici est la seule forme sous laquelle un tarif atteint
| l'agent : PromptBuilder et ListServicesTool l'insèrent tel quel. Une erreur
| de conversion ne se voit donc pas dans l'interface — elle se lit dans la
| réponse envoyée au client, qui engage commercialement l'entreprise.
*/

$service = fn (array $attributes) => new Service($attributes + [
    'name'       => 'Prestation',
    'price_type' => 'fixed',
]);

it('annonce un franc CFA sans le diviser par cent', function () use ($service) {
    // 15 000 francs sont stockés tels quels : le XOF n'a pas de sous-unité.
    expect($service(['price_cents' => 15000, 'currency' => 'XOF'])->formattedPrice())
        ->toBe('15 000 F CFA');
});

it('conserve les centimes sur une devise qui en a', function () use ($service) {
    expect($service(['price_cents' => 15000, 'currency' => 'EUR'])->formattedPrice())
        ->toBe('150,00 €');
});

it('reprend le type de tarif dans le libellé', function () use ($service) {
    expect($service(['price_cents' => 5000, 'currency' => 'XOF', 'price_type' => 'from'])->formattedPrice())
        ->toBe('à partir de 5 000 F CFA')
        ->and($service(['price_cents' => 5000, 'currency' => 'XOF', 'price_type' => 'hourly'])->formattedPrice())
        ->toBe('5 000 F CFA / heure');
});

it('dit « sur devis » plutôt que d\'annoncer zéro', function () use ($service) {
    expect($service(['price_cents' => null, 'currency' => 'XOF'])->formattedPrice())
        ->toBe('sur devis')
        ->and($service(['price_cents' => 15000, 'currency' => 'XOF', 'price_type' => 'quote'])->formattedPrice())
        ->toBe('sur devis');
});
