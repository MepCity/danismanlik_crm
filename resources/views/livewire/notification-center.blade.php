<div class="notification-center" x-data="{ open: @entangle('isOpen') }" @click.outside="open = false; $wire.close()">
    {{-- Notification Bell Trigger Button --}}
    <button
        type="button"
        wire:click="toggleOpen"
        class="notification-center-trigger"
        aria-label="{{ __('collaboration.notification_center.title') }}"
        :aria-expanded="open.toString()"
    >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>

        @if($unreadCount > 0)
            <span class="notification-center-badge">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown / Popover --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        style="display: none;"
        class="notification-center-dropdown"
    >
        {{-- Header --}}
        <div class="notification-center-header">
            <div class="flex items-center gap-2">
                <span class="notification-center-title">{{ __('collaboration.notification_center.title') }}</span>
                @if($unreadCount > 0)
                    <span class="notification-center-pill">
                        {{ __('collaboration.notification_center.new_count', ['count' => $unreadCount]) }}
                    </span>
                @endif
            </div>

            @if($unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    class="notification-center-action-link"
                >
                    {{ __('collaboration.notification_center.mark_all_read') }}
                </button>
            @endif
        </div>

        {{-- Body / List --}}
        <div class="notification-center-list">
            @forelse($notifications as $notification)
                <div
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="openNotification({{ $notification->id }})"
                    class="notification-center-item {{ $notification->read_at === null ? 'notification-center-item--unread' : '' }}"
                >
                    {{-- Status Indicator --}}
                    <div class="mt-1 flex-shrink-0">
                        <span class="notification-center-dot {{ $notification->read_at === null ? 'notification-center-dot--unread' : 'notification-center-dot--read' }}"></span>
                    </div>

                    {{-- Content --}}
                    <div class="notification-center-content">
                        <p class="notification-center-content__title {{ $notification->read_at === null ? 'notification-center-content__title--unread' : '' }}">
                            {{ $notification->title }}
                        </p>
                        <p class="notification-center-content__body">
                            {{ $notification->body }}
                        </p>
                        <span class="notification-center-content__time">
                            {{ $notification->created_at->diffForHumans() }}
                        </span>
                    </div>

                    {{-- Quick Mark as Read Action --}}
                    @if($notification->read_at === null)
                        <button
                            type="button"
                            wire:click.stop="markAsRead({{ $notification->id }})"
                            title="{{ __('collaboration.notification_center.mark_as_read') }}"
                            class="notification-center-check"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </button>
                    @endif
                </div>
            @empty
                <div class="notification-center-empty">
                    <svg class="notification-center-empty__icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    <p class="notification-center-empty__text">{{ __('collaboration.notification_center.empty') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
