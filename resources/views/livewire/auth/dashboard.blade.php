{{-- resources/views/livewire/auth/dashboard.blade.php --}}
@extends('ptah::layouts.forge-dashboard')

@section('content')
<x-forge-page-header title="Dashboard" subtitle="Visão geral do sistema" />

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
    <x-forge-stat-card
        title="Bem-vindo"
        :value="auth()->user()->name ?? 'Usuário'"
        icon='<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'
        color="primary"
    />
    <x-forge-stat-card
        title="Sistema"
        :value="config('app.name')"
        icon='<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>'
        color="secondary"
    />
    <x-forge-stat-card
        title="Ambiente"
        :value="ucfirst(config('app.env'))"
        icon='<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        color="success"
    />
    <x-forge-stat-card
        title="Versão Laravel"
        :value="app()->version()"
        icon='<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'
        color="warning"
    />
</div>
@endsection
