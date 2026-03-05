<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Error Messages (EN)
    |--------------------------------------------------------------------------
    |
    | English translations for Ptah validation error messages.
    |
    */

    'invalid_column_type' => 'Invalid column type ":type" for field ":field". Valid types: :valid_types',
    'missing_required_field' => 'Required field ":field" is missing in section ":section"',
    'invalid_type' => 'Field ":field" has invalid type :actual_type, expected :expected_type',
    'missing_dependency' => 'Field ":field" requires ":dependency" to be configured',
    'invalid_renderer_config' => 'Renderer ":renderer" requires configuration field ":missing_config"',
    'invalid_join' => 'Invalid JOIN configuration for table ":table": :error',
    'duplicate_configuration' => 'Duplicate configuration for ":field" with value ":value"',

    'command' => [
        'missing_argument' => 'Required argument ":argument" is missing for command ":command"',
        'invalid_option_format' => 'Invalid format for option "--:option=:value". Expected format: :expected_format',
        'invalid_option_value' => 'Invalid value ":value" for option "--:option". Valid values: :valid_values',
        'conflicting_options' => 'Options "--:option1" and "--:option2" cannot be used together',
        'model_not_found' => 'Model class ":model" not found',
    ],

    'business_rule' => [
        'resource_protected' => 'Cannot modify ":resource": :reason',
        'resource_in_use' => 'Cannot delete ":resource" because it is in use by :used_by',
        'duplicate_resource' => 'A :resource with :field ":value" already exists',
        'insufficient_permissions' => 'You do not have permission to :action :resource',
        'invalid_state_transition' => 'Cannot transition :resource from ":current_state" to ":target_state"',
    ],

    'generation' => [
        'file_already_exists' => 'File already exists: :file_path',
        'stub_not_found' => 'Stub file ":stub_name" not found',
        'invalid_template' => 'Invalid template ":template": :error',
        'failed_to_write' => 'Failed to write file ":file_path": :error',
        'failed_to_create_directory' => 'Failed to create directory ":directory": :error',
        'invalid_field_definition' => 'Invalid field definition ":field": :error',
    ],

    'suggestions' => [
        'verify_class_name' => 'Verify the fully qualified class name (e.g., App\Models\Product)',
        'use_force_flag' => 'Use --force flag to overwrite existing file',
        'configure_before' => 'Configure \':dependency\' before using \':field\'',
        'add_to_configuration' => 'Add \':missing_config\' to column configuration',
        'use_format' => 'Use format: :format',
        'check_documentation' => 'Check documentation at https://ptah.dev/docs',
    ],

    'context_labels' => [
        'field' => 'Field',
        'actual_value' => 'Actual value',
        'expected_value' => 'Expected value',
        'expected_type' => 'Expected type',
        'line_number' => 'JSON line',
        'json_path' => 'JSON path',
        'section' => 'Section',
        'model' => 'Model',
        'available_options' => 'Available options',
        'suggestion' => 'Suggestion',
        'command' => 'Command',
        'option' => 'Option',
        'argument' => 'Argument',
        'error' => 'Error',
        'file_path' => 'File path',
        'directory' => 'Directory',
    ],
];
