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
  {{ Form::open(['route' => 'admin.plan.update', 'method' => 'POST']) }}
  <div class="card">
    <div class="row card-body">
      <label class="col-sm-4">プラン</label>
      <div class="col-sm-8 form-group">
        {{ Form::select('plan', getPlans() , $config->plan, ['class' => 'form-control']) }}
      </div>
      <label class="col-sm-4">１ヶ月の配信数</label>
      <div class="col-sm-8 form-group">
        {{ Form::number('limitCount', $config->limitCount , ['class' => 'form-control']) }}
      </div>
      <div class="col-sm-12" style="text-align:right">
          {{ Form::submit('設定する', ['class' => 'btn btn-primary pl-5 pr-5 btn-submit']) }}&nbsp;&nbsp;
      </div>
    </div>
  </div>
  {{ Form::close() }}

@stop