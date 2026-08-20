@props(['window', 'recurrence' => null])

@php
    $sameMonth = $window->from->isSameMonth($window->to) && $window->from->isSameYear($window->to);

    $label = $sameMonth
        ? $window->from->day.'–'.$window->to->day.' '.$window->to->translatedFormat('M')
        : $window->from->translatedFormat('j M Y').' – '.$window->to->translatedFormat('j M Y');

    if ($recurrence === \App\Enums\ChallengeRecurrence::Monthly) {
        $label .= ' · '.__('monthly');
    }
@endphp

<span {{ $attributes }}>{{ $label }}</span>
