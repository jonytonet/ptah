<?php

/**
 * ptah UI translations — English (default)
 *
 * Override per-project: php artisan vendor:publish --tag=ptah-lang
 * then edit lang/vendor/ptah/en/ui.php
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Toolbar
    |--------------------------------------------------------------------------
    */
    'btn_new'           => 'New',
    'search_placeholder'=> 'Search...',
    'btn_filters'       => 'Filters',
    'btn_view_active'   => 'View active',
    'btn_view_trash'    => 'View deleted',
    'btn_back'          => 'Back',
    'btn_trash'         => 'Trash',
    'btn_export'        => 'Export',
    'btn_columns'       => 'Columns',
    'col_show_all'      => 'Show all',
    'col_hide_all'      => 'Hide all',
    'btn_density'       => 'Density',
    'btn_refresh'       => 'Refresh',
    'btn_clear_filters' => 'Clear filters',
    'per_page_suffix'   => '/ page',

    /*
    |--------------------------------------------------------------------------
    | Density labels
    |--------------------------------------------------------------------------
    */
    'density_compact'     => 'Compact',
    'density_comfortable' => 'Comfortable',
    'density_spacious'    => 'Spacious',

    /*
    |--------------------------------------------------------------------------
    | Filter panel
    |--------------------------------------------------------------------------
    */
    'filters_title'           => 'Filters',
    'filters_clear_all'       => 'Clear all',
    'filters_date_shortcuts'  => 'Date shortcuts',
    'filters_date_from'       => 'From',
    'filters_date_to'         => 'To',
    'filters_all'             => '-- All --',
    'filters_no_results'      => 'No results found.',
    'filters_change'          => 'Change...',
    'filters_search_label'    => 'Search :label...',
    'filters_op_contains'     => 'contains',
    'filters_op_equals'       => 'equals',
    'filters_op_not_equals'   => 'not equal',
    'filters_op_starts'       => 'starts with',
    'filters_op_ends'         => 'ends with',
    'filters_saved'           => 'Saved:',
    'filters_save_action'     => 'Save current filter with name',
    'filters_save_placeholder'=> 'E.g.: Active clients SP',
    'filters_btn_save'        => 'Save',
    'filters_btn_cancel'      => 'Cancel',

    /*
    |--------------------------------------------------------------------------
    | Date shortcut labels
    |--------------------------------------------------------------------------
    */
    'date_today'      => 'Today',
    'date_yesterday'  => 'Yesterday',
    'date_last7'      => '7 days',
    'date_last30'     => '30 days',
    'date_week'       => 'This week',
    'date_month'      => 'This month',
    'date_last_month' => 'Last month',
    'date_quarter'    => 'Quarter',
    'date_year'       => 'This year',

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */
    'col_drag_title'     => 'Drag to reorder',
    'col_default_action' => 'Action',
    'col_actions'        => 'Actions',
    'btn_edit_title'     => 'Edit',
    'btn_restore_title'  => 'Restore',
    'btn_delete_title'   => 'Delete',
    'empty_title'        => 'No records found',
    'empty_subtitle'     => 'Adjust filters or add a new item',
    'col_id'             => 'ID',
    'col_created_at'     => 'Created at',
    'col_updated_at'     => 'Updated at',
    'pagination'         => 'Showing :first–:last of :total records',

    /*
    |--------------------------------------------------------------------------
    | Currency / number formatting
    |--------------------------------------------------------------------------
    */
    'currency_prefix'  => '$',
    'number_dec_point' => '.',
    'number_thousands' => ',',

    /*
    |--------------------------------------------------------------------------
    | Modal create / edit
    |--------------------------------------------------------------------------
    */
    'modal_edit_prefix'     => 'Edit',
    'modal_new_prefix'      => 'New',
    'modal_edit_subtitle'   => 'Change fields and save',
    'modal_create_subtitle' => 'Fill in the fields below',
    'select_placeholder'    => 'Select...',
    'search_entity'         => 'Search :label...',
    'no_results'            => 'No results found.',
    'btn_cancel'            => 'Cancel',
    'btn_save_changes'      => 'Save Changes',
    'btn_create'            => 'Create',
    'btn_save'              => 'Save',
    'btn_update'            => 'Update',
    'btn_edit'              => 'Edit',

    /*
    |--------------------------------------------------------------------------
    | Delete confirmation modal
    |--------------------------------------------------------------------------
    */
    'delete_title'   => 'Confirm deletion',
    'delete_message' => 'This action cannot be undone.',
    'btn_delete'     => 'Delete',

    /*
    |--------------------------------------------------------------------------
    | Boolean select options (CrudConfig)
    |--------------------------------------------------------------------------
    */
    'bool_yes' => 'Yes',
    'bool_no'  => 'No',

    /*
    |--------------------------------------------------------------------------
    | Permissions / Middleware
    |--------------------------------------------------------------------------
    */
    'permission_denied' => 'You do not have permission to perform this action.',

];
