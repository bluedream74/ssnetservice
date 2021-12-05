@extends('layouts.admin.app')
<style>

.time {
  margin:.375rem .5rem;
}
 </style>
@section('content_header_label')
    <h3 class="m-0">マスター設定</h3>
@stop

@section('content')
  {{ Form::open(['route' => 'admin.payment.update', 'method' => 'POST']) }}
  <div class="card">
    <div class="row card-body">
      <label class="col-sm-4">サブスクリプションプラン</label>
      <div class="col-sm-8 form-group">
        {{ Form::select('plan', 
          array(
            '0' => '44,000円',
            '1' => '66,000円',
            '2' => '77,000円',
            '3' => '88,000円',
            '4' => '132,000円',
            '5' => '176,000円',
          ),'', 
          ['class' => 'form-control']) }}
      </div>
      <label class="col-sm-4">課金の開始日</label>
      <div class="col-sm-8 form-group row">
        <div class="time">{{Form::date('start','', ['class' => 'form-control'])}}</div>
      </div>
      <div class="col-sm-12" style="text-align:right">
          {{ Form::button('保存する', ['class' => 'btn btn-primary pl-5 pr-5 btn-submit']) }}&nbsp;&nbsp;
      </div>
    </div>
  </div>
  {{ Form::close() }}

@stop