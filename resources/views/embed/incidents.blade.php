{{-- The incidents of the last days, newest first: the admin can add a short note (plain text, shown on the status
     page) and mark one as Planned. The state is incident id => {note, planned}, saved with Save. --}}
@php($t = 'simplify::embed.settings.')
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($incidents === [])
        <p class="se-muted">{{ __($t.'incidents_none') }}</p>
    @else
        <ol class="se-incidents">
            @foreach ($incidents as $incident)
                <li class="se-incident">
                    <div class="se-incident-head">
                        <span @class(['se-incident-tag', 'is-open' => $incident['ended'] === null, 'is-planned' => $incident['planned']])>
                            {{ __($t.($incident['planned'] ? 'incident_planned' : ($incident['ended'] === null ? 'incident_open' : 'incident_resolved'))) }}
                        </span>
                        <span class="se-incident-name">{{ $incident['name'] }}</span>
                        <span class="se-incident-time">
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($incident['started'], config('app.timezone'))->isoFormat('D MMM HH:mm') }}
                            @if ($incident['ended'] !== null)
                                · {{ \Carbon\CarbonInterval::seconds(max(60, $incident['ended'] - $incident['started']))->cascade()->forHumans(['parts' => 2, 'short' => true]) }}
                            @endif
                        </span>
                    </div>
                    <div class="se-incident-edit">
                        <input type="text" class="fi-input se-incident-note" maxlength="{{ \Simplify\Core\Embed\Services\IncidentRules::NOTE }}"
                            wire:model="{{ $getStatePath() }}.{{ $incident['id'] }}.note"
                            placeholder="{{ __($t.'incident_note') }}" aria-label="{{ __($t.'incident_note_for', ['name' => $incident['name']]) }}">
                        <label class="se-incident-planned">
                            <input type="checkbox" class="fi-checkbox-input" wire:model="{{ $getStatePath() }}.{{ $incident['id'] }}.planned">
                            <span>{{ __($t.'incident_planned') }}</span>
                        </label>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-dynamic-component>
<style>
    .se-incidents { display: grid; gap: 2px; margin: 0; padding: 0; list-style: none; max-height: 420px; overflow-y: auto; }
    .se-incident { display: grid; gap: 6px; padding: 8px; border-radius: 8px; background: var(--s-raised); min-width: 0; }
    .se-incident-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; min-width: 0; }
    .se-incident-tag { font-size: 11px; font-weight: 700; border-radius: 5px; padding: 1px 6px; color: var(--s-ok); background: var(--s-surface); }
    .se-incident-tag.is-open { color: var(--s-danger); }
    .se-incident-tag.is-planned { color: var(--s-warn); }
    .se-incident-name { flex: 1; min-width: 0; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .se-incident-time { color: var(--s-muted); font-size: 12.5px; }
    .se-incident-edit { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
    .se-incident-note { flex: 1 1 220px; min-width: 0; border: 1px solid var(--s-line); border-radius: 8px; background: var(--s-surface); padding: 6px 10px; font: inherit; color: inherit; }
    .se-incident-planned { display: flex; align-items: center; gap: 6px; font-size: 13px; }
</style>
