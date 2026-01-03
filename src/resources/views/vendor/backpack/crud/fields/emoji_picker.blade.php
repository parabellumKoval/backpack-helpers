@php
    $field['attributes'] = $field['attributes'] ?? [];
    $listId = $field['attributes']['list'] ?? $field['name'].'_emoji_list_'.uniqid();
    $current = old(square_brackets_to_dots($field['name'])) ?? $field['value'] ?? $field['default'] ?? '';

    $field['attributes']['list'] = $listId;
    $field['attributes']['type'] = $field['attributes']['type'] ?? 'text';
    $field['attributes']['class'] = trim(($field['attributes']['class'] ?? 'form-control').' emoji-picker-input');
    $field['attributes']['placeholder'] = $field['attributes']['placeholder'] ?? '😀';

    $emojiList = $field['emoji_list'] ?? [
        '😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','😘','🥰','😗','😙','😚','🙂','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','🥱','😴','😌','😛','😜','😝','🤤','😒','😓','😔','😕','🙃','🫠','😲','☹️','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩','🤯','😬','😰','😱','🥵','🥶','😳','🤪','😵','😵‍💫','😡','😠','🤬','😷','🤒','🤕','🤢','🤮','🤧','😇','🥳','🥸','🥺','🤠','🤡','🤥','🤫','🤭','🫢','🫣','🫡','🤓','😈','👻','💀','☠️','👽','🤖','💩',
        '👍','👎','👌','🤌','🤏','✌️','🤞','🤟','🤘','🤙','👊','✊','👏','🙌','👐','🤲','🙏','💪','🦾','🫶','🖐️','✋','🤚','🫱','🫲','🫳','🫴',
        '🎉','🎊','🎈','🎁','🎀','✨','⭐','🌟','💫','🔥','💥','💯','✅','❌','⚠️','❗','❓','💬','🗨️','🗯️','💡','📌','🔔','🔕','🧿','💎',
        '🛒','🧾','💳','💰','💸','💵','💶','💷','🧮','📦','🚚','🚀','🏷️','🧾','🛍️','📣','📢','📈','📉','📊','🧮',
        '📌','📍','🧭','🗺️','⏰','⌛','⏳','📅','🗓️','🕒','🕕','🕘','🕛','⏱️','⏲️',
        '🎯','🏆','🥇','🥈','🥉','🎖️','🏅','🎬','🎤','🎧','🎼','🎹','🎸','🥁','🎨','🧩','🎮','🎲',
        '🌍','🌎','🌏','🌕','🌙','☀️','🌤️','⛅','🌧️','⛈️','🌩️','❄️','🌈','💧','🌊','🍀','🌿','🌸','🌺','🌻','🌼','🌷','🍁',
        '🍎','🍓','🍒','🍑','🍇','🍉','🍊','🍋','🍌','🍍','🥭','🥝','🍅','🥑','🍕','🍔','🍟','🌭','🥪','🌮','🌯','🥗','🍣','🍪','🍩','🍰','🧁','🍫','🍿','☕','🍵','🥤',
        '🚩','🏁','🚨','🔒','🔓','🔑','🧭','⚙️','🧰','🛠️','🧲','🧪','🧬','📎','📌','✏️','🖊️','🖍️','📖','📚','🗂️','🗃️','🗄️',
        '🏠','🏡','🏢','🏬','🏪','🏭','🏫','🏦','🏨','🏥','🛎️','🛏️','🪟','🚪',
    ];
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')

    <div class="emoji-picker-wrapper d-flex align-items-center gap-2">
        <span class="emoji-picker-preview" data-emoji-preview>{{ $current ?: '⬜️' }}</span>
        <input
            name="{{ $field['name'] }}"
            value="{{ $current }}"
            @include('crud::fields.inc.attributes')
        />
    </div>

    <datalist id="{{ $listId }}">
        <option value=""></option>
        @foreach($emojiList as $emoji)
            <option value="{{ $emoji }}">{{ $emoji }}</option>
        @endforeach
    </datalist>

    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')

@if ($crud->fieldTypeNotLoaded($field))
    @php
        $crud->markFieldTypeAsLoaded($field);
    @endphp

    @push('crud_fields_styles')
        <style>
            .emoji-picker-wrapper {
                gap: 10px;
            }
            .emoji-picker-preview {
                width: 38px;
                height: 38px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 10px;
                background: #f8f9fa;
                border: 1px solid rgba(0, 0, 0, 0.08);
                font-size: 20px;
                line-height: 1;
            }
        </style>
    @endpush

    @push('crud_fields_scripts')
        <script>
            (function($){
                if (typeof window.initEmojiPickers === 'undefined') {
                    window.initEmojiPickers = function(context) {
                        var $context = context ? $(context) : $(document);

                        $context.find('.emoji-picker-input').each(function(){
                            var $input = $(this);
                            if ($input.data('emoji-bound')) {
                                updatePreview($input);
                                return;
                            }

                            $input.data('emoji-bound', true);
                            $input.on('input', function() {
                                updatePreview($input);
                            });

                            updatePreview($input);
                        });
                    };

                    function updatePreview($input) {
                        var value = ($input.val() || '').trim();
                        var $wrapper = $input.closest('.emoji-picker-wrapper');
                        var $preview = $wrapper.find('[data-emoji-preview]');
                        $preview.text(value || '⬜️');
                    }

                    $(document).ready(function () {
                        window.initEmojiPickers();
                    });

                    if (typeof crud !== 'undefined' && typeof crud.addFunctionToCrudFieldScriptsQueue === 'function') {
                        crud.addFunctionToCrudFieldScriptsQueue(function(){
                            window.initEmojiPickers();
                        });
                    }
                } else {
                    window.initEmojiPickers();
                }
            })(jQuery);
        </script>
    @endpush
@endif
