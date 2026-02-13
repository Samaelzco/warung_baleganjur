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
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <form wire:submit="save" class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-1">
                <flux:input wire:model="form.nomor_meja" :label="__('Table Number')" required maxlength="10" />
                <div class="mt-4" data-flux-field>
                    <label data-flux-label>{{ __('Status') }}</label>
                    <flux:select wire:model="form.status">
                        <option value="kosong">{{ __('Empty') }}</option>
                        <option value="terisi">{{ __('Occupied') }}</option>
                        <option value="reservasi">{{ __('Reserved') }}</option>
                    </flux:select>
                </div>
            </div>

            <div class="md:col-span-1">
                <flux:input wire:model="form.qr_token" :label="__('QR Token')" readonly />
                <div class="mt-2">
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent" icon="arrow-path" wire:click="regenerateToken">{{ __('Regenerate') }}</flux:button>
                    <flux:text variant="subtle" class="text-xs">{{ __('Used in URL: /{token}') }}</flux:text>
                </div>
            </div>

            <div class="md:col-span-1">
                <flux:heading size="md">{{ __('QR Preview') }}</flux:heading>
                <div class="mt-2 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700 bg-white dark:bg-neutral-900">
                    @if (!empty($form['qr_token']))
                        <img alt="QR" class="mx-auto" src="{{ route('meja.qr', ['token' => $form['qr_token'], 'size' => 256, 'format' => 'svg'], false) }}" />
                    @else
                        <div class="text-xs text-red-600">{{ __('QR generation failed') }}</div>
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
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                </flux:link>
            </div>
        </form>
    </div>
</section>
