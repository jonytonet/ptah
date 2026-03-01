{{--
    Layout: forge-dashboard
    Modo 1 — Blade @extends (ex: dashboard.blade.php, pages do módulo menu):
      @extends('ptah::layouts.forge-dashboard')
      @section('content') ... @endsection

    Modo 2 — Livewire full-page component (ex: ProfilePage, CompanyList):
      #[Layout('ptah::layouts.forge-dashboard')]
      O conteúdo é injetado automaticamente via $slot.
--}}
<x-forge-dashboard-layout :title="$title ?? null">
    @hasSection('content')
        @yield('content')
    @endif
    @sectionMissing('content')
        {{ $slot ?? '' }}
    @endif
</x-forge-dashboard-layout>
