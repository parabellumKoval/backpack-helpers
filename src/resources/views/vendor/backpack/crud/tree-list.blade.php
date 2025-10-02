<div class="row">
  <div class="nested-table-container col-sm-12">
      <table id="nestedTable-{{$parent_id}}" 
           class="bg-white table table-striped table-hover nowrap rounded shadow-xs border-xs" 
           data-parent-id="{{$parent_id}}"
           data-has-details-row="true"
           data-route="{{ url($crud->route.'/search') }}">
          <thead>
            <tr>
              {{-- Table columns --}}
              @foreach ($crud->columns() as $column)
                <th
                  data-orderable="{{ var_export($column['orderable'], true) }}"
                  data-priority="{{ $column['priority'] }}"
                    {{--

                      data-visible-in-table => if developer forced field in table with 'visibleInTable => true'
                      data-visible => regular visibility of the field
                      data-can-be-visible-in-table => prevents the column to be loaded into the table (export-only)
                      data-visible-in-modal => if column apears on responsive modal
                      data-visible-in-export => if this field is exportable
                      data-force-export => force export even if field are hidden

                  --}}

                  {{-- If it is an export field only, we are done. --}}
                  @if(isset($column['exportOnlyField']) && $column['exportOnlyField'] === true)
                    data-visible="false"
                    data-visible-in-table="false"
                    data-can-be-visible-in-table="false"
                    data-visible-in-modal="false"
                    data-visible-in-export="true"
                    data-force-export="true"
                  @else
                    data-visible-in-table="{{var_export($column['visibleInTable'] ?? false)}}"
                    data-visible="{{var_export($column['visibleInTable'] ?? true)}}"
                    data-can-be-visible-in-table="true"
                    data-visible-in-modal="{{var_export($column['visibleInModal'] ?? true)}}"
                    @if(isset($column['visibleInExport']))
                        @if($column['visibleInExport'] === false)
                          data-visible-in-export="false"
                          data-force-export="false"
                        @else
                          data-visible-in-export="true"
                          data-force-export="true"
                        @endif
                      @else
                        data-visible-in-export="true"
                        data-force-export="false"
                      @endif
                  @endif
                >
                  {!! $column['label'] !!}
                </th>
              @endforeach

              @if ( $crud->buttons()->where('stack', 'line')->count() )
                <th data-orderable="false"
                    data-priority="{{ $crud->getActionsColumnPriority() }}"
                    data-visible-in-export="false"
                    >{{ trans('backpack::crud.actions') }}</th>
              @endif
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
            <tr>
              {{-- Table columns --}}
              @foreach ($crud->columns() as $column)
                <th>{!! $column['label'] !!}</th>
              @endforeach

              @if ( $crud->buttons()->where('stack', 'line')->count() )
                <th>{{ trans('backpack::crud.actions') }}</th>
              @endif
            </tr>
          </tfoot>
        </table>

        @if ( $crud->buttons()->where('stack', 'bottom')->count() )
        <div id="bottom_buttons" class="d-print-none text-center text-sm-left">
          @include('crud::inc.button_stack', ['stack' => 'bottom'])

          <div id="datatable_button_stack" class="float-right text-right hidden-xs"></div>
        </div>
        @endif

  </div>

</div>
<script>
    function initNestedTable() {
        let tableId = 'nestedTable-{{$parent_id}}';
        let $nestedTable = $('#' + tableId);
        let baseUrl = $nestedTable.data('route');
        
        // Modify DataTables configuration for nested table
        let dtConfig = {...window.crud.dataTableConfiguration};
        
        // Configure AJAX
        dtConfig.ajax = {
            url: baseUrl,
            type: 'POST',
            data: function(d) {
                d.parent_id = {{$parent_id}};
                return d;
            }
        };

        // Initialize DataTable
        let dt = $nestedTable.DataTable(dtConfig);
        
        // Add click handler for details control
        $nestedTable.on('click', 'td.details-control', function (e) {
            let tr = $(this).closest('tr');
            let row = dt.row(tr);
            let rowData = row.data();

            if (row.child.isShown()) {
                // This row is already open - close it
                row.child.hide();
                tr.removeClass('shown');
            } else {
                // Close other rows
                dt.rows().every(function(rowIdx, tableLoop, rowLoop) {
                    if (this.child.isShown()) {
                        this.child.hide();
                        $(this.node()).removeClass('shown');
                    }
                });

                // Show details for this row
                $.ajax({
                    url: '{{ url($crud->route."/show-details-row") }}/' + rowData.id,
                    data: {
                        parent_id: {{$parent_id}}
                    },
                    type: 'GET',
                    success: function(response) {
                        row.child(response).show();
                        tr.addClass('shown');
                        
                        // Initialize nested table if present
                        let $childTable = $(row.child()).find('table[data-parent-id]');
                        if ($childTable.length) {
                            if ($.fn.DataTable.isDataTable($childTable)) {
                                $childTable.DataTable().destroy();
                            }
                            window.crud.initializeDataTable($childTable);
                        }
                    }
                });
            }
        });

        // Move search bar
        $("#" + tableId + "_filter").appendTo('#datatable_search_stack_{{$parent_id}}');
        $("#" + tableId + "_filter input").removeClass('form-control-sm');
        
        return dt;
    }

    // Call initialization immediately
    $(document).ready(function() {
        initNestedTable();
    });
</script>
