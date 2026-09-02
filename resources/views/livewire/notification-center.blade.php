<div class="relative inline-flex items-center" x-data="{ open: @entangle('isOpen') }" @click.outside="open = false; $wire.close()">
    {{-- Notification Bell Trigger Button --}}
    <button
        type="button"
        wire:click="toggleOpen"
        class="relative flex items-center justify-center w-9 h-9 rounded-full text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition focus:outline-none focus:ring-2 focus:ring-primary-500/50"
        aria-label="Bildirimler"
        :aria-expanded="open.toString()"
    >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>

        @if($unreadCount > 0)
            <span class="absolute top-1 right-1 flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 text-[10px] font-bold text-white bg-emerald-600 dark:bg-emerald-500 rounded-full tabular-nums shadow-sm ring-2 ring-white dark:ring-zinc-900">
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
        class="absolute right-0 top-full mt-2 w-80 sm:w-96 max-h-[30rem] bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-zinc-200 dark:border-zinc-800 z-50 flex flex-col overflow-hidden text-sm"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/50">
            <div class="flex items-center gap-2">
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">Bildirimler</span>
                @if($unreadCount > 0)
                    <span class="px-1.5 py-0.5 text-xs font-medium text-emerald-700 bg-emerald-100 dark:text-emerald-300 dark:bg-emerald-950/60 rounded-full tabular-nums">
                        {{ $unreadCount }} yeni
                    </span>
                @endif
            </div>

            @if($unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    class="text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 font-medium hover:underline transition focus:outline-none"
                >
                    Tümünü okundu işaretle
                </button>
            @endif
        </div>

        {{-- Body / List --}}
        <div class="overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800/60 flex-1 max-h-80">
            @forelse($notifications as $notification)
                <div
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="openNotification({{ $notification->id }})"
                    class="flex items-start gap-3 p-3.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition cursor-pointer {{ $notification->read_at === null ? 'bg-emerald-50/20 dark:bg-emerald-950/10' : '' }}"
                >
                    {{-- Status Indicator --}}
                    <div class="mt-1 flex-shrink-0">
                        @if($notification->read_at === null)
                            <span class="block w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-emerald-500/20"></span>
                        @else
                            <span class="block w-2 h-2 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate {{ $notification->read_at === null ? 'font-semibold' : '' }}">
                            {{ $notification->title }}
                        </p>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2 mt-0.5">
                            {{ $notification->body }}
                        </p>
                        <span class="block text-[11px] text-zinc-400 dark:text-zinc-500 mt-1">
                            {{ $notification->created_at->diffForHumans() }}
                        </span>
                    </div>

                    {{-- Quick Mark as Read Action --}}
                    @if($notification->read_at === null)
                        <button
                            type="button"
                            wire:click.stop="markAsRead({{ $notification->id }})"
                            title="Okundu işaretle"
                            class="flex-shrink-0 p-1 rounded hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </button>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center">
                    <svg class="w-8 h-8 mx-auto text-zinc-300 dark:text-zinc-700 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Yeni bildiriminiz bulunmuyor.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
