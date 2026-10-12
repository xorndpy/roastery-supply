@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'h-11 rounded-md border-stone-300 bg-white text-sm shadow-none focus:border-orange-500 focus:ring-orange-500']) }}>
