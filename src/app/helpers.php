<?php
if (!function_exists('get_flag')) {
    function get_flag(string|null $cc): string {
        if (!$cc) return '';
        $cc = strtoupper($cc);
        
        if ($cc === 'ZZ') {
          return "🌐"; // global
        }

        if (strlen($cc) !== 2) return '';
        $base = 0x1F1E6;
        $a = mb_ord($cc[0]) - 65 + $base;
        $b = mb_ord($cc[1]) - 65 + $base;
        return mb_chr($a, 'UTF-8').mb_chr($b, 'UTF-8');
    }
}

if (!function_exists('reorder_url_with_parent')) {
    /**
     * Формирует URL для операции reorder с нужным parent_id
     * 
     * @param string $baseUrl Базовый URL операции reorder
     * @param string $scopeKey Ключ параметра для parent (обычно 'parent')
     * @param int|null $id ID родителя или null для корня
     * @return string
     */
    function reorder_url_with_parent(string $baseUrl, string $scopeKey, int|null $id): string {
        $params = request()->all();
        if ($id === null) {
            unset($params[$scopeKey]);
        } else {
            $params[$scopeKey] = $id;
        }
        return $baseUrl.(count($params) ? ('?'.http_build_query($params)) : '');
    }
}

if (!function_exists('render_tree_element_scoped')) {
    /**
     * Рекурсивно отрисовывает элемент дерева для операции reorder
     * 
     * @param object $entry Текущий элемент
     * @param mixed $key Ключ элемента
     * @param \Illuminate\Support\Collection $all_entries Все элементы
     * @param object $crud CRUD объект
     * @param int|null $scopeParentId ID родителя текущего набора
     * @param bool $showChildrenBtn Показывать ли кнопку "Отсортировать детей"
     * @param string $childrenBtnLabel Текст кнопки детей
     * @param string $baseReorderUrl Базовый URL для reorder
     * @param string $scopeKey Ключ параметра scope
     * @return object
     */
function render_tree_element_scoped($entry, $key, $all_entries, $crud, $scopeParentId, $showChildrenBtn, $childrenBtnLabel, $baseReorderUrl, $scopeKey) {
        if (!isset($entry->tree_element_shown)) {
            // помечаем показанным
            $all_entries[$key]->tree_element_shown = true;
            $entry->tree_element_shown = true;

            // считаем детей в ПРЕДЕЛАХ текущего набора $all_entries
            $children = [];
            foreach ($all_entries as $sKey => $subentry) {
                if ((string)$subentry->parent_id === (string)$entry->getKey()) {
                    $children[] = $subentry;
                }
            }
            $children = collect($children)->sortBy('lft');

            // ли-элемент
            echo '<li id="list_'.$entry->getKey().'" data-original-parent-id="'.e((int)$entry->parent_id).'">';
            echo '<div class="d-flex align-items-center justify-content-between gap-2">';
            echo '  <span class="d-inline-flex align-items-center"><span class="disclose"><span></span></span>'.e(object_get($entry, $crud->get('reorder.label'))).'</span>';

            // кнопка «Отсортировать детей» (если у элемента ЕСТЬ дети в текущем наборе)
            if ($showChildrenBtn && count($children)) {
                $childUrl = reorder_url_with_parent($baseReorderUrl, $scopeKey, $entry->getKey());
                echo '  <a href="'.e($childUrl).'" class="btn btn-sm btn-outline-primary ml-2">'.$childrenBtnLabel.'</a>';
            }

            echo '</div>';

            // если есть дети - рекурсивно
            if (count($children)) {
                echo '<ol>';
                foreach ($children as $cKey => $child) {
                    $children[$cKey] = render_tree_element_scoped(
                        $child,
                        $child->getKey(),
                        $all_entries,
                        $crud,
                        $scopeParentId,
                        $showChildrenBtn,
                        $childrenBtnLabel,
                        $baseReorderUrl,
                        $scopeKey
                    );
                }
                echo '</ol>';
            }
            echo '</li>';
        }

        return $entry;
    }
}

if (!function_exists('helper_fetch_key_for_model')) {
    function helper_fetch_key_for_model(?string $modelClass): ?string
    {
        if (!$modelClass) {
            return null;
        }

        $modelClass = ltrim($modelClass, '\\');

        foreach (config('helpers.fetchables', []) as $key => $definition) {
            $definitionClass = ltrim($definition['model'] ?? '', '\\');

            if ($definitionClass === $modelClass) {
                return $key;
            }

            if (
                $definitionClass !== '' &&
                class_exists($definitionClass) &&
                class_exists($modelClass) &&
                (
                    is_a($modelClass, $definitionClass, true) ||
                    is_a($definitionClass, $modelClass, true)
                )
            ) {
                return $key;
            }
        }

        return null;
    }
}
