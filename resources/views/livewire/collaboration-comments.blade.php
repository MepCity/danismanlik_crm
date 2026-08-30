@php($mentionOptions = $mentionCandidates->map(fn ($candidate): array => ['id' => $candidate->id, 'name' => $candidate->name])->values())

<section class="comments-panel {{ $compact ? 'comments-panel--compact' : '' }}" data-testid="comments">
    <form
        wire:submit="save"
        class="comment-composer {{ $compact ? 'comment-composer--compact' : 'operations-panel' }}"
        x-data="{
            open: false,
            draft: @js($body),
            query: '',
            index: 0,
            people: @js($mentionOptions),
            get matches() {
                const q = this.query.toLowerCase();
                return this.people.filter(p => p.name.toLowerCase().includes(q)).slice(0, 6);
            },
            detect(event) {
                const value = event.target.value;
                const caret = event.target.selectionStart ?? value.length;
                const upto = value.slice(0, caret);
                const at = upto.lastIndexOf('@');
                if (at === -1 || /\s/.test(upto.slice(at + 1))) { this.open = false; return; }
                this.query = upto.slice(at + 1);
                this.index = 0;
                this.open = true;
            },
            move(step) {
                if (! this.open) return;
                const total = this.matches.length;
                if (total === 0) return;
                this.index = (this.index + step + total) % total;
            },
            choose(person) {
                if (! person) return;
                this.open = false;
                this.query = '';
                $wire.insertMention(person.id);
            },
            pick() { this.choose(this.matches[this.index]); },
        }"
        x-init="$wire.$watch('body', value => { draft = value ?? '' })"
        x-on:comments-updated.window="draft = ''; $nextTick(() => { $refs.submit.disabled = true })"
        x-on:keydown.escape="open = false"
        x-on:click.outside="open = false"
    >
        <span class="comment-avatar" aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials(auth()->user()->name) }}</span>
        <div class="comment-composer__surface">
            @unless ($compact)
                <header class="comment-composer__header">
                    <h2>{{ $editingId ? __('collaboration.comments.edit_title') : ($parentId ? __('collaboration.comments.reply_title') : __('collaboration.comments.title')) }}</h2>
                </header>
            @endunless

            <div class="comment-composer__field">
                <label class="fi-sr-only" for="comment-body-{{ $this->getId() }}">{{ __('marketing.company.note.label') }}</label>
                <textarea
                    id="comment-body-{{ $this->getId() }}"
                    wire:model="body"
                    rows="{{ $compact ? 1 : 3 }}"
                    placeholder="{{ $compact ? __('marketing.company.note.placeholder') : __('collaboration.comments.placeholder') }}"
                    aria-describedby="comment-mention-hint-{{ $this->getId() }}"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="mention-listbox-{{ $this->getId() }}"
                    x-bind:aria-expanded="(open && matches.length > 0).toString()"
                    x-bind:aria-activedescendant="open && matches.length ? 'mention-option-{{ $this->getId() }}-' + matches[index].id : null"
                    x-on:input="draft = $event.target.value; detect($event)"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter="if (open) { $event.preventDefault(); pick(); }"
                    x-on:keydown.tab="if (open) { $event.preventDefault(); pick(); }"
                ></textarea>
                <p id="comment-mention-hint-{{ $this->getId() }}" class="fi-sr-only">{{ __('marketing.company.note.mention_hint') }}</p>

                <ul
                    id="mention-listbox-{{ $this->getId() }}"
                    class="mention-suggestions"
                    role="listbox"
                    aria-label="{{ __('marketing.company.note.mention_list') }}"
                    x-show="open && matches.length"
                    x-cloak
                    data-testid="mention-suggestions"
                >
                    <template x-for="(person, i) in matches" :key="person.id">
                        <li
                            :id="'mention-option-{{ $this->getId() }}-' + person.id"
                            role="option"
                            :aria-selected="(i === index).toString()"
                            :class="{ 'mention-suggestions__item--active': i === index }"
                            class="mention-suggestions__item"
                            x-on:mousedown.prevent="choose(person)"
                            x-text="person.name"
                        ></li>
                    </template>
                </ul>
            </div>

            @error('body')<span class="operations-error">{{ $message }}</span>@enderror

            <div class="comment-composer__footer">
                @unless ($compact)
                    <span class="comment-composer__hint">{{ __('marketing.company.note.mention_hint') }}</span>
                @endunless
                <div class="comment-composer__actions">
                    @if ($parentId || $editingId)<button type="button" wire:click="cancelComposer" class="operations-link">{{ __('collaboration.comments.cancel') }}</button>@endif
                    <button
                        x-ref="submit"
                        class="{{ $compact ? 'comment-composer__submit' : 'operations-button operations-button--primary' }}"
                        data-testid="comment-submit"
                        {{-- server render decides the state after each round trip, Alpine keeps typing responsive --}}
                        @disabled(trim($body) === '')
                        x-bind:disabled="draft.trim() === ''"
                    >{{ $compact ? __('marketing.company.note.submit') : ($editingId ? __('collaboration.comments.save_edit') : __('collaboration.comments.publish')) }}</button>
                </div>
            </div>
        </div>
    </form>

    <div class="comment-list" @if ($compact) hidden @endif>
        @forelse ($comments as $comment)
            <article class="comment-thread">
                <span class="comment-avatar" aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials($comment->user->name) }}</span>
                <div class="comment-thread__content">
                    <header class="comment-meta">
                        <strong>{{ $comment->user->name }}</strong>
                        <time class="numeric-data">{{ $comment->created_at->format('d.m.Y H:i') }}</time>
                    </header>
                    <p>{!! \App\Filament\Support\CollaborationView::commentHtml($comment->body) !!}</p>
                    <footer class="comment-actions">
                        <button type="button" wire:click="startReply({{ $comment->id }})" class="operations-link">↩ {{ __('collaboration.comments.reply') }}</button>
                        @can('update', $comment)<button type="button" wire:click="startEdit({{ $comment->id }})" class="operations-link">{{ __('collaboration.comments.edit') }}</button>@endcan
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
