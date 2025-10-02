@php
    // ---- Options (с дефолтами) ----
    $attr          = $column['attribute'] ?? $column['name'] ?? 'user_id'; // из какого поля брать ID
    $relation      = $column['relation'] ?? null;                          // если есть relation, используем его
    $userModelFqn  = $column['user_model'] ?? config('backpack.profile.user_model', \App\Models\User::class);
    $avatarField   = $column['avatar_field'] ?? 'avatar';
    $emailField    = $column['email_field'] ?? 'email';
    $nameField     = $column['name_field'] ?? 'name'; // если в модели есть единое поле name
    $nameFields    = $column['name_fields'] ?? ['first_name','last_name']; // если name пустой — склеим first/last
    $size          = (int) ($column['size'] ?? 36); // px, квадрат

    // ---- Получаем пользователя ----
    /** локальный статический кеш на время запроса, чтобы не было N+1 */
    static $UCACHE = [];

    $user = null;

    if ($relation && isset($entry->{$relation})) {
        $user = $entry->{$relation};
    } else {
        $id = $entry->{$attr} ?? null;
        if ($id) {
            $cacheKey = $userModelFqn.'#'.$id;
            if (array_key_exists($cacheKey, $UCACHE)) {
                $user = $UCACHE[$cacheKey];
            } else {
                try {
                    $user = app($userModelFqn)::query()->find($id);
                } catch (\Throwable $e) {
                    $user = null;
                }
                $UCACHE[$cacheKey] = $user;
            }
        }
    }

    // ---- Достаём поля ----
    $email = $user?->{$emailField} ?? null;

    // name → сперва name, затем first+last, иначе “#ID”
    $name = $user?->{$nameField} ?? null;
    if (!$name && $user) {
        $parts = [];
        foreach ($nameFields as $nf) {
            $val = trim((string) ($user->{$nf} ?? ''));
            if ($val !== '') $parts[] = $val;
        }
        $name = $parts ? implode(' ', $parts) : null;
    }
    if (!$name) {
        $idDisp = isset($entry->{$attr}) ? (string)$entry->{$attr} : '—';
        $name = 'User #'.$idDisp;
    }

    // avatar → поле или gravatar по email, иначе инициалы
    $avatarUrl = null;
    if ($user && !empty($user->{$avatarField})) {
        $avatarUrl = $user->{$avatarField};
    } elseif ($email) {
        $hash = md5(strtolower(trim($email)));
        $avatarUrl = "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp";
    }

    // ссылка на профиль (опционально)
    // $profileUrl = $column['profile_url'] ?? null; // например, url("admin/profiles/{$user->id}/show")
    $profileUrl = $user? 'admin/users/'.$user->id.'/show': null;

    // инциалы (fallback)
    $initials = function($text) {
        $words = preg_split('/\s+/u', trim($text));
        $letters = array_map(fn($w)=>mb_substr($w,0,1), array_slice($words,0,2));
        return mb_strtoupper(implode('', $letters));
    };
@endphp

<div style="display:flex; align-items:center; gap:10px;">
    <div style="width:{{$size}}px; height:{{$size}}px; border-radius:6px; background:#f0f0f0; overflow:hidden; display:flex; align-items:center; justify-content:center; font-weight:600;">
        @if($avatarUrl)
            <img src="{{ $avatarUrl }}" alt="avatar" style="width:100%; height:100%; object-fit:cover;">
        @else
            <span>{{ $initials($name) }}</span>
        @endif
    </div>
    <div style="line-height:1.2;">
        @if($profileUrl)
            <div><a href="{{ $profileUrl }}" target="_blank"><strong>{{ e($name) }}</strong></a></div>
        @else
            <div><strong>{{ e($name) }}</strong></div>
        @endif
        @if($email)
            <div style="color:#6c757d;">{{ e($email) }}</div>
        @endif
    </div>
</div>
