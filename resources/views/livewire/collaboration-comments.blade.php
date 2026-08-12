<section class="comments-panel" data-testid="comments" data-audience="{{ $audience }}">
    @if ($audience === 'internal')
        <form wire:submit="save" class="operations-panel comment-composer">
            <header class="collaboration-header">
                <div>
                    <h2>{{ $editingId ? __('collaboration.comments.edit_title') : ($parentId ? __('collaboration.comments.reply_title') : __('collaboration.comments.title')) }}</h2>
                    @if ($parentId || $editingId)<button type="button" wire:click="cancelComposer" class="operations-link">{{ __('collaboration.comments.cancel') }}</button>@endif
                </div>
                <div class="visibility-selector" data-testid="visibility-selector">
                    @foreach (['internal', 'customer'] as $option)
                        <label class="visibility-option visibility-option--{{ $option }} {{ $visibility === $option ? 'visibility-option--selected' : '' }}">
                            <input type="radio" wire:model.live="visibility" value="{{ $option }}">
                            <span>{{ __('collaboration.comments.visibility.'.$option) }}</span>
                        </label>
                    @endforeach
                </div>
            </header>
            <textarea wire:model="body" rows="4" placeholder="{{ __('collaboration.comments.placeholder') }}"></textarea>
            @error('body')<span class="operations-error">{{ $message }}</span>@enderror
            <div class="comment-composer__footer">
                <div class="mention-picker" data-testid="mention-picker">
                    <label for="mention-user-{{ $this->getId() }}">{{ __('collaboration.comments.mention') }}</label>
                    <select id="mention-user-{{ $this->getId() }}" wire:model="mentionUserId">
                        <option value="">{{ __('collaboration.comments.choose_person') }}</option>
                        @foreach ($mentionCandidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }}</option>@endforeach
                    </select>
                    <button type="button" wire:click="addMention" @disabled(! $mentionUserId) class="operations-button">{{ __('collaboration.comments.add_mention') }}</button>
                </div>
                <button class="operations-button operations-button--primary">{{ $editingId ? __('collaboration.comments.save_edit') : __('collaboration.comments.publish') }}</button>
            </div>
        </form>
    @endif

    <div class="comment-list">
        @forelse ($comments as $comment)
            <article class="comment-card comment-card--{{ $comment->visibility }}">
                <header><strong>{{ $comment->user->name }}</strong><span class="comment-visibility">{{ __('collaboration.comments.visibility.'.$comment->visibility) }}</span><time class="numeric-data">{{ $comment->created_at->format('d.m.Y H:i') }}</time></header>
                <p>{{ \App\Filament\Support\CollaborationView::commentText($comment->body) }}</p>
                <footer>
                    @if ($comment->edited_at)<span data-testid="edited-marker">{{ __('collaboration.comments.edited') }}</span>@endif
                    @if ($audience === 'internal')
                        <button type="button" wire:click="startReply({{ $comment->id }})" class="operations-link">{{ __('collaboration.comments.reply') }}</button>
                        @can('update', $comment)<button type="button" wire:click="startEdit({{ $comment->id }})" class="operations-link">{{ __('collaboration.comments.edit') }}</button>@endcan
                    @endif
                </footer>
                @foreach ($comment->replies as $reply)
                    <article class="comment-reply comment-card--{{ $reply->visibility }}">
                        <header><strong>{{ $reply->user->name }}</strong><span class="comment-visibility">{{ __('collaboration.comments.visibility.'.$reply->visibility) }}</span><time class="numeric-data">{{ $reply->created_at->format('d.m.Y H:i') }}</time></header>
                        <p>{{ \App\Filament\Support\CollaborationView::commentText($reply->body) }}</p>
                        <footer>@if ($reply->edited_at)<span data-testid="edited-marker">{{ __('collaboration.comments.edited') }}</span>@endif @can('update', $reply)<button type="button" wire:click="startEdit({{ $reply->id }})" class="operations-link">{{ __('collaboration.comments.edit') }}</button>@endcan</footer>
                    </article>
                @endforeach
            </article>
        @empty
            <p class="operations-panel collaboration-empty">{{ __('collaboration.comments.empty') }}</p>
        @endforelse
    </div>
</section>
