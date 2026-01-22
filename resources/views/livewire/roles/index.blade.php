<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component {
    use WithPagination;

    public ?int $confirmingDeleteId = null;
    public ?int $editingId = null;

    public string $search = '';

    public array $form = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->resetCreateForm();
    }

    public function updatingSearch(): void { $this->resetPage(); }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->resetCreateForm();
        $this->dispatch('modal-show', name: 'create-role');
    }

    public function openEditModal(int $id): void
    {
        $this->authorizeManage();
        $role = Role::query()->with('permissions')->findOrFail($id);

        $this->editingId = $role->id;
        $this->form = [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values()->all(),
        ];

        $this->dispatch('modal-show', name: 'edit-role');
    }

    public function save(): void
    {
        $this->authorizeManage();
        $validated = validator($this->form, [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/'],
        ])->validate();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $permissionNames = $this->normalizePermissionNames($validated['permissions'] ?? []);

        $this->ensurePermissionsExist($permissionNames);
        $role->syncPermissions($permissionNames);

        $this->resetCreateForm();
        $this->dispatch('modal-close', name: 'create-role');
        $this->dispatch('roles-toast', message: __('Role created successfully.'));
    }

    public function update(): void
    {
        $this->authorizeManage();
        if (!$this->editingId) {
            return;
        }

        $validated = validator($this->form, [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $this->editingId],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/'],
        ])->validate();

        $role = Role::findOrFail($this->editingId);
        $role->update([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $permissionNames = $this->normalizePermissionNames($validated['permissions'] ?? []);

        $this->ensurePermissionsExist($permissionNames);
        $role->syncPermissions($permissionNames);

        $this->editingId = null;
        $this->dispatch('modal-close', name: 'edit-role');
        $this->dispatch('roles-toast', message: __('Role updated successfully.'));
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

        Role::whereKey($this->confirmingDeleteId)->delete();
        $this->confirmingDeleteId = null;

        $this->dispatch('modal-close', name: 'confirm-delete-role');
        $this->dispatch('modal-close', name: 'confirm-delete-role-desktop');
        $this->dispatch('roles-toast', message: __('Role deleted successfully.'));
    }

    protected function resetCreateForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'permissions' => [],
        ];
    }

    protected function normalizePermissionNames(array $names): array
    {
        return collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function ensurePermissionsExist(array $names): void
    {
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('roles.manage'), 403);
    }

    public function permissionGroups(): array
    {
        return [
            __('General') => [
                'dashboard.access',
            ],
            __('Menu Access') => [
                'meja.access',
                'kategori.access',
                'menu.access',
                'pajak.access',
                'diskon.access',
                'pesanan.access',
                'users.access',
                'roles.access',
            ],
            __('Manage') => [
                'meja.manage',
                'kategori.manage',
                'menu.manage',
                'pajak.manage',
                'diskon.manage',
                'pesanan.manage',
                'users.manage',
                'roles.manage',
            ],
        ];
    }

    public function clearPermissions(): void
    {
        $this->form['permissions'] = [];
    }

    public function selectAllPermissions(): void
    {
        $default = collect($this->permissionGroups())->flatten()->values()->all();
        $extra = Permission::query()
            ->whereNotIn('name', $default)
            ->pluck('name')
            ->all();

        $this->form['permissions'] = collect(array_merge($default, $extra))
            ->unique()
            ->values()
            ->all();
    }
}; ?>

