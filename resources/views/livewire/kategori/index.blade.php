<?php

use App\Models\KategoriMenu;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?int $confirmingDeleteId = null;
    public ?int $editingId = null;
    public array $form = [];
    public string $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'page'   => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->resetCreateForm();
    }

    public function resettingModal(): void
    {
        $this->resetCreateForm();
        $this->editingId = null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->resetCreateForm();
        $this->dispatch('modal-show', name: 'create-kategori');
    }

    public function openEditModal(int $id): void
    {
        $this->authorizeManage();
        $kategori = KategoriMenu::findOrFail($id);

        $this->editingId = $kategori->id;
        $this->form = [
            'nama_kategori' => $kategori->nama_kategori,
        ];

        $this->dispatch('modal-show', name: 'edit-kategori');
    }

    public function save(): void
    {
        $this->authorizeManage();
        $validated = validator($this->form, [
            'nama_kategori' => ['required', 'string', 'max:100', 'unique:kategori_menus,nama_kategori'],
        ])->validate();

        KategoriMenu::create($validated);

        $this->resetCreateForm();
        $this->dispatch('modal-close', name: 'create-kategori');
        $this->dispatch('kategori-toast', message: __('Category created successfully.'));
    }

    public function update(): void
    {
        $this->authorizeManage();
        if (!$this->editingId) return;

        $validated = validator($this->form, [
            'nama_kategori' => ['required', 'string', 'max:100', 'unique:kategori_menus,nama_kategori,' . $this->editingId],
        ])->validate();

        KategoriMenu::where('id', $this->editingId)->update($validated);

        $this->editingId = null;
        $this->dispatch('modal-close', name: 'edit-kategori');
        $this->dispatch('kategori-toast', message: __('Category updated successfully.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $this->confirmingDeleteId = $id;
    }

    public function delete(): void
    {
        $this->authorizeManage();
        if ($this->confirmingDeleteId) {
            KategoriMenu::where('id', $this->confirmingDeleteId)->delete();
            $this->confirmingDeleteId = null;
            $this->dispatch('modal-close', name: 'confirm-delete-kategori');
            $this->dispatch('modal-close', name: 'confirm-delete-kategori-desktop');
            $this->dispatch('kategori-toast', message: __('Category deleted successfully.'));
        }
    }

    protected function resetCreateForm(): void
    {
        $this->form = [
            'nama_kategori' => '',
        ];
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->can('kategori.manage'), 403);
    }
}; ?>

