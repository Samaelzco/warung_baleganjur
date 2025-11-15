<?php

use App\Models\Pajak;
use Livewire\Volt\Component;

new class extends Component {
    public Pajak $pajak;

    public array $form = [
        'nama' => '',
        'persentase' => '',
        'is_active' => true,
    ];

    public function mount(Pajak $pajak): void
    {
        $this->pajak = $pajak;
        $this->form = [
            'nama' => $pajak->nama,
            'persentase' => $pajak->persentase,
            'is_active' => (bool) $pajak->is_active,
        ];
    }

    public function update(): void
    {
        $validated = validator($this->form, [
            'nama' => ['required', 'string', 'max:100', 'unique:pajaks,nama,' . $this->pajak->id],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ])->validate();

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        $this->pajak->update($validated);

        session()->flash('pajak_toast', __('Tax updated successfully.'));
        $this->redirectRoute('pajak.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1">{{ __('Edit Tax') }}</flux:heading>
        <flux:link :href="route('pajak.index')" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <form wire:submit="update" class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-1">
                <flux:input
                    wire:model="form.nama"
                    :label="__('Name')"
                    required
                    maxlength="100"
                />
            </div>
            <div class="md:col-span-1">
                <flux:input
                    wire:model="form.persentase"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    :label="__('Percentage (%)')"
                    required
                />
            </div>
            <div class="md:col-span-2">
                <flux:checkbox
                    wire:model="form.is_active"
                    :label="__('Active')"
                />
            </div>

            <div class="md:col-span-2 flex items-center gap-3">
                <flux:button type="submit" variant="primary" class="btn-brand">{{ __('Update') }}</flux:button>
                <flux:link :href="route('pajak.index')" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                </flux:link>
            </div>
        </form>
    </div>
</section>

