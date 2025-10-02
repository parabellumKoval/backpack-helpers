<!-- fail, completed, pending, canceled, new -->
@php
$code = $entry->locale ?? null;
@endphp

<span>{{ get_flag($code) }}</span>