<section class="w-full">
    @php
        $defaultPermissionGroups = $this->permissionGroups();

        $defaultPermissionNames = collect($defaultPermissionGroups)->flatten()->values();
        $extraPermissions = Permission::query()
            ->whereNotIn('name', $defaultPermissionNames->all())
            ->orderBy('name')
            ->get();
        $query = Role::query()->withCount('permissions')->orderBy('name');

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $items = $query->paginate(10);

        $totalRoles = Role::query()->count();
        $totalPermissions = Permission::query()->count();
        $rolesWithPermissions = Role::query()->has('permissions')->count();
        $rolesWithoutPermissions = max(0, $totalRoles - $rolesWithPermissions);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Roles') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('roles.manage')
                    <flux:button icon="plus" variant="primary" class="btn-brand" wire:click="openCreateModal">{{ __('Create') }}</flux:button>
                @endcan
            </div>
        </div>

        <!-- Mobile: horizontal scroll chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="font-semibold text-neutral-900 dark:text-white">{{ $totalRoles }}</span>
                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Roles') }}</span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('With permissions') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $rolesWithPermissions }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                        <span class="text-neutral-600 dark:text-neutral-300">{{ __('No permissions') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-white">{{ $rolesWithoutPermissions }}</span>
                    </span>
                </div>
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="font-semibold text-neutral-900 dark:text-white">{{ $totalPermissions }}</span>
                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Permissions') }}</span>
                </div>
            </div>
        </div>

        <!-- Tablet/Desktop: grid summary cards -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Roles') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalRoles }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ __('With permissions') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $rolesWithPermissions }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Roles') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Roles that have at least one permission') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                    <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                    <span>{{ __('No permissions') }}</span>
                </div>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $rolesWithoutPermissions }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Roles') }}</span>
                </div>
                <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Roles without any permission') }}</p>
            </div>
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Permissions') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalPermissions }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.1000ms="search" :placeholder="__('Search role name')" />
            </div>
            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="ghost" class="btn-ghost-accent" wire:click="$set('search','')">{{ __('Clear') }}</flux:button>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
                @forelse ($items as $role)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold text-neutral-900 dark:text-white">{{ $role->name }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Permissions') }}: {{ (int) $role->permissions_count }}
                                </div>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                #{{ str_pad($role->id, 3, '0', STR_PAD_LEFT) }}
                            </span>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            @can('roles.manage')
                                <flux:button size="sm" icon="pencil-square" variant="primary" class="flex-1 btn-accent" wire:click="openEditModal({{ $role->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:modal.trigger name="confirm-delete-role" class="flex-1">
                                    <flux:button size="sm" icon="trash" variant="danger" class="w-full" wire:click="confirmDelete({{ $role->id }})">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No roles found.') }}
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
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Role') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Permissions') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $role)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ $role->name }}</span>
                                            <span class="text-xs text-neutral-500 dark:text-neutral-400">#{{ $role->id }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                            {{ (int) $role->permissions_count }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @can('roles.manage')
                                                <flux:button
                                                    size="sm"
                                                    icon="pencil-square"
                                                    variant="primary"
                                                    class="btn-accent rounded-2xl shadow-sm transition"
                                                    wire:click="openEditModal({{ $role->id }})"
                                                >
                                                    {{ __('Edit') }}
                                                </flux:button>
                                                <flux:modal.trigger name="confirm-delete-role-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        icon="trash"
                                                        variant="danger"
                                                        class="rounded-2xl shadow-sm transition"
                                                        wire:click="confirmDelete({{ $role->id }})"
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
                                    <td colspan="3" class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ __('No roles found.') }}
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

    @php($selectedRole = $items->firstWhere('id', $confirmingDeleteId))

    <!-- Delete confirmation - mobile -->
    <flux:modal name="confirm-delete-role" focusable variant="flyout" position="bottom" class="rounded-t-3xl sm:rounded-xl">
        <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
            <div class="flex items-center justify-center">
                <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
            </div>

            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete this role?') }}</flux:heading>
                <flux:subheading>{{ __('This action cannot be undone.') }}</flux:subheading>
            </div>

            @if ($selectedRole)
                <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">{{ $selectedRole->name }}</p>
                    <p class="text-xs opacity-80">{{ __('Permissions') }}: {{ (int) $selectedRole->permissions_count }}</p>
                </div>
            @endif

            <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white/85 px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                <flux:modal.close>
                    <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Yes, delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete confirmation - desktop -->
    <flux:modal name="confirm-delete-role-desktop" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-lg">
        <div class="space-y-4 p-2">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete this role?') }}</flux:heading>
                <flux:subheading>{{ __('This action cannot be undone.') }}</flux:subheading>
            </div>

            @if ($selectedRole)
                <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">{{ $selectedRole->name }}</p>
                    <p class="text-xs opacity-80">{{ __('Permissions') }}: {{ (int) $selectedRole->permissions_count }}</p>
                </div>
            @endif

            <div class="mt-2 flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Yes, delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Create role modal -->
    <flux:modal name="create-role" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-4xl" closable="false">
        <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
            <div class="sticky top-0 z-10 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                <div>
                    <flux:heading size="lg">{{ __('Create Role') }}</flux:heading>
                    <flux:subheading>{{ __('Create a role and assign permissions.') }}</flux:subheading>
                </div>
                <flux:modal.close class="hidden sm:block">
                    <flux:button variant="ghost" icon="x-mark" class="inline-flex btn-ghost-neutral -mt-1" aria-label="{{ __('Close') }}" />
                </flux:modal.close>
            </div>

            <form id="create-role-form" wire:submit.prevent="save" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <flux:input wire:model.defer="form.name" :label="__('Role name')" required maxlength="255" />
                        @error('form.name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                        <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                            <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                            <ul class="mt-2 list-disc space-y-1 pl-4">
                                <li>{{ __('Use clear names like "Kasir", "Chef", or "Manager".') }}</li>
                                <li>{{ __('Access = can open menu/index. Manage = can create/edit/delete.') }}</li>
                                <li>{{ __('Keep Roles manage permission limited to trusted users.') }}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap items-center gap-3">
                                <flux:heading size="sm">{{ __('Permissions') }}</flux:heading>
                                <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-[11px] font-semibold text-neutral-600 dark:bg-white/5 dark:text-neutral-300">
                                    {{ count($form['permissions'] ?? []) }} {{ __('selected') }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    class="btn-ghost-accent"
                                    type="button"
                                    wire:click="selectAllPermissions"
                                >
                                    {{ __('Select all') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" class="btn-ghost-accent" type="button" wire:click="clearPermissions">{{ __('Clear') }}</flux:button>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                            <div class="max-h-[44dvh] overflow-y-auto pr-1">
                            <div class="space-y-4">
                                @foreach ($defaultPermissionGroups as $groupLabel => $permissionNames)
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ $groupLabel }}</p>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            @foreach ($permissionNames as $permName)
                                                <label class="flex items-center gap-2 rounded-xl border border-neutral-200/60 bg-white px-3 py-2 text-sm text-neutral-700 dark:border-neutral-800/60 dark:bg-neutral-950 dark:text-neutral-200">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $permName }}"
                                                        wire:model.defer="form.permissions"
                                                        class="h-4 w-4 rounded border-neutral-300 text-[color:var(--brand-accent)] focus:ring-[color:var(--brand-accent)] dark:border-neutral-700"
                                                    />
                                                    <span class="truncate">{{ $permName }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                @if ($extraPermissions->isNotEmpty())
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ __('Other') }}</p>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            @foreach ($extraPermissions as $perm)
                                                <label class="flex items-center gap-2 rounded-xl border border-neutral-200/60 bg-white px-3 py-2 text-sm text-neutral-700 dark:border-neutral-800/60 dark:bg-neutral-950 dark:text-neutral-200">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $perm->name }}"
                                                        wire:model.defer="form.permissions"
                                                        class="h-4 w-4 rounded border-neutral-300 text-[color:var(--brand-accent)] focus:ring-[color:var(--brand-accent)] dark:border-neutral-700"
                                                    />
                                                    <span class="truncate">{{ $perm->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @error('form.permissions.*') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hidden md:flex items-center justify-end gap-3 pt-2 px-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="create-role-form" variant="primary" icon="plus" class="btn-brand">{{ __('Create') }}</flux:button>
                </div>
            </form>

            <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                <div class="grid grid-cols-2 gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="create-role-form" variant="primary" icon="plus" class="btn-brand w-full">{{ __('Create') }}</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <!-- Edit role modal -->
    <flux:modal name="edit-role" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-4xl" closable="false">
        <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
            <div class="sticky top-0 z-10 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white/85 px-4 py-3 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                <div>
                    <flux:heading size="lg">{{ __('Edit Role') }}</flux:heading>
                    <flux:subheading>{{ __('Update role and its permissions.') }}</flux:subheading>
                </div>
                <flux:modal.close class="hidden sm:block">
                    <flux:button variant="ghost" icon="x-mark" class="inline-flex btn-ghost-neutral -mt-1" aria-label="{{ __('Close') }}" />
                </flux:modal.close>
            </div>

            <form id="edit-role-form" wire:submit.prevent="update" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] md:pb-0">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <flux:input wire:model.defer="form.name" :label="__('Role name')" required maxlength="255" />
                        @error('form.name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                        <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                            <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                            <ul class="mt-2 list-disc space-y-1 pl-4">
                                <li>{{ __('Access = can open menu/index. Manage = can create/edit/delete.') }}</li>
                                <li>{{ __('Remove manage permissions for read-only roles.') }}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap items-center gap-3">
                                <flux:heading size="sm">{{ __('Permissions') }}</flux:heading>
                                <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-[11px] font-semibold text-neutral-600 dark:bg-white/5 dark:text-neutral-300">
                                    {{ count($form['permissions'] ?? []) }} {{ __('selected') }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    class="btn-ghost-accent"
                                    type="button"
                                    wire:click="selectAllPermissions"
                                >
                                    {{ __('Select all') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" class="btn-ghost-accent" type="button" wire:click="clearPermissions">{{ __('Clear') }}</flux:button>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                            <div class="max-h-[44dvh] overflow-y-auto pr-1">
                            <div class="space-y-4">
                                @foreach ($defaultPermissionGroups as $groupLabel => $permissionNames)
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ $groupLabel }}</p>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            @foreach ($permissionNames as $permName)
                                                <label class="flex items-center gap-2 rounded-xl border border-neutral-200/60 bg-white px-3 py-2 text-sm text-neutral-700 dark:border-neutral-800/60 dark:bg-neutral-950 dark:text-neutral-200">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $permName }}"
                                                        wire:model.defer="form.permissions"
                                                        class="h-4 w-4 rounded border-neutral-300 text-[color:var(--brand-accent)] focus:ring-[color:var(--brand-accent)] dark:border-neutral-700"
                                                    />
                                                    <span class="truncate">{{ $permName }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                @if ($extraPermissions->isNotEmpty())
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ __('Other') }}</p>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            @foreach ($extraPermissions as $perm)
                                                <label class="flex items-center gap-2 rounded-xl border border-neutral-200/60 bg-white px-3 py-2 text-sm text-neutral-700 dark:border-neutral-800/60 dark:bg-neutral-950 dark:text-neutral-200">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $perm->name }}"
                                                        wire:model.defer="form.permissions"
                                                        class="h-4 w-4 rounded border-neutral-300 text-[color:var(--brand-accent)] focus:ring-[color:var(--brand-accent)] dark:border-neutral-700"
                                                    />
                                                    <span class="truncate">{{ $perm->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @error('form.permissions.*') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hidden md:flex items-center justify-end gap-3 pt-2 px-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="edit-role-form" variant="primary" icon="check" class="btn-brand">{{ __('Update') }}</flux:button>
                </div>
            </form>

            <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white/90 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/80">
                <div class="grid grid-cols-2 gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="edit-role-form" variant="primary" icon="check" class="btn-brand w-full">{{ __('Update') }}</flux:button>
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
        x-on:roles-toast.window="handle($event)"
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
