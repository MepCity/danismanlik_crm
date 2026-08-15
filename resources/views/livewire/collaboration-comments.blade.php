<section class="comments-panel" data-testid="comments" data-audience="{{ $audience }}">
    @if ($audience === 'internal')
        <form wire:submit="save" class="operations-panel comment-composer">
            <span class="comment-avatar" aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials(auth()->user()->name) }}</span>
            <div class="comment-composer__surface">
                <header class="comment-composer__header">
                    <h2>{{ $editingId ? __('collaboration.comments.edit_title') : ($parentId ? __('collaboration.comments.reply_title') : __('collaboration.comments.title')) }}</h2>
                    <div class="visibility-selector" data-testid="visibility-selector">
                        @foreach (['internal', 'customer'] as $option)
                            <label class="visibility-option {{ $visibility === $option ? 'visibility-option--selected' : '' }}">
                                <input type="radio" wire:model.live="visibility" value="{{ $option }}">
                                <span>{{ __('collaboration.comments.visibility.'.$option) }}</span>
                            </label>
                        @endforeach
                    </div>
                </header>
                <textarea wire:model="body" rows="3" placeholder="{{ __('collaboration.comments.placeholder') }}"></textarea>
                @error('body')<span class="operations-error">{{ $message }}</span>@enderror
                <div class="comment-composer__footer">
                    <div class="mention-picker" data-testid="mention-picker">
                        <span class="mention-picker__symbol" aria-hidden="true">@</span>
                        <label class="sr-only" for="mention-user-{{ $this->getId() }}">{{ __('collaboration.comments.mention') }}</label>
                        <select id="mention-user-{{ $this->getId() }}" wire:model="mentionUserId">
                            <option value="">{{ __('collaboration.comments.choose_person') }}</option>
                            @foreach ($mentionCandidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }}</option>@endforeach
                        </select>
                        <button type="button" wire:click="addMention" @disabled(! $mentionUserId) class="operations-link">{{ __('collaboration.comments.add_mention') }}</button>
                    </div>
                    <div class="comment-composer__actions">
                        @if ($parentId || $editingId)<button type="button" wire:click="cancelComposer" class="operations-link">{{ __('collaboration.comments.cancel') }}</button>@endif
                        <button class="operations-button operations-button--primary">{{ $editingId ? __('collaboration.comments.save_edit') : __('collaboration.comments.publish') }}</button>
                    </div>
                </div>
            </div>
        </form>
    @endif

    <div class="comment-list">
        @forelse ($comments as $comment)
            <article class="comment-thread">
                <span class="comment-avatar" aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials($comment->user->name) }}</span>
                <div class="comment-thread__content">
                    <header class="comment-meta">
                        <strong>{{ $comment->user->name }}</strong>
                        <time class="numeric-data">{{ $comment->created_at->format('d.m.Y H:i') }}</time>
                        <span class="comment-visibility" data-visibility="{{ $comment->visibility }}">{{ __('collaboration.comments.visibility.'.$comment->visibility) }}</span>
                    </header>
                    <p>{!! \App\Filament\Support\CollaborationView::commentHtml($comment->body) !!}</p>
                    <footer class="comment-actions">
                        @if ($audience === 'internal')
                            <button type="button" wire:click="startReply({{ $comment->id }})" class="operations-link">↩ {{ __('collaboration.comments.reply') }}</button>
                            @can('update', $comment)<button type="button" wire:click="startEdit({{ $comment->id }})" class="operations-link">{{ __('collaboration.comments.edit') }}</button>@endcan
                        @endif
                        @if ($comment->edited_at)<span data-testid="edited-marker">{{ __('collaboration.comments.edited') }}</span>@endif
                    </footer>
                    @if ($comment->replies->isNotEmpty())
                        <div class="comment-replies">
                            @foreach ($comment->replies as $reply)
                                <article class="comment-reply">
                                    <span class="comment-avatar comment-avatar--small" aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials($reply->user->name) }}</span>
                                    <div>
                                        <header class="comment-meta">
                                            <strong>{{ $reply->user->name }}</strong>
                                            <time class="numeric-data">{{ $reply->created_at->format('d.m.Y H:i') }}</time>
                                            <span class="comment-visibility" data-visibility="{{ $reply->visibility }}">{{ __('collaboration.comments.visibility.'.$reply->visibility) }}</span>
                                        </header>
                                        <p>{!! \App\Filament\Support\CollaborationView::commentHtml($reply->body) !!}</p>
                                        <footer class="comment-actions">
                                            @can('update', $reply)<button type="button" wire:click="startEdit({{ $reply->id }})" class="operations-link">{{ __('collaboration.comments.edit') }}</button>@endcan
                                            @if ($reply->edited_at)<span data-testid="edited-marker">{{ __('collaboration.comments.edited') }}</span>@endif
                                        </footer>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <p class="collaboration-empty">{{ __('collaboration.comments.empty') }}</p>
        @endforelse
    </div>
</section>
