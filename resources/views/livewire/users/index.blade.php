<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $confirmingDeleteId = null;

    public string $search = '';

    public string $roleFilter = 'all';

    public string $statusFilter = 'all';

    protected $queryString = [
        'search' => ['except' => ''],
        'roleFilter' => ['except' => 'all'],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->abortIfProtectedUser($id);
        $this->confirmingDeleteId = $id;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        abort_unless(auth()->check(), 403);

        if ((int) auth()->id() === $id) {
            $this->dispatch('users-toast', message: __('You cannot deactivate your own account.'));

            return;
        }

        $this->abortIfProtectedUser($id);

        $user = User::query()->select(['id', 'is_active'])->findOrFail($id);
        $user->forceFill(['is_active' => ! $user->is_active])->save();

        Cache::forget('admin:users:stats:v2');

        $this->dispatch('users-toast', message: $user->is_active
            ? __('User activated successfully.')
            : __('User deactivated successfully.'));
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if (! $this->confirmingDeleteId) {
            return;
        }

        abort_unless(auth()->check(), 403);

        if ((int) auth()->id() === (int) $this->confirmingDeleteId) {
            $this->confirmingDeleteId = null;
            $this->dispatch('modal-close', name: 'confirm-delete-user');
            $this->dispatch('modal-close', name: 'confirm-delete-user-desktop');
            $this->dispatch('users-toast', message: __('You cannot delete your own account.'));

            return;
        }

        $this->abortIfProtectedUser($this->confirmingDeleteId);

        User::whereKey($this->confirmingDeleteId)->delete();
        Cache::forget('admin:users:stats:v2');
        $this->confirmingDeleteId = null;

        $this->dispatch('modal-close', name: 'confirm-delete-user');
        $this->dispatch('modal-close', name: 'confirm-delete-user-desktop');
        $this->dispatch('users-toast', message: __('User deleted successfully.'));
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('users.manage'), 403);
    }

    protected function abortIfProtectedUser(int $id): void
    {
        abort_if(
            User::query()
                ->whereKey($id)
                ->whereHas('roles', fn ($query) => $query->where('name', 'Super Admin'))
                ->exists(),
            404
        );
    }
}; ?>

    <section class="w-full">
    @php
        $roles = \Spatie\Permission\Models\Role::query()
            ->select(['id', 'name'])
            ->where('name', '!=', 'Super Admin')
            ->orderBy('name')
            ->get();

        $stats = Cache::remember('admin:users:stats:v2', 10, fn () => [
            'total' => User::query()
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Super Admin'))
                ->count(),
            'active' => User::query()
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Super Admin'))
                ->where('is_active', true)
                ->count(),
            'inactive' => User::query()
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Super Admin'))
                ->where('is_active', false)
                ->count(),
            'role_counts' => \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', User::class)
                ->where('roles.name', '!=', 'Super Admin')
                ->whereNotIn('model_has_roles.model_id', function ($query) {
                    $query->select('model_has_roles.model_id')
                        ->from('model_has_roles')
                        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                        ->where('model_has_roles.model_type', User::class)
                        ->where('roles.name', 'Super Admin');
                })
                ->select('roles.name')
                ->selectRaw('count(distinct model_has_roles.model_id) as agg')
                ->groupBy('roles.name')
                ->pluck('agg', 'roles.name')
                ->all(),
        ]);

        $totalCount = (int) ($stats['total'] ?? 0);
        $activeCount = (int) ($stats['active'] ?? 0);
        $inactiveCount = (int) ($stats['inactive'] ?? 0);
        $roleCounts = collect($stats['role_counts'] ?? []);

        $query = User::query()
            ->select(['id', 'name', 'email', 'is_active'])
            ->with(['roles:id,name'])
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'));

        if (!empty($search)) {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleFilter !== 'all') {
            if ($roleFilter === 'none') {
                $query->doesntHave('roles');
            } else {
                $query->whereHas('roles', fn ($q) => $q->where('name', $roleFilter));
            }
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $items = $query->orderBy('name')->paginate(10);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('users.manage')
                    <flux:link :href="route('users.create', [], false)" wire:navigate>
                        <flux:button icon="plus" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                    </flux:link>
                @endcan
            </div>
        </div>

        <!-- Mobile: horizontal scroll chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Users') }}</span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Active') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $activeCount }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('Inactive') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $inactiveCount }}</span>
                    </span>
                </div>
                @foreach ($roles as $role)
                    <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span class="text-neutral-600 dark:text-neutral-300">{{ ucfirst($role->name) }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-white">{{ (int) $roleCounts->get($role->name, 0) }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Tablet/Desktop: grid summary cards -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Users') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>

            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('Active') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $activeCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Users') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Can access the system') }}</p>
            </div>

            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                    <span>{{ __('Inactive') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $inactiveCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Users') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Kept for history without access') }}</p>
            </div>

            @foreach ($roles->take(1) as $role)
                <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                    <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span>{{ ucfirst($role->name) }}</span>
                    </div>
                    <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                        <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ (int) $roleCounts->get($role->name, 0) }}</span>
                        <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Users') }}</span>
                    </div>
                    <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Assigned to this role') }}</p>
                </div>
            @endforeach
        </div>

        <!-- Search and filter -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input wire:model.live.debounce.1000ms="search" :placeholder="__('Search name or email')" />
            </div>
            <div class="flex items-center gap-2 min-w-0 sm:justify-end">
                <flux:select
                    wire:model.live="statusFilter"
                    class="flex-1 min-w-0 sm:flex-none sm:w-44 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </flux:select>
                <flux:select
                    wire:model.live="roleFilter"
                    class="flex-1 min-w-0 sm:flex-none sm:w-44 rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All Role') }}</option>
                    <option value="none">{{ __('No role') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                    @endforeach
                </flux:select>
                <flux:button size="sm" variant="ghost" class="btn-ghost-accent whitespace-nowrap shrink-0" wire:click="$wire.set('search','');$wire.set('roleFilter','all');$wire.set('statusFilter','all')">{{ __('Clear') }}</flux:button>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
                @forelse ($items as $user)
                    @php($roleNames = $user->roles->pluck('name')->values())
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100 text-sm font-semibold text-neutral-800 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-100 dark:ring-neutral-700">
                                    {{ $user->initials() }}
                                </span>
                                <div>
                                    <div class="text-base font-semibold text-neutral-900 dark:text-white">{{ $user->name }}</div>
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $user->email }}</div>
                                </div>
                            </div>

                            @if ($roleNames->isEmpty())
                                <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-[11px] font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                    <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                    {{ __('No role') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    {{ $roleNames->join(', ') }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-3">
                            @if ($user->is_active)
                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-100 dark:ring-emerald-800/60">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    {{ __('Active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-[11px] font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                    <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                    {{ __('Inactive') }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            @can('users.manage')
                                <flux:button
                                    size="sm"
                                    icon="{{ $user->is_active ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="btn-ghost-accent w-full justify-center"
                                    wire:click="toggleActive({{ $user->id }})"
                                    :disabled="auth()->id() === $user->id"
                                >
                                    {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                                <flux:link :href="route('users.edit', $user, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:link>
                                <flux:modal.trigger name="confirm-delete-user" class="col-span-2">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full"
                                        wire:click="confirmDelete({{ $user->id }})"
                                        :disabled="auth()->id() === $user->id"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No users found.') }}
                    </div>
                @endforelse
            </div>
            <div class="mt-4">
                {{ $items->links() }}
            </div>
        </div>

        <!-- Tablet cards -->
        <div class="hidden sm:block lg:hidden">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse ($items as $user)
                    @php($roleNames = $user->roles->pluck('name')->values())
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100 text-sm font-semibold text-neutral-800 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-100 dark:ring-neutral-700">
                                    {{ $user->initials() }}
                                </span>
                                <div class="min-w-0">
                                    <div class="truncate text-base font-semibold text-neutral-900 dark:text-white">{{ $user->name }}</div>
                                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $user->email }}</div>
                                </div>
                            </div>

                            @if ($roleNames->isEmpty())
                                <span class="shrink-0 inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-[11px] font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                    <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                    {{ __('No role') }}
                                </span>
                            @else
                                <span class="shrink-0 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60 max-w-[16rem]">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    <span class="truncate">{{ $roleNames->join(', ') }}</span>
                                </span>
                            @endif
                        </div>

                        <div class="mt-3">
                            @if ($user->is_active)
                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-100 dark:ring-emerald-800/60">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    {{ __('Active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-[11px] font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                    <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                    {{ __('Inactive') }}
                                </span>
                            @endif
                        </div>

                        @can('users.manage')
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <flux:button
                                    size="sm"
                                    icon="{{ $user->is_active ? 'pause-circle' : 'check-circle' }}"
                                    variant="ghost"
                                    class="btn-ghost-accent w-full justify-center"
                                    wire:click="toggleActive({{ $user->id }})"
                                    :disabled="auth()->id() === $user->id"
                                >
                                    {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                                <flux:link :href="route('users.edit', $user, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:link>
                                <flux:modal.trigger name="confirm-delete-user-desktop" class="col-span-2">
                                    <flux:button
                                        size="sm"
                                        icon="trash"
                                        variant="danger"
                                        class="w-full"
                                        wire:click="confirmDelete({{ $user->id }})"
                                        :disabled="auth()->id() === $user->id"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="sm:col-span-2 rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No data') }}
                    </div>
                @endforelse
            </div>
            <div class="mt-4">
                {{ $items->links() }}
            </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden lg:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full table-fixed text-sm">
                        <thead>
                            <tr>
                                <th class="w-[19%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Name') }}</th>
                                <th class="w-[23%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Email') }}</th>
                                <th class="w-[17%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Role') }}</th>
                                <th class="w-[13%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Status') }}</th>
                                <th class="w-[28%] border-b border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $user)
                                @php($roleNames = $user->roles->pluck('name')->values())
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <div class="mx-auto flex w-fit min-w-[220px] items-center gap-3 text-left">
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-neutral-100 text-sm font-semibold text-neutral-800 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-100 dark:ring-neutral-700">
                                                {{ $user->initials() }}
                                            </span>
                                            <div class="grid">
                                                <span class="font-semibold text-neutral-900 dark:text-white">{{ $user->name }}</span>
                                                <span class="text-xs text-neutral-500 dark:text-neutral-400">#{{ $user->id }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="text-sm">{{ $user->email }}</span>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        @if ($roleNames->isEmpty())
                                            <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                                <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                                {{ __('No role') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60 max-w-[18rem] lg:max-w-none">
                                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                                <span class="truncate">{{ $roleNames->join(', ') }}</span>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        @if ($user->is_active)
                                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-100 dark:ring-emerald-800/60">
                                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                                {{ __('Active') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-[11px] font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                                <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                                {{ __('Inactive') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 align-middle text-center">
                                        <div class="mx-auto grid w-full min-w-[340px] max-w-[460px] grid-cols-3 gap-2">
                                            @can('users.manage')
                                                <flux:button
                                                    size="sm"
                                                    icon="{{ $user->is_active ? 'pause-circle' : 'check-circle' }}"
                                                    variant="ghost"
                                                    class="btn-ghost-accent w-full rounded-2xl shadow-sm transition justify-center"
                                                    wire:click="toggleActive({{ $user->id }})"
                                                    :disabled="auth()->id() === $user->id"
                                                    title="{{ $user->is_active ? __('Deactivate') : __('Activate') }}"
                                                >
                                                    {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                                </flux:button>
                                                <flux:link :href="route('users.edit', $user, false)" wire:navigate>
                                                    <flux:button
                                                        size="sm"
                                                        icon="pencil-square"
                                                        variant="primary"
                                                        class="btn-accent w-full rounded-2xl shadow-sm transition justify-center"
                                                    >
                                                        {{ __('Edit') }}
                                                    </flux:button>
                                                </flux:link>
                                                <flux:modal.trigger name="confirm-delete-user-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        icon="trash"
                                                        variant="danger"
                                                        class="w-full rounded-2xl shadow-sm transition justify-center"
                                                        wire:click="confirmDelete({{ $user->id }})"
                                                        :disabled="auth()->id() === $user->id"
                                                    >
                                                        {{ __('Delete') }}
                                                    </flux:button>
                                                </flux:modal.trigger>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ __('No users found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="px-4 py-3">
                {{ $items->links() }}
            </div>
        </div>
    </div>

    @php($selectedUser = $items->firstWhere('id', $confirmingDeleteId))

    <!-- Delete confirmation - mobile -->
    <flux:modal name="confirm-delete-user" focusable variant="flyout" position="bottom" :closable="false" class="rounded-t-3xl sm:rounded-xl">
        <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
            <div class="flex items-center justify-center">
                <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
            </div>

            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete this user?') }}</flux:heading>
                <flux:subheading>{{ __('This action cannot be undone. This record will be permanently deleted.') }}</flux:subheading>
            </div>

            @if ($selectedUser)
                <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">{{ $selectedUser->name }}</p>
                    <p class="text-xs opacity-80">{{ $selectedUser->email }}</p>
                </div>
            @endif

            <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white/85 px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                <flux:modal.close>
                    <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">
                    {{ __('Yes, delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete confirmation - desktop -->
    <flux:modal name="confirm-delete-user-desktop" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-lg">
        <div class="space-y-4 p-2">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete this user?') }}</flux:heading>
                <flux:subheading>{{ __('This action cannot be undone. This record will be permanently deleted.') }}</flux:subheading>
            </div>

            @if ($selectedUser)
                <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">{{ $selectedUser->name }}</p>
                    <p class="text-xs opacity-80">{{ $selectedUser->email }}</p>
                </div>
            @endif

            <div class="mt-2 flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">
                    {{ __('Yes, delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Toast -->
    <div
        x-data="{
            show: false,
            message: '',
            timeout: null,
            handle(event) {
                this.message = event.detail?.message || '{{ __('Saved.') }}';
                this.show = true;
                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => this.show = false, 3500);
            }
        }"
        x-on:users-toast.window="handle($event)"
        class="pointer-events-none fixed inset-x-0 top-6 flex justify-center px-4"
    >
        <div
            x-show="show"
            x-transition:enter="transform ease-out duration-200"
            x-transition:enter-start="-translate-y-3 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transform ease-in duration-200"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="-translate-y-3 opacity-0"
            class="pointer-events-auto rounded-2xl toast-brand px-4 py-3 text-sm"
        >
            <div class="flex items-center gap-2">
                <flux:icon icon="check-circle" />
                <span x-text="message"></span>
            </div>
        </div>
    </div>
</section>

@if (session('users_toast'))
    <script>
        window.addEventListener('load', () => {
            try {
                const message = @js(session('users_toast'));
                window.dispatchEvent(new CustomEvent('users-toast', { detail: { message } }));
            } catch (e) {}
        });
    </script>
@endif
