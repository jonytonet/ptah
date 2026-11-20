{{-- CssBootstrapBrowserTest: a host's OWN Bootstrap layout (fixed navbar,
     container) embedding a BaseCrud with <x-ptah-assets /> — no ptah layout. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Host Bootstrap layout</title>
    <x-ptah-assets />
    <link rel="stylesheet" href="/dusk-test/bootstrap.css">
    @livewireStyles
</head>
<body class="pt-5">
    <nav class="navbar navbar-expand fixed-top bg-body-tertiary border-bottom" id="host-navbar">
        <div class="container-fluid"><span class="navbar-brand" id="host-brand">Host</span></div>
    </nav>

    <div class="container py-4" id="host-container">
        <h1 id="host-h1" class="h3">Host page</h1>
        @livewire('ptah-base-crud', ['model' => $model])
    </div>

    @livewireScripts
</body>
</html>
