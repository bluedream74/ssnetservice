<style>

.time {
  margin:.375rem .5rem;
}
 </style>
@extends('layouts.admin.app')

@section('content_header_label')
    <h3 class="m-0">設定</h3>
@stop

@section('content')
  {{ Form::open(['route' => 'admin.config.update', 'method' => 'POST']) }}
  <div class="row">
    <label class="col-sm-4">Limit_Contact_Form</label>
    <div class="col-sm-8 form-group">
      {{ Form::select('MAIL_LIMIT', getMailLimits(), $configs->mailLimit, ['class' => 'form-control']) }}
    </div>
    <label class="col-sm-4">配信許可時間</label>
    <div class="col-sm-8 form-group row">
      <div class="time">{{Form::time('start',$configs->start, ['class' => 'form-control'])}}</div>
      <div class="time">{{Form::time('end',$configs->end, ['class' => 'form-control'])}}</div>
    </div>
    <div class="col-sm-12">
      {{ Form::submit('保存する', ['class' => 'btn btn-primary pl-5 pr-5 btn-submit']) }}
    </div>
  </div>
  {{ Form::close() }}
@stop
