<?php

use App\Http\Controllers\App\Auth\AuthenticatedSessionController;
use App\Http\Controllers\App\Auth\EmailVerificationController;
use App\Http\Controllers\App\Auth\InvitationController;
use App\Http\Controllers\App\Auth\PasswordResetController;
use App\Http\Controllers\App\Auth\RegisteredTenantController;
use App\Http\Controllers\App\Auth\TwoFactorChallengeController;
use App\Http\Controllers\App\Auth\TwoFactorSetupController;
use App\Http\Controllers\App\ContactController;
use App\Http\Controllers\App\ConversationController;
use App\Http\Controllers\App\ConversationNoteController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\KnowledgeDocumentController;
use App\Http\Controllers\App\LeadController;
use App\Http\Controllers\App\MessageController;
use App\Http\Controllers\App\Settings\AgentController;
use App\Http\Controllers\App\Settings\BusinessProfileController;
use App\Http\Controllers\App\Settings\FaqController;
use App\Http\Controllers\App\Settings\ServiceController;
use App\Http\Controllers\App\Settings\TeamUserController;
use App\Http\Controllers\App\Settings\WhatsAppAccountController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Espace client — app.nonalixia.com
|------------------------------------------------------------------------------
| Pile : session → authentification → 2FA confirmée → tenant résolu et actif.
| L'ordre compte : `tenant` doit s'exécuter après `auth`, puisqu'il lit
| l'utilisateur authentifié pour déterminer le contexte.
*/

// --- Invités ------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('login',  [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');

    Route::get('two-factor-challenge',  [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:two-factor');

    // --- Inscription d'une entreprise, sur code d'accès ------------------------
    // Fermée par défaut : sans code valide, aucun compte n'est créé. Le
    // throttling est indispensable — sans lui, le code serait devinable par
    // essais successifs.
    Route::get('register',  [RegisteredTenantController::class, 'create'])->name('register');
    Route::post('register', [RegisteredTenantController::class, 'store'])
        ->middleware('throttle:register');

    Route::post('register/check-code', [RegisteredTenantController::class, 'checkCode'])
        ->middleware('throttle:access-code')
        ->name('register.check-code');

    // --- Mot de passe oublié ---------------------------------------------------
    Route::get('forgot-password',  [PasswordResetController::class, 'requestForm'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('reset-password/{token}', [PasswordResetController::class, 'resetForm'])
        ->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset')
        ->name('password.update');

    // --- Acceptation d'une invitation ------------------------------------------
    // Séparé de la réinitialisation : l'émetteur `invitations` accorde sept
    // jours, contre soixante minutes pour un mot de passe oublié.
    Route::get('invitation/{token}', [InvitationController::class, 'show'])
        ->name('invitation.accept');
    Route::post('invitation', [InvitationController::class, 'accept'])
        ->middleware('throttle:password-reset')
        ->name('invitation.store');
});

// --- Vérification de l'adresse e-mail -----------------------------------------
// Authentifié mais PAS encore vérifié : ces routes doivent rester joignables
// avant la barrière `verified`, sinon l'utilisateur ne pourrait jamais la
// franchir. La 2FA n'est pas exigée ici non plus : on ne demande pas de
// configurer un second facteur avant d'avoir confirmé l'adresse.
Route::middleware('auth')->group(function () {
    Route::get('email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:verification')
        ->name('verification.send');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// --- Configuration de la 2FA (avant qu'elle ne soit exigée) -------------------
Route::middleware(['auth', 'verified'])->prefix('two-factor')->as('two-factor.')->group(function () {
    Route::get('setup',      [TwoFactorSetupController::class, 'show'])->name('setup');
    Route::post('enable',    [TwoFactorSetupController::class, 'enable'])->name('enable');
    Route::post('confirm',   [TwoFactorSetupController::class, 'confirm'])->name('confirm');
    Route::delete('disable', [TwoFactorSetupController::class, 'disable'])->name('disable');
});

// --- Espace authentifié --------------------------------------------------------
// `verified` précède `2fa` : confirmer l'adresse d'abord, puis le second
// facteur. Dans l'autre sens, un client se verrait imposer la configuration
// d'une application d'authentification avant même d'avoir prouvé qu'il
// contrôle l'adresse par laquelle il récupérera son compte.
Route::middleware(['auth', 'verified', '2fa', 'tenant'])->group(function () {

    Route::get('/', DashboardController::class)->name('dashboard');

    // --- Messagerie opérateur --------------------------------------------------
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::patch('conversations/{conversation}', [ConversationController::class, 'update'])->name('conversations.update');
    Route::post('conversations/{conversation}/assign', [ConversationController::class, 'assign'])->name('conversations.assign');
    Route::post('conversations/{conversation}/handover', [ConversationController::class, 'handover'])->name('conversations.handover');
    Route::post('conversations/{conversation}/resume-ai', [ConversationController::class, 'resumeAi'])->name('conversations.resume-ai');

    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('quota:messages_sent')
        ->name('conversations.messages.store');

    Route::post('conversations/{conversation}/notes', [ConversationNoteController::class, 'store'])
        ->name('conversations.notes.store');

    // --- Contacts et prospects -------------------------------------------------
    Route::resource('contacts', ContactController::class)->only(['index', 'show', 'update']);
    Route::resource('leads', LeadController::class)->only(['index', 'show', 'update']);

    // --- Base de connaissances -------------------------------------------------
    Route::resource('knowledge', KnowledgeDocumentController::class)
        ->parameters(['knowledge' => 'document'])
        ->only(['index', 'store', 'destroy']);

    Route::post('knowledge/{document}/reprocess', [KnowledgeDocumentController::class, 'reprocess'])
        ->name('knowledge.reprocess');

    // --- Configuration ---------------------------------------------------------
    Route::prefix('settings')->as('settings.')->group(function () {
        Route::get('business',   [BusinessProfileController::class, 'edit'])->name('business.edit');
        Route::put('business',   [BusinessProfileController::class, 'update'])->name('business.update');
        Route::put('hours',      [BusinessProfileController::class, 'updateHours'])->name('hours.update');

        Route::resource('services', ServiceController::class)->except(['show', 'create', 'edit']);
        Route::resource('faqs',     FaqController::class)->except(['show', 'create', 'edit']);

        Route::get('agent',  [AgentController::class, 'edit'])->name('agent.edit');
        Route::put('agent',  [AgentController::class, 'update'])->name('agent.update');
        Route::post('agent/preview', [AgentController::class, 'preview'])->name('agent.preview');

        Route::get('whatsapp',       [WhatsAppAccountController::class, 'edit'])->name('whatsapp.edit');
        Route::put('whatsapp',       [WhatsAppAccountController::class, 'update'])->name('whatsapp.update');
        Route::post('whatsapp/test', [WhatsAppAccountController::class, 'test'])->name('whatsapp.test');
        Route::post('whatsapp/sync-templates', [WhatsAppAccountController::class, 'syncTemplates'])
            ->name('whatsapp.sync-templates');

        Route::resource('users', TeamUserController::class)->except(['show', 'create', 'edit']);

        Route::post('users/{user}/resend-invitation', [TeamUserController::class, 'resendInvitation'])
            ->name('users.resend-invitation');
    });
});
