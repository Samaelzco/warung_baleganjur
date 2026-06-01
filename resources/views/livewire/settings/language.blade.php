<?php

use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

new class extends Component {
    public string $locale = 'en';

    public function mount(): void
    {
        $this->locale = (string) session('locale', config('app.locale', 'en'));
        if (! in_array($this->locale, ['en', 'id'], true)) {
            $this->locale = 'en';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'locale' => ['required', 'in:en,id'],
        ]);

        Session::put('locale', $validated['locale']);
        app()->setLocale($validated['locale']);

        $this->dispatch('language-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Language')" :subheading="__('Choose your preferred language for the app')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:select wire:model="locale" :label="__('Language')" required>
                <option value="en">{{ __('English') }}</option>
                <option value="id">{{ __('Indonesian') }}</option>
            </flux:select>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full btn-brand">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="language-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
