@php
    /** @var list<\App\Filament\Navigation\RailItem> $railItems */
    $railItems = \App\Filament\Navigation\RailNavigation::build();
@endphp

<div
    class="crm-rail"
    x-data="{ open: null }"
    x-on:keydown.escape.window="open = null"
    x-on:click.outside="open = null"
>
    <ul class="crm-rail__list">
        @foreach ($railItems as $railItem)
            <li class="crm-rail__item" data-rail-key="{{ $railItem->key }}">
                @if ($railItem->isFlyout())
                    <button
                        type="button"
                        class="crm-rail__control{{ $railItem->active ? ' crm-rail__control--selected' : '' }}"
                        aria-haspopup="true"
                        aria-controls="crm-rail-flyout-{{ $railItem->key }}"
                        @if ($railItem->active) aria-current="true" @endif
                        x-bind:aria-expanded="(open === '{{ $railItem->key }}').toString()"
                        x-on:click="open = (open === '{{ $railItem->key }}') ? null : '{{ $railItem->key }}'"
                    >
                        {{ \Filament\Support\generate_icon_html($railItem->icon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['crm-rail__icon'])) }}
                        <span class="crm-rail__label">{{ $railItem->label }}</span>
                    </button>

                    <div
                        id="crm-rail-flyout-{{ $railItem->key }}"
                        role="menu"
                        aria-label="{{ $railItem->label }}"
                        class="crm-rail__flyout"
                        x-bind:class="{ 'crm-rail__flyout--open': open === '{{ $railItem->key }}' }"
                        x-bind:aria-hidden="(open !== '{{ $railItem->key }}').toString()"
                    >
                        <ul class="crm-rail__flyout-list">
                            @foreach ($railItem->children as $child)
                                <li>
                                    <a
                                        {{ \Filament\Support\generate_href_html($child['url']) }}
                                        role="menuitem"
                                        class="crm-rail__flyout-link{{ $child['active'] ? ' crm-rail__flyout-link--selected' : '' }}"
                                        @if ($child['active']) aria-current="page" @endif
                                        x-on:click="open = null; window.matchMedia('(max-width: 1024px)').matches && $store.sidebar.close()"
                                    >
                                        {{ $child['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <a
                        {{ \Filament\Support\generate_href_html($railItem->url) }}
                        class="crm-rail__control{{ $railItem->active ? ' crm-rail__control--selected' : '' }}"
                        @if ($railItem->active) aria-current="page" @endif
                        x-on:click="window.matchMedia('(max-width: 1024px)').matches && $store.sidebar.close()"
                    >
                        {{ \Filament\Support\generate_icon_html($railItem->icon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['crm-rail__icon'])) }}
                        <span class="crm-rail__label">{{ $railItem->label }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</div>
