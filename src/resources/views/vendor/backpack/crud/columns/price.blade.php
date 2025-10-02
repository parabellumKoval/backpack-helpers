<!-- fail, completed, pending, canceled, new -->
@php
$style = isset($muted) && $muted? 'opacity: 0.4;': '';
$small = !empty($small)? $small: false;
$price = $price? round($price, 2): null;

// Rates
$rate = !empty($rate)? $rate: null;
$rate_from = !empty($rate_from)? $rate_from: '???';
$rate_to = !empty($rate_to)? $rate_to: '???';
$human_rate = $rate? round(1 / $rate, 4): null;
@endphp

@if($price)
<div style="{{ $style }}">
  <div class="text-monospace">{{ $currency? $currency . ' ': '' }}
      @if($small)
        <span style="font-size: 0.9em;">{{ $price }}</span>
      @else
        <b>{{ $price }}</b>
      @endif
  </div>
  @if($rate)
    <div class="text-muted small"><i class="la la-exchange"></i> 1 {{$rate_to}} = {{ $human_rate }} {{ $rate_from }}</div>
  @endif
</div>
@endif