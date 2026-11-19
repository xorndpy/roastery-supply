@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'h-11 rounded-md border-stone-300 bg-white text-sm shadow-none focus:border-copper-500 focus:ring-copper-500']) }}>
