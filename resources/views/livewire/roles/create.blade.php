<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component {
    public array $form = [
        'name' => '',
        'permissions' => [],
    ];

    public function save(): void
    {
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

        Cache::forget('admin:roles:stats:v1');

        session()->flash('roles_toast', __('Role created successfully.'));
        $this->redirectRoute('roles.index', navigate: true);
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
                'addon.access',
                'pajak.access',
                'diskon.access',
                'pesanan.access',
                'waiting-list.access',
                'kitchen.access',
                'pembayaran.access',
                'users.access',
                'roles.access',
            ],
            __('Manage') => [
                'meja.manage',
                'kategori.manage',
                'menu.manage',
                'addon.manage',
                'pajak.manage',
                'diskon.manage',
                'pesanan.manage',
                'waiting-list.manage',
                'kitchen.manage',
                'pembayaran.manage',
                'users.manage',
                'roles.manage',
            ],
        ];
    }
}; ?>

<section class="w-full space-y-6">
    @php
        $defaultPermissionGroups = $this->permissionGroups();
        $defaultPermissionNames = collect($defaultPermissionGroups)->flatten()->values();
        $extraPermissions = Permission::query()
            ->select(['id', 'name'])
            ->whereNotIn('name', $defaultPermissionNames->all())
            ->orderBy('name')
            ->get();
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Create Role') }}</flux:heading>
            <flux:subheading>{{ __('Create a role and assign permissions.') }}</flux:subheading>
        </div>
        <flux:link :href="route('roles.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <form id="create-role-form" wire:submit.prevent="save" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
            <div class="grid gap-6 lg:grid-cols-5">
                <div class="space-y-4 lg:col-span-2">
                    <flux:input wire:model.defer="form.name" :label="__('Role name')" required maxlength="255" />
                    @error('name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                    <div class="rounded-2xl border border-neutral-200/70 bg-neutral-50/60 p-4 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-950/40 dark:text-neutral-400">
                        <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                        <ul class="mt-2 list-disc space-y-1 pl-4">
                            <li>{{ __('Use clear names like "Kasir", "Chef", or "Manager".') }}</li>
                            <li>{{ __('Access = can open menu/index. Manage = can create/edit/delete.') }}</li>
                            <li>{{ __('Keep Roles manage permission limited to trusted users.') }}</li>
                        </ul>
                    </div>
                </div>

                <div class="space-y-3 lg:col-span-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap items-center gap-3">
                            <flux:heading size="sm">{{ __('Permissions') }}</flux:heading>
                            <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-[11px] font-semibold text-neutral-600 dark:bg-white/5 dark:text-neutral-300">
                                {{ count($form['permissions'] ?? []) }} {{ __('selected') }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <flux:button size="sm" variant="ghost" class="btn-ghost-accent" type="button" wire:click="selectAllPermissions">{{ __('Select all') }}</flux:button>
                            <flux:button size="sm" variant="ghost" class="btn-ghost-accent" type="button" wire:click="clearPermissions">{{ __('Clear') }}</flux:button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                        <div class="max-h-[58dvh] overflow-y-auto pr-1">
                            <div class="space-y-4">
                                @foreach ($defaultPermissionGroups as $groupLabel => $permissionNames)
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ $groupLabel }}</p>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            @foreach ($permissionNames as $permName)
                                                <label class="flex items-center gap-2 rounded-xl border border-neutral-200/60 bg-white px-3 py-2 text-sm text-neutral-700 dark:border-neutral-800/60 dark:bg-neutral-950 dark:text-neutral-200">
                                                    <input type="checkbox" value="{{ $permName }}" wire:model.live="form.permissions" class="h-4 w-4 rounded border-neutral-300 text-[color:var(--brand-accent)] focus:ring-[color:var(--brand-accent)] dark:border-neutral-700" />
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
                                                    <input type="checkbox" value="{{ $perm->name }}" wire:model.live="form.permissions" class="h-4 w-4 rounded border-neutral-300 text-[color:var(--brand-accent)] focus:ring-[color:var(--brand-accent)] dark:border-neutral-700" />
                                                    <span class="truncate">{{ $perm->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @error('permissions.*') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <flux:link :href="route('roles.index', [], false)" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
                </flux:link>
                <flux:button type="submit" form="create-role-form" variant="primary" icon="plus" class="btn-brand w-full justify-center sm:w-auto">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </form>
</section>
