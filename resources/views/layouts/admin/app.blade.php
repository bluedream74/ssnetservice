@php
    $authUser = Auth::guard('admin')->user() ?? null;

    $config = [
        'appName' => config('app.name'),
        'locale' => $locale = app()->getLocale(),
        'locales' => config('app.locales'),
    ];

    $logoUrl = "/img/logo.svg";
@endphp

@extends('adminlte::page')

@section('title', '管理画面')
@section('dashboard_url', 'admin')

@section('meta_tags')
<meta name="csrf" value="{{ csrf_token() }}"/>
@stop

@section('adminlte_css')
    <link href="{{asset('css/adminlte_custom.css')}}" rel="stylesheet">
    <link href="{{asset('css/admin.css')}}" rel="stylesheet">
    @yield('styles')
@stop

@section('content_header')
    <div class="row">
        <div class="col-sm-6">
            @yield('content_header_label')
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                @yield('breadcrumbs')
            </ol>
        </div>
    </div>

    <common-loading />
@stop

@section('flash')
    @include('includes.flash')
@stop

@section('js')
    <script>
        window.config = @json($config);

        $(document).ready(function() {
            $('.btn-submit').click(function() {
                $('#showLoading').click();
            })
        })
    </script>
   
    <script src="{{ asset('js/admin.js') }}"></script>
    @yield('stripe_js')
    @yield('admin_scripts')
    <!-- <script src="https://code.jquery.com/jquery-1.12.4.js"></script> -->
    @yield('scripts')

@stop