@extends('layouts.admin.app')
<style>

.card-brand {
  margin:.375rem .5rem;
}
 </style>
@section('content_header_label')
    <h3 class="m-0">支払い管理</h3>
@stop
@section('content')
  {{ Form::open(['route' => 'admin.payment.update', 'method' => 'POST']) }}
  <div class="card">
    <div class="row card-body">
      <label class="col-sm-4">カード番号</label>
      <div class="col-sm-6 form-group">
        {{ Form::text('stripe_id', $user->stripe_id, ['class' => 'form-control']) }}
      </div>
      <label class="col-sm-4">セキュリティコード</label>
      <div class="col-sm-8 form-group row">
        <div class="card-brand">{{Form::text('card_brand',$user->card_brand, ['class' => 'form-control'])}}</div>
      </div>
      <div class="col-sm-12" style="text-align:right">
        @if($user->subscription)
          {{ Form::button('サブスクリプションの開始', ['class' => 'btn btn-primary pl-5 pr-5 btn-submit start']) }}&nbsp;&nbsp;&nbsp;&nbsp;
        @else
          {{ Form::button('サブスクリプションの停止', ['class' => 'btn btn-danger pl-5 pr-5 btn-submit stop']) }}&nbsp;&nbsp;&nbsp;&nbsp;
        @endif
        {{ Form::submit('保存する', ['class' => 'btn btn-primary pl-5 pr-5 btn-submit']) }}&nbsp;&nbsp;
      </div>
    </div>
  </div>
  {{ Form::close() }}

  {{ Form::open(['route' => 'admin.subscription.stop', 'method' => 'POST', 'id' => 'subscriptionForm']) }}
  {{ Form::close() }}

  {{ Form::open(['route' => 'admin.subscription.start', 'method' => 'POST', 'id' => 'startForm']) }}
  {{ Form::close() }}
@stop


@section('scripts')
<script>

$('.stop').click(function() {
  toastr.fire({
      html: "サブスクリプションを停止しますか？",
      showDenyButton: false,
      showCancelButton: true,
      showConfirmButton: true,
      confirmButtonText: "停止",
      cancelButtonText: "キャンセル",
      confirmButtonColor: "#dc3545",
      allowOutsideClick: false,
      allowEscapeKey: false,
      timer: undefined
  }).then((result) => {
      if (result.isConfirmed) {
          $('#subscriptionForm').submit();
          $('#showLoading').click();
      }
  })
})

$('.start').click(function() {
  toastr.fire({
      html: "サブスクリプションを開始しますか？",
      showDenyButton: false,
      showCancelButton: true,
      showConfirmButton: true,
      confirmButtonText: "開始",
      cancelButtonText: "キャンセル",
      confirmButtonColor: "#007bff",
      allowOutsideClick: false,
      allowEscapeKey: false,
      timer: undefined
  }).then((result) => {
      if (result.isConfirmed) {
          $('#startForm').submit();
          $('#showLoading').click();
      }
  })
})

</script>
@stop