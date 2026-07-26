<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Gestion des membres de l'équipe cliente.
 *
 * Les rôles sont cloisonnés par tenant (mode « teams » de spatie/permission) :
 * attribuer le rôle `admin` ici ne donne aucun droit ailleurs.
 */
class TeamUserController
{
    /** Rôles qu'un client peut attribuer. `super-admin` en est évidemment exclu. */
    private const ASSIGNABLE_ROLES = ['owner', 'admin', 'agent', 'viewer'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', User::class), 403);

        return Inertia::render('Settings/Users', [
            'users' => User::query()
                ->ofTenant((string) $this->context->id())
                ->with('roles:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'status'        => $user->status->value,
                    'roles'         => $user->getRoleNames(),
                    'two_factor'    => $user->hasTwoFactorEnabled(),
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                ]),
            'roles' => self::ASSIGNABLE_ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', User::class), 403);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role'  => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
        ]);

        // Seul un `owner` peut créer un autre `owner`.
        abort_if(
            $validated['role'] === 'owner' && ! $request->user()->hasRole('owner'),
            403,
        );

        $user = User::create([
            'tenant_id' => $this->context->id(),
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            // Mot de passe aléatoire jamais communiqué : l'utilisateur passe
            // obligatoirement par la réinitialisation, ce qui évite de faire
            // circuler un secret par e-mail.
            'password'  => Hash::make(Str::random(48)),
            'status'    => UserStatus::Invited,
        ]);

        $user->assignRole($this->roleFor($validated['role']));

        $this->audit->log('team.user_invited', $user, [
            'after' => ['email' => $user->email, 'role' => $validated['role']],
        ]);

        // TODO Phase 1.9 : envoi de l'invitation par e-mail.
        return back()->with('success', "Invitation créée pour {$user->email}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('update', $user), 403);

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:120'],
            'role'   => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        abort_if(
            $validated['role'] === 'owner' && ! $request->user()->hasRole('owner'),
            403,
        );

        // On ne se retire pas soi-même le droit d'administrer : sinon le
        // dernier `owner` peut se verrouiller hors de sa propre entreprise.
        if ($user->id === $request->user()->id && $validated['role'] !== $user->getRoleNames()->first()) {
            return back()->withErrors(['role' => 'Vous ne pouvez pas modifier votre propre rôle.']);
        }

        $user->fill(['name' => $validated['name'], 'status' => $validated['status']])->save();
        $user->syncRoles([$this->roleFor($validated['role'])]);

        $this->audit->logUpdate('team.user_updated', $user);

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $user), 403);

        $this->audit->log('team.user_removed', $user, ['before' => ['email' => $user->email]]);

        // Soft delete : les messages et notes de cette personne restent
        // rattachés à un utilisateur identifiable dans l'historique.
        $user->delete();

        return back()->with('success', 'Utilisateur retiré.');
    }

    /** Récupère (ou crée) le rôle dans le périmètre du tenant courant. */
    private function roleFor(string $name): Role
    {
        return Role::findOrCreate($name, 'web');
    }
}
