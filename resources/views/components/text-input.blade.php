@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm']) }}>
