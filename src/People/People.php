<?php

declare(strict_types=1);

namespace Simplify\Core\People;

use App\Models\Role;
use App\Models\User;
use App\Services\Users\UserCreationService;
use App\Services\Users\UserUpdateService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * People and roles, built on Pelican's own users and roles so admin permissions and other plugins keep working.
 *
 * Rules, enforced here and not only on the page:
 * - only root admins manage people and roles;
 * - nobody removes their own root access, and the last root admin keeps it and cannot be deleted;
 * - other people's passwords are never set or shown: new people and resets get an email link;
 * - only permissions Pelican knows are stored, and the Root Admin role is locked.
 */
final class People
{
    public static function authorize(): void
    {
        abort_unless((bool) user()?->isRootAdmin(), 403);
    }

    /** @return array<string, array<string, string>> Pelican's admin permissions as model => ["prefix model" => label] */
    public static function permissionGroups(): array
    {
        $groups = [];
        foreach (Role::getPermissionList() as $model => $prefixes) {
            foreach ($prefixes as $prefix) {
                $groups[$model][$prefix.' '.$model] = ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $prefix) ?? $prefix));
            }
        }

        return $groups;
    }

    /** @param array{username?: mixed, email?: mixed, roles?: mixed} $data */
    public static function createUser(array $data): User
    {
        self::authorize();
        $data = self::validateUser($data, null);

        return DB::transaction(function () use ($data): User {
            // No password: Pelican makes a random one, and the person chooses their own through the email link.
            $user = app(UserCreationService::class)->handle(['username' => $data['username'], 'email' => $data['email']]);
            self::syncRoles($user, $data['roles']);

            return $user;
        });
    }

    /** @param array{username?: mixed, email?: mixed, roles?: mixed} $data */
    public static function updateUser(User $target, array $data): User
    {
        self::authorize();
        $data = self::validateUser($data, $target);

        return DB::transaction(function () use ($target, $data): User {
            app(UserUpdateService::class)->handle($target, ['username' => $data['username'], 'email' => $data['email']]);
            self::syncRoles($target, $data['roles']);

            return $target->refresh();
        });
    }

    public static function deleteUser(User $target): void
    {
        self::authorize();
        if ((int) $target->id === (int) user()->id) {
            throw ValidationException::withMessages(['user' => __('simplify::people.errors.delete_self')]);
        }
        if ($target->isRootAdmin() && self::rootAdminCount() <= 1) {
            throw ValidationException::withMessages(['user' => __('simplify::people.errors.last_root')]);
        }
        if ($target->servers()->exists()) {
            throw ValidationException::withMessages(['user' => __('simplify::people.errors.owns_servers')]);
        }
        $target->delete();
    }

    /** Emails a reset link; the password itself never passes through here. */
    public static function sendPasswordReset(User $target): string
    {
        self::authorize();

        return Password::broker(Filament::getPanel('app')->getAuthPasswordBroker())->sendResetLink(['email' => $target->email]);
    }

    /** @param list<string> $permissions Pelican admin permissions such as "update server" */
    public static function saveRole(?Role $role, string $name, array $permissions): Role
    {
        self::authorize();
        $name = trim($name);
        if ($role?->isRootAdmin() || strcasecmp($name, Role::ROOT_ADMIN) === 0) {
            throw ValidationException::withMessages(['name' => __('simplify::people.errors.root_role')]);
        }
        validator(['name' => $name], ['name' => ['required', 'string', 'max:64', Rule::unique('roles', 'name')->ignore($role?->id)]])->validate();
        $known = array_merge([], ...array_values(array_map('array_keys', self::permissionGroups())));
        $permissions = array_values(array_intersect(array_unique(array_map('strval', $permissions)), $known));

        return DB::transaction(function () use ($role, $name, $permissions): Role {
            $role ??= new Role(['guard_name' => Role::DEFAULT_GUARD_NAME]);
            $role->name = $name;
            $role->guard_name = Role::DEFAULT_GUARD_NAME;
            $role->save();
            $role->syncPermissions(array_map(
                fn (string $permission) => Permission::firstOrCreate(['name' => $permission, 'guard_name' => Role::DEFAULT_GUARD_NAME]),
                $permissions,
            ));

            return $role;
        });
    }

    public static function deleteRole(Role $role): void
    {
        self::authorize();
        if ($role->isRootAdmin()) {
            throw ValidationException::withMessages(['role' => __('simplify::people.errors.root_role')]);
        }
        $role->delete();
    }

    /** @return array{username: string, email: string, roles: list<int>} */
    private static function validateUser(array $data, ?User $target): array
    {
        $valid = validator($data, [
            'username' => ['required', 'string', 'between:1,255', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('users', 'username')->ignore($target?->id)],
            'email' => ['required', 'email', 'between:1,255', Rule::unique('users', 'email')->ignore($target?->id)],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ], ['username.regex' => __('simplify::people.errors.username')])->validate();
        $valid['roles'] = array_values(array_unique(array_map('intval', $valid['roles'] ?? [])));

        return $valid;
    }

    /** @param list<int> $roleIds */
    private static function syncRoles(User $target, array $roleIds): void
    {
        $wasRoot = $target->isRootAdmin();
        $staysRoot = in_array((int) Role::getRootAdmin()->id, $roleIds, true);
        if ($wasRoot && ! $staysRoot) {
            if ((int) $target->id === (int) user()->id) {
                throw ValidationException::withMessages(['roles' => __('simplify::people.errors.own_root')]);
            }
            if (self::rootAdminCount() <= 1) {
                throw ValidationException::withMessages(['roles' => __('simplify::people.errors.last_root')]);
            }
        }
        $target->syncRoles(Role::query()->whereIn('id', $roleIds)->get());
    }

    private static function rootAdminCount(): int
    {
        return (int) Role::getRootAdmin()->users()->count();
    }
}
