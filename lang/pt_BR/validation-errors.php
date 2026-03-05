<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Error Messages (PT-BR)
    |--------------------------------------------------------------------------
    |
    | Portuguese translations for Ptah validation error messages.
    |
    */

    'invalid_column_type' => 'Tipo de coluna inválido ":type" para o campo ":field". Tipos válidos: :valid_types',
    'missing_required_field' => 'Campo obrigatório ":field" ausente na seção ":section"',
    'invalid_type' => 'Campo ":field" possui tipo inválido :actual_type, esperado :expected_type',
    'missing_dependency' => 'Campo ":field" requer que ":dependency" esteja configurado',
    'invalid_renderer_config' => 'Renderer ":renderer" requer o campo de configuração ":missing_config"',
    'invalid_join' => 'Configuração de JOIN inválida para a tabela ":table": :error',
    'duplicate_configuration' => 'Configuração duplicada para ":field" com valor ":value"',

    'command' => [
        'missing_argument' => 'Argumento obrigatório ":argument" ausente para o comando ":command"',
        'invalid_option_format' => 'Formato inválido para a opção "--:option=:value". Formato esperado: :expected_format',
        'invalid_option_value' => 'Valor inválido ":value" para a opção "--:option". Valores válidos: :valid_values',
        'conflicting_options' => 'As opções "--:option1" e "--:option2" não podem ser usadas juntas',
        'model_not_found' => 'Classe do modelo ":model" não encontrada',
    ],

    'business_rule' => [
        'resource_protected' => 'Não é possível modificar ":resource": :reason',
        'resource_in_use' => 'Não é possível excluir ":resource" porque está sendo usado por :used_by',
        'duplicate_resource' => 'Um :resource com :field ":value" já existe',
        'insufficient_permissions' => 'Você não tem permissão para :action :resource',
        'invalid_state_transition' => 'Não é possível transicionar :resource de ":current_state" para ":target_state"',
    ],

    'generation' => [
        'file_already_exists' => 'Arquivo já existe: :file_path',
        'stub_not_found' => 'Arquivo stub ":stub_name" não encontrado',
        'invalid_template' => 'Template inválido ":template": :error',
        'failed_to_write' => 'Falha ao escrever arquivo ":file_path": :error',
        'failed_to_create_directory' => 'Falha ao criar diretório ":directory": :error',
        'invalid_field_definition' => 'Definição de campo inválida ":field": :error',
    ],

    'suggestions' => [
        'verify_class_name' => 'Verifique o nome completo da classe (ex: App\Models\Product)',
        'use_force_flag' => 'Use a flag --force para sobrescrever o arquivo existente',
        'configure_before' => 'Configure \':dependency\' antes de usar \':field\'',
        'add_to_configuration' => 'Adicione \':missing_config\' à configuração da coluna',
        'use_format' => 'Use o formato: :format',
        'check_documentation' => 'Consulte a documentação em https://ptah.dev/docs',
    ],

    'context_labels' => [
        'field' => 'Campo',
        'actual_value' => 'Valor atual',
        'expected_value' => 'Valor esperado',
        'expected_type' => 'Tipo esperado',
        'line_number' => 'Linha do JSON',
        'json_path' => 'Caminho JSON',
        'section' => 'Seção',
        'model' => 'Modelo',
        'available_options' => 'Opções válidas',
        'suggestion' => 'Sugestão',
        'command' => 'Comando',
        'option' => 'Opção',
        'argument' => 'Argumento',
        'error' => 'Erro',
        'file_path' => 'Caminho do arquivo',
        'directory' => 'Diretório',
    ],
];
