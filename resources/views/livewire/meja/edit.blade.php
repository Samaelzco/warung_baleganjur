<?php

use App\Models\Meja;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;

new class extends Component {
    public Meja $meja;

    public array $form = [
        'nomor_meja' => '',
        'qr_token' => '',
        'status' => 'kosong',
        'kapasitas' => 4,
    ];

    public function mount(Meja $meja): void
    {
        $this->meja = $meja;
        $this->form = [
            'nomor_meja' => $meja->nomor_meja,
            'qr_token' => $meja->qr_token,
            'status' => $meja->status,
            'kapasitas' => $meja->kapasitas ?? 4,
        ];
    }

    public function regenerateToken(): void
    {
        do {
            $token = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10));
        } while (Meja::where('qr_token', $token)->where('id', '!=', $this->meja->id)->exists());

        $this->form['qr_token'] = $token;
    }

    public function update(): void
    {
        $validated = validator($this->form, [
            'nomor_meja' => ['required', 'string', 'max:10'],
            'qr_token' => ['required', 'string', 'max:100', 'unique:mejas,qr_token,' . $this->meja->id],
            'status' => ['required', 'in:kosong,terisi,reservasi'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:99'],
        ])->validate();

        $this->meja->update($validated);
        Cache::forget('admin:meja:stats:v1');

        session()->flash('meja_toast', __('Table updated successfully.'));
        $this->redirectRoute('meja.index', navigate: true);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Edit Table') }}</flux:heading>
            <flux:subheading>{{ __('Update the details for this table.') }}</flux:subheading>
        </div>
        <flux:link :href="route('meja.index', [], false)" wire:navigate>
            <flux:button variant="ghost" icon="arrow-left" class="btn-ghost-accent">{{ __('Back') }}</flux:button>
        </flux:link>
    </div>

    <form wire:submit.prevent="update" class="space-y-6">
        <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900 sm:p-5">
            <div class="grid gap-6 md:grid-cols-5">
                <div class="space-y-4 md:col-span-3">
                    <flux:input wire:model.defer="form.nomor_meja" :label="__('Table Number')" required maxlength="10" help="{{ __('Up to 10 characters, e.g. A12') }}" />
                    @error('form.nomor_meja')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <flux:input wire:model.defer="form.kapasitas" type="number" min="1" max="99" :label="__('Capacity')" required />
                    @error('form.kapasitas')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <div data-flux-field>
                        <flux:select wire:model.defer="form.status" :label="__('Status')">
                            <option value="kosong">{{ __('Empty') }}</option>
                            <option value="terisi">{{ __('Occupied') }}</option>
                            <option value="reservasi">{{ __('Reserved') }}</option>
                        </flux:select>
                        @error('form.status')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="space-y-4 md:col-span-2 md:pl-2">
                    <flux:input wire:model.defer="form.qr_token" :label="__('QR Token')" readonly class="font-mono" help="{{ __('Unique code used in the QR link') }}" />
                    @error('form.qr_token')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-col gap-2 rounded-2xl border border-neutral-200/70 bg-neutral-50/70 px-3 py-2 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-950/60 dark:text-neutral-300 sm:flex-row sm:items-center sm:justify-between">
                        <span class="break-all">{{ __('Used in URL: /{token}') }}</span>
                        <flux:button size="xs" variant="ghost" class="btn-ghost-accent self-start sm:self-auto" icon="arrow-path" type="button" wire:click="regenerateToken">{{ __('Regenerate') }}</flux:button>
                    </div>

                    <div class="space-y-3 rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                        <div class="flex items-center justify-between gap-2">
                            <flux:heading size="sm">{{ __('QR Preview') }}</flux:heading>
                            <flux:link :href="route('meja.print', ['ids' => $meja->id], false)" target="_blank">
                                <flux:button size="xs" icon="printer" variant="ghost" class="btn-ghost-accent whitespace-nowrap">{{ __('Print (PDF)') }}</flux:button>
                            </flux:link>
                        </div>
                        <div class="rounded-xl border border-dashed border-neutral-200 bg-white p-3 text-center dark:border-neutral-700 dark:bg-neutral-900">
                            @if (!empty($form['qr_token']))
                                <img alt="QR" class="mx-auto h-40 w-40 sm:h-44 sm:w-44" src="{{ route('meja.qr', ['token' => $form['qr_token'], 'size' => 320, 'format' => 'svg'], false) }}" />
                            @else
                                <div class="text-xs text-red-600">{{ __('QR generation failed') }}</div>
                            @endif
                        </div>
                        <flux:text variant="subtle" class="text-xs">{{ __('Preview for printing and testing') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 border-t border-neutral-200 bg-white/90 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 dark:shadow-black/20 sm:static sm:mx-0 sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:px-0 sm:pt-0 sm:pb-0 sm:shadow-none sm:backdrop-blur-none">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <flux:link :href="route('meja.index', [], false)" wire:navigate>
                    <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full justify-center sm:w-auto">{{ __('Cancel') }}</flux:button>
                </flux:link>
                <flux:button type="submit" variant="primary" icon="check" class="btn-brand w-full justify-center sm:w-auto">{{ __('Update') }}</flux:button>
            </div>
        </div>
    </form>
</section>
