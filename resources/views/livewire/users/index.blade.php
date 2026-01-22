<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new class extends Component {
    use WithPagination;

    public ?int $confirmingDeleteId = null;
    public ?int $editingId = null;
    public string $search = '';
    public string $roleFilter = 'all';
    public array $form = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'roleFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->resetCreateForm();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingRoleFilter(): void { $this->resetPage(); }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->resetCreateForm();
        $this->dispatch('modal-show', name: 'create-user');
    }

    public function openEditModal(int $id): void
    {
        $this->authorizeManage();
        $user = User::query()->with('roles')->findOrFail($id);

        $this->editingId = $user->id;
        $this->form = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => $user->roles->pluck('name')->first() ?? '',
        ];

        $this->dispatch('modal-show', name: 'edit-user');
    }

    public function save(): void
    {
        $this->authorizeManage();
        $validated = validator($this->form, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string', 'max:255', 'exists:roles,name'],
        ])->validate();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->syncRoles(!empty($validated['role']) ? [$validated['role']] : []);

        $this->resetCreateForm();
        $this->dispatch('modal-close', name: 'create-user');
        $this->dispatch('users-toast', message: __('User created successfully.'));
    }

    public function update(): void
    {
        $this->authorizeManage();
        if (!$this->editingId) {
            return;
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $this->editingId],
            'role' => ['nullable', 'string', 'max:255', 'exists:roles,name'],
        ];

        if (!empty($this->form['password'])) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        } else {
            $rules['password'] = ['nullable', 'string'];
        }

        $validated = validator($this->form, $rules)->validate();

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user = User::findOrFail($this->editingId);
        $user->update($payload);
        $user->syncRoles(!empty($validated['role']) ? [$validated['role']] : []);

        $this->editingId = null;
        $this->dispatch('modal-close', name: 'edit-user');
        $this->dispatch('users-toast', message: __('User updated successfully.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if (!$this->confirmingDeleteId) {
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

        User::whereKey($this->confirmingDeleteId)->delete();
        $this->confirmingDeleteId = null;

        $this->dispatch('modal-close', name: 'confirm-delete-user');
        $this->dispatch('modal-close', name: 'confirm-delete-user-desktop');
        $this->dispatch('users-toast', message: __('User deleted successfully.'));
    }

    protected function resetCreateForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
            'role' => '',
        ];
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('users.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $roles = Role::query()->orderBy('name')->get();

        $roleCounts = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->select('roles.name')
            ->selectRaw('count(distinct model_has_roles.model_id) as agg')
            ->groupBy('roles.name')
            ->pluck('agg', 'roles.name');

        $totalCount = User::query()->count();

        $query = User::query()->with('roles');

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

        $items = $query->orderBy('name')->paginate(10);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('users.manage')
                    <flux:button icon="plus" variant="primary" class="btn-brand" wire:click="openCreateModal">{{ __('Create') }}</flux:button>
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

            @foreach ($roles->take(3) as $role)
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
            <div class="flex-1">
                <flux:input wire:model.live.debounce.1000ms="search" :placeholder="__('Search name or email')" />
            </div>
            <div class="flex items-center gap-2">
                <flux:select
                    wire:model.live="roleFilter"
                    class="rounded-full border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900 focus:outline-hidden focus:ring-2 focus:ring-[color:var(--brand-accent)] focus:ring-offset-2 focus:ring-offset-[color:var(--brand-accent-foreground)]"
                >
                    <option value="all">{{ __('All') }}</option>
                    <option value="none">{{ __('No role') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                    @endforeach
                </flux:select>
                <flux:button size="sm" variant="ghost" class="btn-ghost-accent" wire:click="$set('search','');$set('roleFilter','all')">{{ __('Clear') }}</flux:button>
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

                        <div class="mt-4 flex items-center gap-2">
                            @can('users.manage')
                                <flux:button size="sm" icon="pencil-square" variant="primary" class="flex-1 btn-accent" wire:click="openEditModal({{ $user->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:modal.trigger name="confirm-delete-user" class="flex-1">
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

        <!-- Desktop table -->
        <div class="hidden sm:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Name') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Email') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Role') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $user)
                                @php($roleNames = $user->roles->pluck('name')->values())
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-neutral-100 text-sm font-semibold text-neutral-800 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-100 dark:ring-neutral-700">
                                                {{ $user->initials() }}
                                            </span>
                                            <div class="grid">
                                                <span class="font-semibold text-neutral-900 dark:text-white">{{ $user->name }}</span>
                                                <span class="text-xs text-neutral-500 dark:text-neutral-400">#{{ $user->id }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="text-sm">{{ $user->email }}</span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        @if ($roleNames->isEmpty())
                                            <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                                <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                                                {{ __('No role') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60">
                                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                                {{ $roleNames->join(', ') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @can('users.manage')
                                                <flux:button
                                                    size="sm"
                                                    icon="pencil-square"
                                                    variant="primary"
                                                    class="btn-accent rounded-2xl shadow-sm transition"
                                                    wire:click="openEditModal({{ $user->id }})"
                                                >
                                                    {{ __('Edit') }}
                                                </flux:button>
                                                <flux:modal.trigger name="confirm-delete-user-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        icon="trash"
                                                        variant="danger"
                                                        class="rounded-2xl shadow-sm transition"
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
                                    <td colspan="4" class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
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
    <flux:modal name="confirm-delete-user" focusable variant="flyout" position="bottom" class="rounded-t-3xl sm:rounded-xl">
        <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
            <div class="flex items-center justify-center">
                <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
            </div>

            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete this user?') }}</flux:heading>
                <flux:subheading>{{ __('This action cannot be undone.') }}</flux:subheading>
            </div>

            @if ($selectedUser)
                <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">{{ $selectedUser->name }}</p>
                    <p class="text-xs opacity-80">{{ $selectedUser->email }}</p>
                </div>
            @endif

            <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white/85 px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                <flux:modal.close>
                    <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
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
                <flux:subheading>{{ __('This action cannot be undone.') }}</flux:subheading>
            </div>

            @if ($selectedUser)
                <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">{{ $selectedUser->name }}</p>
                    <p class="text-xs opacity-80">{{ $selectedUser->email }}</p>
                </div>
            @endif

            <div class="mt-2 flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">
                    {{ __('Yes, delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Create user modal -->
    <flux:modal name="create-user" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl" closable="false">
        <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
            <div class="sticky top-0 z-10 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                <div>
                    <flux:heading size="lg">{{ __('Create User') }}</flux:heading>
                    <flux:subheading>{{ __('Fill the details below to add a new user.') }}</flux:subheading>
                </div>
                <flux:modal.close class="hidden sm:block">
                    <flux:button variant="ghost" icon="x-mark" class="inline-flex btn-ghost-neutral -mt-1" aria-label="{{ __('Close') }}" />
                </flux:modal.close>
            </div>

            <form id="create-user-form" wire:submit.prevent="save" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <flux:input wire:model.defer="form.name" :label="__('Name')" required maxlength="255" />
                        @error('form.name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                        <flux:input wire:model.defer="form.email" type="email" :label="__('Email')" required maxlength="255" />
                        @error('form.email') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                        <div data-flux-field>
                            <flux:select wire:model.defer="form.role" :label="__('Role')">
                                <option value="">{{ __('No role') }}</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                                @endforeach
                            </flux:select>
                            @error('form.role') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-4">
                        <flux:input wire:model.defer="form.password" type="password" :label="__('Password')" required />
                        @error('form.password') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                        <flux:input wire:model.defer="form.password_confirmation" type="password" :label="__('Confirm Password')" required />

                        <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                            <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                            <ul class="mt-2 list-disc space-y-1 pl-4">
                                <li>{{ __('Use a strong password (min 8 characters).') }}</li>
                                <li>{{ __('Assign a role to match responsibilities.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="hidden md:flex items-center justify-end gap-3 pt-2 px-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="create-user-form" variant="primary" icon="plus" class="btn-brand">{{ __('Create') }}</flux:button>
                </div>
            </form>

            <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                <div class="grid grid-cols-2 gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="create-user-form" variant="primary" icon="plus" class="btn-brand w-full">{{ __('Create') }}</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <!-- Edit user modal -->
    <flux:modal name="edit-user" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl" closable="false">
        <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
            <div class="sticky top-0 z-10 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                <div>
                    <flux:heading size="lg">{{ __('Edit User') }}</flux:heading>
                    <flux:subheading>{{ __('Update the details for this user.') }}</flux:subheading>
                </div>
                <flux:modal.close class="hidden sm:block">
                    <flux:button variant="ghost" icon="x-mark" class="inline-flex btn-ghost-neutral -mt-1" aria-label="{{ __('Close') }}" />
                </flux:modal.close>
            </div>

            <form id="edit-user-form" wire:submit.prevent="update" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <flux:input wire:model.defer="form.name" :label="__('Name')" required maxlength="255" />
                        @error('form.name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                        <flux:input wire:model.defer="form.email" type="email" :label="__('Email')" required maxlength="255" />
                        @error('form.email') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                        <div data-flux-field>
                            <flux:select wire:model.defer="form.role" :label="__('Role')">
                                <option value="">{{ __('No role') }}</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                                @endforeach
                            </flux:select>
                            @error('form.role') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-4">
                        <flux:input wire:model.defer="form.password" type="password" :label="__('New Password')" />
                        @error('form.password') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                        <flux:input wire:model.defer="form.password_confirmation" type="password" :label="__('Confirm Password')" />

                        <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                            <flux:heading size="sm">{{ __('Notes') }}</flux:heading>
                            <ul class="mt-2 list-disc space-y-1 pl-4">
                                <li>{{ __('Leave password empty to keep the current password.') }}</li>
                                <li>{{ __('Role selection will replace existing roles (single-role mode).') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="hidden md:flex items-center justify-end gap-3 pt-2 px-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="edit-user-form" variant="primary" icon="check" class="btn-brand">{{ __('Update') }}</flux:button>
                </div>
            </form>

            <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                <div class="grid grid-cols-2 gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="edit-user-form" variant="primary" icon="check" class="btn-brand w-full">{{ __('Update') }}</flux:button>
                </div>
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