<section class="w-full">
    @php
        $query = \App\Models\KategoriMenu::query();

        if (!empty($search)) {
            $query->where(function ($sub) use ($search) {
                $sub->where('nama_kategori', 'like', '%'.$search.'%');
            });
        }

        $items        = $query->orderBy('nama_kategori')->paginate(10);
        $totalCount   = \App\Models\KategoriMenu::count();
        $summaryMeta = [
            [
                'label' => __('Categories'),
                'count' => $totalCount,
                'dot'   => 'bg-neutral-500',
            ],
        ];
    @endphp

    <div class="space-y-6">
        <!-- Heading & actions -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Categories') }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2">
                @can('kategori.manage')
                    <flux:button icon="plus" variant="primary" class="btn-brand" wire:click="openCreateModal">{{ __('Create') }}</flux:button>
                @endcan
            </div>
        </div>

        <!-- Mobile chips (match meja) -->
        <div class="block sm:hidden -mx-4 overflow-x-auto no-scrollbar">
            <div class="flex gap-2 px-4">
                @foreach($summaryMeta as $meta)
                    <div class="whitespace-nowrap rounded-full border border-neutral-200/70 bg-white/85 px-3 py-2 text-[11px] shadow-sm dark:border-neutral-700/60 dark:bg-neutral-900/70">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                            <span class="text-neutral-600 dark:text-neutral-300">{{ $meta['label'] }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-white">{{ $meta['count'] }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Desktop summary cards (match meja) -->
        <div class="hidden sm:grid gap-2 sm:gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl sm:rounded-2xl border border-neutral-200/70 bg-white/85 p-3 sm:p-4 shadow-sm sm:shadow-lg shadow-neutral-200/40 dark:border-neutral-700/60 dark:bg-neutral-900/70 dark:shadow-black/30">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-[0.18em] sm:tracking-[0.2em] text-neutral-400">{{ __('Total Categories') }}</p>
                <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-semibold text-neutral-900 dark:text-white">{{ $totalCount }}</span>
                    <span class="text-[11px] sm:text-xs text-neutral-500 dark:text-neutral-400">{{ __('records') }}</span>
                </div>
            </div>
        </div>

        <!-- Search (match menu filters) -->
        <div class="flex items-center gap-2 sm:justify-between">
            <div class="flex-1 min-w-0">
                <flux:input wire:model.live.debounce.800ms="search" :placeholder="__('Search name or description')" />
            </div>
            <flux:button
                size="sm"
                variant="ghost"
                class="btn-ghost-accent whitespace-nowrap shrink-0"
                wire:click="$set('search','')"
            >
                {{ __('Clear') }}
            </flux:button>
        </div>

        <!-- Mobile cards (match meja style) -->
        <div class="block sm:hidden">
            <div class="grid gap-3">
                @forelse ($items as $k)
                    <div class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800/70 dark:bg-neutral-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold text-neutral-900 dark:text-white">{{ $k->nama_kategori }}</div>
                                <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Created at') }} {{ $k->created_at?->format('d M Y') }}</div>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            @can('kategori.manage')
                                <flux:button size="sm" icon="pencil-square" variant="primary" class="flex-1 btn-accent" wire:click="openEditModal({{ $k->id }})">{{ __('Edit') }}</flux:button>
                                <flux:modal.trigger name="confirm-delete-kategori" class="flex-1">
                                    <flux:button size="sm" icon="trash" variant="danger" class="w-full" wire:click="confirmDelete({{ $k->id }})">{{ __('Delete') }}</flux:button>
                                </flux:modal.trigger>
                            @endcan
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

        <!-- Desktop table (match meja) -->
        <div class="hidden sm:block rounded-3xl border border-neutral-200/80 bg-gradient-to-b from-white/95 via-white/90 to-white/70 shadow-2xl shadow-neutral-200/60 backdrop-blur-xl dark:border-neutral-800/80 dark:from-neutral-950/80 dark:via-neutral-950/60 dark:to-neutral-950/40 dark:shadow-black/30">
            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th class="hidden md:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('ID') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Name') }}</th>
                                <th class="hidden xl:table-cell px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Created') }}</th>
                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100/80 text-neutral-700 dark:divide-neutral-900/40 dark:text-neutral-200">
                            @forelse ($items as $k)
                                <tr class="group transition hover:bg-white/70 focus-within:bg-white/90 dark:hover:bg-neutral-900/40 dark:focus-within:bg-neutral-900/50">
                                    <td class="hidden md:table-cell px-6 py-4 align-middle">
                                        <span class="inline-flex items-center rounded-full bg-neutral-900/5 px-3 py-1 text-xs font-semibold text-neutral-500 dark:bg-white/5 dark:text-neutral-300">
                                            #{{ str_pad($k->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-col">
                                            <span class="text-base font-semibold text-neutral-900 dark:text-white">{{ $k->nama_kategori }}</span>
                                        </div>
                                    </td>
                                    <td class="hidden xl:table-cell px-6 py-4 align-middle text-neutral-500 dark:text-neutral-400">
                                        {{ $k->created_at?->format('d M Y') }}
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @can('kategori.manage')
                                                <flux:button
                                                    size="sm"
                                                    icon="pencil-square"
                                                    variant="primary"
                                                    class="btn-accent rounded-2xl shadow-sm transition"
                                                    wire:click="openEditModal({{ $k->id }})"
                                                >
                                                    {{ __('Edit') }}
                                                </flux:button>
                                                <flux:modal.trigger name="confirm-delete-kategori-desktop">
                                                    <flux:button
                                                        size="sm"
                                                        icon="trash"
                                                        variant="danger"
                                                        class="rounded-2xl shadow-sm transition"
                                                        wire:click="confirmDelete({{ $k->id }})"
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
                                    <td class="px-6 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400" colspan="4">
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

        @php($selectedKategori = $items->firstWhere('id', $confirmingDeleteId))

        <!-- Create modal (match meja) -->
        <flux:modal name="create-kategori" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl md:max-w-3xl lg:max-w-4xl" closable="false">
            <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
                <div class="sticky top-0 z-10 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white px-4 py-3 dark:border-neutral-700 dark:bg-neutral-900">
                    <div>
                        <flux:heading size="lg">{{ __('Create Category') }}</flux:heading>
                        <flux:subheading>{{ __('Fill the details below to add a new category.') }}</flux:subheading>
                    </div>
                    <flux:modal.close class="hidden sm:block">
                        <flux:button variant="ghost" icon="x-mark" class="inline-flex text-neutral-600 hover:text-neutral-900 dark:text-neutral-200" aria-label="{{ __('Close') }}" />
                    </flux:modal.close>
                </div>

                <form id="create-kategori-form" wire:submit.prevent="save" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+3.25rem)] md:pb-0">
                    <div class="grid gap-6 md:grid-cols-5">
                        <div class="space-y-4 md:col-span-3">
                            <flux:input wire:model.defer="form.nama_kategori" :label="__('Category Name')" required maxlength="100" help="{{ __('e.g. Makanan, Minuman, Snack') }}" />
                            @error('form.nama_kategori')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-4 md:col-span-2 md:pl-2">
                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                                <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Use categories to group menu items in the customer QR page.') }}
                                </p>
                            </div>
                            <div class="hidden md:flex items-center justify-end gap-3 pt-2">
                                <flux:modal.close>
                                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button type="submit" form="create-kategori-form" variant="primary" icon="plus" class="btn-brand">{{ __('Create') }}</flux:button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white px-4 py-3 pb-[calc(env(safe-area-inset-bottom)+0.25rem)] dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="grid grid-cols-2 gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="create-kategori-form" variant="primary" icon="plus" class="btn-brand w-full">{{ __('Create') }}</flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        <!-- Edit modal (match meja) -->
        <flux:modal name="edit-kategori" focusable class="mx-4 w-[calc(100%-2rem)] sm:mx-auto sm:max-w-4xl md:max-w-3xl lg:max-w-4xl" closable="false">
            <div class="flex flex-col max-h-[85dvh] overflow-y-auto no-scrollbar md:max-h-none md:overflow-visible">
                <div class="sticky top-0 z-10 -mx-4 flex items-start justify-between gap-2 border-b border-neutral-200 bg-white px-4 py-3 dark:border-neutral-700 dark:bg-neutral-900">
                    <div>
                        <flux:heading size="lg">{{ __('Edit Category') }}</flux:heading>
                        <flux:subheading>{{ __('Update the details for this category.') }}</flux:subheading>
                    </div>
                    <flux:modal.close class="hidden sm:block">
                        <flux:button variant="ghost" icon="x-mark" class="inline-flex text-neutral-600 hover:text-neutral-900 dark:text-neutral-200" aria-label="{{ __('Close') }}" />
                    </flux:modal.close>
                </div>

                <form id="edit-kategori-form" wire:submit.prevent="update" class="flex-1 space-y-6 px-1 py-4 pb-[calc(env(safe-area-inset-bottom)+3.25rem)] md:pb-0">
                    <div class="grid gap-6 md:grid-cols-5">
                        <div class="space-y-4 md:col-span-3">
                            <flux:input wire:model.defer="form.nama_kategori" :label="__('Category Name')" required maxlength="100" />
                            @error('form.nama_kategori')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-4 md:col-span-2 md:pl-2">
                            <div class="rounded-2xl border border-neutral-200/70 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900">
                                <flux:heading size="sm">{{ __('Info') }}</flux:heading>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ __('Changes will affect how menu items are grouped in the QR page.') }}
                                </p>
                            </div>
                            <div class="hidden md:flex items-center justify-end gap-3 pt-2">
                                <flux:modal.close>
                                    <flux:button type="button" variant="ghost" class="btn-ghost-accent">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button type="submit" form="edit-kategori-form" variant="primary" icon="check" class="btn-brand">{{ __('Update') }}</flux:button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="sticky bottom-0 z-10 -mx-4 md:hidden border-t border-neutral-200 bg-white px-4 py-3 pb-[calc(env(safe-area-inset-bottom)+0.25rem)] dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="grid grid-cols-2 gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" class="btn-ghost-accent w-full">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" form="edit-kategori-form" variant="primary" icon="check" class="btn-brand w-full">{{ __('Update') }}</flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>

        <!-- Mobile bottom-sheet delete (match meja) -->
        <flux:modal name="confirm-delete-kategori" focusable variant="flyout" position="bottom" class="rounded-t-3xl sm:rounded-xl">
            <div class="space-y-4 p-2 max-h-[60dvh] overflow-y-auto">
                <div class="flex items-center justify-center">
                    <div class="mx-auto mb-1 h-1.5 w-12 rounded-full bg-neutral-300 dark:bg-neutral-700"></div>
                </div>

                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this category?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone.') }}
                    </flux:subheading>
                </div>

                @if ($selectedKategori)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedKategori->nama_kategori }}</p>
                    </div>
                @endif

                <div class="sticky bottom-0 -mx-2 mt-2 flex items-center justify-end gap-2 border-t border-neutral-200 bg-white px-2 py-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] dark:border-neutral-700 dark:bg-neutral-900">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">
                        {{ __('Yes, delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- Desktop delete modal -->
        <flux:modal name="confirm-delete-kategori-desktop" focusable class="mx-4 max-w-full sm:mx-auto sm:max-w-lg">
            <div class="space-y-4 p-2">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Delete this category?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone.') }}
                    </flux:subheading>
                </div>

                @if ($selectedKategori)
                    <div class="rounded-2xl border border-red-200/60 bg-red-50/60 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-200">
                        <p class="font-semibold">{{ $selectedKategori->nama_kategori }}</p>
                    </div>
                @endif

                <div class="mt-2 flex items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled" wire:click="$set('confirmingDeleteId', null)">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">
                        {{ __('Yes, delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- Toast (match meja) -->
        <div
            x-data="{
                show: false,
                message: '',
                timeout: null,
                handle(event) {
                    this.message = event.detail?.message || '{{ __('Category deleted successfully.') }}';
                    this.show = true;
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            }"
            x-on:kategori-toast.window="handle($event)"
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
    </div>
</section>
