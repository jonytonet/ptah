<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Caminhos de geração de arquivos
    |--------------------------------------------------------------------------
    |
    | Define os diretórios onde cada tipo de arquivo será gerado pelo comando
    | ptah:make. Todos os caminhos são relativos ao diretório da aplicação.
    |
    */
    'paths' => [
        'models'       => app_path('Models'),
        'services'     => app_path('Services'),
        'repositories' => app_path('Repositories'),
        'dtos'         => app_path('DTOs'),
        'actions'      => app_path('Actions'),
        'livewire'     => app_path('Livewire'),
        'requests'     => app_path('Http/Requests'),
        'resources'    => app_path('Http/Resources'),
        'controllers'  => app_path('Http/Controllers'),
        'views'        => resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sistema de preferências de usuário
    |--------------------------------------------------------------------------
    |
    | Define o driver para armazenamento de preferências de usuário.
    | Drivers disponíveis: database
    |
    */
    'preferences' => [
        'driver' => 'database',
        'cache'  => true,
        'ttl'    => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */
    'api' => [
        'prefix'     => 'api',
        'middleware' => ['api', 'auth:sanctum'],
        'docs'       => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ptah Forge — Sistema de Componentes
    |--------------------------------------------------------------------------
    |
    | Configurações do design system Ptah Forge.
    |
    | prefix    : prefixo dos componentes Blade (<x-forge-button>, etc.)
    | tailwind  : versão do TailwindCSS usada nos componentes
    | sidebar_items: itens da sidebar gerada pelo scaffold
    |
    */
    'forge' => [
        'prefix'        => 'forge',
        'tailwind'      => 'v4',
        'sidebar_items' => [
            // Exemplo:
            // ['label' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'home', 'match' => 'dashboard'],
            // ['label' => 'Usuários', 'url' => '/users', 'icon' => 'users', 'match' => 'users*'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scaffold
    |--------------------------------------------------------------------------
    */
    'scaffold' => [
        'layout'    => 'forge-dashboard',
        'auth_layout' => 'forge-auth',
    ],

    /*
    |--------------------------------------------------------------------------
    | BaseCrud
    |--------------------------------------------------------------------------
    |
    | Configurações do sistema BaseCrud gerado pelo ptah:forge.
    |
    */
    'crud' => [
        'cache_enabled'  => true,
        'cache_ttl'      => 3600,
        'per_page'       => 25,
        'soft_deletes'   => true,
        'confirm_delete' => true,
        'export_driver'  => 'excel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulos opcionais
    |--------------------------------------------------------------------------
    | Use `php artisan ptah:module {auth|menu}` para instalar cada módulo.
    */
    'modules' => [
        'auth' => env('PTAH_MODULE_AUTH', false),
        'menu' => env('PTAH_MODULE_MENU', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulo Auth
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'guard'               => 'web',
        'home'                => '/dashboard',
        'register_enabled'    => false,
        'two_factor'          => true,
        'remember_me'         => true,
        'session_protection'  => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulo Menu
    |--------------------------------------------------------------------------
    | driver 'config' usa sidebar_items (padrão, não quebra nada).
    | driver 'database' lê da tabela menus.
    */
    'menu' => [
        'driver'    => env('PTAH_MENU_DRIVER', 'config'),
        'cache'     => true,
        'cache_ttl' => 300,
        'max_depth' => 4,
    ],

];
