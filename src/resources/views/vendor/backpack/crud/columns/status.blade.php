<!-- fail, completed, pending, canceled, new -->
@php
  $class = '';
  $type = isset($type) && !empty($type)? $type: 'badge';

  if($status === 'new'){
    $class = $type === 'badge'? 'badge badge-warning': 'text-danger';
  }elseif($status === 'completed') {
    $class = $type === 'badge'? 'badge badge-success': 'text-success';
  }elseif($status === 'failed') {
    $class = $type === 'badge'? 'badge badge-secondary': 'text-danger';
  }elseif($status === 'canceled') {
    $class = $type === 'badge'? 'badge badge-secondary': 'text-danger';
  }
  
  // payment
  elseif($status === 'waiting') {
    $class = $type === 'badge'? 'badge badge-secondary': 'text-warning';
  }elseif($status === 'paied') {
    $class = $type === 'badge'? 'badge badge-success': 'text-success';
  }

  // delivery
  elseif($status === 'sent') {
    $class = $type === 'badge'? 'badge badge-success': 'text-success';
  }
  elseif($status === 'delivered') {
    $class = $type === 'badge'? 'badge badge-success': 'text-success';
  }
  elseif($status === 'pickedup') {
    $class = $type === 'badge'? 'badge badge-success': 'text-success';
  }

  //
  if($status === 'pending'){
    $class = $type === 'badge'? 'badge badge-warning': 'text-danger';
  }elseif($status === 'paid') {
    $class = $type === 'badge'? 'badge badge-success': 'text-success';
  }elseif($status === 'rejected') {
    $class = $type === 'badge'? 'badge badge-secondary': 'text-danger';
  }

  //
  if($status === 'processed') {
    $class = $type === 'badge'? 'badge badge-success': 'text-success';
  }

  $namespace = isset($namespace) && !empty($namespace)? $namespace: 'backpack-store::shop';
@endphp


<span class="{{ $class }}">{{ __($namespace . '.' . $context . '_status.' . $status) }}</span>