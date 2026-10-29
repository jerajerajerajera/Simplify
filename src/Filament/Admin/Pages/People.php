<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Simplify\Core\People\People as PeopleService;

/** People and roles on one page, for root admins. Pelican's own Users and Roles pages keep working next to it. */
final class People extends Page
{
    protected static ?string $slug = 'simplify-people';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-users';

    protected static ?int $navigationSort = 2;

    protected string $view = 'simplify::pages.people';

    private const PER_PAGE = 25;

    public string $tab = 'people';

    public string $search = '';

    public int $peoplePage = 1;

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    /** Simplify's own group in Pelican's menu, shared with the add-ons (ADDONS.md, "Admin menu"). */
    public static function getNavigationGroup(): ?string
    {
        return 'Simplify';
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify::people.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('simplify::people.title');
    }

    public function mount(): void
    {
        $this->tab = request()->query('tab') === 'roles' ? 'roles' : 'people';
    }

    public function updatedSearch(): void
    {
        $this->peoplePage = 1;
    }

    public function updatedTab(): void
    {
        $this->tab = $this->tab === 'roles' ? 'roles' : 'people';
    }

    public function setPage(int $page): void
    {
        $this->peoplePage = max(1, $page);
    }

    /** @return array{rows: list<array<string, mixed>>, total: int, pages: int} */
    public function people(): array
    {
        $query = User::query()->with('roles:id,name')->withCount('servers')->orderBy('username');
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($where) => $where->where('username', 'like', $like)->orWhere('email', 'like', $like));
        }
        $total = (clone $query)->count();
        $pages = (int) ceil(max(1, $total) / self::PER_PAGE);
        $this->peoplePage = max(1, min($this->peoplePage, $pages));
        $rows = $query->forPage($this->peoplePage, self::PER_PAGE)->get()->map(fn (User $user) => [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->all(),
            'servers' => $user->servers_count,
            'mfa' => filled($user->mfa_app_secret) || (bool) $user->mfa_email_enabled,
            'self' => (int) $user->id === (int) user()?->id,
        ])->all();

        return ['rows' => $rows, 'total' => $total, 'pages' => $pages];
    }

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        return Role::query()->withCount(['users', 'permissions'])
            ->orderByRaw('name = ? desc', [Role::ROOT_ADMIN])->orderBy('name')->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'root' => $role->isRootAdmin(),
                'members' => $role->users_count,
                'permissions' => $role->permissions_count,
            ])->all();
    }

    public function addPersonAction(): Action
    {
        return Action::make('addPerson')->label(__('simplify::people.add'))->icon('tabler-user-plus')
            ->modalHeading(__('simplify::people.add'))->modalDescription(__('simplify::people.add_help'))
            ->schema($this->personFields())->modalSubmitActionLabel(__('simplify::people.add_submit'))
            ->action(fn (array $data) => $this->guarded(fn () => PeopleService::createUser($data), __('simplify::people.added')));
    }

    public function editPersonAction(): Action
    {
        return Action::make('editPerson')->modalHeading(__('simplify::people.edit'))
            ->schema($this->personFields())->modalSubmitActionLabel(__('simplify::people.save'))
            ->fillForm(function (array $arguments): array {
                $user = User::query()->findOrFail((int) ($arguments['user'] ?? 0));

                return ['username' => $user->username, 'email' => $user->email, 'roles' => $user->roles()->pluck('id')->all()];
            })
            ->action(fn (array $data, array $arguments) => $this->guarded(
                fn () => PeopleService::updateUser(User::query()->findOrFail((int) $arguments['user']), $data),
                __('simplify::people.saved'),
            ));
    }

    public function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')->requiresConfirmation()
            ->modalHeading(__('simplify::people.reset'))->modalDescription(__('simplify::people.reset_help'))
            ->modalSubmitActionLabel(__('simplify::people.reset_submit'))
            ->action(function (array $arguments): void {
                $status = PeopleService::sendPasswordReset(User::query()->findOrFail((int) $arguments['user']));
                $sent = $status === Password::RESET_LINK_SENT;
                $notification = Notification::make()->title(__($sent ? 'simplify::people.reset_sent' : 'simplify::people.reset_failed'));
                ($sent ? $notification->success() : $notification->body(__($status))->warning())->send();
            });
    }

    public function deletePersonAction(): Action
    {
        return Action::make('deletePerson')->requiresConfirmation()->color('danger')
            ->modalHeading(__('simplify::people.delete'))->modalDescription(__('simplify::people.delete_help'))
            ->modalSubmitActionLabel(__('simplify::people.delete_submit'))
            ->action(fn (array $arguments) => $this->guarded(
                fn () => PeopleService::deleteUser(User::query()->findOrFail((int) $arguments['user'])),
                __('simplify::people.deleted'),
            ));
    }

    public function addRoleAction(): Action
    {
        return Action::make('addRole')->label(__('simplify::people.roles.add'))->icon('tabler-shield-plus')->slideOver()
            ->modalHeading(__('simplify::people.roles.add'))->modalSubmitActionLabel(__('simplify::people.roles.add'))
            ->schema($this->roleFields())
            ->action(fn (array $data) => $this->guarded(fn () => PeopleService::saveRole(null, ...$this->roleData($data)), __('simplify::people.roles.added')));
    }

    public function editRoleAction(): Action
    {
        return Action::make('editRole')->slideOver()->schema($this->roleFields())
            ->modalHeading(__('simplify::people.roles.edit'))->modalSubmitActionLabel(__('simplify::people.save'))
            ->fillForm(function (array $arguments): array {
                $role = Role::query()->findOrFail((int) ($arguments['role'] ?? 0));
                abort_if($role->isRootAdmin(), 403);
                $owned = $role->permissions()->pluck('name')->all();
                $data = ['name' => $role->name];
                foreach (PeopleService::permissionGroups() as $model => $options) {
                    $data['permissions_'.$model] = array_values(array_intersect(array_keys($options), $owned));
                }

                return $data;
            })
            ->action(fn (array $data, array $arguments) => $this->guarded(
                fn () => PeopleService::saveRole(Role::query()->findOrFail((int) $arguments['role']), ...$this->roleData($data)),
                __('simplify::people.saved'),
            ));
    }

    public function deleteRoleAction(): Action
    {
        return Action::make('deleteRole')->requiresConfirmation()->color('danger')
            ->modalHeading(__('simplify::people.roles.delete'))->modalDescription(__('simplify::people.roles.delete_help'))
            ->modalSubmitActionLabel(__('simplify::people.delete_submit'))
            ->action(fn (array $arguments) => $this->guarded(
                fn () => PeopleService::deleteRole(Role::query()->findOrFail((int) $arguments['role'])),
                __('simplify::people.roles.deleted'),
            ));
    }

    /** @return list<Component|Field> */
    private function personFields(): array
    {
        return [
            TextInput::make('username')->label(__('simplify::people.username'))->required()->maxLength(255)->regex('/^[A-Za-z0-9_.-]+$/'),
            TextInput::make('email')->label(__('simplify::people.email'))->email()->required()->maxLength(255),
            CheckboxList::make('roles')->label(__('simplify::people.roles.title'))->columns(2)
                ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                ->helperText(__('simplify::people.roles_help')),
        ];
    }

    /** @return list<Component|Field> */
    private function roleFields(): array
    {
        $groups = [];
        foreach (PeopleService::permissionGroups() as $model => $options) {
            $groups[] = CheckboxList::make('permissions_'.$model)
                ->label(ucfirst(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $model) ?? $model)))
                ->options($options)->columns(3)->bulkToggleable();
        }

        return [
            TextInput::make('name')->label(__('simplify::people.roles.name'))->required()->maxLength(64),
            Section::make(__('simplify::people.roles.permissions'))
                ->description(__('simplify::people.roles.permissions_help'))->schema($groups),
        ];
    }

    /** @return array{0: string, 1: list<string>} */
    private function roleData(array $data): array
    {
        $permissions = [];
        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, 'permissions_') && is_array($value)) {
                array_push($permissions, ...array_map('strval', $value));
            }
        }

        return [(string) ($data['name'] ?? ''), $permissions];
    }

    /** Shows a refused change as a notification as well as on the form. */
    private function guarded(callable $run, string $done): void
    {
        try {
            $run();
            Notification::make()->title($done)->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()->title(__('simplify::people.not_saved'))->body(collect($exception->errors())->flatten()->first())->danger()->send();
            throw $exception;
        }
    }
}
