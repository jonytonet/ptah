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
    | Drivers disponíveis: database, cache
    |
    */
    'preferences' => [
        'driver' => 'database',
    ],

];
