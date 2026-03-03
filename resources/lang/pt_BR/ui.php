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
    | Auth — Página de login
    |--------------------------------------------------------------------------
    */
    'login_title'        => 'Entrar na sua conta',
    'login_subtitle'     => 'Bem-vindo de volta',
    'login_password'     => 'Senha',
    'login_remember_me'  => 'Lembrar-me',
    'login_forgot'       => 'Esqueceu a senha?',
    'login_btn'          => 'Entrar',
    'login_btn_loading'  => 'Entrando...',

    /*
    |--------------------------------------------------------------------------
    | Auth — Recuperar senha
    |--------------------------------------------------------------------------
    */
    'forgot_title'        => 'Recuperar senha',
    'forgot_subtitle'     => 'Enviaremos um link de redefinição para o seu e-mail',
    'forgot_btn'          => 'Enviar link de recuperação',
    'forgot_btn_loading'  => 'Enviando...',
    'forgot_remembered'   => 'Lembrou sua senha?',
    'forgot_back_login'   => 'Voltar ao login',

    /*
    |--------------------------------------------------------------------------
    | Auth — Redefinir senha
    |--------------------------------------------------------------------------
    */
    'reset_title'            => 'Nova senha',
    'reset_subtitle'         => 'Digite e confirme sua nova senha',
    'reset_new_password'     => 'Nova senha',
    'reset_confirm_password' => 'Confirmar nova senha',
    'reset_btn'              => 'Redefinir senha',
    'reset_btn_loading'      => 'Salvando...',

    /*
    |--------------------------------------------------------------------------
    | Auth — Verificação em dois fatores
    |--------------------------------------------------------------------------
    */
    'two_fa_page_title'          => 'Verificação em dois passos',
    'two_fa_recovery_subtitle'   => 'Digite um dos seus códigos de recuperação',
    'two_fa_auth_subtitle'       => 'Digite o código do seu aplicativo autenticador ou e-mail',
    'two_fa_recovery_code_label' => 'Código de recuperação',
    'two_fa_verification_label'  => 'Código de verificação',
    'two_fa_verify_btn'          => 'Verificar',
    'two_fa_verifying'           => 'Verificando...',
    'two_fa_use_authenticator'   => 'Usar código autenticador',
    'two_fa_use_recovery_code'   => 'Usar código de recuperação',
    'two_fa_resend_email'        => 'Reenviar código por e-mail',
    'two_fa_back_login'          => 'Voltar ao login',

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard_subtitle'    => 'Visão geral do sistema',
    'dashboard_welcome'     => 'Bem-vindo',
    'dashboard_system'      => 'Sistema',
    'dashboard_environment' => 'Ambiente',
    'dashboard_laravel_ver' => 'Versão do Laravel',

    /*
    |--------------------------------------------------------------------------
    | Perfil — Labels de UI
    |--------------------------------------------------------------------------
    */
    'profile_title'               => 'Meu Perfil',
    'profile_subtitle'            => 'Gerencie suas informações pessoais e segurança',
    'profile_tab_profile'         => 'Perfil',
    'profile_tab_password'        => 'Senha',
    'profile_tab_2fa'             => 'Autenticação 2FA',
    'profile_tab_sessions'        => 'Sessões',
    'profile_tab_photo'           => 'Foto',
    'profile_name'                => 'Nome',
    'profile_save_btn'            => 'Salvar perfil',
    'profile_current_pw'          => 'Senha atual',
    'profile_new_pw'              => 'Nova senha',
    'profile_confirm_pw'          => 'Confirmar nova senha',
    'profile_change_pw_btn'       => 'Alterar senha',
    'profile_2fa_intro'           => 'A autenticação em dois fatores adiciona uma camada extra de segurança à sua conta. Escolha seu método preferido:',
    'profile_totp_apps'           => 'Google Authenticator, Authy, Bitwarden\u2026',
    'profile_scan_qr'             => 'Escaneie o QR code com seu aplicativo autenticador:',
    'profile_enter_key'           => 'Ou insira a chave manualmente:',
    'profile_confirm_btn'         => 'Confirmar',
    'profile_setup_btn'           => 'Configurar',
    'profile_email_code_hint'     => 'Código enviado para :email',
    'profile_enable_btn'          => 'Ativar',
    'profile_2fa_active_label'    => '2FA está ativo',
    'profile_2fa_authenticator'   => 'Aplicativo Autenticador',
    'profile_recovery_codes_title'=> 'Códigos de recuperação',
    'profile_recovery_codes_hint' => 'Guarde esses códigos em local seguro \u2014 cada um pode ser usado apenas uma vez.',
    'profile_regenerate_btn'      => 'Regenerar códigos',
    'profile_view_recovery_btn'   => 'Ver códigos de recuperação',
    'profile_disable_2fa_btn'     => 'Desativar 2FA',
    'profile_disable_2fa_confirm' => 'Desativar 2FA?',
    'profile_sessions_intro'      => 'Dispositivos com sessões ativas na sua conta.',
    'profile_disconnect_others'   => 'Desconectar outros',
    'profile_disconnect_confirm'  => 'Desconectar todos os outros dispositivos?',
    'profile_no_sessions'         => 'Nenhuma sessão encontrada.',
    'profile_this_session'        => 'esta sessão',
    'profile_unknown_browser'     => 'Navegador desconhecido',
    'profile_last_activity'       => 'última atividade',
    'profile_revoke_btn'          => 'Revogar',
    'profile_select_image'        => 'Selecionar imagem',
    'profile_save_photo_btn'      => 'Salvar foto',
    'profile_saving'              => 'Salvando...',
    'profile_remove_btn'          => 'Remover',
    'profile_remove_confirm'      => 'Remover foto de perfil?',

    /*
    |--------------------------------------------------------------------------
    | Labels de status (compartilhados)
    |--------------------------------------------------------------------------
    */
    'lbl_active'              => 'Ativo',
    'lbl_inactive'            => 'Inativo',
    'lbl_all_types'           => 'Todos os tipos',
    'btn_clear'               => 'Limpar',
    'switcher_select_company' => 'Selecionar empresa',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Empresas
    |--------------------------------------------------------------------------
    */
    'company_title'           => 'Empresas',
    'company_subtitle'        => 'Gerencie as empresas e filiais do sistema.',
    'company_new_btn'         => 'Nova Empresa',
    'company_search_ph'       => 'Buscar por nome, e-mail ou CNPJ...',
    'company_col_abbr'        => 'Sigla',
    'company_col_name'        => 'Nome',
    'company_col_default'     => 'Padrão',
    'company_col_status'      => 'Status',
    'company_col_actions'     => 'Ações',
    'company_pagination'      => ':first\u2013:last de :total',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Menu
    |--------------------------------------------------------------------------
    */
    'menu_title'           => 'Gerenciar Menu',
    'menu_subtitle'        => 'Cadastre e organize os itens da barra lateral do sistema.',
    'menu_new_item_btn'    => 'Novo Item',
    'menu_search_ph'       => 'Buscar item de menu...',
    'menu_all_types'       => 'Todos os tipos',
    'menu_col_icon'        => 'Ícone',
    'menu_col_text'        => 'Texto',
    'menu_col_type'        => 'Tipo',
    'menu_col_url'         => 'URL',
    'menu_col_parent'      => 'Grupo pai',
    'menu_col_order'       => 'Ordem',
    'menu_col_status'      => 'Status',
    'menu_col_actions'     => 'Ações',
    'menu_empty'           => 'Adicione o primeiro item usando o botão \'Novo Item\'',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Roles / Permissões
    |--------------------------------------------------------------------------
    */
    'role_title'              => 'Roles / Perfis',
    'role_subtitle'           => 'Gerencie os perfis de acesso e suas permissões por objeto.',
    'role_new_btn'            => 'Novo Role',
    'role_search_ph'          => 'Buscar role...',
    'role_col_name'           => 'Nome',
    'role_col_department'     => 'Departamento',
    'role_col_permissions'    => 'Permissões',
    'role_col_status'         => 'Status',
    'role_col_actions'        => 'Ações',
    'role_objects_count'      => ':count objetos',
    'role_manage_perms_btn'       => '\uD83D\uDD11 Permissões',
    'role_manage_perms_title'     => 'Gerenciar permissões',
    'role_form_title_edit'        => 'Editar Role',
    'role_form_name'              => 'Nome *',
    'role_form_desc'              => 'Descrição',
    'role_form_color'             => 'Cor (hex)',
    'role_form_dept'              => 'Departamento',
    'role_form_active'            => 'Role ativo',
    'role_form_no_dept'           => 'Sem departamento',
    'role_form_master'            => 'Role MASTER (bypass total)',
    'role_form_is_master_badge'   => '\uD83D\uDC51 Este é o role MASTER',
    'role_form_master_warn'       => '\u26A0\uFE0F Roles MASTER têm acesso irrestrito. Apenas 1 role pode ser MASTER.',
    'role_empty_found'            => 'Nenhum role encontrado',
    'role_empty_hint'             => 'Adicione o primeiro perfil de acesso',
    'role_bind_modal_prefix'      => 'Gerenciar Permissões \u2014',
    'role_bind_perm_read'         => 'Ler',
    'role_bind_perm_create'       => 'Criar',
    'role_bind_perm_edit'         => 'Editar',
    'role_bind_perm_delete'       => 'Excluir',
    'role_bind_empty'             => 'Nenhum objeto cadastrado. Acesse Páginas e cadastre os objetos primeiro.',
    'role_bind_save'              => 'Salvar Permissões',
    'role_delete_text'            => 'Excluir este role? As permissões e vínculos com usuários serão removidos.',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Empresas (formulário)
    |--------------------------------------------------------------------------
    */
    'company_modal_new'           => 'Nova Empresa',
    'company_modal_edit'          => 'Editar Empresa',
    'company_form_label'          => 'Sigla (4 chars)',
    'company_form_label_hint'     => 'Exibida no badge do menu',
    'company_form_phone'          => 'Telefone',
    'company_form_phone_ph'       => '(00) 00000-0000',
    'company_form_email_ph'       => 'contato@empresa.com',
    'company_form_doc_type'       => 'Tipo de documento',
    'company_form_is_active'      => 'Empresa ativa',
    'company_form_is_default'     => 'Empresa padrão',
    'company_empty_found'         => 'Nenhuma empresa encontrada',
    'company_empty_adjust'        => 'Ajuste o filtro de busca',
    'company_empty_add'           => 'Adicione a primeira empresa',
    'company_delete_text'         => 'Tem certeza que deseja excluir esta empresa? Esta ação não pode ser desfeita.',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Menu (formulário)
    |--------------------------------------------------------------------------
    */
    'menu_form_title_new'         => 'Novo Item de Menu',
    'menu_form_title_edit'        => 'Editar Item de Menu',
    'menu_form_type'              => 'Tipo',
    'menu_form_direct_link'       => 'Link direto',
    'menu_form_group_type'        => 'Grupo (com sub-itens)',
    'menu_form_text_label'        => 'Texto exibido',
    'menu_form_text_ph'           => 'ex: Produtos, Relatórios\u2026',
    'menu_form_url_ph'            => '/dashboard, /produtos, https://\u2026',
    'menu_form_icon_label'        => 'Ícone',
    'menu_form_icon_hint'         => '(classe CSS \u2014 Boxicons ou FontAwesome)',
    'menu_form_icon_ph'           => 'bx bx-home  /  fas fa-user',
    'menu_form_parent_group'      => 'Grupo pai',
    'menu_form_root'              => '\u2014 Raiz (nível superior) \u2014',
    'menu_form_order'             => 'Ordem',
    'menu_form_opening'           => 'Abertura',
    'menu_form_same_tab'          => 'Mesma aba',
    'menu_form_new_tab'           => 'Nova aba',
    'menu_form_active'            => 'Ativo',
    'menu_save_changes'           => 'Salvar alterações',
    'menu_create_item'            => 'Criar item',
    'menu_delete_title'           => 'Excluir item',
    'menu_delete_text'            => 'Essa ação não pode ser desfeita. Se for um grupo, os filhos serão desvinculados.',
    'menu_delete_confirm'         => 'Sim, excluir',
    'menu_group_badge'            => 'Grupo',
    'menu_link_badge'             => 'Link',
    'menu_toggle_disable'         => 'Clique para desativar',
    'menu_toggle_enable'          => 'Clique para ativar',
    'menu_empty_found'            => 'Nenhum item de menu encontrado',

    /*
    |--------------------------------------------------------------------------
    | UI compartilhada
    |--------------------------------------------------------------------------
    */
    'btn_saving'                  => 'Salvando...',
    'btn_yes_delete'              => 'Sim, excluir',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Departamentos
    |--------------------------------------------------------------------------
    */
    'dept_title'           => 'Departamentos',
    'dept_subtitle'        => 'Agrupe perfis/roles por departamento.',
    'dept_new_btn'         => 'Novo Departamento',
    'dept_search_ph'       => 'Buscar departamento...',
    'dept_col_name'        => 'Nome',
    'dept_col_desc'        => 'Descrição',
    'dept_col_roles'       => 'Roles',
    'dept_col_status'      => 'Status',
    'dept_col_actions'     => 'Ações',
    'dept_empty_found'     => 'Nenhum departamento encontrado',
    'dept_empty_hint'      => 'Adicione o primeiro departamento',
    'dept_modal_new'       => 'Novo Departamento',
    'dept_modal_edit'      => 'Editar Departamento',
    'dept_form_name'       => 'Nome *',
    'dept_form_desc'       => 'Descrição',
    'dept_form_active'     => 'Departamento ativo',
    'dept_delete_text'     => 'Excluir este departamento? Roles vinculados perderão o departamento.',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Auditoria
    |--------------------------------------------------------------------------
    */
    'audit_title'             => 'Auditoria de Permissões',
    'audit_subtitle'          => 'Log de acessos concedidos e negados. Somente leitura.',
    'audit_search_ph'         => 'Buscar recurso, IP, usuário...',
    'audit_all_results'       => 'Todos os resultados',
    'audit_result_granted'    => '\u2705 Concedido',
    'audit_result_denied'     => '\u274C Negado',
    'audit_all_actions'       => 'Todas as ações',
    'audit_action_create'     => 'Criar',
    'audit_action_read'       => 'Ler',
    'audit_action_update'     => 'Editar',
    'audit_action_delete'     => 'Excluir',
    'audit_title_from'        => 'De',
    'audit_title_to'          => 'Até',
    'audit_col_datetime'      => 'Data/Hora',
    'audit_col_user'          => 'Usuário',
    'audit_col_resource'      => 'Recurso',
    'audit_col_action'        => 'Ação',
    'audit_col_result'        => 'Resultado',
    'audit_col_ip'            => 'IP',
    'audit_empty_filtered'    => 'Nenhum registro encontrado',
    'audit_empty_filtered_hint'=> 'Tente ajustar os filtros aplicados.',
    'audit_empty_title'       => 'Nenhum registro de auditoria',
    'audit_empty_hint'        => 'Ative com PTAH_PERMISSION_AUDIT=true no .env.',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Páginas e Objetos
    |--------------------------------------------------------------------------
    */
    'page_title'              => 'Páginas e Objetos',
    'page_subtitle'           => 'Cadastre as páginas do sistema e seus objetos (botões, campos, links) para controle de acesso.',
    'page_col_pages'          => 'Páginas',
    'page_new_btn'            => 'Página',
    'page_search_ph'          => 'Buscar página...',
    'page_empty_found'        => 'Nenhuma página cadastrada',
    'page_empty_hint'         => 'Crie a primeira página para começar.',
    'page_objects_header'     => 'Objetos \u2014 :page',
    'page_new_obj_btn'        => 'Objeto',
    'page_obj_search_ph'      => 'Buscar objeto...',
    'page_obj_col_key_label'  => 'Chave / Label',
    'page_obj_col_type'       => 'Tipo',
    'page_obj_col_section'    => 'Seção',
    'page_obj_col_actions'    => 'Ações',
    'page_obj_empty_found'    => 'Nenhum objeto nesta página',
    'page_obj_empty_hint'     => 'Adicione objetos para controlar o acesso.',
    'page_select_hint'        => 'Selecione uma página para ver seus objetos',
    'page_modal_new'          => 'Nova Página',
    'page_modal_edit'         => 'Editar Página',
    'page_form_slug'          => 'Slug *',
    'page_form_name'          => 'Nome *',
    'page_form_desc'          => 'Descrição',
    'page_form_route'         => 'Rota Laravel',
    'page_form_icon'          => 'Ícone',
    'page_form_active'        => 'Página ativa',
    'page_form_order'         => 'Ordem',
    'page_obj_modal_new'      => 'Novo Objeto',
    'page_obj_modal_edit'     => 'Editar Objeto',
    'page_obj_form_section'   => 'Seção',
    'page_obj_form_type'      => 'Tipo *',
    'page_obj_form_key'       => 'Chave *',
    'page_obj_form_label'     => 'Label *',
    'page_obj_form_active'    => 'Objeto ativo',
    'page_obj_form_order'     => 'Ordem',
    'page_delete_page_text'   => 'Excluir esta página? Todos os objetos vinculados também serão removidos.',
    'page_delete_obj_text'    => 'Excluir este objeto? As permissões de roles vinculadas serão removidas.',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Ajuste de Acesso de Usuários
    |--------------------------------------------------------------------------
    */
    'user_perm_title'         => 'Usuários \u2014 Controle de Acesso',
    'user_perm_subtitle'      => 'Atribua roles e empresas aos usuários do sistema.',
    'user_perm_search_ph'     => 'Buscar por nome ou e-mail...',
    'user_perm_all_roles'     => 'Todos os roles',
    'user_perm_col_user'      => 'Usuário',
    'user_perm_col_roles'     => 'Roles atribuídos',
    'user_perm_col_actions'   => 'Ações',
    'user_perm_no_roles'      => 'Sem roles',
    'user_perm_manage_btn'    => '\uD83D\uDD11 Gerenciar Acesso',
    'user_perm_empty'         => 'Nenhum usuário encontrado',
    'user_perm_empty_hint'    => 'Tente ajustar os filtros de busca.',
    'user_perm_modal_prefix'  => 'Acesso \u2014',
    'user_perm_assigned_roles'=> 'Roles atribuídos',
    'user_perm_remove_btn'    => 'Remover',
    'user_perm_protected'     => 'Protegido',
    'user_perm_no_assigned'   => 'Nenhum role atribuído.',
    'user_perm_add_role'      => 'Adicionar role',
    'user_perm_company_label' => 'Empresa',
    'user_perm_global'        => 'Global (sem empresa)',
    'user_perm_add_btn'       => 'Adicionar',
    'user_perm_close_btn'     => 'Fechar',

    /*
    |--------------------------------------------------------------------------
    | Módulo — Guia de Permissões
    |--------------------------------------------------------------------------
    */
    'guide_title'    => 'Guia do Sistema de Permissões',
    'guide_subtitle' => 'Como funciona o ACL do Ptah e como configurar acessos passo a passo.',
    'guide_badge'    => '\uD83D\uDCD6 Documentação',

    // Abas
    'guide_tab_overview' => '🗺️ Visão Geral',
    'guide_tab_setup'    => '🔧 Passo a Passo',
    'guide_tab_code'     => '💻 Exemplos de Código',
    'guide_tab_faq'      => '❓ Perguntas Frequentes',

    // Visão Geral — Introdução
    'guide_ov_title' => 'O que é o sistema de permissões do Ptah?',
    'guide_ov_body'  => 'O ACL (Access Control List) do Ptah é um sistema de controle de acesso baseado em <strong>Roles (perfis)</strong>, inspirado no padrão RBAC. Ele permite definir <em>quem pode fazer o quê</em> em cada parte do sistema, com granularidade até o nível de botão ou campo individual.<br><br>Ao contrário do simples <code class="bg-indigo-100 px-1.5 py-0.5 rounded text-xs font-mono">Gate/Policy</code> do Laravel, o Ptah ACL é <strong>dinâmico e gerenciável pela interface</strong> — sem necessidade de alterar código para adicionar novas permissões.',

    // Visão Geral — Arquitetura
    'guide_ov_arch_title'  => 'Arquitetura — Como os conceitos se relacionam',
    'guide_ov_dept_title'  => 'Departamentos',
    'guide_ov_dept_desc'   => 'Agrupamento lógico opcional dos Roles',
    'guide_ov_dept_ex'     => 'ex: TI, Comercial, Financeiro',
    'guide_ov_roles_title' => 'Roles / Perfis',
    'guide_ov_roles_desc'  => 'Carrega as permissões por objeto',
    'guide_ov_roles_ex'    => 'ex: Admin, Vendedor, Suporte',
    'guide_ov_pages_title' => 'Páginas + Objetos',
    'guide_ov_pages_desc'  => 'O que pode ser controlado',
    'guide_ov_pages_ex'    => 'ex: /vendas, botão "Exportar", campo "Desconto"',
    'guide_ov_users_title' => 'Usuários',
    'guide_ov_users_desc'  => 'Recebem Roles por Empresa',
    'guide_ov_users_ex'    => 'ex: João — Admin na Empresa A',
    'guide_ov_co_title'    => 'Empresas',
    'guide_ov_co_desc'     => 'Escopo do vínculo (opcional)',
    'guide_ov_co_ex'       => 'ex: Multi-tenant ou Global',

    // Visão Geral — Conceitos
    'guide_ov_concepts_title' => 'Conceitos fundamentais',
    'guide_con_role_title'    => 'Role (Perfil)',
    'guide_con_role_body'     => 'Um Role é um conjunto de permissões. Em vez de dar permissões diretamente ao usuário, você cria um Role com as permissões e atribui o Role ao usuário.',
    'guide_con_page_title'    => 'Página',
    'guide_con_page_body'     => 'Representa um módulo ou rota do sistema. Cada Página contém <strong>Objetos</strong> — elementos individuais cujo acesso pode ser controlado (botões, campos, links, ações).',
    'guide_con_obj_title'     => 'Objeto + Permissão',
    'guide_con_obj_body'      => 'Um Objeto é um elemento granular dentro de uma Página. Cada objeto tem 4 flags de permissão: <strong>Ler, Criar, Editar, Excluir</strong>. Um Role pode ter permissão parcial (só ler, por exemplo).',
    'guide_con_perms_read'    => 'Ler',
    'guide_con_perms_create'  => 'Criar',
    'guide_con_perms_edit'    => 'Editar',
    'guide_con_perms_delete'  => 'Excluir',
    'guide_con_master_title'  => 'Role MASTER',
    'guide_con_master_body'   => 'Um Role marcado como MASTER tem acesso irrestrito a <strong>todos os recursos</strong>, ignorando verificações. Só pode existir 1 Role MASTER. Use apenas para superadmins.',
    'guide_con_master_warn'   => '⚠️ Use com cuidado — bypassa todas as verificações',
    'guide_con_scope_title'   => 'Escopo por Empresa',
    'guide_con_scope_body'    => 'Um usuário pode ter Roles diferentes em empresas diferentes. Ex: João é Admin na Empresa A e apenas Leitor na Empresa B. Defina <code class="font-mono bg-slate-100 px-1 rounded text-xs">NULL</code> para acesso global.',
    'guide_con_audit_title'   => 'Auditoria',
    'guide_con_audit_body'    => 'Quando habilitada, cada verificação de permissão é registrada em log com usuário, recurso, ação e resultado (concedido/negado). Ative com <code class="font-mono bg-slate-100 px-1 rounded text-xs">PTAH_PERMISSION_AUDIT=true</code> no .env.',

    // Visão Geral — Fluxo
    'guide_ov_flow_title' => 'Fluxo de verificação de acesso',
    'guide_flow_start'    => 'Usuário tenta acessar recurso',
    'guide_flow_q1'       => '① Usuário está autenticado?',
    'guide_flow_q2'       => '② Algum Role do usuário é MASTER?',
    'guide_flow_q3'       => '③ Role possui permissão (ex: can_read) para este objeto?',
    'guide_flow_yes'      => 'Sim',
    'guide_flow_no'       => 'Não',
    'guide_flow_granted'  => '✅ ACESSO LIBERADO',
    'guide_flow_denied'   => '🚫 ACESSO NEGADO',
    'guide_flow_login'    => '🚫 Redireciona para login',

    // Passo a Passo — Pré-requisito
    'guide_setup_prereq' => '<strong>Pré-requisito:</strong> Execute <code class="font-mono text-xs bg-indigo-100 px-1.5 rounded">php artisan migrate</code> para criar as tabelas do Ptah, e <code class="font-mono text-xs bg-indigo-100 px-1.5 rounded">php artisan db:seed --class=Ptah\\Seeders\\DefaultCompanySeeder</code> para criar a empresa padrão.',

    // Passo 1
    'guide_s1_title'    => 'Cadastrar Departamentos <span class="text-slate-400 font-normal">(Opcional)</span>',
    'guide_s1_desc'     => 'Agrupe seus Roles em departamentos para melhor organização.',
    'guide_s1_btn'      => 'Ir para Departamentos →',
    'guide_s1_body'     => 'Departamentos são agrupamentos lógicos opcionais para seus Roles. Útil quando o sistema tem muitos perfis.',
    'guide_s1_example'  => 'Exemplo',
    'guide_s1_ex_it'    => 'Departamento <strong>TI</strong> → Roles: Desenvolvedor, DevOps, Suporte TI',
    'guide_s1_ex_sales' => 'Departamento <strong>Comercial</strong> → Roles: Vendedor, Gerente Comercial, SDR',
    'guide_s1_ex_fin'   => 'Departamento <strong>Financeiro</strong> → Roles: Analista Financeiro, Controller',

    // Passo 2
    'guide_s2_title'      => 'Cadastrar Páginas e Objetos',
    'guide_s2_desc'       => 'Registre os módulos do sistema e o que pode ser controlado neles.',
    'guide_s2_btn'        => 'Ir para Páginas →',
    'guide_s2_body'       => 'Uma <strong>Página</strong> representa um módulo ou seção do sistema (ex: <code class="font-mono text-xs bg-slate-100 px-1 rounded">admin.vendas</code>). Cada página pode ter vários <strong>Objetos</strong> — que representam elementos granulares como botões, campos ou ações.',
    'guide_s2_page_title' => '📄 Exemplo de Página',
    'guide_s2_page_slug'  => 'Slug',
    'guide_s2_page_name'  => 'Nome',
    'guide_s2_page_icon'  => 'Ícone',
    'guide_s2_obj_title'  => '🔑 Objetos desta Página',

    // Passo 3
    'guide_s3_title'      => 'Criar Roles e definir permissões',
    'guide_s3_desc'       => 'Crie os perfis de acesso e configure quais objetos cada perfil pode acessar.',
    'guide_s3_btn'        => 'Ir para Roles →',
    'guide_s3_body'       => 'Crie um Role com nome e cor. Depois clique em <strong>🔑 Permissões</strong> para definir quais objetos este Role pode <em>Ler, Criar, Editar e Excluir</em>.',
    'guide_s3_ex_title'   => 'Exemplo: Role "Vendedor Padrão"',
    'guide_s3_col_obj'    => 'Objeto',
    'guide_s3_col_read'   => 'Ler',
    'guide_s3_col_create' => 'Criar',
    'guide_s3_col_edit'   => 'Editar',
    'guide_s3_col_delete' => 'Excluir',
    'guide_s3_note'       => '↑ Vendedor pode criar pedidos mas não vê desconto e não pode exportar de forma irrestrita.',

    // Passo 4
    'guide_s4_title'    => 'Vincular usuários a Roles',
    'guide_s4_desc'     => 'Atribua um ou mais Roles a cada usuário, com escopo de empresa.',
    'guide_s4_btn'      => 'Ir para Usuários →',
    'guide_s4_body'     => 'Na tela de Controle de Acesso, clique em <strong>🔑 Gerenciar Acesso</strong> ao lado do usuário. Selecione um Role e uma Empresa (ou "Global" para acesso sem escopo).',
    'guide_s4_ex_title' => 'Exemplo: Usuário <span class="text-indigo-600">João Silva</span>',
    'guide_s4_ex1'      => 'Role <strong class="text-purple-700">Admin</strong> na empresa <strong>Empresa A Ltda</strong> <span class="text-slate-400">→ acesso total na Empresa A</span>',
    'guide_s4_ex2'      => 'Role <strong class="text-blue-700">Leitor</strong> na empresa <strong>Empresa B SA</strong> <span class="text-slate-400">→ só pode ler na Empresa B</span>',

    // Passo 5
    'guide_s5_title' => 'Usar as permissões no código',
    'guide_s5_desc'  => 'Veja a aba "Exemplos de Código" para detalhes completos.',
    'guide_s5_btn'   => 'Ver exemplos →',
    'guide_s5_body'  => 'Use o helper <code class="font-mono text-xs bg-slate-100 px-1.5 rounded">ptah_can(\'objeto.chave\', \'read\')</code> nas views Blade ou o middleware <code class="font-mono text-xs bg-slate-100 px-1.5 rounded">ptah.can:objeto.chave,read</code> nas rotas para proteger o acesso.',

    // FAQ
    'guide_faq_q1' => 'O que acontece se o usuário não tiver nenhum Role?',
    'guide_faq_a1' => 'Sem nenhum Role, o usuário não terá acesso a nenhum objeto controlado. As verificações com <code class="font-mono text-xs bg-slate-100 px-1 rounded">ptah_can()</code> retornam <strong>false</strong> e o middleware <code class="font-mono text-xs bg-slate-100 px-1 rounded">ptah.can</code> retorna HTTP 403.',
    'guide_faq_q2' => 'Posso ter mais de um Role por usuário?',
    'guide_faq_a2' => 'Sim! Um usuário pode ter múltiplos Roles, inclusive em empresas diferentes. Se qualquer um dos Roles do usuário tiver a permissão solicitada, o acesso é concedido.',
    'guide_faq_q3' => 'O que é o Role MASTER e quando usar?',
    'guide_faq_a3' => 'Um Role MASTER bypassa <strong>todas</strong> as verificações de permissão, concedendo acesso irrestrito. Use exclusivamente para superadministradores do sistema. Só pode existir 1 Role MASTER configurado.',
    'guide_faq_q4' => 'Como funciona o escopo por empresa?',
    'guide_faq_a4' => 'Ao vincular um usuário a um Role, você pode especificar uma Empresa. A verificação considera apenas os Roles válidos para a empresa atual do contexto. Vínculos com empresa <code class="font-mono text-xs bg-slate-100 px-1 rounded">NULL</code> são válidos globalmente.',
    'guide_faq_q5' => 'As permissões são cacheadas?',
    'guide_faq_a5' => 'Sim. O Ptah usa o cache do Laravel para evitar queries excessivas. O cache é invalidado automaticamente quando os vínculos de um usuário são alterados via interface. Você pode limpar com <code class="font-mono text-xs bg-slate-100 px-1 rounded">php artisan cache:clear</code>.',
    'guide_faq_q6' => 'Posso criar Páginas e Objetos automaticamente via código?',
    'guide_faq_a6' => 'Sim. Use o seeder ou crie registros em <code class="font-mono text-xs bg-slate-100 px-1 rounded">Ptah\Models\Page</code> e <code class="font-mono text-xs bg-slate-100 px-1 rounded">Ptah\Models\PageObject</code> diretamente. É útil para popular via migration ao fazer deploy.',
    'guide_faq_q7' => 'O que acontece se eu excluir um Objeto que já tem permissões definidas?',
    'guide_faq_a7' => 'As entradas da tabela de permissões associadas ao objeto são removidas em cascata. Os Roles que tinham aquele objeto perdem a permissão automaticamente. Usuários MASTER não são afetados (bypass).',
    'guide_faq_q8' => 'Como auditar quem acessou o que?',
    'guide_faq_a8' => 'Habilite <code class="font-mono text-xs bg-slate-100 px-1 rounded">PTAH_PERMISSION_AUDIT=true</code> no .env. Cada verificação (concedida ou negada) será registrada na tabela <code class="font-mono text-xs bg-slate-100 px-1 rounded">ptah_permission_audits</code>. Acesse o log em <a href=":audit_url" class="text-indigo-600 underline">Auditoria</a>.',
    'guide_faq_help_title' => 'Precisa de mais ajuda?',
    'guide_faq_help_body'  => 'Consulte a <strong>Visão Geral</strong> para entender a arquitetura, o <strong>Passo a Passo</strong> para configurar e os <strong>Exemplos de Código</strong> para integrar no seu projeto.',

    /*
    |--------------------------------------------------------------------------
    | CRUD Config (crud-config.blade.php)
    |--------------------------------------------------------------------------
    */
    // Botão & cabeçalho
    'cfg_btn_title'                => 'Configurar CRUD',

    // Nav da sidebar
    'cfg_nav_cols'                 => 'Colunas',
    'cfg_nav_actions'              => 'Ações',
    'cfg_nav_filters'              => 'Filtros Custom',
    'cfg_nav_styles'               => 'Estilos',
    'cfg_nav_joins'                => 'JOINs',
    'cfg_nav_general'              => 'Geral',
    'cfg_nav_permissions'          => 'Permissões',

    // Títulos das abas (top bar)
    'cfg_tab_title_cols'           => 'Configuração de Colunas',
    'cfg_tab_title_actions'        => 'Ações por Linha',
    'cfg_tab_title_filters'        => 'Filtros Personalizados',
    'cfg_tab_title_styles'         => 'Estilos Condicionais',
    'cfg_tab_title_joins'          => 'JOINs Configurados',
    'cfg_tab_title_general'        => 'Configurações Gerais',
    'cfg_tab_title_permissions'    => 'Permissões e Acesso',

    // Descrições das abas (top bar)
    'cfg_tab_desc_cols'            => 'Defina, ordene e configure cada coluna da tabela',
    'cfg_tab_desc_actions'         => 'Botões e links exibidos em cada linha',
    'cfg_tab_desc_filters'         => 'Filtros avançados com relações e agregações',
    'cfg_tab_desc_styles'          => 'Estilize linhas com base em condições dos dados',
    'cfg_tab_desc_joins'           => 'JOINs SQL entre tabelas — sem depender de relacionamentos Eloquent',
    'cfg_tab_desc_general'         => 'Cache, exportação, aparência e comportamento',
    'cfg_tab_desc_permissions'     => 'Gates do Laravel e visibilidade de botões',

    // Aba Colunas — tabela
    'cfg_col_table_title'          => 'Colunas da Tabela',
    'cfg_col_table_hint'           => 'Arraste para reordenar. Clique em ✏ para editar.',
    'cfg_col_th_field'             => 'Campo Físico',
    'cfg_col_th_label'             => 'Label',
    'cfg_col_th_type'              => 'Tipo',
    'cfg_col_th_renderer'          => 'Renderer',
    'cfg_col_th_mask'              => 'Máscara',
    'cfg_col_th_save'              => 'Gravar',
    'cfg_col_th_filterable'        => 'Filtrável',
    'cfg_col_th_actions'           => 'Ações',
    'cfg_col_empty'                => 'Nenhuma coluna configurada. Adicione abaixo.',
    'cfg_col_remove_confirm'       => "Remover coluna ':col'?",

    // Aba Colunas — formulário
    'cfg_col_form_editing'         => '✏ Editando coluna',
    'cfg_col_form_new'             => '+ Nova Coluna',
    'cfg_col_cancel_edit'          => 'Cancelar edição',
    'cfg_col_subtab_basic'         => 'Básico',
    'cfg_col_subtab_display'       => 'Exibição',
    'cfg_col_subtab_mask'          => 'Máscara',
    'cfg_col_subtab_validation'    => 'Validação',
    'cfg_col_subtab_relation'      => 'Relação',
    'cfg_col_subtab_sd'            => 'SearchDropdown',
    'cfg_col_subtab_totalizer'     => 'Totalizador',

    // Aba Colunas — campos Básico
    'cfg_col_field_label'          => 'Campo Físico (DB) *',
    'cfg_col_logic_label'          => 'Label (exibição)',
    'cfg_col_sql_label'            => 'Fonte SQL',
    'cfg_col_type_label'           => 'Tipo',
    'cfg_col_align_label'          => 'Alinhamento',
    'cfg_col_align_left'           => 'Esquerda',
    'cfg_col_align_center'         => 'Centro',
    'cfg_col_align_right'          => 'Direita',
    'cfg_col_cb_save'              => 'Incluir no Formulário (Gravar)',
    'cfg_col_cb_required'          => 'Obrigatório',
    'cfg_col_cb_filterable'        => 'Filtrável',

    // Opções de tipo
    'cfg_col_type_text'            => 'text — Texto',
    'cfg_col_type_number'          => 'number — Número',
    'cfg_col_type_date'            => 'date — Data',
    'cfg_col_type_datetime'        => 'datetime — Data e Hora',
    'cfg_col_type_select'          => 'select — Seleção',
    'cfg_col_type_sd'              => 'searchdropdown — Busca Relacional',
    'cfg_col_type_boolean'         => 'boolean — Sim/Não',
    'cfg_col_type_textarea'        => 'textarea — Texto Longo',

    // Estilo da célula
    'cfg_col_cell_style_title'     => 'Estilo da Célula',
    'cfg_col_cell_preview'         => 'Preview:',
    'cfg_col_cell_example'         => 'Exemplo de valor',

    // Renderer
    'cfg_col_renderer_badge_map'   => 'Mapeamento de Badges',
    'cfg_col_renderer_add_badge'   => '+ Adicionar badge',
    'cfg_col_renderer_bool_true'   => 'Texto Verdadeiro',
    'cfg_col_renderer_bool_false'  => 'Texto Falso',
    'cfg_col_renderer_currency'    => 'Moeda',
    'cfg_col_renderer_decimals'    => 'Casas Decimais',
    'cfg_col_renderer_url_tmpl'    => 'Template da URL',
    'cfg_col_renderer_link_label'  => 'Label do link (opcional)',
    'cfg_col_renderer_new_tab'     => 'Abrir em nova aba',
    'cfg_col_renderer_width'       => 'Largura (px)',
    'cfg_col_renderer_height'      => 'Altura (px, opcional)',
    'cfg_col_renderer_max_chars'   => 'Máximo de Caracteres',
    'cfg_col_renderer_max_val'     => 'Valor Máximo',
    'cfg_col_renderer_color'       => 'Cor',
    'cfg_col_renderer_max_stars'   => 'Máximo de Estrelas',
    'cfg_col_renderer_duration'    => 'Unidade de Entrada',
    'cfg_col_renderer_qr_size'     => 'Tamanho',

    // Sub-aba Máscara
    'cfg_col_mask_label'           => 'Máscara de Entrada',
    'cfg_col_mask_transform'       => 'Transformação antes de Salvar',

    // Labels das regras de validação
    'cfg_col_valid_email'          => 'E-mail válido',
    'cfg_col_valid_url'            => 'URL válida',
    'cfg_col_valid_integer'        => 'Inteiro',
    'cfg_col_valid_numeric'        => 'Numérico',
    'cfg_col_valid_cpf'            => 'CPF válido',
    'cfg_col_valid_cnpj'           => 'CNPJ válido',
    'cfg_col_valid_phone'          => 'Telefone válido',
    'cfg_col_valid_alpha'          => 'Somente letras',
    'cfg_col_valid_alphanum'       => 'Letras + números',
    'cfg_col_valid_ncm'            => 'NCM válido (8 dígitos)',
    'cfg_col_valid_min'            => 'Valor Mínimo (min:X)',
    'cfg_col_valid_max'            => 'Valor Máximo (max:X)',
    'cfg_col_valid_min_len'        => 'Comprimento Mínimo (minLength:X)',
    'cfg_col_valid_max_len'        => 'Comprimento Máximo (maxLength:X)',
    'cfg_col_valid_regex'          => 'Regex Personalizado',
    'cfg_col_valid_digits'         => 'Exatamente N Dígitos (digits:N)',
    'cfg_col_valid_digits_btw'     => 'Dígitos Entre N e M (digitsBetween:N,M)',
    'cfg_col_valid_after'          => 'Data Após (after:data)',
    'cfg_col_valid_before'         => 'Data Antes (before:data)',
    'cfg_col_valid_date_fmt'       => 'Formato de Data (dateFormat:formato)',
    'cfg_col_valid_confirmed'      => 'Confirmação (confirmed:campo)',
    'cfg_col_valid_unique'         => 'Único (unique:Model,campo)',
    'cfg_col_valid_in'             => 'Valores Permitidos (in:a,b,c)',
    'cfg_col_valid_not_in'         => 'Valores Proibidos (notIn:a,b,c)',
    'cfg_col_valid_rules_active'   => 'Regras ativas:',

    // Sub-aba Relação
    'cfg_col_rel_name'             => 'Relação Eloquent',
    'cfg_col_rel_display'          => 'Campo a Exibir',

    // Sub-aba Totalizador
    'cfg_col_total_enable'         => 'Habilitar Totalizador nesta Coluna',

    // Botões salvar coluna
    'cfg_col_btn_save'             => 'Salvar Alterações da Coluna',
    'cfg_col_btn_add'              => 'Adicionar Coluna',

    // Aba Ações
    'cfg_act_tab_title'            => 'Ações por Linha',
    'cfg_act_th_name'              => 'Nome',
    'cfg_act_th_type'              => 'Tipo',
    'cfg_act_th_value'             => 'Valor / URL',
    'cfg_act_th_icon'              => 'Ícone',
    'cfg_act_th_color'             => 'Cor',
    'cfg_act_th_permission'        => 'Permissão',
    'cfg_act_remove_confirm'       => 'Remover ação?',
    'cfg_act_form_editing'         => '✏️ Editar Ação',
    'cfg_act_form_new'             => '+ Nova Ação',
    'cfg_act_cancel_edit'          => 'Cancelar edição',
    'cfg_act_name_label'           => 'Nome da Ação',
    'cfg_act_type_label'           => 'Tipo',
    'cfg_act_value_label'          => 'Valor',
    'cfg_act_icon_label'           => 'Ícone (classe CSS Boxicons)',
    'cfg_act_color_label'          => 'Cor',
    'cfg_act_permission_label'     => 'Permissão Gate (opcional)',
    'cfg_act_type_link'            => 'link — Redirecionar URL',
    'cfg_act_type_livewire'        => 'livewire — Chamar método',
    'cfg_act_type_js'              => 'javascript — Executar JS',
    'cfg_act_btn_save'             => '💾 Salvar Alterações',
    'cfg_act_btn_add'              => '+ Adicionar Ação',

    // Aba Filtros
    'cfg_filter_guide_title'       => 'Como usar os Filtros Personalizados',
    'cfg_filter_remove_confirm'    => 'Remover filtro?',
    'cfg_filter_form_title'        => '+ Novo Filtro Personalizado',
    'cfg_filter_field_label'       => 'Campo',
    'cfg_filter_lbl_label'         => 'Label',
    'cfg_filter_type_label'        => 'Tipo de Input',
    'cfg_filter_op_label'          => 'Operador',
    'cfg_filter_rel_sep'           => 'Relação Eloquent (opcional)',
    'cfg_filter_rel_field'         => 'Campo na Relação',
    'cfg_filter_aggregate'         => 'Agregação',
    'cfg_filter_agg_none'          => '— Nenhuma (filtro direto) —',
    'cfg_filter_btn_add'           => '+ Adicionar Filtro',
    'cfg_filter_type_text'         => 'text — campo livre',
    'cfg_filter_type_number'       => 'number — numérico',
    'cfg_filter_type_date'         => 'date — data',
    'cfg_filter_type_select'       => 'select — lista fixa',
    'cfg_filter_type_sd'           => 'searchdropdown — busca FK',

    // Aba Estilos
    'cfg_style_guide_title'        => 'Como usar os Estilos Condicionais',
    'cfg_style_remove_confirm'     => 'Remover estilo?',
    'cfg_style_preview_row'        => 'Preview desta linha',
    'cfg_style_form_title'         => '+ Novo Estilo Condicional',
    'cfg_style_field_label'        => 'Campo',
    'cfg_style_op_label'           => 'Operador',
    'cfg_style_val_label'          => 'Valor',
    'cfg_style_css_label'          => 'CSS Inline',
    'cfg_style_preview_label'      => 'Preview:',
    'cfg_style_presets'            => 'Presets rápidos:',
    'cfg_style_preset_cancelled'   => 'Cancelado',
    'cfg_style_preset_urgent'      => 'Urgente',
    'cfg_style_preset_success'     => 'Sucesso',
    'cfg_style_preset_alert'       => 'Alerta',
    'cfg_style_preset_info'        => 'Info',
    'cfg_style_btn_add'            => '+ Adicionar Estilo',

    // Aba JOINs
    'cfg_join_guide_title'         => 'Como usar JOINs configuráveis',
    'cfg_join_remove_confirm'      => "Remover o JOIN com ':table'?",
    'cfg_join_edit_btn'            => 'Editar',
    'cfg_join_remove_btn'          => 'Remover',
    'cfg_join_no_cols_warn'        => '⚠ Nenhuma coluna configurada — o JOIN será aplicado mas não adicionará colunas ao SELECT.',
    'cfg_join_empty'               => 'Nenhum JOIN configurado',
    'cfg_join_empty_hint'          => 'Use o formulário abaixo para adicionar o primeiro JOIN',
    'cfg_join_form_editing'        => 'Editando',
    'cfg_join_form_new'            => '+ Novo JOIN',
    'cfg_join_type_label'          => 'Tipo',
    'cfg_join_table_label'         => 'Tabela',
    'cfg_join_left_col'            => 'Coluna Esquerda',
    'cfg_join_right_col'           => 'Coluna Direita',
    'cfg_join_distinct'            => 'Aplicar DISTINCT',
    'cfg_join_cols_label'          => 'Colunas a selecionar',
    'cfg_join_type_left'           => 'LEFT JOIN — inclui todos os registros principais',
    'cfg_join_type_inner'          => 'INNER JOIN — somente correspondências',
    'cfg_join_cancel_edit'         => 'Cancelar Edição',
    'cfg_join_btn_update'          => 'Atualizar JOIN',
    'cfg_join_btn_add'             => '+ Adicionar JOIN',
    'cfg_join_cols_show'           => 'Colunas',

    // Aba Geral
    'cfg_gen_appearance'           => 'Aparência',
    'cfg_gen_link_linha'           => 'Link da Linha (colsLinkLinha)',
    'cfg_gen_broadcast_desc'       => 'Atualiza a tabela silenciosamente quando um evento Echo é recebido.',
    'cfg_gen_display_name'         => 'Nome de Exibição',
    'cfg_gen_table_class'          => 'Classe da Tabela',
    'cfg_gen_thead_class'          => 'Classe do Thead',
    'cfg_gen_compact'              => 'Modo Compacto',
    'cfg_gen_sticky'               => 'Cabeçalho Fixo',
    'cfg_gen_totalizer'            => 'Exibir Totalizador',
    'cfg_gen_cache'                => 'Cache',
    'cfg_gen_cache_enabled'        => 'Habilitado',
    'cfg_gen_ttl'                  => 'TTL (segundos)',
    'cfg_gen_export'               => 'Exportação',
    'cfg_gen_export_async'         => 'Threshold Assíncrono (linhas)',
    'cfg_gen_export_max'           => 'Máximo de Linhas',
    'cfg_gen_export_orientation'   => 'Orientação PDF',
    'cfg_gen_export_landscape'     => 'Paisagem',
    'cfg_gen_export_portrait'      => 'Retrato',
    'cfg_gen_broadcast'            => 'Tempo Real (Broadcast)',
    'cfg_gen_broadcast_enabled'    => 'Habilitado',
    'cfg_gen_channel'              => 'Canal (channel)',
    'cfg_gen_event'                => 'Evento (.event)',
    'cfg_gen_theme'                => 'Tema Visual',
    'cfg_gen_theme_desc'           => 'Define a aparência do componente BaseCrud: paleta clara (padrão) ou escura.',
    'cfg_gen_theme_light'          => '☀️ Light',
    'cfg_gen_theme_dark'           => '🌙 Dark',
    'cfg_gen_theme_light_desc'     => 'Fundo branco, bordas cinza-claro',
    'cfg_gen_theme_dark_desc'      => 'Fundo escuro, bordas slate-700',

    // Aba Permissões
    'cfg_perm_gates_title'         => 'Gates de Acesso',
    'cfg_perm_create'              => 'Criar',
    'cfg_perm_edit'                => 'Editar',
    'cfg_perm_delete'              => 'Excluir',
    'cfg_perm_export'              => 'Exportar',
    'cfg_perm_restore'             => 'Restaurar',
    'cfg_perm_identifier'          => 'Identificador de Permissão',
    'cfg_perm_visibility_title'    => 'Visibilidade de Botões',
    'cfg_perm_btn_create'          => 'Botão Criar',
    'cfg_perm_btn_edit'            => 'Botão Editar',
    'cfg_perm_btn_delete'          => 'Botão Excluir',
    'cfg_perm_btn_trash'           => 'Botão Lixeira',

    // Footer
    'cfg_footer_cancel'            => 'Cancelar',
    'cfg_footer_save'              => 'Salvar Configuração',
    'cfg_footer_unit_cols'         => 'colunas',
    'cfg_footer_unit_filters'      => 'filtros',
    'cfg_footer_unit_styles'       => 'estilos',

    /*
    |--------------------------------------------------------------------------
    | Geral
    |--------------------------------------------------------------------------
    */
    'unknown' => 'Desconhecido',

];
