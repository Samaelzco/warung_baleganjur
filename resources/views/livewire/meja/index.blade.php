<?php

use App\Models\Meja;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    public ?int $confirmingDeleteId = null;
    public ?int $editingId = null;
    public array $form = [];
    public string $search = '';
    public string $statusFilter = 'all';
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->resetCreateForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function openCreateModal(): void
    {
        $this->resetCreateForm();
        $this->dispatch('modal-show', name: 'create-meja');
    }

    public function regenerateToken(): void
    {
        $this->form['qr_token'] = $this->generateUniqueToken();
    }

    public function openEditModal(int $id): void
    {
        $meja = Meja::findOrFail($id);
        $this->editingId = $meja->id;
        $this->form = [
            'nomor_meja' => $meja->nomor_meja,
            'qr_token' => $meja->qr_token,
            'status' => $meja->status,
        ];

        $this->dispatch('modal-show', name: 'edit-meja');
    }

    public function save(): void
    {
        $validated = validator($this->form, [
            'nomor_meja' => ['required', 'string', 'max:10'],
            'qr_token' => ['required', 'string', 'max:100', 'unique:mejas,qr_token'],
            'status' => ['required', 'in:kosong,terisi,reservasi'],
        ])->validate();

        Meja::create($validated);

        $this->resetCreateForm();
        $this->dispatch('modal-close', name: 'create-meja');
        $this->dispatch('meja-toast', message: __('Table created successfully.'));
    }

    public function update(): void
    {
        if (!$this->editingId) return;

        $validated = validator($this->form, [
            'nomor_meja' => ['required', 'string', 'max:10'],
            'qr_token' => ['required', 'string', 'max:100', 'unique:mejas,qr_token,' . $this->editingId],
            'status' => ['required', 'in:kosong,terisi,reservasi'],
        ])->validate();

        Meja::where('id', $this->editingId)->update($validated);

        $this->editingId = null;
        $this->dispatch('modal-close', name: 'edit-meja');
        $this->dispatch('meja-toast', message: __('Table updated successfully.'));
    }

    public function delete(): void
    {
        if ($this->confirmingDeleteId) {
            Meja::where('id', $this->confirmingDeleteId)->delete();
            $this->confirmingDeleteId = null;
            $this->dispatch('modal-close', name: 'confirm-delete-meja');
            $this->dispatch('meja-toast', message: __('Table deleted successfully.'));
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    protected function resetCreateForm(): void
    {
        $this->form = [
            'nomor_meja' => '',
            'qr_token' => $this->generateUniqueToken(),
            'status' => 'kosong',
        ];
    }

    protected function generateUniqueToken(): string
    {
        do {
            $token = Str::upper(Str::random(10));
        } while (Meja::where('qr_token', $token)->exists());

        return $token;
    }

}; ?>

<section class="w-full">
    @php
        $query = \App\Models\Meja::query();
        if (!empty($search)) {
            $query->where(function ($sub) use ($search) {
                $sub->where('nomor_meja', 'like', '%'.$search.'%')
                    ->orWhere('qr_token', 'like', '%'.$search.'%');
            });
        }
        if ($statusFilter !== 'all' && !empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }
        $items = $query->orderBy('nomor_meja')->paginate(5);
        $statusMeta = [
            'kosong' => [
                'label' => __('Empty'),
                'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800/60',
                'dot' => 'bg-emerald-500',
                'hint' => __('Ready to welcome guests'),
            ],
            'terisi' => [
                'label' => __('Occupied'),
                'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800/60',
                'dot' => 'bg-amber-500',
                'hint' => __('Currently in use'),
            ],
            'reservasi' => [
                'label' => __('Reserved'),
                'badge' => 'bg-purple-50 text-purple-700 ring-1 ring-purple-100 dark:bg-purple-900/40 dark:text-purple-200 dark:ring-purple-800/60',
                'dot' => 'bg-purple-500',
                'hint' => __('Booked in advance'),
            ],
        ];
        $statusCounts = \App\Models\Meja::query()
            ->select('status')
            ->selectRaw('count(*) as agg')
            ->groupBy('status')
            ->pluck('agg','status');
        $totalCount = \App\Models\Meja::count();
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Tables') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                <flux:link :href="route('meja.print', [], false)" wire:navigate>
                    <flux:button icon="printer" variant="ghost">{{ __('Print All QR') }}</flux:button>
                </flux:link>
                <flux:button icon="plus" variant="primary" class="btn-brand" wire:click="openCreateModal">{{ __('Create') }}</flux:button>
            </div>
        </div>

        <!-- Mobile: horizontal scroll chips -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                    <span class="font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Tables') }}</span>
                </div>
                @foreach ($statusMeta as $key => $meta)
                    <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                            <span class="text-neutral-600 dark:text-neutral-300">{{ $meta['label'] }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-white">{{ $statusCounts->get($key, 0) }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Tablet/Desktop: grid summary cards -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Tables') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>
            @foreach ($statusMeta as $key => $meta)
                <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                    <div class="flex items-center gap-2 text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">
                        <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                        <span>{{ $meta['label'] }}</span>
                    </div>
                    <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                        <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $statusCounts->get($key, 0) }}</span>
                        <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('Tables') }}</span>
                    </div>
                    <p class="mt-1 text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ $meta['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <!-- Search and filter -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.1000ms="search" :placeholder="__('Search table number or token')" />
            </div>
            <div class="flex items-center gap-2">
                <select wire:model.live="statusFilter" class="rounded-full border border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <option value="all">{{ __('All') }}</option>
                    <option value="kosong">{{ __('Empty') }}</option>
                    <option value="terisi">{{ __('Occupied') }}</option>
                    <option value="reservasi">{{ __('Reserved') }}</option>
                </select>
                <flux:button size="sm" variant="ghost" wire:click="$set('search','');$set('statusFilter','all')">{{ __('Clear') }}</flux:button>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
                @forelse ($items as $m)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold text-neutral-900 dark:text-white">{{ __('Table') }} {{ $m->nomor_meja }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $m->created_at?->format('d M Y') }}</div>
                            </div>
                            @php($status = $m->status)
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusMeta[$status]['badge'] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                <span class="h-2 w-2 rounded-full {{ $statusMeta[$status]['dot'] ?? 'bg-neutral-400' }}"></span>
                                {{ $statusMeta[$status]['label'] ?? ucfirst($status) }}
                            </span>
                        </div>

                        <div class="mt-3 flex items-center gap-3">
                            <div class="inline-flex items-center justify-center rounded-xl border border-neutral-200/70 bg-white p-1 shadow-sm dark:border-neutral-800/60 dark:bg-neutral-950">
                                <img alt="QR" class="h-24 w-24 rounded-lg border border-white/70 bg-white object-contain dark:border-neutral-800" src="{{ route('meja.qr', ['token' => $m->qr_token, 'size' => 192, 'format' => 'svg'], false) }}" />
                            </div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ __('Scan to order') }}</p>
                                <p>{{ __('Last updated') }} {{ $m->updated_at?->diffForHumans() }}</p>
                            </div>
                        </div>

                        <div class="mt-3 inline-flex items-center gap-2 rounded-2xl border border-neutral-200/80 bg-neutral-50 px-3 py-2 font-mono text-xs tracking-wide text-neutral-600 dark:border-neutral-800/70 dark:bg-neutral-900/60 dark:text-neutral-300">
                            <span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($m->qr_token, 12, '...')) }}</span>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <flux:link class="flex-1" :href="route('meja.qr', ['token' => $m->qr_token, 'download' => 1], false)">
                                <flux:button size="sm" icon="arrow-down-tray" variant="ghost" class="w-full btn-ghost-accent">{{ __('Download PNG') }}</flux:button>
                            </flux:link>
                            <flux:button size="sm" icon="pencil-square" variant="primary" class="flex-1 btn-accent" wire:click="openEditModal({{ $m->id }})">{{ __('Edit') }}</flux:button>
                            <flux:modal.trigger name="confirm-delete-meja" class="flex-1">
                                <flux:button size="sm" icon="trash" variant="danger" class="w-full" wire:click="confirmDelete({{ $m->id }})">{{ __('Delete') }}</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-6 text-center text-sm text-neutral-500 dark:border-neutral-800/70 dark:bg-neutral-900 dark:text-neutral-400">
                        {{ __('No data') }}
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
                            <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('ID') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Table') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Status') }}</th>
                            <th class="hidden lg:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('QR Code') }}</th>
                            <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('QR Token') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse($items as $m)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($m->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ __('Table') }} {{ $m->nomor_meja }}</span>
                                            <span class="hidden sm:block text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $m->created_at?->format('d M Y') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        @php($status = $m->status)
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $statusMeta[$status]['badge'] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200' }}">
                                            <span class="h-2 w-2 rounded-full {{ $statusMeta[$status]['dot'] ?? 'bg-neutral-400' }}"></span>
                                            {{ $statusMeta[$status]['label'] ?? ucfirst($status) }}
                                        </span>
                                    </td>
                                    <td class="hidden lg:table-cell px-6 py-4 align-middle">
                                        <div class="flex items-center gap-4">
                                            <div class="inline-flex items-center justify-center rounded-2xl border border-neutral-200/70 bg-white p-1 shadow-sm dark:border-neutral-800/60 dark:bg-neutral-950">
                                                <img alt="QR" class="h-16 w-16 sm:h-20 sm:w-20 rounded-xl border border-white/70 bg-white object-contain dark:border-neutral-800" src="{{ route('meja.qr', ['token' => $m->qr_token, 'size' => 192, 'format' => 'svg'], false) }}" />
                                            </div>
                                            <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                                <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ __('Scan to order') }}</p>
                                                <p>{{ __('Last updated') }} {{ $m->updated_at?->diffForHumans() }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <div class="inline-flex items-center gap-2 rounded-2xl border border-neutral-200/80 bg-neutral-50 px-3 py-2 font-mono text-xs tracking-wide text-neutral-600 dark:border-neutral-800/70 dark:bg-neutral-900/60 dark:text-neutral-300">
                                            <span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($m->qr_token, 12, '...')) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:link :href="route('meja.qr', ['token' => $m->qr_token, 'download' => 1], false)" target="_blank">
                                                <flux:button
                                                    size="sm"
                                                    icon="arrow-down-tray"
                                                    variant="ghost"
                                                class="btn-ghost-accent rounded-2xl shadow-sm transition"
                                                >
                                                    {{ __('Download PNG') }}
                                                </flux:button>
                                            </flux:link>
                                            <flux:button
                                                size="sm"
                                                icon="pencil-square"
                                                variant="primary"
                                                class="btn-accent rounded-2xl shadow-sm transition"
                                                wire:click="openEditModal({{ $m->id }})"
                                            >
                                                {{ __('Edit') }}
                                            </flux:button>
                                            <flux:modal.trigger name="confirm-delete-meja">
                                                <flux:button
                                                    size="sm"
                                                    icon="trash"
                                                    variant="danger"
                                                    class="rounded-2xl shadow-sm transition"
                                                    wire:click="confirmDelete({{ $m->id }})"
                                                >
                                                    {{ __('Delete') }}
                                                </flux:button>
                                            </flux:modal.trigger>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400" colspan="6">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="h-12 w-12 rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-900/60 dark:text-neutral-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-full w-full p-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 4.5h9m-9 6h9m-9 6h9" />
                                                </svg>
                                            </div>
                                            <p>{{ __('No data') }}</p>
                                        </div>
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

        <flux:modal name="create-meja" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-3xl" closable="false">
            <div class="flex max-h-[85dvh] flex-col">
                <div class="sticky top-0 z-10 -mx-4 border-b border-neutral-200 bg-white/85 px-4 py-3 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <flux:heading size="lg">{{ __('Create Table') }}</flux:heading>
                    <flux:subheading>{{ __('Fill the details below to add a new table.') }}</flux:subheading>
                </div>

                <form id="create-meja-form" wire:submit.prevent="save" class="flex-1 space-y-6 overflow-y-auto px-1 py-4">
                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="space-y-4 md:col-span-1">
                            <flux:input wire:model.defer="form.nomor_meja" :label="__('Table Number')" required maxlength="10" />
                            @error('form.nomor_meja')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                        <div>
                            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Status') }}</label>
                            <select wire:model.defer="form.status" class="mt-2 w-full rounded-xl border border-neutral-200 bg-white p-3 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900">
                                <option value="kosong">{{ __('Empty') }}</option>
                                <option value="terisi">{{ __('Occupied') }}</option>
                                <option value="reservasi">{{ __('Reserved') }}</option>
                            </select>
                            @error('form.status')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-4 md:col-span-1">
                        <flux:input wire:model.defer="form.qr_token" :label="__('QR Token')" readonly />
                        @error('form.qr_token')
                            <p class="text-xs text-red-500">{{ $message }}</p>
                        @enderror

                        <div class="flex items-center justify-between rounded-2xl border border-neutral-200/70 bg-neutral-50/70 px-3 py-2 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300">
                            <span>{{ __('Used in URL: /order/{token}') }}</span>
                            <flux:button size="xs" variant="ghost" icon="arrow-path" type="button" wire:click="regenerateToken">
                                {{ __('Regenerate') }}
                            </flux:button>
                        </div>
                    </div>

                    <div class="space-y-3 md:col-span-1">
                        <flux:heading size="sm">{{ __('QR Preview') }}</flux:heading>
                        <div class="rounded-2xl border border-dashed border-neutral-200 bg-white p-3 text-center dark:border-neutral-700 dark:bg-neutral-900">
                            @if (!empty($form['qr_token']))
                                <img alt="QR" class="mx-auto h-40 w-40" src="{{ route('meja.qr', ['token' => $form['qr_token'], 'size' => 256, 'format' => 'svg'], false) }}" />
                            @else
                                <div class="text-xs text-red-600">{{ __('QR generation failed') }}</div>
                            @endif
                        </div>
                        <flux:link :href="route('meja.qr', ['token' => $form['qr_token'] ?? null, 'download' => 1], false)" target="_blank">
                            <flux:button icon="arrow-down-tray" variant="ghost" class="btn-ghost-accent">{{ __('Download PNG') }}</flux:button>
                        </flux:link>
                    </div>
                    </div>
                </form>

                <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-3 border-t border-neutral-200 bg-white/85 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="create-meja-form" variant="primary" icon="plus" class="btn-brand">{{ __('Create') }}</flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="edit-meja" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-3xl" closable="false">
            <div class="flex max-h-[85dvh] flex-col">
                <div class="sticky top-0 z-10 -mx-4 border-b border-neutral-200 bg-white/85 px-4 py-3 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <flux:heading size="lg">{{ __('Edit Table') }}</flux:heading>
                    <flux:subheading>{{ __('Update the details for this table.') }}</flux:subheading>
                </div>

                <form id="edit-meja-form" wire:submit.prevent="update" class="flex-1 space-y-6 overflow-y-auto px-1 py-4">
                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="space-y-4 md:col-span-1">
                            <flux:input wire:model.defer="form.nomor_meja" :label="__('Table Number')" required maxlength="10" />
                            @error('form.nomor_meja')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                        <div>
                            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Status') }}</label>
                            <select wire:model.defer="form.status" class="mt-2 w-full rounded-xl border border-neutral-200 bg-white p-3 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900">
                                <option value="kosong">{{ __('Empty') }}</option>
                                <option value="terisi">{{ __('Occupied') }}</option>
                                <option value="reservasi">{{ __('Reserved') }}</option>
                            </select>
                            @error('form.status')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-4 md:col-span-1">
                        <flux:input wire:model.defer="form.qr_token" :label="__('QR Token')" readonly />
                        @error('form.qr_token')
                            <p class="text-xs text-red-500">{{ $message }}</p>
                        @enderror

                        <div class="flex items-center justify-between rounded-2xl border border-neutral-200/70 bg-neutral-50/70 px-3 py-2 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300">
                            <span>{{ __('Used in URL: /order/{token}') }}</span>
                            <flux:button size="xs" variant="ghost" icon="arrow-path" type="button" wire:click="regenerateToken">
                                {{ __('Regenerate') }}
                            </flux:button>
                        </div>
                    </div>

                    <div class="space-y-3 md:col-span-1">
                        <flux:heading size="sm">{{ __('QR Preview') }}</flux:heading>
                        <div class="rounded-2xl border border-dashed border-neutral-200 bg-white p-3 text-center dark:border-neutral-700 dark:bg-neutral-900">
                            @if (!empty($form['qr_token']))
                                <img alt="QR" class="mx-auto h-40 w-40" src="{{ route('meja.qr', ['token' => $form['qr_token'], 'size' => 256, 'format' => 'svg'], false) }}" />
                            @else
                                <div class="text-xs text-red-600">{{ __('QR generation failed') }}</div>
                            @endif
                        </div>
                        <flux:link :href="route('meja.qr', ['token' => $form['qr_token'] ?? null, 'download' => 1], false)" target="_blank">
                            <flux:button icon="arrow-down-tray" variant="ghost" class="btn-ghost-accent">{{ __('Download PNG') }}</flux:button>
                        </flux:link>
                    </div>
                    </div>
                </form>

                <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-3 border-t border-neutral-200 bg-white/85 px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" form="edit-meja-form" variant="primary" icon="check" class="btn-brand">{{ __('Update') }}</flux:button>
                </div>
            </div>
        </flux:modal>

        @php($selectedMeja = $items->firstWhere('id', $confirmingDeleteId))

        <flux:modal name="confirm-delete-meja" focusable variant="flyout" position="bottom" class="rounded-t-3xl sm:rounded-xl">
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this table?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. Orders linked to this table will lose their QR link.') }}
                    </flux:subheading>
                </div>

                @if ($selectedMeja)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ __('Table') }} {{ $selectedMeja->nomor_meja }}</p>
                        <p class="text-xs opacity-80">{{ __('Created at') }} {{ $selectedMeja->created_at?->format('d M Y') }}</p>
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

        <div
            x-data="{
                show: false,
                message: '',
                timeout: null,
                handle(event) {
                    this.message = event.detail?.message || '{{ __('Table deleted successfully.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:meja-toast.window="handle($event)"
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
                class="pointer-events-auto rounded-2xl border border-emerald-200/70 bg-white/90 px-4 py-3 text-sm text-emerald-800 shadow-lg shadow-emerald-200/50 dark:border-emerald-700/60 dark:bg-emerald-900/80 dark:text-emerald-100"
            >
                <div class="flex items-center gap-2">
                    <flux:icon icon="check-circle" class="text-emerald-500" />
                    <span x-text="message"></span>
                </div>
            </div>
        </div>
    </div>
</section>

@if (session('meja_toast'))
    <script>
        window.addEventListener('load', () => {
            try {
                const message = @js(session('meja_toast'));
                window.dispatchEvent(new CustomEvent('meja-toast', { detail: { message } }));
            } catch (e) {}
        });
    </script>
@endif

