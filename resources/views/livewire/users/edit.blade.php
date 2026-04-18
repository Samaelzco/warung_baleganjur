<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new class extends Component {
    public User $user;

    public array $form = [
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
        'role' => '',
    ];

    public function mount(User $user): void
    {
        $this->user = $user->loadMissing('roles');

        $this->form = [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => $this->user->roles->pluck('name')->first() ?? '',
        ];
    }

    public function update(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $this->user->id],
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

        $this->user->update($payload);

        if (!empty($validated['role'])) {
            $this->user->syncRoles([$validated['role']]);
        } else {
            $this->user->syncRoles([]);
        }

        Cache::forget('admin:users:stats:v1');

        session()->flash('users_toast', __('User updated successfully.'));
        $this->redirectRoute('users.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    @php($roles = Role::query()->select(['id', 'name'])->orderBy('name')->get())

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Edit User') }}</flux:heading>
            <flux:subheading>{{ __('Update profile and access for this user.') }}</flux:subheading>
        </div>
        <flux:link :href="route('users.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <form id="edit-user-form" wire:submit.prevent="update" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <flux:input wire:model.defer="form.name" :label="__('Name')" required maxlength="255" />
                    @error('form.name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                    <flux:input wire:model.defer="form.email" type="email" :label="__('Email')" required maxlength="255" />
                    @error('form.email') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                    <flux:select wire:model.defer="form.role" :label="__('Role')">
                        <option value="">{{ __('No role') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                        @endforeach
                    </flux:select>
                    @error('form.role') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        <flux:input wire:model.defer="form.password" type="password" :label="__('New Password')" />
                        <flux:input wire:model.defer="form.password_confirmation" type="password" :label="__('Confirm Password')" />
                    </div>
                    @error('form.password') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                    <div class="rounded-2xl border border-neutral-200/70 bg-neutral-50/60 p-4 dark:border-neutral-800 dark:bg-neutral-950/40">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100 text-sm font-semibold text-neutral-800 ring-1 ring-neutral-200 dark:bg-neutral-800/60 dark:text-neutral-100 dark:ring-neutral-700">
                                {{ $user->initials() }}
                            </span>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-neutral-900 dark:text-white">{{ $form['name'] ?: $user->name }}</div>
                                <div class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $form['email'] ?: $user->email }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <flux:link :href="route('users.index', [], false)" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
                </flux:link>
                <flux:button type="submit" form="edit-user-form" variant="primary" icon="check" class="btn-brand w-full justify-center sm:w-auto">{{ __('Update') }}</flux:button>
            </div>
        </div>
    </form>
</section>
