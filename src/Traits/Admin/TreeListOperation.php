<?php

namespace Backpack\Helpers\Traits\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait TreeListOperation
{
    protected $treeListConfig = [];

    protected function setupTreeList(array $cfg = []): void
    {
        $this->treeListConfig = $cfg;

        $this->crud->addFilter([
            'type'  => 'simple',
            'name'  => 'tree',
            'label' => 'Древовидный вид',
        ], false, function () {
            // показываем только корни
            $this->crud->query->where(function (Builder $q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            });

            if ($this->hasColumn($this->crud->getModel(), 'lft')) {
                $this->crud->orderBy('lft');
            } else {
                $this->crud->orderBy($this->crud->getModel()->getKeyName());
            }

            // стандартный details_row
            $this->crud->enableDetailsRow();
            $this->crud->setOperationSetting('tree_mode', true);
        });
    }

    /**
     * Backpack вызывает этот метод при клике на кнопку в строке (и внутри вложенных таблиц тоже).
     * Возвращаем «контейнер-таблицу» без данных — данные придут ajax-ом через /search.
     */
    public function showDetailsRowTrait($id)
    {
        $crud  = $this->crud;
        $entry = $crud->getEntry($id);

        return view('crud::list.tree_children_shell', [
            'crud'          => $crud,
            'parent'        => $entry,
            'columns'       => $crud->columns(), // те же колонки
            'line_buttons'  => $crud->buttons()->where('stack', 'line'),
            'title'         => $this->treeListConfig['title'] ?? null,
            'pageLength'    => $this->treeListConfig['pageLength'] ?? 10,
        ]);
    }

    protected function hasColumn($model, string $column): bool
    {
        try {
            return Schema::hasColumn($model->getTable(), $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
