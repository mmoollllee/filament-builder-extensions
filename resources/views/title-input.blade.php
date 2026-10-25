@php
    // ItemTitle::input() is public, so default every optional key to keep external callers from
    // hitting an "undefined variable". Without a wireModel (a block outside a builder) the input
    // is bound to nothing.
    $wireModel ??= null;
    $live ??= false;
    $value ??= null;
    $placeholder ??= '';
    $suffix ??= null;
    $error ??= null;
@endphp
{{-- Live for a block with a preview: the server renders it, so it shows a new title at once. --}}
<label
    x-data="{
        state: @if ($wireModel) $wire.$entangle(@js($wireModel), @js((bool) $live)) @else '' @endif,
        inputWidth: {{ mb_strlen($placeholder) }},
        resize() {
            const input = this.$refs.input;
            this.inputWidth = (input.value || input.placeholder || '').length;
        },
        init() {
            this.$nextTick(() => this.resize());
        },
    }"
    x-effect="state; resize()"
    x-on:click.stop
    class="fi-builder-title-wrapper"
>
    <input
        type="text"
        x-ref="input"
        x-model.lazy="state"
        x-on:input="resize()"
        x-on:focus.stop
        x-on:keydown.stop
        :style="'width: ' + inputWidth + 'ch'"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        role="presentation"
        @class([
            'fi-builder-title-input',
            'fi-builder-title-input-error' => ! empty($error),
        ])
        @if (! empty($error))
            title="{{ $error }}"
        @endif
    />
    {{-- The title as text, for those who read the label as text: Filament's reorder announcements to screen readers. --}}
    @if (is_scalar($value) && filled($value))
        <span hidden>{{ $value }}</span>
    @endif
    @if ($suffix)
        <span class="fi-builder-title-suffix">{{ $suffix }}</span>
    @endif
    @if (! empty($error))
        <span class="fi-builder-title-error">{{ $error }}</span>
    @endif
</label>
