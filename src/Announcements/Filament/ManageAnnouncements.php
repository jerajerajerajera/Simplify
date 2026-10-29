<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Filament;

use App\Models\Egg;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Simplify\Core\Announcements\Models\GameCommand;
use Simplify\Core\Announcements\Support\GameChat;

final class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->commandsAction(),
            CreateAction::make()
                ->icon('tabler-plus')
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => user()?->id])
                ->after(fn () => AnnouncementResource::deliverNow()),
        ];
    }

    /** The chat command per game for "Also send in game". Empty is the automatic command, "-" skips the game. */
    private function commandsAction(): Action
    {
        $text = fn (string $key): string => __('pelican-simplify::announcements.commands.'.$key);

        return Action::make('commands')
            ->label($text('action'))
            ->icon('tabler-message-chatbot')
            ->color('gray')
            ->modalHeading($text('heading'))
            ->modalDescription($text('help'))
            ->modalSubmitActionLabel(__('pelican-simplify::announcements.commands.save'))
            ->fillForm(fn (): array => collect(GameCommand::query()->pluck('command', 'egg_id'))->mapWithKeys(fn (string $command, int $egg) => ['egg_'.$egg => $command])->all())
            ->schema(fn (): array => Egg::query()->orderBy('name')->get()->map(fn (Egg $egg) => TextInput::make('egg_'.$egg->id)
                ->label($egg->name)
                ->placeholder(GameChat::defaultCommand($egg->name) ?? $text('none'))
                ->maxLength(GameChat::COMMAND_LENGTH)
                ->rule(fn () => function (string $attribute, mixed $value, $fail) use ($text): void {
                    if (filled($value) && ! GameChat::isValidCommand(trim((string) $value))) {
                        $fail($text('invalid'));
                    }
                }))->all())
            ->action(function (array $data): void {
                foreach ($data as $key => $command) {
                    $egg = (int) substr((string) $key, 4);
                    $command = trim((string) $command);
                    $command === ''
                        ? GameCommand::query()->whereKey($egg)->delete()
                        : GameCommand::query()->updateOrCreate(['egg_id' => $egg], ['command' => $command]);
                }
                Notification::make()->title(__('pelican-simplify::announcements.commands.saved'))->success()->send();
            });
    }
}
