<?php

use App\Models\Meja;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [
        'nomor_meja' => '',
        'qr_token' => '',
        'status' => 'kosong',
    ];

    public function mount(): void
    {
        if (blank($this->form['qr_token'])) {
            $this->form['qr_token'] = $this->generateUniqueToken();
        }
    }

    public function generateUniqueToken(): string
    {
        do {
            $token = Str::upper(Str::random(10));
        } while (Meja::where('qr_token', $token)->exists());

        return $token;
    }

    public function regenerateToken(): void
    {
        $this->form['qr_token'] = $this->generateUniqueToken();
    }

    public function save(): void
    {
        $validated = validator($this->form, [
            'nomor_meja' => ['required', 'string', 'max:10'],
            'qr_token' => ['required', 'string', 'max:100', 'unique:mejas,qr_token'],
            'status' => ['required', 'in:kosong,terisi,reservasi'],
        ])->validate();

        Meja::create($validated);

        session()->flash('meja_toast', __('Table created successfully.'));
        $this->redirectRoute('meja.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1">{{ __('Create Table') }}</flux:heading>
        <flux:link :href="route('meja.index')" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <form wire:submit="save" class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-1">
                <flux:input wire:model="form.nomor_meja" :label="__('Table Number')" required maxlength="10" />
                <div class="mt-4">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Status') }}</label>
                    <select wire:model="form.status" class="mt-2 w-full rounded-md border border-neutral-300 bg-white p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                        <option value="kosong">{{ __('Empty') }}</option>
                        <option value="terisi">{{ __('Occupied') }}</option>
                        <option value="reservasi">{{ __('Reserved') }}</option>
                    </select>
                </div>
            </div>

            <div class="md:col-span-1">
                <flux:input wire:model="form.qr_token" :label="__('QR Token')" readonly />
                <div class="mt-2">
                    <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="regenerateToken">{{ __('Regenerate') }}</flux:button>
                    <flux:text variant="subtle" class="text-xs">{{ __('Used in URL: /order/{token}') }}</flux:text>
                </div>
            </div>

            <div class="md:col-span-1">
                <flux:heading size="md">{{ __('QR Preview') }}</flux:heading>
                <div class="mt-2 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700 bg-white dark:bg-neutral-900">
                    @if (!empty($form['qr_token']))
                        <img alt="QR" class="mx-auto" src="{{ route('meja.qr', ['token' => $form['qr_token'], 'size' => 256, 'format' => 'svg'], false) }}" />
                    @else
                        <div class="text-xs text-red-600">QR generation failed</div>
                    @endif
                </div>
                <div class="mt-3">
                    <flux:link :href="route('meja.qr', ['token' => $form['qr_token'] ?? null, 'download' => 1], false)" target="_blank">
                        <flux:button icon="arrow-down-tray" variant="outline" class="btn-outline-accent">{{ __('Download PNG') }}</flux:button>
                    </flux:link>
                </div>
            </div>

            <div class="md:col-span-3 flex items-center gap-3">
                <flux:button type="submit" variant="primary" class="btn-brand">{{ __('Create') }}</flux:button>
                <flux:link :href="route('meja.index')" wire:navigate>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:link>
            </div>
        </form>
    </div>
</section>
