{{--
    forge-demo — Ptah Forge Component Showcase
    Rota sugerida: Route::get('/ptah-forge-demo', fn() => view('ptah::forge-demo'));
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ptah Forge — Component Demo</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#5b21b6', light: '#ede9fe', dark: '#4c1d95' },
                        success: { DEFAULT: '#10b981', light: '#d1fae5', dark: '#059669' },
                        danger:  { DEFAULT: '#ef4444', light: '#fee2e2', dark: '#dc2626' },
                        warn:    { DEFAULT: '#f59e0b', light: '#fef3c7', dark: '#d97706' },
                        dark:    { DEFAULT: '#1e293b', light: '#f1f5f9', dark: '#0f172a' },
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .scrollbar-none { scrollbar-width: none; -ms-overflow-style: none; }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        @keyframes wave { 0%, 100% { transform: scaleY(0.4); } 50% { transform: scaleY(1.0); } }
        .animate-wave { animation: wave 1s ease-in-out infinite; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 font-sans text-dark antialiased" x-data="{ notif: false, notifMsg: 'Notificação de teste!' }">

<header class="sticky top-0 z-50 bg-primary text-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center gap-3">
        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center font-bold text-white">P</div>
        <div>
            <h1 class="text-xl font-bold leading-none">Ptah Forge</h1>
            <p class="text-xs text-white/70">Component Showcase</p>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-10 space-y-16">

    {{-- ===== BUTTONS ===== --}}
    <section id="buttons">
        <h2 class="demo-title">forge-button</h2>
        <div class="demo-grid">
            <x-forge-button color="primary">Primary</x-forge-button>
            <x-forge-button color="success">Success</x-forge-button>
            <x-forge-button color="danger">Danger</x-forge-button>
            <x-forge-button color="warn">Warn</x-forge-button>
            <x-forge-button color="dark">Dark</x-forge-button>
        </div>
        <div class="demo-grid mt-3">
            <x-forge-button color="primary" flat>Flat</x-forge-button>
            <x-forge-button color="primary" relief>Relief</x-forge-button>
            <x-forge-button color="primary" rounded>Rounded</x-forge-button>
            <x-forge-button color="primary" size="sm">Small</x-forge-button>
            <x-forge-button color="primary" size="lg">Large</x-forge-button>
            <x-forge-button color="primary" loading>Loading</x-forge-button>
            <x-forge-button color="danger" disabled>Disabled</x-forge-button>
        </div>
    </section>

    {{-- ===== ALERTS ===== --}}
    <section id="alerts">
        <h2 class="demo-title">forge-alert</h2>
        <div class="space-y-3">
            <x-forge-alert type="info">Informação — verifique os dados antes de continuar.</x-forge-alert>
            <x-forge-alert type="success" :dismissible="true">Operação realizada com sucesso!</x-forge-alert>
            <x-forge-alert type="warning" :dismissible="true">Atenção: esta ação não pode ser desfeita.</x-forge-alert>
            <x-forge-alert type="danger" :dismissible="true">Erro ao processar a requisição.</x-forge-alert>
        </div>
    </section>

    {{-- ===== CARDS ===== --}}
    <section id="cards">
        <h2 class="demo-title">forge-card</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-forge-card title="Card Padrão">
                Conteúdo do card. Ideal para agrupar informações relacionadas.
            </x-forge-card>
            <x-forge-card title="Card Hoverable" :hoverable="true">
                Passe o mouse — observa o efeito de elevação!
            </x-forge-card>
            <x-forge-card title="Card Flat" :flat="true">
                Sem sombra, borda suave.
            </x-forge-card>
        </div>
    </section>

    {{-- ===== BADGES ===== --}}
    <section id="badges">
        <h2 class="demo-title">forge-badge</h2>
        <div class="demo-grid items-start">
            <x-forge-badge color="primary">Primary</x-forge-badge>
            <x-forge-badge color="success">Success</x-forge-badge>
            <x-forge-badge color="danger">Danger</x-forge-badge>
            <x-forge-badge color="warn">Warn</x-forge-badge>
            <x-forge-badge color="dark">Dark</x-forge-badge>
        </div>
        <div class="demo-grid items-start mt-3">
            <x-forge-badge color="success" :dot="true" :animate="true">Online</x-forge-badge>
            <x-forge-badge color="danger" :dot="true">Offline</x-forge-badge>
        </div>
    </section>

    {{-- ===== AVATAR ===== --}}
    <section id="avatar">
        <h2 class="demo-title">forge-avatar</h2>
        <div class="demo-grid items-end">
            <x-forge-avatar text="João" size="xs" />
            <x-forge-avatar text="Maria" size="sm" color="success" />
            <x-forge-avatar text="Pedro" size="md" color="warn" />
            <x-forge-avatar text="Ana" size="lg" color="danger" />
            <x-forge-avatar text="Lu" size="xl" color="dark" badge-color="success" />
        </div>
    </section>

    {{-- ===== BREADCRUMB ===== --}}
    <section id="breadcrumb">
        <h2 class="demo-title">forge-breadcrumb</h2>
        <x-forge-breadcrumb :items="[
            ['label' => 'Dashboard', 'url' => '#'],
            ['label' => 'Usuários',  'url' => '#'],
            ['label' => 'João Silva'],
        ]" />
    </section>

    {{-- ===== FORMS: INPUT ===== --}}
    <section id="input">
        <h2 class="demo-title">forge-input</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl">
            <x-forge-input name="name" label="Nome completo" />
            <x-forge-input name="email" type="email" label="E-mail" />
            <x-forge-input name="phone" label="Telefone" placeholder="(11) 90000-0000" />
            <x-forge-input name="disabled_ex" label="Desabilitado" value="Não editável" disabled />
        </div>
    </section>

    {{-- ===== FORMS: TEXTAREA ===== --}}
    <section id="textarea">
        <h2 class="demo-title">forge-textarea</h2>
        <div class="max-w-2xl">
            <x-forge-textarea name="desc" label="Descrição" :maxlength="200" :rows="4" />
        </div>
    </section>

    {{-- ===== FORMS: SELECT ===== --}}
    <section id="select">
        <h2 class="demo-title">forge-select</h2>
        <div class="max-w-sm">
            <x-forge-select
                name="status"
                label="Status"
                :options="[
                    ['value' => 'active',   'label' => 'Ativo'],
                    ['value' => 'inactive', 'label' => 'Inativo'],
                    ['value' => 'pending',  'label' => 'Pendente'],
                ]"
            />
        </div>
    </section>

    {{-- ===== FORMS: CHECKBOX / RADIO / SWITCH ===== --}}
    <section id="controls">
        <h2 class="demo-title">forge-checkbox / forge-radio / forge-switch</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="space-y-3">
                <x-forge-checkbox name="option_a" label="Opção A" :checked="true" />
                <x-forge-checkbox name="option_b" label="Opção B" color="success" />
                <x-forge-checkbox name="option_c" label="Desabilitado" disabled />
            </div>
            <div class="space-y-3">
                <x-forge-radio name="plan" value="basic"    label="Básico" :checked="true" />
                <x-forge-radio name="plan" value="pro"      label="Pro" color="success" />
                <x-forge-radio name="plan" value="business" label="Business" color="danger" />
            </div>
            <div class="space-y-4">
                <x-forge-switch name="notifications" label="Notificações" :checked="true" />
                <x-forge-switch name="dark_mode" label="Modo Escuro" color="dark" />
                <x-forge-switch name="maintenance" label="Manutenção" color="danger" />
            </div>
        </div>
    </section>

    {{-- ===== SPINNER ===== --}}
    <section id="spinner">
        <h2 class="demo-title">forge-spinner</h2>
        <div class="demo-grid items-center">
            <x-forge-spinner type="circle" color="primary" />
            <x-forge-spinner type="circle" color="success" size="lg" />
            <x-forge-spinner type="dots"   color="danger" />
            <x-forge-spinner type="wave"   color="warn" />
        </div>
    </section>

    {{-- ===== PROGRESS ===== --}}
    <section id="progress">
        <h2 class="demo-title">forge-progress</h2>
        <div class="max-w-xl space-y-3">
            <x-forge-progress :value="25" color="primary" label="Processando..." />
            <x-forge-progress :value="60" color="success" label="Em andamento" />
            <x-forge-progress :value="90" color="danger" label="Quase completo" :animated="true" />
        </div>
    </section>

    {{-- ===== TABS ===== --}}
    <section id="tabs">
        <h2 class="demo-title">forge-tabs</h2>
        <x-forge-tabs
            :tabs="[
                ['key' => 'info',    'label' => 'Informações'],
                ['key' => 'history', 'label' => 'Histórico'],
                ['key' => 'logs',    'label' => 'Logs'],
            ]"
        >
            <x-slot:info>
                <p class="text-gray-600">Conteúdo da aba <strong>Informações</strong>.</p>
            </x-slot:info>
            <x-slot:history>
                <p class="text-gray-600">Conteúdo da aba <strong>Histórico</strong>.</p>
            </x-slot:history>
            <x-slot:logs>
                <p class="text-gray-600">Conteúdo da aba <strong>Logs</strong>.</p>
            </x-slot:logs>
        </x-forge-tabs>
    </section>

    {{-- ===== MODAL ===== --}}
    <section id="modal">
        <h2 class="demo-title">forge-modal</h2>
        <div x-data="{ open: false }">
            <x-forge-button @click="open = true" color="primary">Abrir Modal</x-forge-button>
            <x-forge-modal x-model="open" title="Título do Modal" size="md">
                <p class="text-gray-600 mb-4">
                    Este é o conteúdo do modal. Você pode colocar qualquer elemento aqui.
                </p>
                <x-slot:footer>
                    <x-forge-button @click="open = false" color="primary">Confirmar</x-forge-button>
                    <x-forge-button @click="open = false" color="dark" flat>Cancelar</x-forge-button>
                </x-slot:footer>
            </x-forge-modal>
        </div>
    </section>

    {{-- ===== NOTIFICATION ===== --}}
    <section id="notification">
        <h2 class="demo-title">forge-notification</h2>
        <x-forge-button @click="notif = true; notifMsg = 'Operação realizada com sucesso!'" color="success">
            Mostrar Notificação
        </x-forge-button>
        <x-forge-notification
            x-model="notif"
            :message="''"
            x-bind:message="notifMsg"
            type="success"
            title="Sucesso"
            :auto-close="4000"
        />
    </section>

    {{-- ===== STAT CARDS ===== --}}
    <section id="stat-cards">
        <h2 class="demo-title">forge-stat-card</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-forge-stat-card label="Faturamento"   value="R$ 42.890"  trend="+12.5%" color="primary" />
            <x-forge-stat-card label="Pedidos"        value="1.340"       trend="+8.2%"  color="success" />
            <x-forge-stat-card label="Devoluções"     value="38"          trend="-3.1%"  color="danger" />
            <x-forge-stat-card label="Ticket Médio"   value="R$ 320"      trend="+5.0%"  color="warn" />
        </div>
    </section>

    {{-- ===== LIST ===== --}}
    <section id="list">
        <h2 class="demo-title">forge-list</h2>
        <x-forge-card class="max-w-lg">
            <x-forge-list :items="[
                ['name' => 'João Silva',   'description' => 'Admin',    'badge' => 'Ativo',    'badge_color' => 'success'],
                ['name' => 'Maria Santos', 'description' => 'Editor',   'badge' => 'Inativo',  'badge_color' => 'danger'],
                ['name' => 'Pedro Lima',   'description' => 'Viewer',   'badge' => 'Pendente', 'badge_color' => 'warn'],
            ]" />
        </x-forge-card>
    </section>

    {{-- ===== STEPPER ===== --}}
    <section id="stepper">
        <h2 class="demo-title">forge-stepper</h2>
        <x-forge-stepper
            :steps="[
                ['label' => 'Dados pessoais',  'description' => 'Nome, CPF, e-mail'],
                ['label' => 'Endereço',         'description' => 'Logradouro, cidade'],
                ['label' => 'Pagamento',         'description' => 'Forma de pagamento'],
                ['label' => 'Confirmação',       'description' => 'Revise os dados'],
            ]"
            :current="2"
        />
    </section>

    {{-- ===== TABLE ===== --}}
    <section id="table">
        <h2 class="demo-title">forge-table</h2>
        <x-forge-card>
            <x-forge-table
                :columns="[
                    ['key' => 'id',     'label' => 'ID',      'sortable' => true],
                    ['key' => 'name',   'label' => 'Nome',    'sortable' => true],
                    ['key' => 'status', 'label' => 'Status',  'sortable' => false],
                    ['key' => 'total',  'label' => 'Total',   'sortable' => true],
                ]"
                :rows="[
                    ['id' => 1, 'name' => 'Pedido #001', 'status' => 'Aprovado',  'total' => 'R$ 350,00'],
                    ['id' => 2, 'name' => 'Pedido #002', 'status' => 'Pendente',  'total' => 'R$ 120,00'],
                    ['id' => 3, 'name' => 'Pedido #003', 'status' => 'Cancelado', 'total' => 'R$ 90,00'],
                    ['id' => 4, 'name' => 'Pedido #004', 'status' => 'Aprovado',  'total' => 'R$ 540,00'],
                ]"
                :searchable="true"
                :show-actions="false"
            />
        </x-forge-card>
    </section>

    {{-- ===== PAGINATION ===== --}}
    <section id="pagination">
        <h2 class="demo-title">forge-pagination</h2>
        <p class="text-sm text-gray-500 mb-3">
            Em produção, passe o objeto <code class="bg-gray-100 px-1 rounded">$paginator</code> do Laravel.
            Abaixo um exemplo estático:
        </p>
        <div class="flex justify-center">
            <nav class="inline-flex rounded-xl overflow-hidden shadow-sm border border-gray-200">
                @foreach(range(1, 7) as $p)
                    <a href="#" class="px-4 py-2 text-sm border-r border-gray-200 last:border-0
                        {{ $p === 3 ? 'bg-primary text-white font-semibold' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ $p }}
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    {{-- ===== CHART CARD ===== --}}
    <section id="chart-card">
        <h2 class="demo-title">forge-chart-card</h2>
        <div class="max-w-lg">
            <x-forge-chart-card title="Vendas mensais">
                <x-slot:legend>
                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                        <span class="w-3 h-3 rounded-full bg-primary inline-block"></span> 2024
                    </span>
                </x-slot:legend>
                <div class="h-48 bg-gradient-to-t from-primary/10 to-transparent rounded-xl flex items-end justify-around px-4 pb-4">
                    @foreach([40, 65, 55, 80, 70, 90, 75, 95, 60, 85, 100, 88] as $h)
                        <div class="bg-primary rounded-t w-4 transition-all duration-500"
                             style="height: {{ $h }}%"></div>
                    @endforeach
                </div>
            </x-forge-chart-card>
        </div>
    </section>

</main>

<footer class="mt-20 py-8 text-center text-sm text-gray-400 border-t border-gray-200">
    Ptah Forge &mdash; Component showcase &mdash; {{ now()->year }}
</footer>

<style>
    .demo-title {
        @apply text-lg font-bold text-dark mb-4 pb-2 border-b border-gray-200;
    }
    .demo-grid {
        @apply flex flex-wrap gap-3;
    }
</style>

</body>
</html>
