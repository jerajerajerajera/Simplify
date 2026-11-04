{{--
    The head of a page (ADDONS.md, Page head) for an add-on page with its own Blade view: title, an optional subtitle
    and the actions on the right, Save last. It is drawn as the head of a Filament section, so it looks the same as a
    page whose first Section gives its title.

    @include('simplify::partials.page-head', [
        'title' => __('my-addon::page.title'),
        'subtitle' => __('my-addon::page.subtitle'),   // optional
        'actions' => $this->saveAction,                // optional: an Htmlable (a Filament action) or a list of them
    ])
--}}
@php($actions = array_filter(is_array($actions ?? null) ? $actions : [$actions ?? null]))
<header class="fi-section-header s-page-head">
    <div class="fi-section-header-text-ctn s-page-head-text">
        <h2 class="fi-section-header-heading">{{ $title }}</h2>
        @if (filled($subtitle ?? null))
            <p class="fi-section-header-description">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($actions !== [])
        <div class="s-page-head-actions">
            @foreach ($actions as $action)
                {{ $action }}
            @endforeach
        </div>
    @endif
</header>
