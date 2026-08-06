<?php

use App\Http\Controllers\Admin\AccessCodeController;
use App\Http\Controllers\Admin\AccessRequestController as AdminAccessRequestController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\App\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\IncidentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlatformKeyController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UsageController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Super-administration NONALIX — admin.nonalixia.com
|------------------------------------------------------------------------------
| Réservé aux utilisateurs `is_super_admin` (tenant_id = null). Le middleware
| `tenant` est volontairement ABSENT : ces routes travaillent en transversal
| et lisent explicitement, par requête, les données qu'elles ont besoin de voir.
|
| Toute consultation de données client passe soit par une agrégation, soit par
| une impersonation tracée — jamais par une désactivation silencieuse du scope.
*/

// La déconnexion doit exister sur CE domaine aussi : le bouton de
// l'administration poste en relatif, et sans cette route il tombait sur un 404.
// Hors du groupe `super-admin` à dessein — pouvoir fermer sa session ne doit
// dépendre d'aucun privilège, y compris pour un compte en état incohérent.
Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('admin.logout');

Route::middleware(['auth', '2fa', 'super-admin'])->group(function () {

    Route::get('/', DashboardController::class)->name('admin.dashboard');

    // --- Entreprises clientes --------------------------------------------------
    Route::resource('tenants', TenantController::class)
        ->except(['create', 'edit'])
        ->names('admin.tenants');

    // Une invitation se perd ou expire : sans ce renvoi, la seule issue
    // serait de recréer l'entreprise.
    Route::post('tenants/{tenant}/resend-invitation', [TenantController::class, 'resendInvitation'])
        ->name('admin.tenants.resend-invitation');

    Route::post('tenants/{tenant}/suspend',   [TenantController::class, 'suspend'])->name('admin.tenants.suspend');
    Route::post('tenants/{tenant}/reactivate', [TenantController::class, 'reactivate'])->name('admin.tenants.reactivate');
    Route::put('tenants/{tenant}/plan',        [TenantController::class, 'changePlan'])->name('admin.tenants.plan');
    Route::put('tenants/{tenant}/quotas',      [TenantController::class, 'overrideQuotas'])->name('admin.tenants.quotas');

    // --- Impersonation (support) -----------------------------------------------
    // Durée limitée, motif obligatoire, journalisée à l'entrée et à la sortie.
    Route::post('tenants/{tenant}/impersonate', [ImpersonationController::class, 'start'])
        ->name('admin.impersonate.start');
    Route::delete('impersonate', [ImpersonationController::class, 'stop'])
        ->name('admin.impersonate.stop');

    // --- Catalogue commercial ---------------------------------------------------
    Route::resource('plans', PlanController::class)
        ->except(['create', 'edit'])
        ->names('admin.plans');

    // --- Demandes d'accès -------------------------------------------------------
    // File d'entrée du parcours commercial : approuver génère le code ET
    // l'envoie, pour qu'aucun code ne reste émis sans destinataire.
    Route::get('access-requests', [AdminAccessRequestController::class, 'index'])
        ->name('admin.access-requests.index');
    Route::post('access-requests/{accessRequest}/approve', [AdminAccessRequestController::class, 'approve'])
        ->name('admin.access-requests.approve');
    Route::post('access-requests/{accessRequest}/reject', [AdminAccessRequestController::class, 'reject'])
        ->name('admin.access-requests.reject');
    Route::post('access-requests/{accessRequest}/resend', [AdminAccessRequestController::class, 'resend'])
        ->name('admin.access-requests.resend');

    // --- Codes d'accès ----------------------------------------------------------
    // Seul levier d'ouverture de compte : sans code émis ici, personne ne peut
    // créer d'entreprise sur la plateforme.
    Route::get('access-codes',  [AccessCodeController::class, 'index'])->name('admin.access-codes.index');
    Route::post('access-codes', [AccessCodeController::class, 'store'])->name('admin.access-codes.store');

    // Révocation et non suppression : le code doit rester lisible dans le
    // journal d'audit et dans les consommations déjà enregistrées.
    Route::post('access-codes/{accessCode}/revoke', [AccessCodeController::class, 'revoke'])
        ->name('admin.access-codes.revoke');

    // --- Cles IA de la plateforme -----------------------------------------------
    // Elles ne vivaient que dans le .env : un exploitant sans acces SSH ne
    // pouvait en renseigner aucune.
    Route::get('platform-keys',       [PlatformKeyController::class, 'index'])->name('admin.platform-keys.index');
    Route::post('platform-keys',      [PlatformKeyController::class, 'update'])->name('admin.platform-keys.update');
    Route::post('platform-keys/test', [PlatformKeyController::class, 'test'])->name('admin.platform-keys.test');

    // --- Exploitation -----------------------------------------------------------
    Route::get('usage',              UsageController::class)->name('admin.usage');
    Route::get('incidents',          [IncidentController::class, 'index'])->name('admin.incidents.index');
    Route::post('incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('admin.incidents.resolve');
    Route::get('audit-logs',         [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
});
