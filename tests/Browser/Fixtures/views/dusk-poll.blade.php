{{-- Fixture de SpaNavigationBrowserTest: poll que cruza uma acao lenta. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dusk Poll</title>
    @livewireStyles
</head>
<body>
    @livewire('dusk-poll', ['pollExpression' => $expression])
    @livewireScripts
</body>
</html>
