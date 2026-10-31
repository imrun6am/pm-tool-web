@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'field-control h-10 px-3']) }}>
