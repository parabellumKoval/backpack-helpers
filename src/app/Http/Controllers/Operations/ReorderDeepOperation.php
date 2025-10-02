<?php

namespace Backpack\Helpers\app\Http\Controllers\Operations;

use Illuminate\Support\Facades\Route;

trait ReorderDeepOperation
{

    // Разрешённые фильтры: ключ = query param, column = имя столбца (поддерживает JSON path)
    // public $reorder_filterable = [
        // 'page'      => ['type' => 'string', 'column' => 'page'],          // ?page=product
        // 'is_active' => ['type' => 'bool',   'column' => 'is_active'],    // ?is_active=1
        // 'type'      => ['type' => 'string', 'column' => 'meta->type'],   // ?type=article (JSON)
    // ];

    /**
     * Define which routes are needed for this operation.
     *
     * @param  string  $name  Name of the current entity (singular). Used as first URL segment.
     * @param  string  $routeName  Prefix of the route name.
     * @param  string  $controller  Name of the current CrudController.
     */
    protected function setupReorderRoutes($segment, $routeName, $controller)
    {
        Route::get($segment.'/reorder', [
            'as'        => $routeName.'.reorder',
            'uses'      => $controller.'@reorder',
            'operation' => 'reorder',
        ]);

        Route::post($segment.'/reorder', [
            'as'        => $routeName.'.save.reorder',
            'uses'      => $controller.'@saveReorder',
            'operation' => 'reorder',
        ]);
    }

    /**
     * Add the default settings, buttons, etc that this operation needs.
     */
    protected function setupReorderDefaults()
    {
        $this->crud->set('reorder.enabled', true);
        $this->crud->allowAccess('reorder');

        $this->crud->operation('reorder', function () {
            $this->crud->loadDefaultOperationSettingsFromConfig();
        });
        
        $button_enabled = $this->crud->get('reorder.enable_button', true);

        $this->crud->operation('list', function () use ($button_enabled){
            
            $this->crud->addButton('top', 'reorder', 'view', 'crud::buttons.reorder');
            
        });
    }

    /**
     *  Reorder the items in the database using the Nested Set pattern.
     *
     *  Database columns needed: id, parent_id, lft, rgt, depth, name/title
     *
     *  @return Response
     */
    public function reorder()
    {
        $this->crud->hasAccessOrFail('reorder');

        $this->reorder_filterable = !empty($this->reorder_filterable)? $this->reorder_filterable: [];
        $this->crud->set('reorder.filterable', array_keys($this->reorder_filterable)); // чтобы вид знал, что показать в «активных фильтрах»

        $q = $this->crud->model->newQuery();

        // Применяем разрешённые фильтры
        foreach ($this->reorder_filterable as $param => $meta) {
            if (!request()->filled($param)) continue;

            $col  = $meta['column'] ?? $param;
            $type = $meta['type']   ?? 'string';
            $raw  = request($param);

            // Поддержка списков: ?page=product,article → whereIn
            if (is_string($raw) && str_contains($raw, ',')) {
                $vals = array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== '');
                if ($vals) {
                    $q->whereIn($col, $vals);
                    continue;
                }
            }

            switch ($type) {
                case 'int':  $q->where($col, (int)$raw); break;
                case 'bool': $q->where($col, in_array($raw, [1,'1',true,'true','on'], true)); break;
                default:     $q->where($col, $raw);
            }
        }

        // Ограничение по поддереву (если выбран родитель)
        $parent = null;
        $parentId = request('parent');

        if ($parentId) {
            $parent = $this->crud->model->findOrFail($parentId);

            // считать поддерево только если у родителя есть валидные границы
            $wantSubtree = (bool) ($this->crud->get('reorder.use_subtree', true));
            $hasBounds   = (int)$parent->lft > 0 && (int)$parent->rgt > 0;

            if ($wantSubtree && $hasBounds) {
                // всё поддерево выбранного родителя
                $q->where('lft', '>', $parent->lft)
                ->where('rgt', '<', $parent->rgt);
            } else {
                // фолбэк: только прямые дети (пока дерево «нулевое»)
                $q->where('parent_id', $parent->getKey());
            }
        }

        $entries = $q->orderBy('lft')->get();

        // Передаём в вид нужные параметры
        $this->crud->set('reorder.label', 'name');
        $this->crud->set('reorder.parent_id', $parentId);
        $this->crud->set('reorder.show_children_button', true);
        $this->crud->set('reorder.children_button_label', 'Отсортировать детей');

        return view('crud::deep-reorder', [
            'crud'    => $this->crud,
            'entries' => $entries,
            'parent'  => $parent,
        ]);
    }


    /**
     * Save the new order, using the Nested Set pattern.
     *
     * Database columns needed: id, parent_id, lft, rgt, depth, name/title
     *
     * @return
     */
    public function saveReorder()
    {
        $this->crud->hasAccessOrFail('reorder');

        $all_entries = \Request::input('tree');

        if (count($all_entries)) {
            $count = $this->crud->updateTreeOrder($all_entries);
        } else {
            return false;
        }

        return 'success for '.$count.' items';
    }
}
