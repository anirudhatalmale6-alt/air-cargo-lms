@props(['name', 'label' => null, 'help' => null])

<div {{ $attributes->merge(['class' => 'field']) }}>
    @if ($label)
        <label for="{{ $name }}">{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($help)
        <span class="help">{{ $help }}</span>
    @endif
    @error($name)
        <span class="err">{{ $message }}</span>
    @enderror
</div>
