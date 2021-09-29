@extends('layouts.admin.app')

@section('content_header_label')
    <h3 class="m-0">会社検索</h3>
@stop
@section('content')
    {{ Form::open(['route' => 'admin.do.fetch', 'method' => 'POST']) }}
    <div class="row">
        <label class="col-sm-4">カテゴリ </label>
        <div class="col-sm-8 form-group">
            {{ Form::select('source', getSources(), old('source', 0), ['class' => 'form-control']) }}
        </div>
        <label class="col-sm-4">ページ</label>
        <div class="col-sm-8 form-group d-flex align-items-center">
            {{ Form::number('from', old('from'), ['class' => 'form-control flex-1', 'placeholder' => 1]) }}
            <span class="ml-3 mr-3" style="width: 80px;">から</span>
            {{ Form::number('to', old('to'), ['class' => 'form-control flex-1', 'placeholder' => 10]) }}
        </div>
        <div class="col-sm-12">
            {{ Form::submit('検索する', ['class' => 'btn btn-primary pl-5 pr-5 btn-submit']) }}
        </div>
    </div>
    {{ Form::close() }}
@stop
