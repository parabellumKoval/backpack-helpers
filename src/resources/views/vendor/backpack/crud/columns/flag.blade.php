@php
$value = data_get($entry, $column['name']);
@endphp

<span>{{ get_flag($value) }}</span>