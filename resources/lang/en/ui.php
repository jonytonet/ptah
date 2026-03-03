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
    'profile_totp_apps'           => 'Google Authenticator, Authy, Bitwarden\u2026',
    'profile_scan_qr'             => 'Scan the QR code with your authenticator app:',
    'profile_enter_key'           => 'Or enter the key manually:',
    'profile_confirm_btn'         => 'Confirm',
    'profile_setup_btn'           => 'Set up',
    'profile_email_code_hint'     => 'Code sent to :email',
    'profile_enable_btn'          => 'Enable',
    'profile_2fa_active_label'    => '2FA is active',
    'profile_2fa_authenticator'   => 'Authenticator App',
    'profile_recovery_codes_title'=> 'Recovery codes',
    'profile_recovery_codes_hint' => 'Store these codes in a safe place \u2014 each one can only be used once.',
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
    'company_pagination'      => ':first\u2013:last of :total',

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
    'role_manage_perms_btn'       => '\uD83D\uDD11 Permissions',
    'role_manage_perms_title'     => 'Manage permissions',
    'role_form_title_edit'        => 'Edit Role',
    'role_form_name'              => 'Name *',
    'role_form_desc'              => 'Description',
    'role_form_color'             => 'Color (hex)',
    'role_form_dept'              => 'Department',
    'role_form_active'            => 'Active role',
    'role_form_no_dept'           => 'No department',
    'role_form_master'            => 'MASTER Role (total bypass)',
    'role_form_is_master_badge'   => '\uD83D\uDC51 This is the MASTER role',
    'role_form_master_warn'       => '\u26A0\uFE0F MASTER roles have unrestricted access. Only 1 role can be MASTER.',
    'role_empty_found'            => 'No roles found',
    'role_empty_hint'             => 'Add the first access profile',
    'role_bind_modal_prefix'      => 'Manage Permissions \u2014',
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
    'menu_form_text_ph'           => 'e.g.: Products, Reports\u2026',
    'menu_form_url_ph'            => '/dashboard, /products, https://\u2026',
    'menu_form_icon_label'        => 'Icon',
    'menu_form_icon_hint'         => '(CSS class \u2014 Boxicons or FontAwesome)',
    'menu_form_icon_ph'           => 'bx bx-home  /  fas fa-user',
    'menu_form_parent_group'      => 'Parent group',
    'menu_form_root'              => '\u2014 Root (top level) \u2014',
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
    'audit_result_granted'    => '\u2705 Granted',
    'audit_result_denied'     => '\u274C Denied',
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
    'page_objects_header'     => 'Objects \u2014 :page',
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
    'user_perm_title'         => 'Users \u2014 Access Control',
    'user_perm_subtitle'      => 'Assign roles and companies to system users.',
    'user_perm_search_ph'     => 'Search by name or e-mail...',
    'user_perm_all_roles'     => 'All roles',
    'user_perm_col_user'      => 'User',
    'user_perm_col_roles'     => 'Assigned roles',
    'user_perm_col_actions'   => 'Actions',
    'user_perm_no_roles'      => 'No roles',
    'user_perm_manage_btn'    => '\uD83D\uDD11 Manage Access',
    'user_perm_empty'         => 'No users found',
    'user_perm_empty_hint'    => 'Try adjusting the search filters.',
    'user_perm_modal_prefix'  => 'Access \u2014',
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
    'guide_badge'    => '\uD83D\uDCD6 Documentation',

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
    | General
    |--------------------------------------------------------------------------
    */
    'unknown' => 'Unknown',

];
