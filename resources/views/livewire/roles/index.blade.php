<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new class extends Component
{
    use WithPagination;

    public ?int $confirmingDeleteId = null;

    public string $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->abortIfProtectedRole($id);
        $this->confirmingDeleteId = $id;
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if (! $this->confirmingDeleteId) {
            return;
        }

        $this->abortIfProtectedRole($this->confirmingDeleteId);

        $isAssigned = DB::table('model_has_roles')
            ->where('role_id', $this->confirmingDeleteId)
            ->exists();

        if ($isAssigned) {
            $this->dispatch('modal-close', name: 'confirm-delete-role');
            $this->dispatch('modal-close', name: 'confirm-delete-role-desktop');
            $this->dispatch('roles-toast', message: __('This role cannot be deleted because it is still assigned to users.'));
            $this->confirmingDeleteId = null;

            return;
        }

        Role::whereKey($this->confirmingDeleteId)->delete();
        Cache::forget('admin:roles:stats:v2');
        $this->confirmingDeleteId = null;

        $this->dispatch('modal-close', name: 'confirm-delete-role');
        $this->dispatch('modal-close', name: 'confirm-delete-role-desktop');
        $this->dispatch('roles-toast', message: __('Role deleted successfully.'));
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('roles.manage'), 403);
    }

    protected function abortIfProtectedRole(int $id): void
    {
        abort_if(Role::whereKey($id)->where('name', 'Super Admin')->exists(), 404);
    }
}; ?>

<section class="w-full">
    @php
        $query = Role::query()
            ->select(['id', 'name'])
            ->withCount('permissions')
            ->where('name', '!=', 'Super Admin')
            ->orderBy('name');

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $items = $query->paginate(10);

        $stats = Cache::remember('admin:roles:stats:v2', 10, fn () => [
            'roles' => Role::query()->where('name', '!=', 'Super Admin')->count(),
            'permissions' => \Spatie\Permission\Models\Permission::query()->count(),
            'roles_with_permissions' => Role::query()
                ->where('name', '!=', 'Super Admin')
                ->has('permissions')
                ->count(),
        ]);

        $totalRoles = (int) ($stats['roles'] ?? 0);
        $totalPermissions = (int) ($stats['permissions'] ?? 0);
        $rolesWithPermissions = (int) ($stats['roles_with_permissions'] ?? 0);
        $rolesWithoutPermissions = max(0, $totalRoles - $rolesWithPermissions);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Roles') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('roles.manage')
                    <flux:link :href="route('roles.create', [], false)" wire:navigate>
                        <flux:button icon="plus" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                    </flux:link>
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
        <div class="flex items-center gap-2 sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input wire:model.live.debounce.1000ms="search" :placeholder="__('Search role name')" />
            </div>
            <flux:button
                size="sm"
                variant="ghost"
                class="btn-ghost-accent whitespace-nowrap shrink-0"
                wire:click="$wire.set('search','')"
            >
                {{ __('Clear') }}
            </flux:button>
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
                                <flux:link class="flex-1" :href="route('roles.edit', $role, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:link>
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

        <!-- Tablet cards -->
        <div class="hidden sm:block lg:hidden">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse ($items as $role)
                    <div class="flex h-full flex-col rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-base font-semibold text-neutral-900 dark:text-white">{{ $role->name }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Permissions') }}: {{ (int) $role->permissions_count }}
                                </div>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                #{{ str_pad($role->id, 3, '0', STR_PAD_LEFT) }}
                            </span>
                        </div>

                        @can('roles.manage')
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <flux:link :href="route('roles.edit', $role, false)" wire:navigate>
                                    <flux:button size="sm" icon="pencil-square" variant="primary" class="w-full btn-accent">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:link>
                                <flux:modal.trigger name="confirm-delete-role-desktop">
                                    <flux:button size="sm" icon="trash" variant="danger" class="w-full" wire:click="confirmDelete({{ $role->id }})">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="sm:col-span-2 rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No roles found.') }}
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
                                <th class="w-[34%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Role') }}</th>
                                <th class="w-[26%] border-b border-r border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Permissions') }}</th>
                                <th class="w-[40%] border-b border-neutral-200/80 px-6 py-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:border-neutral-800/70 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $role)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ $role->name }}</span>
                                    </td>
                                    <td class="border-r border-neutral-200/80 px-6 py-4 align-middle text-center dark:border-neutral-800/70">
                                        <span class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-200 dark:ring-neutral-700">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                            {{ (int) $role->permissions_count }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle text-center">
                                        <div class="mx-auto grid w-full max-w-[220px] grid-cols-1 gap-2 xl:grid-cols-2">
                                            @can('roles.manage')
                                                <flux:link :href="route('roles.edit', $role, false)" wire:navigate>
                                                    <flux:button
                                                        size="sm"
                                                        icon="pencil-square"
                                                        variant="primary"
                                                        class="btn-accent w-full rounded-2xl shadow-sm transition justify-center"
                                                    >
                                                        {{ __('Edit') }}
                                                    </flux:button>
                                                </flux:link>
                                                <flux:modal.trigger name="confirm-delete-role-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        icon="trash"
                                                        variant="danger"
                                                        class="w-full rounded-2xl shadow-sm transition justify-center"
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
    <flux:modal name="confirm-delete-role" focusable variant="flyout" position="bottom" :closable="false" class="rounded-t-3xl sm:rounded-xl">
        <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
            <div class="flex items-center justify-center">
                <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
            </div>

            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete this role?') }}</flux:heading>
                <flux:subheading>{{ __('This action cannot be undone. Roles that are still assigned to users cannot be deleted.') }}</flux:subheading>
            </div>

            @if ($selectedRole)
                <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">{{ $selectedRole->name }}</p>
                    <p class="text-xs opacity-80">{{ __('Permissions') }}: {{ (int) $selectedRole->permissions_count }}</p>
                </div>
            @endif

            <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white/85 px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                <flux:modal.close>
                    <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
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
                <flux:subheading>{{ __('This action cannot be undone. Roles that are still assigned to users cannot be deleted.') }}</flux:subheading>
            </div>

            @if ($selectedRole)
                <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">{{ $selectedRole->name }}</p>
                    <p class="text-xs opacity-80">{{ __('Permissions') }}: {{ (int) $selectedRole->permissions_count }}</p>
                </div>
            @endif

            <div class="mt-2 flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" wire:click="$wire.set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Yes, delete') }}</flux:button>
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

@if (session('roles_toast'))
    <script>
        window.addEventListener('load', () => {
            try {
                const message = @js(session('roles_toast'));
                window.dispatchEvent(new CustomEvent('roles-toast', { detail: { message } }));
            } catch (e) {}
        });
    </script>
@endif
