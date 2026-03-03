<?php

/**
 * ptah UI translations — Português do Brasil
 *
 * Ative via: PTAH_LOCALE=pt_BR no .env do projeto
 * Override per-project: php artisan vendor:publish --tag=ptah-lang
 * então edite lang/vendor/ptah/pt_BR/ui.php
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Toolbar
    |--------------------------------------------------------------------------
    */
    'btn_new'           => 'Novo',
    'search_placeholder'=> 'Buscar...',
    'btn_filters'       => 'Filtros',
    'btn_view_active'   => 'Ver ativos',
    'btn_view_trash'    => 'Ver excluídos',
    'btn_back'          => 'Voltar',
    'btn_trash'         => 'Lixeira',
    'btn_export'        => 'Exportar',
    'btn_columns'       => 'Colunas',
    'col_show_all'      => 'Mostrar todas',
    'col_hide_all'      => 'Ocultar todas',
    'btn_density'       => 'Densidade',
    'btn_refresh'       => 'Atualizar',
    'btn_clear_filters' => 'Limpar filtros',
    'per_page_suffix'   => '/ pág.',

    /*
    |--------------------------------------------------------------------------
    | Density labels
    |--------------------------------------------------------------------------
    */
    'density_compact'     => 'Compacto',
    'density_comfortable' => 'Confortável',
    'density_spacious'    => 'Espaçoso',

    /*
    |--------------------------------------------------------------------------
    | Filter panel
    |--------------------------------------------------------------------------
    */
    'filters_title'           => 'Filtros',
    'filters_clear_all'       => 'Limpar tudo',
    'filters_date_shortcuts'  => 'Atalhos de data',
    'filters_date_from'       => 'De',
    'filters_date_to'         => 'Até',
    'filters_all'             => '-- Todos --',
    'filters_no_results'      => 'Nenhum resultado encontrado.',
    'filters_change'          => 'Alterar...',
    'filters_search_label'    => 'Buscar :label...',
    'filters_op_contains'     => 'contém',
    'filters_op_equals'       => 'igual a',
    'filters_op_not_equals'   => 'diferente',
    'filters_op_starts'       => 'inicia com',
    'filters_op_ends'         => 'termina com',
    'filters_saved'           => 'Salvos:',
    'filters_save_action'     => 'Salvar filtro atual com nome',
    'filters_save_placeholder'=> 'Ex: Clientes ativos SP',
    'filters_btn_save'        => 'Salvar',
    'filters_btn_cancel'      => 'Cancelar',

    /*
    |--------------------------------------------------------------------------
    | Date shortcut labels
    |--------------------------------------------------------------------------
    */
    'date_today'      => 'Hoje',
    'date_yesterday'  => 'Ontem',
    'date_last7'      => '7 dias',
    'date_last30'     => '30 dias',
    'date_week'       => 'Esta semana',
    'date_month'      => 'Este mês',
    'date_last_month' => 'Mês passado',
    'date_quarter'    => 'Trimestre',
    'date_year'       => 'Este ano',
    'filter_period_label'   => 'Período',
    'date_range_from_label' => 'de',
    'date_range_to_label'   => 'até',

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */
    'col_drag_title'     => 'Arrastar para reordenar',
    'col_default_action' => 'Ação',
    'col_actions'        => 'Ações',
    'btn_edit_title'     => 'Editar',
    'btn_restore_title'  => 'Restaurar',
    'btn_delete_title'   => 'Excluir',
    'empty_title'        => 'Nenhum registro encontrado',
    'empty_subtitle'     => 'Ajuste os filtros ou adicione um novo item',
    'col_id'             => 'ID',
    'col_created_at'     => 'Criado em',
    'col_updated_at'     => 'Atualizado em',
    'pagination'         => 'Exibindo :first–:last de :total registros',

    /*
    |--------------------------------------------------------------------------
    | Currency / number formatting
    |--------------------------------------------------------------------------
    */
    'currency_prefix'  => 'R$ ',
    'number_dec_point' => ',',
    'number_thousands' => '.',

    /*
    |--------------------------------------------------------------------------
    | Modal create / edit
    |--------------------------------------------------------------------------
    */
    'modal_edit_prefix'     => 'Editar',
    'modal_new_prefix'      => 'Novo',
    'modal_edit_subtitle'   => 'Altere os campos e salve',
    'modal_create_subtitle' => 'Preencha os campos abaixo',
    'select_placeholder'    => 'Selecione...',
    'search_entity'         => 'Buscar :label...',
    'no_results'            => 'Nenhum resultado encontrado.',
    'btn_cancel'            => 'Cancelar',
    'btn_save_changes'      => 'Salvar Alterações',
    'btn_create'            => 'Criar',
    'btn_save'              => 'Salvar',
    'btn_update'            => 'Atualizar',
    'btn_edit'              => 'Editar',

    /*
    |--------------------------------------------------------------------------
    | Delete confirmation modal
    |--------------------------------------------------------------------------
    */
    'delete_title'   => 'Confirmar exclusão',
    'delete_message' => 'Esta ação não pode ser desfeita.',
    'btn_delete'     => 'Excluir',

    /*
    |--------------------------------------------------------------------------
    | Boolean select options (CrudConfig)
    |--------------------------------------------------------------------------
    */
    'bool_yes'    => 'Sim',
    'bool_no'     => 'Não',
    'flag_green'  => 'Verde',
    'flag_yellow' => 'Amarelo',
    'flag_red'    => 'Vermelho',

    /*
    |--------------------------------------------------------------------------
    | Mensagens de runtime do CRUD
    |--------------------------------------------------------------------------
    */
    'crud_load_error'  => 'Falha ao carregar os dados. Preferências redefinidas.',
    'export_processing'=> 'Processando... você receberá uma notificação.',
    'crud_save_error'  => 'Erro ao salvar: :message',

    /*
    |--------------------------------------------------------------------------
    | Permissions / Middleware
    |--------------------------------------------------------------------------
    */
    'permission_denied' => 'Você não tem permissão para realizar esta ação.',

    /*
    |--------------------------------------------------------------------------
    | Auth — páginas Livewire
    |--------------------------------------------------------------------------
    */
    'auth_link_sent'           => 'Link de recuperação enviado! Verifique seu e-mail.',
    'auth_too_many_attempts'   => 'Muitas tentativas. Tente novamente em :seconds segundos.',
    'auth_invalid_credentials' => 'E-mail ou senha incorretos.',
    'auth_password_reset_ok'   => 'Senha alterada com sucesso! Faça login.',

    /*
    |--------------------------------------------------------------------------
    | Página de perfil
    |--------------------------------------------------------------------------
    */
    'profile_updated'          => 'Perfil atualizado com sucesso!',
    'profile_password_wrong'   => 'Senha atual incorreta.',
    'profile_password_updated' => 'Senha alterada com sucesso!',
    'profile_totp_enabled'     => 'Autenticação TOTP ativada!',
    'profile_totp_invalid'     => 'Código inválido. Tente novamente.',
    'profile_email_2fa_sent'   => 'Código enviado! Verifique seu e-mail para confirmar.',
    'profile_recovery_regen'   => 'Códigos regenerados. Guarde-os em local seguro!',
    'profile_2fa_disabled'     => 'Autenticação em duas etapas desativada.',
    'profile_session_revoked'  => 'Sessão encerrada.',
    'profile_sessions_revoked' => ':count sessão(ões) encerrada(s).',
    'profile_photo_updated'    => 'Foto atualizada!',
    'profile_photo_removed'    => 'Foto removida.',

    /*
    |--------------------------------------------------------------------------
    | Página de verificação 2FA
    |--------------------------------------------------------------------------
    */
    'two_fa_code_invalid' => 'Código inválido ou expirado.',
    'two_fa_email_sent'   => 'Código enviado para :email',

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    */
    'mail_two_factor_subject' => 'Seu código de verificação — :app',

    /*
    |--------------------------------------------------------------------------
    | Mensagens de validação (FormValidatorService)
    |--------------------------------------------------------------------------
    */
    'validation_required'       => ':label é obrigatório.',
    'validation_min'            => ':label deve ser no mínimo :param.',
    'validation_max'            => ':label deve ser no máximo :param.',
    'validation_minlength'      => ':label deve ter pelo menos :param caracteres.',
    'validation_maxlength'      => ':label deve ter no máximo :param caracteres.',
    'validation_between'        => ':label deve estar entre :min e :max.',
    'validation_digits'         => ':label deve ter exatamente :param dígito(s).',
    'validation_digits_between' => ':label deve ter entre :min e :max dígitos.',
    'validation_in'             => ':label deve ser um dos valores: :param.',
    'validation_not_in'         => ':label não pode ser: :param.',
    'validation_email'          => ':label deve ser um e-mail válido.',
    'validation_url'            => ':label deve ser uma URL válida.',
    'validation_integer'        => ':label deve ser um número inteiro.',
    'validation_numeric'        => ':label deve ser um valor numérico.',
    'validation_alpha'          => ':label deve conter apenas letras.',
    'validation_alpha_num'      => ':label deve conter apenas letras e números.',
    'validation_ncm'            => ':label deve ser um NCM válido (ex: 8471.30.19 ou 84713019).',
    'validation_invalid'        => ':label inválido.',
    'validation_phone'          => ':label deve ser um telefone válido.',
    'validation_regex'          => ':label possui formato inválido.',
    'validation_date_invalid'   => ':label possui uma data inválida.',
    'validation_after'          => ':label deve ser uma data posterior a :ref.',
    'validation_before'         => ':label deve ser uma data anterior a :ref.',
    'validation_confirmed'      => ':label não confere com a confirmação.',
    'validation_unique'         => ':label já está em uso.',
    'validation_date_format'    => ':label deve estar no formato :format.',

    /*
    |--------------------------------------------------------------------------
    | Validação de roles (RoleService)
    |--------------------------------------------------------------------------
    */
    'role_master_cannot_deactivate' => 'O role MASTER não pode ser desativado.',
    'role_master_cannot_delete'     => 'O role MASTER não pode ser excluído.',
    'role_master_already_exists'    => 'Já existe um role MASTER. Só é permitido um role MASTER no sistema.',

    /*
    |--------------------------------------------------------------------------
    | Geral
    |--------------------------------------------------------------------------
    */
    'unknown' => 'Desconhecido',

];
