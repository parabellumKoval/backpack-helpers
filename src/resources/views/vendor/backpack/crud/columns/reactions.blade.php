{{-- resources/views/vendor/backpack/crud/columns/likes_dislikes.blade.php --}}

@php
    /**
     * Поддерживаемые параметры:
     * - name: string            // имя аксессора-массива (например 'reactions')
     * - likes_key: string       // ключ лайков внутри массива (по умолчанию 'likes')
     * - dislikes_key: string    // ключ дизлайков внутри массива (по умолчанию 'dislikes')
     * - likes_name: string      // ИЛИ отдельное поле модели с лайками (если не используем accessor-массив)
     * - dislikes_name: string   // ИЛИ отдельное поле модели с дизлайками
     *
     * Внешний вид:
     * - likes_color: string     // цвет лайков (default #28a745 — bootstrap success)
     * - dislikes_color: string  // цвет дизлайков (default #dc3545 — bootstrap danger)
     * - size: string            // размер шрифта/иконок, напр. '14px' (необязательно)
     * - gap: string             // расстояние между блоками, по умолчанию '10px'
     * - compact: bool           // компактные бейджи без бэкграунда (по умолчанию false)
     * - show_total: bool        // показать суммарно и % одобрения, по умолчанию false
     * - thousand_sep: string    // разделитель тысяч, по умолчанию ' '
     * - tooltip: bool           // включить title с деталями, по умолчанию true
     */

    // 1) Считываем конфиг
    $likesKey        = $column['likes_key']        ?? 'likes';
    $dislikesKey     = $column['dislikes_key']     ?? 'dislikes';
    $likesName       = $column['likes_name']       ?? null;
    $dislikesName    = $column['dislikes_name']    ?? null;

    $likesColor      = $column['likes_color']      ?? '#28a745';
    $dislikesColor   = $column['dislikes_color']   ?? '#dc3545';
    $size            = $column['size']             ?? null;    // '14px'
    $gap             = $column['gap']              ?? '10px';
    $compact         = (bool)($column['compact']   ?? false);
    $showTotal       = (bool)($column['show_total']?? false);
    $thousandSep     = $column['thousand_sep']     ?? ' ';
    $tooltipEnabled  = array_key_exists('tooltip', $column) ? (bool)$column['tooltip'] : true;

    // 2) Достаём значения лайков/дизлайков
    $likes = 0; $dislikes = 0;

    // Приоритет: accessor-массив в $column['name']
    $maybeArray = null;
    if (!empty($column['name'])) {
        $maybeArray = data_get($entry, $column['name']);
    }

    if (is_array($maybeArray)) {
        $likes    = (int) data_get($maybeArray, $likesKey, 0);
        $dislikes = (int) data_get($maybeArray, $dislikesKey, 0);
    } elseif ($likesName || $dislikesName) {
        // Если указаны отдельные имена полей
        $likes    = (int) data_get($entry, $likesName, 0);
        $dislikes = (int) data_get($entry, $dislikesName, 0);
    } else {
        // Фолбэк: пробуем прямые поля 'likes'/'dislikes' на модели
        $likes    = (int) data_get($entry, 'likes', 0);
        $dislikes = (int) data_get($entry, 'dislikes', 0);
    }

    // 3) Подсчёты
    $total = max(0, $likes + $dislikes);
    $approval = $total > 0 ? round(($likes / $total) * 100) : 0;

    // 4) Форматирование чисел
    $fmt = function ($n) use ($thousandSep) {
        return number_format((int)$n, 0, '.', $thousandSep);
    };

    // 5) Tooltip
    $title = null;
    if ($tooltipEnabled) {
        $parts = [];
        $parts[] = '👍 ' . $fmt($likes);
        $parts[] = '👎 ' . $fmt($dislikes);
        if ($showTotal) {
            $parts[] = 'Σ ' . $fmt($total) . ' (' . $approval . '%)';
        }
        $title = implode(' • ', $parts);
    }

    // 6) Классы/стили
    $wrapperStyle = "display:inline-flex; align-items:center; gap:{$gap}; line-height:1;";
    if ($size) $wrapperStyle .= " font-size:{$size};";
@endphp

<span class="bp-likes-dislikes"
      @if($title) title="{{ $title }}" @endif
      style="{{ $wrapperStyle }}">

    {{-- Лайки --}}
    @if ($compact)
        <span class="d-inline-flex align-items-center"
              style="gap:6px; color: {{ $likesColor }};">
            <i class="la la-thumbs-up" aria-hidden="true"></i>
            <span>{{ $fmt($likes) }}</span>
        </span>
    @else
        <span class="badge badge-light d-inline-flex align-items-center"
              style="gap:6px; border:1px solid rgba(0,0,0,.075); color: {{ $likesColor }};">
            <i class="la la-thumbs-up" aria-hidden="true"></i>
            <span>{{ $fmt($likes) }}</span>
        </span>
    @endif

    {{-- Дизлайки --}}
    @if ($compact)
        <span class="d-inline-flex align-items-center"
              style="gap:6px; color: {{ $dislikesColor }};">
            <i class="la la-thumbs-down" aria-hidden="true"></i>
            <span>{{ $fmt($dislikes) }}</span>
        </span>
    @else
        <span class="badge badge-light d-inline-flex align-items-center"
              style="gap:6px; border:1px solid rgba(0,0,0,.075); color: {{ $dislikesColor }};">
            <i class="la la-thumbs-down" aria-hidden="true"></i>
            <span>{{ $fmt($dislikes) }}</span>
        </span>
    @endif

    {{-- Итого (опционально) --}}
    @if ($showTotal)
        <span class="text-muted" style="margin-left:2px;">
            <small>Σ {{ $fmt($total) }} · {{ $approval }}%</small>
        </span>
    @endif
</span>

<span class="sr-only">
    Лайки: {{ $likes }}, дизлайки: {{ $dislikes }}@if($showTotal), всего: {{ $total }}, одобрение: {{ $approval }}%@endif
</span>
