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
    'pagination_previous' => '← Previous',
    'pagination_next'     => 'Next →',
    'pagination_page_of'  => 'Page :current of :last',

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
    | Export PDF/Excel
    |--------------------------------------------------------------------------
    */
    'export_date'           => 'Export date',
    'export_total_records'  => 'Total records',
    'export_no_data'        => 'No records found to export.',
    'export_totalizers'     => 'Totalizers',
    'export_sum'            => 'Sum',
    'export_avg'            => 'Average',
    'export_count'          => 'Count',
    'export_max'            => 'Maximum',
    'export_min'            => 'Minimum',

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
    | Auth — Login page
    |--------------------------------------------------------------------------
    */
    'login_title'        => 'Sign in to your account',
    'login_subtitle'     => 'Welcome back',
    'login_password'     => 'Password',
    'login_remember_me'  => 'Remember me',
    'login_forgot'       => 'Forgot your password?',
    'login_btn'          => 'Sign in',
    'login_btn_loading'  => 'Signing in...',

    /*
    |--------------------------------------------------------------------------
    | Auth — Forgot password page
    |--------------------------------------------------------------------------
    */
    'forgot_title'        => 'Recover password',
    'forgot_subtitle'     => "We'll send a password reset link to your email",
    'forgot_btn'          => 'Send recovery link',
    'forgot_btn_loading'  => 'Sending...',
    'forgot_remembered'   => 'Remembered your password?',
    'forgot_back_login'   => 'Back to login',

    /*
    |--------------------------------------------------------------------------
    | Auth — Reset password page
    |--------------------------------------------------------------------------
    */
    'reset_title'            => 'New password',
    'reset_subtitle'         => 'Enter and confirm your new password',
    'reset_new_password'     => 'New password',
    'reset_confirm_password' => 'Confirm new password',
    'reset_btn'              => 'Reset password',
    'reset_btn_loading'      => 'Saving...',

    /*
    |--------------------------------------------------------------------------
    | Auth — Two-factor challenge page
    |--------------------------------------------------------------------------
    */
    'two_fa_page_title'          => 'Two-step verification',
    'two_fa_recovery_subtitle'   => 'Enter one of your recovery codes',
    'two_fa_auth_subtitle'       => 'Enter the code from your authenticator app or email',
    'two_fa_recovery_code_label' => 'Recovery code',
    'two_fa_verification_label'  => 'Verification code',
    'two_fa_verify_btn'          => 'Verify',
    'two_fa_verifying'           => 'Verifying...',
    'two_fa_use_authenticator'   => 'Use authenticator code',
    'two_fa_use_recovery_code'   => 'Use recovery code',
    'two_fa_resend_email'        => 'Resend code via email',
    'two_fa_back_login'          => 'Back to login',

    /*
    |--------------------------------------------------------------------------
    | Dashboard page
    |--------------------------------------------------------------------------
    */
    'dashboard_subtitle'    => 'System overview',
    'dashboard_welcome'     => 'Welcome',
    'dashboard_system'      => 'System',
    'dashboard_environment' => 'Environment',
    'dashboard_laravel_ver' => 'Laravel Version',

    /*
    |--------------------------------------------------------------------------
    | Profile page — UI labels
    |--------------------------------------------------------------------------
    */
    'profile_title'               => 'My Profile',
    'profile_subtitle'            => 'Manage your personal information and security',
    'profile_tab_profile'         => 'Profile',
    'profile_tab_password'        => 'Password',
    'profile_tab_2fa'             => '2FA Authentication',
    'profile_tab_sessions'        => 'Sessions',
    'profile_tab_photo'           => 'Photo',
    'profile_name'                => 'Name',
    'profile_save_btn'            => 'Save profile',
    'profile_current_pw'          => 'Current password',
    'profile_new_pw'              => 'New password',
    'profile_confirm_pw'          => 'Confirm new password',
    'profile_change_pw_btn'       => 'Change password',
    'profile_2fa_intro'           => 'Two-factor authentication adds an extra layer of security to your account. Choose your preferred method:',
    'profile_totp_apps'           => 'Google Authenticator, Authy, Bitwarden…',
    'profile_scan_qr'             => 'Scan the QR code with your authenticator app:',
    'profile_enter_key'           => 'Or enter the key manually:',
    'profile_confirm_btn'         => 'Confirm',
    'profile_setup_btn'           => 'Set up',
    'profile_email_code_hint'     => 'Code sent to :email',
    'profile_enable_btn'          => 'Enable',
    'profile_2fa_active_label'    => '2FA is active',
    'profile_2fa_authenticator'   => 'Authenticator App',
    'profile_recovery_codes_title'=> 'Recovery codes',
    'profile_recovery_codes_hint' => 'Store these codes in a safe place each one can only be used once.',
    'profile_regenerate_btn'      => 'Regenerate codes',
    'profile_view_recovery_btn'   => 'View recovery codes',
    'profile_disable_2fa_btn'     => 'Disable 2FA',
    'profile_disable_2fa_confirm' => 'Disable 2FA?',
    'profile_sessions_intro'      => 'Devices with active sessions on your account.',
    'profile_disconnect_others'   => 'Disconnect others',
    'profile_disconnect_confirm'  => 'Disconnect all other devices?',
    'profile_no_sessions'         => 'No sessions found.',
    'profile_this_session'        => 'this session',
    'profile_unknown_browser'     => 'Unknown browser',
    'profile_last_activity'       => 'last activity',
    'profile_revoke_btn'          => 'Revoke',
    'profile_select_image'        => 'Select image',
    'profile_save_photo_btn'      => 'Save photo',
    'profile_saving'              => 'Saving...',
    'profile_remove_btn'          => 'Remove',
    'profile_remove_confirm'      => 'Remove profile photo?',

    /*
    |--------------------------------------------------------------------------
    | Status labels (shared)
    |--------------------------------------------------------------------------
    */
    'lbl_active'              => 'Active',
    'lbl_inactive'            => 'Inactive',
    'lbl_all_types'           => 'All types',
    'btn_clear'               => 'Clear',
    'switcher_select_company' => 'Select company',

    /*
    |--------------------------------------------------------------------------
    | Module pages — Company
    |--------------------------------------------------------------------------
    */
    'company_title'           => 'Companies',
    'company_subtitle'        => 'Manage the companies and branches in the system.',
    'company_new_btn'         => 'New Company',
    'company_search_ph'       => 'Search by name, e-mail or tax ID...',
    'company_col_abbr'        => 'Abbr',
    'company_col_name'        => 'Name',
    'company_col_default'     => 'Default',
    'company_col_status'      => 'Status',
    'company_col_actions'     => 'Actions',
    'company_pagination'      => ':first–:last of :total',

    /*
    |--------------------------------------------------------------------------
    | Module pages — Menu
    |--------------------------------------------------------------------------
    */
    'menu_title'           => 'Manage Menu',
    'menu_subtitle'        => 'Register and organize the system sidebar items.',
    'menu_new_item_btn'    => 'New Item',
    'menu_search_ph'       => 'Search menu item...',
    'menu_all_types'       => 'All types',
    'menu_filter_by_type'  => 'Filter by type:',
    'menu_col_icon'        => 'Icon',
    'menu_col_text'        => 'Text',
    'menu_col_type'        => 'Type',
    'menu_col_url'         => 'URL',
    'menu_col_parent'      => 'Parent group',
    'menu_col_order'       => 'Order',
    'menu_col_status'      => 'Status',
    'menu_col_actions'     => 'Actions',
    'menu_empty'           => 'Add the first item using the \'New Item\' button',

    /*
    |--------------------------------------------------------------------------
    | Module pages — Roles / Permissions
    |--------------------------------------------------------------------------
    */
    'role_title'              => 'Roles / Profiles',
    'role_subtitle'           => 'Manage access profiles and their permissions per object.',
    'role_new_btn'            => 'New Role',
    'role_search_ph'          => 'Search role...',
    'role_col_name'           => 'Name',
    'role_col_department'     => 'Department',
    'role_col_permissions'    => 'Permissions',
    'role_col_status'         => 'Status',
    'role_col_actions'        => 'Actions',
    'role_objects_count'      => ':count objects',
    'role_manage_perms_btn'       => '🔑 Permissions',
    'role_manage_perms_title'     => 'Manage permissions',
    'role_form_title_edit'        => 'Edit Role',
    'role_form_name'              => 'Name *',
    'role_form_desc'              => 'Description',
    'role_form_color'             => 'Color (hex)',
    'role_form_dept'              => 'Department',
    'role_form_active'            => 'Active role',
    'role_form_no_dept'           => 'No department',
    'role_form_master'            => 'MASTER Role (total bypass)',
    'role_form_is_master_badge'   => '👑 This is the MASTER role',
    'role_form_master_warn'       => '⚠️ MASTER roles have unrestricted access. Only 1 role can be MASTER.',
    'role_empty_found'            => 'No roles found',
    'role_empty_hint'             => 'Add the first access profile',
    'role_bind_modal_prefix'      => 'Manage Permissions ',
    'role_bind_perm_read'         => 'Read',
    'role_bind_perm_create'       => 'Create',
    'role_bind_perm_edit'         => 'Edit',
    'role_bind_perm_delete'       => 'Delete',
    'role_bind_empty'             => 'No objects registered. Go to Pages and register objects first.',
    'role_bind_save'              => 'Save Permissions',
    'role_delete_text'            => 'Delete this role? Permissions and user bindings will be removed.',

    /*
    |--------------------------------------------------------------------------
    | Module pages — Company (form)
    |--------------------------------------------------------------------------
    */
    'company_modal_new'           => 'New Company',
    'company_modal_edit'          => 'Edit Company',
    'company_form_label'          => 'Abbr (4 chars)',
    'company_form_label_hint'     => 'Displayed in the menu badge',
    'company_form_phone'          => 'Phone',
    'company_form_phone_ph'       => '(00) 00000-0000',
    'company_form_email_ph'       => 'contact@company.com',
    'company_form_doc_type'       => 'Document type',
    'company_form_is_active'      => 'Active company',
    'company_form_is_default'     => 'Default company',
    'company_empty_found'         => 'No companies found',
    'company_empty_adjust'        => 'Adjust the search filter',
    'company_empty_add'           => 'Add the first company',
    'company_delete_text'         => 'Are you sure you want to delete this company? This action cannot be undone.',

    /*
    |--------------------------------------------------------------------------
    | Module pages — Menu (form)
    |--------------------------------------------------------------------------
    */
    'menu_form_title_new'         => 'New Menu Item',
    'menu_form_title_edit'        => 'Edit Menu Item',
    'menu_form_type'              => 'Type',
    'menu_form_direct_link'       => 'Direct link',
    'menu_form_group_type'        => 'Group (with sub-items)',
    'menu_form_text_label'        => 'Displayed text',
    'menu_form_text_ph'           => 'e.g.: Products, Reports...',
    'menu_form_url_ph'            => '/dashboard, /products, https://...',
    'menu_form_icon_label'        => 'Icon',
    'menu_form_icon_hint'         => '(CSS class Boxicons or FontAwesome)',
    'menu_form_icon_ph'           => 'bx bx-home  /  fas fa-user',
    'menu_form_parent_group'      => 'Parent group',
    'menu_form_root'              => 'Root (top level) ',
    'menu_form_order'             => 'Order',
    'menu_form_opening'           => 'Opening target',
    'menu_form_same_tab'          => 'Same tab',
    'menu_form_new_tab'           => 'New tab',
    'menu_form_active'            => 'Active',
    'menu_save_changes'           => 'Save changes',
    'menu_create_item'            => 'Create item',
    'menu_delete_title'           => 'Delete item',
    'menu_delete_text'            => 'This action cannot be undone. If it is a group, the children will be unlinked.',
    'menu_delete_confirm'         => 'Yes, delete',
    'menu_group_badge'            => 'Group',
    'menu_link_badge'             => 'Link',
    'menu_toggle_disable'         => 'Click to disable',
    'menu_toggle_enable'          => 'Click to enable',
    'menu_empty_found'            => 'No menu items found',

    /*
    |--------------------------------------------------------------------------
    | Shared UI
    |--------------------------------------------------------------------------
    */
    'btn_saving'                  => 'Saving...',
    'btn_yes_delete'              => 'Yes, delete',

    /*
    |--------------------------------------------------------------------------
    | Module pages — Department
    |--------------------------------------------------------------------------
    */
    'dept_title'           => 'Departments',
    'dept_subtitle'        => 'Group profiles/roles by department.',
    'dept_new_btn'         => 'New Department',
    'dept_search_ph'       => 'Search department...',
    'dept_col_name'        => 'Name',
    'dept_col_desc'        => 'Description',
    'dept_col_roles'       => 'Roles',
    'dept_col_status'      => 'Status',
    'dept_col_actions'     => 'Actions',
    'dept_empty_found'     => 'No departments found',
    'dept_empty_hint'      => 'Add the first department',
    'dept_modal_new'       => 'New Department',
    'dept_modal_edit'      => 'Edit Department',
    'dept_form_name'       => 'Name *',
    'dept_form_desc'       => 'Description',
    'dept_form_active'     => 'Active department',
    'dept_delete_text'     => 'Delete this department? Linked roles will lose their department.',

    /*
    |--------------------------------------------------------------------------
    | Module pages — Audit
    |--------------------------------------------------------------------------
    */
    'audit_title'             => 'Permission Auditing',
    'audit_subtitle'          => 'Log of granted and denied accesses. Read-only.',
    'audit_search_ph'         => 'Search resource, IP, user...',
    'audit_all_results'       => 'All results',
    'audit_result_granted'    => '✅ Granted',
    'audit_result_denied'     => '❌ Denied',
    'audit_all_actions'       => 'All actions',
    'audit_action_create'     => 'Create',
    'audit_action_read'       => 'Read',
    'audit_action_update'     => 'Edit',
    'audit_action_delete'     => 'Delete',
    'audit_title_from'        => 'From',
    'audit_title_to'          => 'To',
    'audit_col_datetime'      => 'Date/Time',
    'audit_col_user'          => 'User',
    'audit_col_resource'      => 'Resource',
    'audit_col_action'        => 'Action',
    'audit_col_result'        => 'Result',
    'audit_col_ip'            => 'IP',
    'audit_empty_filtered'    => 'No records found',
    'audit_empty_filtered_hint'=> 'Try adjusting the applied filters.',
    'audit_empty_title'       => 'No audit records',
    'audit_empty_hint'        => 'Activate with PTAH_PERMISSION_AUDIT=true in .env.',

    /*
    |--------------------------------------------------------------------------
    | Module pages — Pages & Objects
    |--------------------------------------------------------------------------
    */
    'page_title'              => 'Pages and Objects',
    'page_subtitle'           => 'Register system pages and their objects (buttons, fields, links) for access control.',
    'page_col_pages'          => 'Pages',
    'page_new_btn'            => 'Page',
    'page_search_ph'          => 'Search page...',
    'page_empty_found'        => 'No pages registered',
    'page_empty_hint'         => 'Create the first page to get started.',
    'page_objects_header'     => 'Objects :page',
    'page_new_obj_btn'        => 'Object',
    'page_obj_search_ph'      => 'Search object...',
    'page_obj_col_key_label'  => 'Key / Label',
    'page_obj_col_type'       => 'Type',
    'page_obj_col_section'    => 'Section',
    'page_obj_col_actions'    => 'Actions',
    'page_obj_empty_found'    => 'No objects on this page',
    'page_obj_empty_hint'     => 'Add objects to control access.',
    'page_select_hint'        => 'Select a page to see its objects',
    'page_modal_new'          => 'New Page',
    'page_modal_edit'         => 'Edit Page',
    'page_form_slug'          => 'Slug *',
    'page_form_name'          => 'Name *',
    'page_form_desc'          => 'Description',
    'page_form_route'         => 'Laravel Route',
    'page_form_icon'          => 'Icon',
    'page_form_active'        => 'Active page',
    'page_form_order'         => 'Order',
    'page_obj_modal_new'      => 'New Object',
    'page_obj_modal_edit'     => 'Edit Object',
    'page_obj_form_section'   => 'Section',
    'page_obj_form_type'      => 'Type *',
    'page_obj_form_key'       => 'Key *',
    'page_obj_form_label'     => 'Label *',
    'page_obj_form_active'    => 'Active object',
    'page_obj_form_order'     => 'Order',
    'page_delete_page_text'   => 'Delete this page? All linked objects will also be removed.',
    'page_delete_obj_text'    => 'Delete this object? Role permissions linked to it will be removed.',

    /*
    |--------------------------------------------------------------------------
    | Module pages — User Permission
    |--------------------------------------------------------------------------
    */
    'user_perm_title'         => 'Users Access Control',
    'user_perm_subtitle'      => 'Assign roles and companies to system users.',
    'user_perm_search_ph'     => 'Search by name or e-mail...',
    'user_perm_all_roles'     => 'All roles',
    'user_perm_filter_role'   => 'Filter by role:',
    'user_perm_col_user'      => 'User',
    'user_perm_col_roles'     => 'Assigned roles',
    'user_perm_col_actions'   => 'Actions',
    'user_perm_no_roles'      => 'No roles',
    'user_perm_manage_btn'    => '🔑 Manage Access',
    'user_perm_empty'         => 'No users found',
    'user_perm_empty_hint'    => 'Try adjusting the search filters.',
    'user_perm_modal_prefix'  => 'Access ',
    'user_perm_assigned_roles'=> 'Assigned roles',
    'user_perm_remove_btn'    => 'Remove',
    'user_perm_protected'     => 'Protected',
    'user_perm_no_assigned'   => 'No roles assigned.',
    'user_perm_add_role'      => 'Add role',
    'user_perm_company_label' => 'Company',
    'user_perm_global'        => 'Global (no company)',
    'user_perm_add_btn'       => 'Add',
    'user_perm_close_btn'     => 'Close',

    /*
    |--------------------------------------------------------------------------
    | Module pages — Permission Guide
    |--------------------------------------------------------------------------
    */
    'guide_title'    => 'Permission System Guide',
    'guide_subtitle' => 'How the Ptah ACL works and how to configure access step by step.',
    'guide_badge'    => '📖 Documentation',

    // Tabs
    'guide_tab_overview' => '🗺️ Overview',
    'guide_tab_setup'    => '🔧 Step by Step',
    'guide_tab_code'     => '💻 Code Examples',
    'guide_tab_faq'      => '❓ FAQ',

    // Overview — Intro
    'guide_ov_title' => "What is Ptah's permission system?",
    'guide_ov_body'  => 'The Ptah ACL (Access Control List) is an access control system based on <strong>Roles</strong>, inspired by the RBAC standard. It allows you to define <em>who can do what</em> in each part of the system, with granularity down to a button or individual field level.<br><br>Unlike Laravel\'s simple <code class="bg-indigo-100 px-1.5 py-0.5 rounded text-xs font-mono">Gate/Policy</code>, Ptah ACL is <strong>dynamic and manageable via interface</strong> — no need to change code to add new permissions.',

    // Overview — Architecture
    'guide_ov_arch_title'  => 'Architecture — How concepts relate',
    'guide_ov_dept_title'  => 'Departments',
    'guide_ov_dept_desc'   => 'Optional logical grouping of Roles',
    'guide_ov_dept_ex'     => 'ex: IT, Sales, Finance',
    'guide_ov_roles_title' => 'Roles / Profiles',
    'guide_ov_roles_desc'  => 'Carries permissions per object',
    'guide_ov_roles_ex'    => 'ex: Admin, Seller, Support',
    'guide_ov_pages_title' => 'Pages + Objects',
    'guide_ov_pages_desc'  => 'What can be controlled',
    'guide_ov_pages_ex'    => 'ex: /sales, "Export" button, "Discount" field',
    'guide_ov_users_title' => 'Users',
    'guide_ov_users_desc'  => 'Receive Roles per Company',
    'guide_ov_users_ex'    => 'ex: John — Admin in Company A',
    'guide_ov_co_title'    => 'Companies',
    'guide_ov_co_desc'     => 'Binding scope (optional)',
    'guide_ov_co_ex'       => 'ex: Multi-tenant or Global',

    // Overview — Concepts
    'guide_ov_concepts_title' => 'Core concepts',
    'guide_con_role_title'    => 'Role (Profile)',
    'guide_con_role_body'     => 'A Role is a set of permissions. Instead of giving permissions directly to the user, you create a Role with the permissions and assign the Role to the user.',
    'guide_con_page_title'    => 'Page',
    'guide_con_page_body'     => 'Represents a module or route in the system. Each Page contains <strong>Objects</strong> — individual elements whose access can be controlled (buttons, fields, links, actions).',
    'guide_con_obj_title'     => 'Object + Permission',
    'guide_con_obj_body'      => 'An Object is a granular element within a Page. Each object has 4 permission flags: <strong>Read, Create, Edit, Delete</strong>. A Role can have partial permission (read-only, for example).',
    'guide_con_perms_read'    => 'Read',
    'guide_con_perms_create'  => 'Create',
    'guide_con_perms_edit'    => 'Edit',
    'guide_con_perms_delete'  => 'Delete',
    'guide_con_master_title'  => 'MASTER Role',
    'guide_con_master_body'   => 'A Role marked as MASTER has unrestricted access to <strong>all resources</strong>, bypassing checks. Only 1 MASTER Role can exist. Use only for superadmins.',
    'guide_con_master_warn'   => '⚠️ Use with care — bypasses all checks',
    'guide_con_scope_title'   => 'Company Scope',
    'guide_con_scope_body'    => 'A user can have different Roles in different companies. Example: John is Admin in Company A and only Reader in Company B. Set <code class="font-mono bg-slate-100 px-1 rounded text-xs">NULL</code> for global access.',
    'guide_con_audit_title'   => 'Audit',
    'guide_con_audit_body'    => 'When enabled, each permission check is logged with user, resource, action and result (granted/denied). Enable with <code class="font-mono bg-slate-100 px-1 rounded text-xs">PTAH_PERMISSION_AUDIT=true</code> in .env.',

    // Overview — Flow
    'guide_ov_flow_title' => 'Access verification flow',
    'guide_flow_start'    => 'User tries to access resource',
    'guide_flow_q1'       => '① Is user authenticated?',
    'guide_flow_q2'       => '② Does any user Role have MASTER?',
    'guide_flow_q3'       => '③ Does Role have permission (e.g.: can_read) for this object?',
    'guide_flow_yes'      => 'Yes',
    'guide_flow_no'       => 'No',
    'guide_flow_granted'  => '✅ ACCESS GRANTED',
    'guide_flow_denied'   => '🚫 ACCESS DENIED',
    'guide_flow_login'    => '🚫 Redirect to login',

    // Setup tab - Prerequisite
    'guide_setup_prereq' => '<strong>Prerequisite:</strong> Run <code class="font-mono text-xs bg-indigo-100 px-1.5 rounded">php artisan migrate</code> to create the Ptah tables, and <code class="font-mono text-xs bg-indigo-100 px-1.5 rounded">php artisan db:seed --class=Ptah\\Seeders\\DefaultCompanySeeder</code> to create the default company.',

    // Setup — Step 1
    'guide_s1_title'    => 'Register Departments <span class="text-slate-400 font-normal">(Optional)</span>',
    'guide_s1_desc'     => 'Group your Roles into departments for better organization.',
    'guide_s1_btn'      => 'Go to Departments →',
    'guide_s1_body'     => 'Departments are optional logical groupings for your Roles. Useful when the system has many profiles.',
    'guide_s1_example'  => 'Example',
    'guide_s1_ex_it'    => 'Department <strong>IT</strong> → Roles: Developer, DevOps, IT Support',
    'guide_s1_ex_sales' => 'Department <strong>Sales</strong> → Roles: Seller, Sales Manager, SDR',
    'guide_s1_ex_fin'   => 'Department <strong>Finance</strong> → Roles: Financial Analyst, Controller',

    // Setup — Step 2
    'guide_s2_title'      => 'Register Pages and Objects',
    'guide_s2_desc'       => 'Register the system modules and what can be controlled in them.',
    'guide_s2_btn'        => 'Go to Pages →',
    'guide_s2_body'       => 'A <strong>Page</strong> represents a module or section of the system (ex: <code class="font-mono text-xs bg-slate-100 px-1 rounded">admin.sales</code>). Each page can have multiple <strong>Objects</strong> — representing granular elements such as buttons, fields or actions.',
    'guide_s2_page_title' => '📄 Page Example',
    'guide_s2_page_slug'  => 'Slug',
    'guide_s2_page_name'  => 'Name',
    'guide_s2_page_icon'  => 'Icon',
    'guide_s2_obj_title'  => '🔑 Objects of this Page',

    // Setup — Step 3
    'guide_s3_title'      => 'Create Roles and define permissions',
    'guide_s3_desc'       => 'Create access profiles and configure which objects each profile can access.',
    'guide_s3_btn'        => 'Go to Roles →',
    'guide_s3_body'       => 'Create a Role with name and color. Then click <strong>🔑 Permissions</strong> to define which objects this Role can <em>Read, Create, Edit and Delete</em>.',
    'guide_s3_ex_title'   => 'Example: Role "Standard Seller"',
    'guide_s3_col_obj'    => 'Object',
    'guide_s3_col_read'   => 'Read',
    'guide_s3_col_create' => 'Create',
    'guide_s3_col_edit'   => 'Edit',
    'guide_s3_col_delete' => 'Delete',
    'guide_s3_note'       => '↑ Seller can create orders but cannot see discounts and cannot export without restriction.',

    // Setup — Step 4
    'guide_s4_title'    => 'Link users to Roles',
    'guide_s4_desc'     => 'Assign one or more Roles to each user, with company scope.',
    'guide_s4_btn'      => 'Go to Users →',
    'guide_s4_body'     => 'On the Access Control screen, click <strong>🔑 Manage Access</strong> next to the user. Select a Role and a Company (or "Global" for scope-free access).',
    'guide_s4_ex_title' => 'Example: User <span class="text-indigo-600">John Silva</span>',
    'guide_s4_ex1'      => 'Role <strong class="text-purple-700">Admin</strong> at company <strong>Company A Ltda</strong> <span class="text-slate-400">→ full access in Company A</span>',
    'guide_s4_ex2'      => 'Role <strong class="text-blue-700">Reader</strong> at company <strong>Company B SA</strong> <span class="text-slate-400">→ read-only in Company B</span>',

    // Setup — Step 5
    'guide_s5_title' => 'Use permissions in code',
    'guide_s5_desc'  => 'See the "Code Examples" tab for full details.',
    'guide_s5_btn'   => 'See examples →',
    'guide_s5_body'  => 'Use the helper <code class="font-mono text-xs bg-slate-100 px-1.5 rounded">ptah_can(\'object.key\', \'read\')</code> in Blade views or the middleware <code class="font-mono text-xs bg-slate-100 px-1.5 rounded">ptah.can:object.key,read</code> in routes to protect access.',

    // FAQ items
    'guide_faq_q1' => 'What happens if the user has no Role?',
    'guide_faq_a1' => 'Without any Role, the user will not have access to any controlled object. Checks with <code class="font-mono text-xs bg-slate-100 px-1 rounded">ptah_can()</code> return <strong>false</strong> and the middleware <code class="font-mono text-xs bg-slate-100 px-1 rounded">ptah.can</code> returns HTTP 403.',
    'guide_faq_q2' => 'Can a user have more than one Role?',
    'guide_faq_a2' => 'Yes! A user can have multiple Roles, including in different companies. If any of the user\'s Roles has the requested permission, access is granted.',
    'guide_faq_q3' => 'What is the MASTER Role and when should I use it?',
    'guide_faq_a3' => 'A MASTER Role bypasses <strong>all</strong> permission checks, granting unrestricted access. Use exclusively for system superadmins. Only 1 MASTER Role can be configured.',
    'guide_faq_q4' => 'How does company scope work?',
    'guide_faq_a4' => 'When linking a user to a Role, you can specify a Company. The check considers only the Roles valid for the current company context. Links with company <code class="font-mono text-xs bg-slate-100 px-1 rounded">NULL</code> are valid globally.',
    'guide_faq_q5' => 'Are permissions cached?',
    'guide_faq_a5' => 'Yes. Ptah uses Laravel\'s cache to avoid excessive queries. The cache is automatically invalidated when a user\'s links are changed via the interface. You can clear it with <code class="font-mono text-xs bg-slate-100 px-1 rounded">php artisan cache:clear</code>.',
    'guide_faq_q6' => 'Can I create Pages and Objects automatically via code?',
    'guide_faq_a6' => 'Yes. Use the seeder or create records in <code class="font-mono text-xs bg-slate-100 px-1 rounded">Ptah\Models\Page</code> and <code class="font-mono text-xs bg-slate-100 px-1 rounded">Ptah\Models\PageObject</code> directly. Useful for populating via migration on deploy.',
    'guide_faq_q7' => 'What happens if I delete an Object that already has defined permissions?',
    'guide_faq_a7' => 'The permission table entries associated with the object are cascade-removed. Roles that had that object lose permission automatically. MASTER users are not affected (bypass).',
    'guide_faq_q8' => 'How to audit who accessed what?',
    'guide_faq_a8' => 'Enable <code class="font-mono text-xs bg-slate-100 px-1 rounded">PTAH_PERMISSION_AUDIT=true</code> in .env. Each check (granted or denied) will be logged in the <code class="font-mono text-xs bg-slate-100 px-1 rounded">ptah_permission_audits</code> table. Access the log at <a href=":audit_url" class="text-indigo-600 underline">Audit</a>.',
    'guide_faq_help_title' => 'Need more help?',
    'guide_faq_help_body'  => 'Check the <strong>Overview</strong> to understand the architecture, the <strong>Step by Step</strong> to configure, and the <strong>Code Examples</strong> to integrate into your project.',

    /*
    |--------------------------------------------------------------------------
    | CRUD Config (crud-config.blade.php)
    |--------------------------------------------------------------------------
    */
    // Button & header
    'cfg_btn_title'                => 'Configure CRUD',

    // Sidebar nav
    'cfg_nav_cols'                 => 'Columns',
    'cfg_nav_actions'              => 'Actions',
    'cfg_nav_filters'              => 'Custom Filters',
    'cfg_nav_styles'               => 'Styles',
    'cfg_nav_joins'                => 'JOINs',
    'cfg_nav_general'              => 'General',
    'cfg_nav_permissions'          => 'Permissions',
    'cfg_nav_hooks'                => 'Lifecycle Hooks',

    // Top bar tab titles
    'cfg_tab_title_cols'           => 'Column Configuration',
    'cfg_tab_title_actions'        => 'Row Actions',
    'cfg_tab_title_filters'        => 'Custom Filters',
    'cfg_tab_title_styles'         => 'Conditional Styles',
    'cfg_tab_title_joins'          => 'Configured JOINs',
    'cfg_tab_title_general'        => 'General Settings',
    'cfg_tab_title_permissions'    => 'Permissions & Access',
    'cfg_tab_title_hooks'          => 'Lifecycle Hooks',

    // Top bar tab descriptions
    'cfg_tab_desc_cols'            => 'Define, sort and configure each table column',
    'cfg_tab_desc_actions'         => 'Buttons and links displayed on each row',
    'cfg_tab_desc_filters'         => 'Advanced filters with relations and aggregations',
    'cfg_tab_desc_styles'          => 'Style rows based on data conditions',
    'cfg_tab_desc_joins'           => 'SQL JOINs between tables — without relying on Eloquent relationships',
    'cfg_tab_desc_general'         => 'Cache, export, appearance and behavior',
    'cfg_tab_desc_permissions'     => 'Laravel Gates and button visibility',
    'cfg_tab_desc_hooks'           => 'Execute custom PHP code before/after create/update operations',

    // Lifecycle Hooks tab
    'cfg_hooks_info_title'         => 'Dynamic Code Execution',
    'cfg_hooks_info_desc'          => 'Write inline PHP code OR reference a PHP class with @ syntax. Errors are logged but won\'t break the save operation.',
    'cfg_hooks_before_create'      => 'Before Create',
    'cfg_hooks_before_create_desc' => 'Runs before inserting a new record. Modify $data by reference. Variables: $data (array)',
    'cfg_hooks_after_create'       => 'After Create',
    'cfg_hooks_after_create_desc'  => 'Runs after creating a new record. Variables: $record (Model), $data (array)',
    'cfg_hooks_before_update'      => 'Before Update',
    'cfg_hooks_before_update_desc' => 'Runs before updating an existing record. Modify $data by reference. Variables: $data (array), $record (Model)',
    'cfg_hooks_after_update'       => 'After Update',
    'cfg_hooks_after_update_desc'  => 'Runs after updating a record. Variables: $record (Model), $data (array)',
    'cfg_hooks_example'            => 'Example',
    'cfg_hooks_example_syntax'     => 'Two syntaxes available:',
    'cfg_hooks_example_inline'     => 'Inline code (eval):',
    'cfg_hooks_example_class'      => 'PHP Class (recommended):',
    'cfg_hooks_warning_title'      => 'Security Warning',
    'cfg_hooks_warning_desc'       => 'Inline code is executed with eval(). For complex logic, use PHP classes (@MyHooks::method). Only administrators should have access to this configuration.',

    // Columns tab — table
    'cfg_col_table_title'          => 'Table Columns',
    'cfg_col_table_hint'           => 'Drag to reorder. Click ✏ to edit.',
    'cfg_col_th_field'             => 'Physical Field',
    'cfg_col_th_label'             => 'Label',
    'cfg_col_th_type'              => 'Type',
    'cfg_col_th_renderer'          => 'Renderer',
    'cfg_col_th_mask'              => 'Mask',
    'cfg_col_th_save'              => 'Save',
    'cfg_col_th_filterable'        => 'Filterable',
    'cfg_col_th_actions'           => 'Actions',
    'cfg_col_empty'                => 'No columns configured. Add below.',
    'cfg_col_remove_confirm'       => "Remove column ':col'?",

    // Columns tab — form
    'cfg_col_form_editing'         => '✏ Editing column',
    'cfg_col_form_new'             => '+ New Column',
    'cfg_col_cancel_edit'          => 'Cancel editing',
    'cfg_col_subtab_basic'         => 'Basic',
    'cfg_col_subtab_display'       => 'Display',
    'cfg_col_subtab_mask'          => 'Mask',
    'cfg_col_subtab_validation'    => 'Validation',
    'cfg_col_subtab_relation'      => 'Relation',
    'cfg_col_subtab_sd'            => 'SearchDropdown',
    'cfg_col_subtab_totalizer'     => 'Totalizer',

    // Columns tab — Basic fields
    'cfg_col_field_label'          => 'Physical Field (DB) *',
    'cfg_col_logic_label'          => 'Label (display)',
    'cfg_col_sql_label'            => 'SQL Source',
    'cfg_col_type_label'           => 'Type',
    'cfg_col_align_label'          => 'Alignment',
    'cfg_col_align_left'           => 'Left',
    'cfg_col_align_center'         => 'Center',
    'cfg_col_align_right'          => 'Right',
    'cfg_col_cb_save'              => 'Include in Form (Save)',
    'cfg_col_cb_required'          => 'Required',
    'cfg_col_cb_filterable'        => 'Filterable',

    // Type options
    'cfg_col_type_text'            => 'text — Text',
    'cfg_col_type_number'          => 'number — Number',
    'cfg_col_type_date'            => 'date — Date',
    'cfg_col_type_datetime'        => 'datetime — Date & Time',
    'cfg_col_type_select'          => 'select — Selection',
    'cfg_col_type_sd'              => 'searchdropdown — Relational Search',
    'cfg_col_type_boolean'         => 'boolean — Yes/No',
    'cfg_col_type_textarea'        => 'textarea — Long Text',
    'cfg_col_type_image'           => 'image — Image with Preview',

    // Image input
    'image_pick_file'              => 'Pick a file…',
    'image_preview_label'          => 'preview',

    // Cell style
    'cfg_col_cell_style_title'     => 'Cell Style',
    'cfg_col_cell_preview'         => 'Preview:',
    'cfg_col_cell_example'         => 'Example value',

    // Renderer
    'cfg_col_renderer_badge_map'   => 'Badge Mapping',
    'cfg_col_renderer_add_badge'   => '+ Add badge',
    'cfg_col_renderer_bool_true'   => 'True Text',
    'cfg_col_renderer_bool_false'  => 'False Text',
    'cfg_col_renderer_currency'    => 'Currency',
    'cfg_col_renderer_decimals'    => 'Decimal Places',
    'cfg_col_renderer_url_tmpl'    => 'URL Template',
    'cfg_col_renderer_link_label'  => 'Link label (optional)',
    'cfg_col_renderer_new_tab'     => 'Open in new tab',
    'cfg_col_renderer_width'       => 'Width (px)',
    'cfg_col_renderer_height'      => 'Height (px, optional)',
    'cfg_col_renderer_max_chars'   => 'Max Characters',
    'cfg_col_renderer_max_val'     => 'Maximum Value',
    'cfg_col_renderer_color'       => 'Color',
    'cfg_col_renderer_max_stars'   => 'Max Stars',
    'cfg_col_renderer_duration'    => 'Input Unit',
    'cfg_col_renderer_qr_size'     => 'Size',

    // Mask sub-tab
    'cfg_col_mask_label'           => 'Input Mask',
    'cfg_col_mask_transform'       => 'Transform before Saving',

    // Validation sub-tab
    'cfg_col_valid_email'          => 'Valid e-mail',
    'cfg_col_valid_url'            => 'Valid URL',
    'cfg_col_valid_integer'        => 'Integer',
    'cfg_col_valid_numeric'        => 'Numeric',
    'cfg_col_valid_cpf'            => 'Valid CPF',
    'cfg_col_valid_cnpj'           => 'Valid CNPJ',
    'cfg_col_valid_phone'          => 'Valid phone',
    'cfg_col_valid_alpha'          => 'Letters only',
    'cfg_col_valid_alphanum'       => 'Letters + numbers',
    'cfg_col_valid_ncm'            => 'Valid NCM (8 digits)',
    'cfg_col_valid_min'            => 'Minimum Value (min:X)',
    'cfg_col_valid_max'            => 'Maximum Value (max:X)',
    'cfg_col_valid_min_len'        => 'Minimum Length (minLength:X)',
    'cfg_col_valid_max_len'        => 'Maximum Length (maxLength:X)',
    'cfg_col_valid_regex'          => 'Custom Regex',
    'cfg_col_valid_digits'         => 'Exactly N Digits (digits:N)',
    'cfg_col_valid_digits_btw'     => 'Digits Between N and M (digitsBetween:N,M)',
    'cfg_col_valid_after'          => 'Date After (after:date)',
    'cfg_col_valid_before'         => 'Date Before (before:date)',
    'cfg_col_valid_date_fmt'       => 'Date Format (dateFormat:format)',
    'cfg_col_valid_confirmed'      => 'Confirmation (confirmed:field)',
    'cfg_col_valid_unique'         => 'Unique (unique:Model,field)',
    'cfg_col_valid_in'             => 'Allowed Values (in:a,b,c)',
    'cfg_col_valid_not_in'         => 'Forbidden Values (notIn:a,b,c)',
    'cfg_col_valid_rules_active'   => 'Active rules:',

    // Relation sub-tab
    'cfg_col_rel_name'             => 'Eloquent Relation',
    'cfg_col_rel_display'          => 'Display Field',

    // Totalizer sub-tab
    'cfg_col_total_enable'         => 'Enable Totalizer on this Column',

    // Column save buttons
    'cfg_col_btn_save'             => 'Save Column Changes',
    'cfg_col_btn_add'              => 'Add Column',

    // Columns > Basic extra
    'cfg_col_select_opts'          => 'Select Options',
    'cfg_col_custom_method'        => 'Custom Method (PHP)',
    'cfg_col_method_raw_label'     => 'colsMetodoRaw — Render return as raw HTML (no escape)',
    'cfg_col_method_syntax'        => 'View syntax and examples',
    'cfg_col_order_by'             => 'Alternative sort (colsOrderBy)',

    // Columns > Cell Style extra labels
    'cfg_col_cell_style_hint'      => 'Applied to the cell content wrapper',
    'cfg_col_cell_min_width'       => 'Min Width (colsMinWidth)',
    'cfg_col_cell_icon_prefix'     => 'Prefix Icon (colsCellIcon)',
    'cfg_col_cell_tw_class'        => 'Tailwind Class / Extra CSS',

    // Columns > Renderer select options
    'cfg_col_renderer_label'       => 'Renderer',
    'cfg_col_renderer_none'        => '— None (raw value) —',
    'cfg_col_renderer_opt_badge'   => 'badge — Colored badge by value',
    'cfg_col_renderer_opt_pill'    => 'pill — Rounded pill',
    'cfg_col_renderer_opt_boolean' => 'boolean — Yes / No',
    'cfg_col_renderer_opt_money'   => 'money — Monetary value',
    'cfg_col_renderer_opt_date'    => 'date — Date (d/m/Y)',
    'cfg_col_renderer_opt_datetime'=> 'datetime — Date and Time',
    'cfg_col_renderer_opt_link'    => 'link — Clickable link',
    'cfg_col_renderer_opt_image'   => 'image — Thumbnail',
    'cfg_col_renderer_opt_truncate'=> 'truncate — Truncated text',
    'cfg_col_renderer_group_data'  => 'Data',
    'cfg_col_renderer_opt_number'  => 'number — Formatted number (1,234.56)',
    'cfg_col_renderer_opt_filesize'=> 'filesize — File size (KB/MB)',
    'cfg_col_renderer_opt_duration'=> 'duration — Duration (1h 35min)',
    'cfg_col_renderer_opt_code'    => 'code — Monospace code',
    'cfg_col_renderer_opt_color_sw'=> 'color — Hex color swatch',
    'cfg_col_renderer_group_visual'=> 'Visual',
    'cfg_col_renderer_opt_progress'=> 'progress — Progress bar',
    'cfg_col_renderer_opt_rating'  => 'rating — Star rating',
    'cfg_col_renderer_opt_qrcode'  => 'qrcode — QR Code (via JS)',

    // Columns > Mask extra
    'cfg_col_mask_regex'           => 'Regex Pattern (IMask)',
    'cfg_col_valid_hint'           => 'Additional rules beyond <strong>Required</strong> (configured in Basic tab).',

    // Columns > SearchDropdown
    'cfg_col_sd_search_mode'       => 'Search Mode',
    'cfg_col_sd_mode_service'      => 'Custom Service',
    'cfg_col_sd_model'             => 'Model (relative to App\\Models)',
    'cfg_col_sd_service'           => 'Service (relative to App\\Services)',
    'cfg_col_sd_method'            => 'Service Method',
    'cfg_col_sd_value_field'       => 'Value Field (value)',
    'cfg_col_sd_label_field'       => 'Label Field (label)',
    'cfg_col_sd_label_two'          => 'Label Two (optional)',
    'cfg_col_sd_order_by'          => 'Sort order (orderByRaw)',
    'cfg_col_sd_limit'             => 'Results Limit',
    'cfg_col_sd_filters'           => 'Static Filters (JSON)',

    // Columns > Totalizer extra
    'cfg_col_total_func'           => 'Function',
    'cfg_col_total_format'         => 'Format',
    'cfg_col_total_sum'            => 'SUM — Sum',
    'cfg_col_total_avg'            => 'AVG — Average',
    'cfg_col_total_count'          => 'COUNT — Count',
    'cfg_col_total_min'            => 'MIN — Minimum',
    'cfg_col_total_max'            => 'MAX — Maximum',

    // Filter aggregate options
    'cfg_filter_agg_sum'           => 'SUM — sum of values',
    'cfg_filter_agg_count'         => 'COUNT — record count',
    'cfg_filter_agg_avg'           => 'AVG — average',
    'cfg_filter_agg_max'           => 'MAX — maximum value',
    'cfg_filter_agg_min'           => 'MIN — minimum value',
    'cfg_filter_agg_hint'          => 'Only fill if you want to filter by a calculated value (ex: SUM >= 100).',

    // Shared operator options
    'op_eq'                        => '= (equals)',
    'op_eq2'                       => '== (equals)',
    'op_neq'                       => '!= (different)',
    'op_like'                      => 'LIKE (contains text)',
    'op_gt'                        => '> (greater than)',
    'op_lt'                        => '< (less than)',
    'op_gte'                       => '>= (greater or equal)',
    'op_lte'                       => '<= (less or equal)',
    'op_style_case_hint'           => 'Exact as in the database (case-sensitive).',

    // base-crud
    'crud_no_config'               => 'BaseCrud configuration not found for',

    // Columns > Select options placeholder & inline hint
    'cfg_col_select_opts_ph'       => 'key;Label;;key2;Label2',
    'cfg_col_select_fmt_hint'      => 'Format: <code class="px-1 rounded bg-slate-100">key;Label</code> separated by <code class="px-1 rounded bg-slate-100">;;</code>',

    // Columns > Cell icon hint
    'cfg_col_cell_icon_hint'       => 'CSS class of the icon (Boxicons, FontAwesome...)',

    // Columns > Badge / Pill map
    'cfg_col_badge_map_hint'       => 'Each entry maps a database value to a label and color.',
    'cfg_col_badge_label_ph'       => 'label',

    // Columns > Boolean renderer placeholder
    'cfg_col_bool_false_ph'        => 'No',

    // Columns > Currency options
    'cfg_col_currency_brl'         => 'BRL — Brazilian Real',
    'cfg_col_currency_usd'         => 'USD — Dollar',
    'cfg_col_currency_eur'         => 'EUR — Euro',

    // Columns > Mask select options
    'cfg_col_mask_none'            => '— No mask —',
    'cfg_col_mask_grp_monetary'    => 'Monetary',
    'cfg_col_mask_ean13'           => 'ean13 — 0000000000000 (13 digits)',
    'cfg_col_mask_grp_vehicle'     => 'Vehicles',
    'cfg_col_mask_uppercase_opt'   => 'uppercase — automatic UPPERCASE',
    'cfg_col_mask_custom_regex_opt'=> 'custom_regex — Custom expression',
    'cfg_col_mask_plate_sfx'       => '(upper. + alphanum.)',
    'cfg_col_mask_trim_opt'        => 'trim — Remove spaces from edges',
    'cfg_col_mask_transform_save'  => '⚡ Transformation applied on save:',
    'cfg_col_mask_plate_case'      => '(uppercase + alphanumeric)',

    // Columns > Validation inline hints
    'cfg_col_valid_confirmed_hint' => 'Confirmation field name in the form',
    'cfg_col_valid_unique_hint'    => 'Automatically ignores the record being edited',

    // Columns > Relation inline hints
    'cfg_col_rel_name_hint'        => 'Relation method name on the Model',
    'cfg_col_rel_nested_title'     => '🔗 Nested Relation (Dot Notation)',
    'cfg_col_rel_nested_desc'      => 'Use when data is at multiple levels:',
    'cfg_col_rel_nested_auto'      => 'Eager loading is automatic. The last segment is the field; the previous ones are the relations.',
    'cfg_col_rel_nested_label'     => 'Dot Notation Path',

    // Columns > SearchDropdown type hint
    'cfg_col_sd_type_hint'         => 'Configuration for type <strong>searchdropdown</strong>. Available only when the column type is SearchDropdown.',

    // Filter > field inline hints
    'cfg_filter_field_hint'        => 'Unique name for this filter. Does not need to exist in the database.',
    'cfg_filter_rel_method_hint'   => 'Relation method name on the Model (ex: <code class="px-1 rounded bg-slate-100">supplier</code>, <code class="px-1 rounded bg-slate-100">stockMovements</code>).',
    'cfg_filter_rel_col_hint'      => 'Column inside the related table that will be filtered.',

    // Filter > guide box titles & descriptions
    'cfg_filter_guide_s1_title'    => '① Simple filter — direct field in the table',
    'cfg_filter_guide_s1_desc'     => 'Use when the field you want to filter is in the model\'s own table.',
    'cfg_filter_guide_s2_title'    => '② Filter via relation — whereHas',
    'cfg_filter_guide_s2_desc'     => 'Use when the field is in an Eloquent relation (ex: filter Products by Supplier name).',
    'cfg_filter_guide_s3_title'    => '③ Aggregate filter — whereHas + Aggregate',
    'cfg_filter_guide_s3_desc'     => 'Use to filter by calculated values within a relation (ex: products with total stock &gt; X).',
    'cfg_filter_guide_tip'         => '<strong>Tip:</strong> The <em>Field</em> is just an internal identifier — it does not need to exist in the database. What matters for the query is the <em>whereHas</em> + <em>Relation Field</em>.',

    // Style > guide box
    'cfg_style_guide_intro'        => 'The CSS style is applied to the <strong>entire row</strong> of the table when the condition is true. The <strong>Field</strong> must be a real model attribute (database column or relation).',
    'cfg_style_guide_ex2_title'    => '② Highlight by numeric value',
    'cfg_style_guide_ex2_sample'   => 'Example — critical stock ≤ 5',
    'cfg_style_guide_tip'          => 'The CSS is applied as <code class="px-1 rounded bg-slate-100">style=""</code> directly on the <code class="px-1 rounded bg-slate-100">&lt;tr&gt;</code> tag.',

    // JOIN > guide box
    'cfg_join_guide_intro'         => 'Configurable JOINs allow bringing columns from other tables <strong>without Eloquent relationships</strong>, with support for filtering, sorting and export.',
    'cfg_join_guide_ex1_title'     => 'Simple example — 1 level',
    'cfg_join_guide_ex2_title'     => 'Chained example — 2 JOINs (3 levels)',
    'cfg_join_guide_j1_title'      => 'JOIN 1 — intermediate (no extra columns)',
    'cfg_join_guide_j2_title'      => 'JOIN 2 — target table (with column)',
    'cfg_join_guide_chain_note'    => 'The 2nd JOIN can use columns from the 1st JOIN in the ON condition — SQL is generated in sequence.',

    // JOIN > form inline hints
    'cfg_join_distinct_hint'       => 'Avoids duplicate rows when the JOIN can generate multiple matches (1-to-many).',
    'cfg_join_selectraw_hint'      => 'Each line defines a column: <code class="px-1 rounded bg-slate-100">table.column:alias</code>. The alias is how the field appears in Blade and filters. If you omit the alias, it will be auto-generated (ex: <code class="px-1 rounded bg-slate-100">suppliers.name</code> → <code class="px-1 rounded bg-slate-100">suppliers_name</code>).',
    'cfg_join_exist_err_prefix'    => 'A JOIN for table',
    'cfg_join_exist_err_suffix'    => 'already exists. Edit the existing one or use a different name.',
    'cfg_join_notice_title'        => 'Next step: add the columns in the <span class="text-indigo-700">Columns</span> tab',
    'cfg_join_notice_footer'       => 'For chained JOINs (3+ levels), set up an intermediate JOIN <em>without columns</em> and a second JOIN with the desired columns. See the guide above for the full example.',

    // Shared guide-box terms (reused across Columns/Filter/JOIN guides)
    'cfg_term_phys_name'           => 'Physical Name',
    'cfg_term_sql_source'          => 'SQL Source',
    'cfg_term_rel_name_ph'         => '(relation name)',

    // General tab form hints
    'cfg_gen_display_name_ph'      => 'e.g. Business Partners',
    'cfg_gen_display_name_hint'    => 'Appears in the modal header and toolbar. Default: model name.',
    'cfg_gen_broadcast_off_hint'   => 'Enable to configure the Echo channel and event that will trigger automatic table update.',

    // JOIN notice box list items
    'cfg_join_notice_phys'         => '<strong>Physical Name</strong> = the alias (ex: <code class="px-1 rounded bg-amber-100">supplier_name</code>)',
    'cfg_join_notice_sql'          => '<strong>SQL Source</strong> = qualified SQL name (ex: <code class="px-1 rounded bg-amber-100">suppliers.name</code>) — enables filters and sorting',

    // JOIN guide box: rules list items
    'cfg_join_guide_rule_phys'     => '<strong>Physical Name</strong> = alias declared in the Columns field above (ex: <code class="px-1 rounded bg-slate-100">product_name</code>)',
    'cfg_join_guide_rule_left'     => '<strong>LEFT JOIN</strong> keeps records without a match (optional data). <strong>INNER JOIN</strong> filters only matching records.',

    // Columns > SQL Source info box (always-visible reference box)
    'cfg_col_field_guide_title'    => 'Relationship between Physical Name and SQL Source:',
    'cfg_col_field_guide_phys_desc'=> '= alias declared in the JOIN (ex: <code class="px-1 bg-white rounded">supplier_name</code>). This is how Blade accesses the value.',
    'cfg_col_field_guide_sql_desc' => '= qualified SQL name (ex: <code class="px-1 bg-white rounded">suppliers.name</code>). Used in <code class="px-1 bg-white rounded">WHERE</code> and <code class="px-1 bg-white rounded">ORDER BY</code>. <strong>Without this, filters won\'t work.</strong>',
    'cfg_col_field_guide_formats'  => 'Accepted formats <span class="text-slate-400">(the system corrects automatically)</span>:',
    'cfg_col_field_guide_enc'      => 'chained → extracted as',
    'cfg_col_field_guide_qualified' => '— qualified SQL (correct)',
    'cfg_col_field_guide_singular'  => '— singular Eloquent → converted to',
    'cfg_col_sql_optional'          => '(optional — only for JOIN columns)',
    'cfg_col_write_warn'            => '⚠ <strong>Write</strong> must be disabled for JOIN columns — never write to external tables.',

    // Columns > Method syntax guide bullets
    'cfg_col_method_guide_sub'     => 'replaced by the field value in the record',
    'cfg_col_method_guide_multi'   => 'Multiple parameters separated by comma — each becomes a separate PHP argument',
    'cfg_col_method_guide_prefix'  => 'is added automatically',

    // Badge/Pill renderer — color swatch tooltip names
    'color_green'                  => 'Green',
    'color_yellow'                 => 'Yellow',
    'color_red'                    => 'Red',
    'color_blue'                   => 'Blue',
    'color_indigo'                 => 'Indigo',
    'color_purple'                 => 'Purple',
    'color_pink'                   => 'Pink',
    'color_gray'                   => 'Gray',

    // Actions tab
    'cfg_act_tab_title'            => 'Row Actions',
    'cfg_act_th_name'              => 'Name',
    'cfg_act_th_type'              => 'Type',
    'cfg_act_th_value'             => 'Value / URL',
    'cfg_act_th_icon'              => 'Icon',
    'cfg_act_th_color'             => 'Color',
    'cfg_act_th_permission'        => 'Permission',
    'cfg_act_remove_confirm'       => 'Remove action?',
    'cfg_act_form_editing'         => '✏️ Edit Action',
    'cfg_act_form_new'             => '+ New Action',
    'cfg_act_cancel_edit'          => 'Cancel editing',
    'cfg_act_name_label'           => 'Action Name',
    'cfg_act_type_label'           => 'Type',
    'cfg_act_value_label'          => 'Value',
    'cfg_act_icon_label'           => 'Icon (Boxicons CSS class)',
    'cfg_act_color_label'          => 'Color',
    'cfg_act_permission_label'     => 'Gate Permission (optional)',
    'cfg_act_type_link'            => 'link — Redirect URL',
    'cfg_act_type_livewire'        => 'livewire — Call method',
    'cfg_act_type_js'              => 'javascript — Execute JS',
    'cfg_act_btn_save'             => '💾 Save Changes',
    'cfg_act_btn_add'              => '+ Add Action',

    // Filters tab
    'cfg_filter_guide_title'       => 'How to use Custom Filters',
    'cfg_filter_remove_confirm'    => 'Remove filter?',
    'cfg_filter_form_title'        => '+ New Custom Filter',
    'cfg_filter_field_label'       => 'Field',
    'cfg_filter_lbl_label'         => 'Label',
    'cfg_filter_type_label'        => 'Input Type',
    'cfg_filter_op_label'          => 'Operator',
    'cfg_filter_rel_sep'           => 'Eloquent Relation (optional)',
    'cfg_filter_rel_field'         => 'Relation Field',
    'cfg_filter_aggregate'         => 'Aggregation',
    'cfg_filter_agg_none'          => '— None (direct filter) —',
    'cfg_filter_btn_add'           => '+ Add Filter',
    'cfg_filter_type_text'         => 'text — free text',
    'cfg_filter_type_number'       => 'number — numeric',
    'cfg_filter_type_date'         => 'date — date',
    'cfg_filter_type_select'       => 'select — fixed list',
    'cfg_filter_type_sd'           => 'searchdropdown — FK search',

    // Styles tab
    'cfg_style_guide_title'        => 'How to use Conditional Styles',
    'cfg_style_remove_confirm'     => 'Remove style?',
    'cfg_style_preview_row'        => 'Preview of this row',
    'cfg_style_form_title'         => '+ New Conditional Style',
    'cfg_style_field_label'        => 'Field',
    'cfg_style_op_label'           => 'Operator',
    'cfg_style_val_label'          => 'Value',
    'cfg_style_css_label'          => 'Inline CSS',
    'cfg_style_preview_label'      => 'Preview:',
    'cfg_style_presets'            => 'Quick presets:',
    'cfg_style_preset_cancelled'   => 'Cancelled',
    'cfg_style_preset_urgent'      => 'Urgent',
    'cfg_style_preset_success'     => 'Success',
    'cfg_style_preset_alert'       => 'Alert',
    'cfg_style_preset_info'        => 'Info',
    'cfg_style_btn_add'            => '+ Add Style',

    // JOINs tab
    'cfg_join_guide_title'         => 'How to use configurable JOINs',
    'cfg_join_remove_confirm'      => "Remove JOIN with ':table'?",
    'cfg_join_edit_btn'            => 'Edit',
    'cfg_join_remove_btn'          => 'Remove',
    'cfg_join_no_cols_warn'        => '⚠ No columns configured — the JOIN will be applied but will not add columns to SELECT.',
    'cfg_join_empty'               => 'No JOINs configured',
    'cfg_join_empty_hint'          => 'Use the form below to add the first JOIN',
    'cfg_join_form_editing'        => 'Editing',
    'cfg_join_form_new'            => '+ New JOIN',
    'cfg_join_type_label'          => 'Type',
    'cfg_join_table_label'         => 'Table',
    'cfg_join_left_col'            => 'Left Column',
    'cfg_join_right_col'           => 'Right Column',
    'cfg_join_distinct'            => 'Apply DISTINCT',
    'cfg_join_cols_label'          => 'Columns to select',
    'cfg_join_type_left'           => 'LEFT JOIN — includes all main records',
    'cfg_join_type_inner'          => 'INNER JOIN — only matches',
    'cfg_join_cancel_edit'         => 'Cancel Edit',
    'cfg_join_btn_update'          => 'Update JOIN',
    'cfg_join_btn_add'             => '+ Add JOIN',
    'cfg_join_cols_show'           => 'Columns',

    // General tab
    'cfg_gen_appearance'           => 'Appearance',
    'cfg_gen_link_linha'           => 'Row Link (colsLinkLinha)',
    'cfg_gen_broadcast_desc'       => 'Silently updates the table when an Echo event is received.',
    'cfg_gen_display_name'         => 'Display Name',
    'cfg_gen_table_class'          => 'Table Class',
    'cfg_gen_thead_class'          => 'Thead Class',
    'cfg_gen_compact'              => 'Compact Mode',
    'cfg_gen_sticky'               => 'Sticky Header',
    'cfg_gen_totalizer'            => 'Show Totalizer',
    'cfg_gen_cache'                => 'Cache',
    'cfg_gen_cache_enabled'        => 'Enabled',
    'cfg_gen_ttl'                  => 'TTL (seconds)',
    'cfg_gen_export'               => 'Export',
    'cfg_gen_export_async'         => 'Async Threshold (rows)',
    'cfg_gen_export_max'           => 'Max Rows',
    'cfg_gen_export_orientation'   => 'PDF Orientation',
    'cfg_gen_export_landscape'     => 'Landscape',
    'cfg_gen_export_portrait'      => 'Portrait',
    'cfg_gen_broadcast'            => 'Real Time (Broadcast)',
    'cfg_gen_broadcast_enabled'    => 'Enabled',
    'cfg_gen_channel'              => 'Channel (channel)',
    'cfg_gen_event'                => 'Event (.event)',
    'cfg_gen_theme'                => 'Visual Theme',
    'cfg_gen_theme_desc'           => 'Sets the appearance of the BaseCrud component: light palette (default) or dark.',
    'cfg_gen_theme_light'          => '☀️ Light',
    'cfg_gen_theme_dark'           => '🌙 Dark',
    'cfg_gen_theme_light_desc'     => 'White background, light gray borders',
    'cfg_gen_theme_dark_desc'      => 'Dark background, slate-700 borders',

    // Permissions tab
    'cfg_perm_gates_title'         => 'Access Gates',
    'cfg_perm_create'              => 'Create',
    'cfg_perm_edit'                => 'Edit',
    'cfg_perm_delete'              => 'Delete',
    'cfg_perm_export'              => 'Export',
    'cfg_perm_restore'             => 'Restore',
    'cfg_perm_identifier'          => 'Permission Identifier',
    'cfg_perm_visibility_title'    => 'Button Visibility',
    'cfg_perm_btn_create'          => 'Create Button',
    'cfg_perm_btn_edit'            => 'Edit Button',
    'cfg_perm_btn_delete'          => 'Delete Button',
    'cfg_perm_btn_trash'           => 'Trash Button',

    // Footer
    'cfg_footer_cancel'            => 'Cancel',
    'cfg_footer_save'              => 'Save Configuration',
    'cfg_footer_unit_cols'         => 'columns',
    'cfg_footer_unit_filters'      => 'filters',
    'cfg_footer_unit_styles'       => 'styles',

    /*
    |--------------------------------------------------------------------------
    | General
    |--------------------------------------------------------------------------
    */
    'unknown' => 'Unknown',

    /*
    |--------------------------------------------------------------------------
    | GroupBy
    |--------------------------------------------------------------------------
    */
    'groupby_label' => 'Grouped by :field',

    /*
    |--------------------------------------------------------------------------
    | Navbar & Sidebar
    |--------------------------------------------------------------------------
    */
    'navbar_dark_title'        => 'Switch to dark mode',
    'navbar_light_title'       => 'Switch to light mode',
    'navbar_admin_title'       => 'Administration',
    'navbar_admin_company'     => 'Company',
    'navbar_admin_departments' => 'Departments',
    'navbar_admin_roles'       => 'Access Profiles',
    'navbar_admin_pages'       => 'Pages & Objects',
    'navbar_admin_users'       => 'Users & Permissions',
    'navbar_admin_audit'       => 'Audit Log',
    'navbar_admin_guide'       => 'Permissions Guide',
    'navbar_admin_menu'        => 'Manage Menu',
    'navbar_user_profile'      => 'Profile',
    'navbar_user_logout'       => 'Log out',

];
