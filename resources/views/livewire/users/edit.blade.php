<?php

use App\Models\User;
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

        session()->flash('users_toast', __('User updated successfully.'));
        $this->redirectRoute('users.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    @php($roles = Role::query()->select(['id', 'name'])->orderBy('name')->get())

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1">{{ __('Edit User') }}</flux:heading>
        <flux:link :href="route('users.index')" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <form wire:submit="update" class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-1 space-y-4">
                <flux:input wire:model="form.name" :label="__('Name')" required maxlength="255" />
                <flux:input wire:model="form.email" type="email" :label="__('Email')" required maxlength="255" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.password" type="password" :label="__('New Password')" />
                    <flux:input wire:model="form.password_confirmation" type="password" :label="__('Confirm Password')" />
                </div>

                <div data-flux-field>
                    <label data-flux-label>{{ __('Role') }}</label>
                    <select
                        wire:model="form.role"
                        class="mt-1 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                    >
                        <option value="">{{ __('No role') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="md:col-span-1 flex flex-col justify-between space-y-4">
                <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 text-sm text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                    <flux:heading size="sm">{{ __('Notes') }}</flux:heading>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-xs">
                        <li>{{ __('Leave password empty to keep the current password.') }}</li>
                        <li>{{ __('Changing role will replace existing roles (single-role mode).') }}</li>
                    </ul>
                </div>
            </div>

            <div class="md:col-span-2 flex items-center gap-3">
                <flux:button type="submit" variant="primary" class="btn-brand">{{ __('Update') }}</flux:button>
                <flux:link :href="route('users.index')" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                </flux:link>
            </div>
        </form>
    </div>
</section>
