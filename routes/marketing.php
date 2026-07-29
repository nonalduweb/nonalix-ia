<?php

use App\Http\Controllers\Marketing\AccessRequestController;
use App\Http\Controllers\Marketing\PageController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Site commercial — nonalixia.com
|------------------------------------------------------------------------------
| Public, sans authentification. Les tarifs sont lus depuis la table `plans`
| pour rester alignés avec ce qui est réellement facturé.
*/

Route::get('/',         [PageController::class, 'home'])->name('marketing.home');
Route::get('tarifs',    [PageController::class, 'pricing'])->name('marketing.pricing');

// --- Demande d'accès ----------------------------------------------------------
// Chaînon entre la visite et l'inscription : celle-ci exige un code, et rien
// ne permettait d'en demander un. Formulaire public, donc limité en débit.
Route::get('demande',  [AccessRequestController::class, 'create'])->name('marketing.access-request');
Route::post('demande', [AccessRequestController::class, 'store'])
    ->middleware('throttle:access-request')
    ->name('marketing.access-request.store');
Route::get('mentions-legales',       [PageController::class, 'legal'])->name('marketing.legal');
Route::get('confidentialite',        [PageController::class, 'privacy'])->name('marketing.privacy');
Route::get('conditions-utilisation', [PageController::class, 'terms'])->name('marketing.terms');
