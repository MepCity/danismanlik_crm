<section class="tasks-layout" data-testid="subject-tasks">
    <form wire:submit="create" class="operations-panel operations-inline-form task-form">
        <h2>{{ __('collaboration.tasks.new') }}</h2>
        <label>{{ __('collaboration.tasks.title') }}<input wire:model="title" required></label>
        <label>{{ __('collaboration.tasks.assignee') }}
            <select wire:model="assigneeId" required><option value="">{{ __('collaboration.tasks.choose_assignee') }}</option>@foreach ($assignees as $assignee)<option value="{{ $assignee->id }}">{{ $assignee->name }}</option>@endforeach</select>
        </label>
        <label>{{ __('collaboration.tasks.due_at') }}<input type="datetime-local" wire:model="dueAt"></label>
        <label>{{ __('collaboration.tasks.description') }}<textarea wire:model="description" rows="2"></textarea></label>
        <button class="operations-button operations-button--primary">{{ __('collaboration.tasks.create') }}</button>
    </form>
    <div class="operations-panel task-list">
        @forelse ($tasks as $task)
            <article class="task-row {{ $task->completed_at ? 'task-row--completed' : '' }}">
                <button type="button" wire:click="toggle({{ $task->id }})" class="task-toggle" aria-label="{{ $task->completed_at ? __('collaboration.tasks.reopen') : __('collaboration.tasks.complete') }}">{{ $task->completed_at ? '✓' : '○' }}</button>
                <div><strong>{{ $task->title }}</strong><p>{{ $task->description }}</p></div>
                <span>{{ $task->assignee->name }}</span><time class="numeric-data">{{ $task->due_at?->format('d.m.Y H:i') }}</time>
            </article>
        @empty
            <p class="collaboration-empty">{{ __('collaboration.tasks.empty') }}</p>
        @endforelse
    </div>
</section>
