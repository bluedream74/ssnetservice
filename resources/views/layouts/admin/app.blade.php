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

@section('footer')
    <a href="/admin/term/agreement">利用規約</a>
        /
    <a href="/admin/company/info">会社概要</a>
@stop

<style type="text/css">
    .main-footer {
        background-color: #f4f6f9 !important;
        border-top: unset !important;
        text-align: right !important;
    }

    .main-footer > a {
        text-decoration: underline !important;
        color: black !important;
    }

    .layout-navbar-fixed .wrapper .content-wrapper {
        margin-top: 0px !important;
        min-height: 94vh !important;
    }

    .content-header {
        padding-top: 80px !important;
    }

    div#page-wrapper {
        background: #f4f6f9;
    }
</style>