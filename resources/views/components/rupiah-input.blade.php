@props([
    'model',
    'label' => null,
    'help' => null,
    'required' => false,
    'placeholder' => 'Rp 0',
])

<div
    x-data="{
        model: @js($model),
        display: '',
        init() {
            this.display = this.format(this.$wire.get(this.model));
        },
        digits(value) {
            const text = String(value ?? '').trim();

            if (/^\d+([.,]\d{1,2})?$/.test(text)) {
                return String(Math.round(Number(text.replace(',', '.'))));
            }

            return text.replace(/\D/g, '');
        },
        format(value) {
            const raw = this.digits(value);

            if (!raw) {
                return '';
            }

            return `Rp ${Number(raw).toLocaleString('id-ID')}`;
        },
        sync(value) {
            const raw = this.digits(value);

            this.display = this.format(raw);
            this.$wire.set(this.model, raw, false);
        },
    }"
>
    <flux:input
        x-model="display"
        x-on:input="sync($event.target.value)"
        x-on:blur="display = format(display)"
        type="text"
        inputmode="numeric"
        :label="$label"
        :placeholder="$placeholder"
        :required="$required"
        :help="$help"
        {{ $attributes }}
    />
</div>
