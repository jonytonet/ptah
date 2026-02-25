{{--
    Layout: forge-dashboard
    Uso (Blade @extends):
      @extends('ptah::layouts.forge-dashboard')
      @section('title', 'Dashboard')
      @section('content')
          ...
      @endsection
--}}
<x-forge-dashboard-layout :title="$title ?? null">
    @yield('content')
</x-forge-dashboard-layout>
