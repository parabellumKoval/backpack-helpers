{{-- resources/views/vendor/backpack/crud/columns/rating_stars.blade.php --}}
@php
    // Настройки
    $max = $column['max'] ?? 5;                                // максимальное число звёзд (обязательная по смыслу)
    $color = $column['color'] ?? '#f2c200';                    // цвет звёзд (золото/жёлтый)
    $size = $column['size'] ?? null;                           // например '18px' — необязательно
    $showValue = array_key_exists('show_value', $column)
        ? (bool)$column['show_value']
        : true;                                                // показывать значение во всплывашке (title)

    // Значение рейтинга из поля
    $raw = data_get($entry, $column['name']);
    $rating = is_numeric($raw) ? (float)$raw : 0;

    // Обрезаем в диапазон [0, max]
    $rating = max(0, min($max, $rating));

    // Округляем до 0.5, чтобы можно было показать половинку
    $rounded = round($rating * 2) / 2;

    // Считаем сколько каких звёзд
    $full = (int) floor($rounded);
    $half = (($rounded - $full) >= 0.5) ? 1 : 0;
    $empty = max(0, $max - $full - $half);

    // Подсказка
    $title = $showValue ? (number_format($rating, 1) . ' / ' . $max) : null;

    // Доп. классы-обёртки (если нужно)
    $wrapperClass = $column['wrapper_class'] ?? '';
@endphp

<span class="bp-rating-stars {{ $wrapperClass }}"
      @if($title) title="{{ $title }}" @endif
      style="display:inline-flex; align-items:center; gap:2px; line-height:1; {{ $size ? 'font-size:'.$size.';' : '' }} color: {{ $color }};">
    @for ($i = 0; $i < $full; $i++)
        <i class="la la-star" aria-hidden="true"></i>
    @endfor

    @if ($half)
        <i class="la la-star-half-o" aria-hidden="true"></i>
    @endif

    @for ($i = 0; $i < $empty; $i++)
        <i class="la la-star-o" aria-hidden="true"></i>
    @endfor
</span>

{{-- Если нужен доступный текст для screen-readers --}}
<span class="sr-only">
    {{ number_format($rating, 1) }} из {{ $max }}
</span>
