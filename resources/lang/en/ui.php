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
    'filter_period_label'   => 'Period',
    'date_range_from_label' => 'from',
    'date_range_to_label'   => 'to',

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
    'bool_yes'    => 'Yes',
    'bool_no'     => 'No',
    'flag_green'  => 'Green',
    'flag_yellow' => 'Yellow',
    'flag_red'    => 'Red',

    /*
    |--------------------------------------------------------------------------
    | CRUD runtime messages
    |--------------------------------------------------------------------------
    */
    'crud_load_error'  => 'Failed to load data. Preferences reset.',
    'export_processing'=> 'Processing... you will receive a notification.',
    'crud_save_error'  => 'Error saving: :message',

    /*
    |--------------------------------------------------------------------------
    | Permissions / Middleware
    |--------------------------------------------------------------------------
    */
    'permission_denied' => 'You do not have permission to perform this action.',

    /*
    |--------------------------------------------------------------------------
    | Auth — Livewire pages
    |--------------------------------------------------------------------------
    */
    'auth_link_sent'           => 'Recovery link sent! Check your email.',
    'auth_too_many_attempts'   => 'Too many attempts. Try again in :seconds seconds.',
    'auth_invalid_credentials' => 'Invalid email or password.',
    'auth_password_reset_ok'   => 'Password changed successfully! Please log in.',

    /*
    |--------------------------------------------------------------------------
    | Profile page
    |--------------------------------------------------------------------------
    */
    'profile_updated'          => 'Profile updated successfully!',
    'profile_password_wrong'   => 'Current password is incorrect.',
    'profile_password_updated' => 'Password changed successfully!',
    'profile_totp_enabled'     => 'TOTP authentication enabled!',
    'profile_totp_invalid'     => 'Invalid code. Please try again.',
    'profile_email_2fa_sent'   => 'Code sent! Check your email to confirm.',
    'profile_recovery_regen'   => 'Codes regenerated. Store them in a safe place!',
    'profile_2fa_disabled'     => 'Two-factor authentication disabled.',
    'profile_session_revoked'  => 'Session ended.',
    'profile_sessions_revoked' => ':count session(s) ended.',
    'profile_photo_updated'    => 'Photo updated!',
    'profile_photo_removed'    => 'Photo removed.',

    /*
    |--------------------------------------------------------------------------
    | Two-factor challenge page
    |--------------------------------------------------------------------------
    */
    'two_fa_code_invalid' => 'Invalid or expired code.',
    'two_fa_email_sent'   => 'Code sent to :email',

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    */
    'mail_two_factor_subject' => 'Your verification code — :app',

    /*
    |--------------------------------------------------------------------------
    | Validation messages (FormValidatorService)
    |--------------------------------------------------------------------------
    */
    'validation_required'       => ':label is required.',
    'validation_min'            => ':label must be at least :param.',
    'validation_max'            => ':label must be at most :param.',
    'validation_minlength'      => ':label must be at least :param characters.',
    'validation_maxlength'      => ':label must be at most :param characters.',
    'validation_between'        => ':label must be between :min and :max.',
    'validation_digits'         => ':label must have exactly :param digit(s).',
    'validation_digits_between' => ':label must have between :min and :max digits.',
    'validation_in'             => ':label must be one of: :param.',
    'validation_not_in'         => ':label must not be: :param.',
    'validation_email'          => ':label must be a valid e-mail.',
    'validation_url'            => ':label must be a valid URL.',
    'validation_integer'        => ':label must be an integer.',
    'validation_numeric'        => ':label must be a numeric value.',
    'validation_alpha'          => ':label must contain only letters.',
    'validation_alpha_num'      => ':label must contain only letters and numbers.',
    'validation_ncm'            => ':label must be a valid NCM (e.g. 8471.30.19 or 84713019).',
    'validation_invalid'        => ':label is invalid.',
    'validation_phone'          => ':label must be a valid phone number.',
    'validation_regex'          => ':label has an invalid format.',
    'validation_date_invalid'   => ':label has an invalid date.',
    'validation_after'          => ':label must be a date after :ref.',
    'validation_before'         => ':label must be a date before :ref.',
    'validation_confirmed'      => ':label does not match the confirmation.',
    'validation_unique'         => ':label is already in use.',
    'validation_date_format'    => ':label must be in the format :format.',

    /*
    |--------------------------------------------------------------------------
    | Role validation (RoleService)
    |--------------------------------------------------------------------------
    */
    'role_master_cannot_deactivate' => 'The MASTER role cannot be deactivated.',
    'role_master_cannot_delete'     => 'The MASTER role cannot be deleted.',
    'role_master_already_exists'    => 'A MASTER role already exists. Only one MASTER role is allowed.',

    /*
    |--------------------------------------------------------------------------
    | General
    |--------------------------------------------------------------------------
    */
    'unknown' => 'Unknown',

];
