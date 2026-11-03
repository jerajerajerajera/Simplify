<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Filament;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Simplify\Core\Embed\Services\EmbedDetails;
use Simplify\Core\Embed\Services\EmbedServers;
use Simplify\Core\Embed\Services\PublicAddress;
use Simplify\Core\Filament\FormPage;
use Simplify\Core\Overview\Artwork;
use Simplify\Core\Server\Looks;

/**
 * The server's owner (or a root admin) puts the server on the website, fills in what the website shows besides
 * Pelican's own data, and copies the code for this one server. Nothing is saved before Save.
 */
final class Website extends FormPage
{
    use WithFileUploads;

    private const T = 'simplify::embed.server.';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-code';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'website';

    /** @var TemporaryUploadedFile|null a new image for the website, stored on Save */
    public $upload = null;

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server && EmbedServers::canManage($server);
    }

    public static function getNavigationLabel(): string
    {
        return __(self::T.'title');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $details = EmbedDetails::for($this->server());
        $this->form->fill([
            'visible' => EmbedServers::visible($this->server()),
            'description' => $details['description'],
            'banner' => $details['banner'] ?? '',
            'download' => $details['download'],
            'address2' => $details['address2'],
            'position' => $details['position'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $shown = fn (Get $get) => (bool) $get('visible');

        return $schema->statePath('data')->components([
            Section::make(__(self::T.'title'))
                ->description(__(self::T.'help'))
                ->schema([
                    Toggle::make('visible')->label(__(self::T.'show'))->live(),
                    Textarea::make('description')->label(__(self::T.'description'))
                        ->placeholder(fn () => Str::limit(trim((string) $this->server()->description), 120) ?: __(self::T.'description_placeholder'))
                        ->helperText(__(self::T.'description_help'))
                        ->maxLength(EmbedDetails::DESCRIPTION)->rows(2)->autosize()->visible($shown),
                    ViewField::make('banner')->view('simplify::embed.image')->label(__(self::T.'image'))
                        ->viewData(fn () => ['choices' => $this->images(), 'pending' => $this->upload instanceof TemporaryUploadedFile ? $this->upload->temporaryUrl() : null])
                        ->helperText(__(self::T.'image_help'))->visible($shown),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('download')->label(__(self::T.'download'))
                            ->placeholder(__(self::T.'download_placeholder'))
                            ->helperText(__(self::T.'download_help'))
                            ->url()->startsWith(['https://'])->maxLength(500)->extraInputAttributes(['class' => 'font-mono']),
                        TextInput::make('address2')->label(__(self::T.'address2'))
                            ->placeholder('bedrock.example.com:19132')
                            ->helperText(__(self::T.'address2_help'))
                            ->maxLength(120)->extraInputAttributes(['class' => 'font-mono'])
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                if (is_string($value) && trim($value) !== '' && ! PublicAddress::reachable(trim($value))) {
                                    $fail(__(self::T.'address2_invalid'));
                                }
                            }),
                    ])->visible($shown),
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        TextInput::make('position')->label(__(self::T.'position'))
                            ->helperText(__(self::T.'position_help'))
                            ->integer()->minValue(0)->maxValue(9999),
                    ])->visible($shown),
                    View::make('simplify::embed.code')
                        ->viewData(fn () => ['show' => ['server' => (string) $this->server()->uuid_short]])
                        ->visible($shown),
                ]),
        ]);
    }

    /**
     * The images to pick from: the server's own banner first (stored as ''), Simplify's scenes, the banners of the
     * person's servers, and the image uploaded here before.
     *
     * @return list<array{id: string, url: string, label: string}>
     */
    public function images(): array
    {
        $server = $this->server();
        $images = [['id' => '', 'url' => Artwork::background($server, Looks::for($server)['banner']), 'label' => __(self::T.'image_server')]];
        $user = user();
        foreach ($user === null ? [] : Looks::choices($user) as $choice) {
            $images[] = ['id' => $choice['upload'] ? Looks::DIRECTORY.'/'.$choice['id'] : $choice['id'], 'url' => $choice['url'], 'label' => ''];
        }
        $own = EmbedDetails::for($server)['banner'];
        if ($own !== null && str_starts_with($own, EmbedDetails::DIRECTORY.'/')) {
            $images[] = ['id' => $own, 'url' => url('/simplify/banners/'.basename($own)), 'label' => ''];
        }

        return $images;
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        $server = $this->server();
        $banner = (string) ($state['banner'] ?? '');
        if ($this->upload instanceof TemporaryUploadedFile) {
            $this->validate(['upload' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);
            $extension = strtolower($this->upload->extension());
            $banner = (string) $this->upload->storeAs(EmbedDetails::DIRECTORY, Str::random(40).'.'.($extension === 'jpeg' ? 'jpg' : $extension), 'local');
            $this->upload = null;
        } elseif (! in_array($banner, array_column($this->images(), 'id'), true)) {
            $banner = (string) (EmbedDetails::for($server)['banner'] ?? '');
        }
        $visible = (bool) ($state['visible'] ?? false);
        EmbedServers::setVisible($server, $visible);
        EmbedDetails::save($server, [
            'description' => $state['description'] ?? null,
            'banner' => $banner === '' ? null : $banner,
            'download' => $state['download'] ?? null,
            'address2' => $state['address2'] ?? null,
            'position' => is_numeric($state['position'] ?? null) ? (int) $state['position'] : null,
        ]);
        $this->data['banner'] = EmbedDetails::for($server)['banner'] ?? '';
        Notification::make()->title(__(self::T.'saved'))->success()->send();
    }
}
