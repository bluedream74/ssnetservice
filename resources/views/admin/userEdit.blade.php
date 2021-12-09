@extends('layouts.admin.app')

@section('content_header_label')
    <h3 class="m-0">ユーザー編集</h3>
@stop
@section('content')
  <div class="card">
    <div class="card-body table-responsive">
      <div class="row mb-3">
        <label class="col-sm-4">ユーザー名</label>
        <div class="col-sm-8 pre-wrap">
          <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                  <td>{{ $user->name }}</td>
                  <td class="text-right"><button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#user-name-edit-modal" data-id="{{ $user->id }}">編集</button></td>
                </tr>
            </table>
          </div>
        </div>
      </div>
     
      <div class="row mb-3">
        <label class="col-sm-4">メールアドレス</label>
        <div class="col-sm-8 pre-wrap">
          <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                  <td>{{ $user->email }}</td>
                  <td class="text-right"><button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#user-email-edit-modal" data-id="{{ $user->id }}">編集</button></td>
                </tr>
            </table>
          </div>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-4">パスワード</label>
        <div class="col-sm-8 pre-wrap">
          <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                  <td></td>
                  <td class="text-right"><button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#user-password-edit-modal" data-id="{{ $user->id }}">更新</button></td>
                </tr>
            </table>
          </div>
        </div>
      </div>
      <div class="row mb-3" style="justify-content:end">
        @if($user->paycheck)
            <div class="text-right"><button type="button" class="btn btn-sm btn-danger stop" data-id="{{ $user->id }}">ユーザーの利用を停止</button></div>
        @else
            <div class="text-right"><button type="button" class="btn btn-sm btn-primary start" data-id="{{ $user->id }}">ユーザーの利用を継続</button></div>
        @endif
      </div>
    </div>
  </div>

  <div class="modal fade" id="user-name-edit-modal">
    {{ Form::open(['route' => ['admin.user.edit.name', $user], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>ユーザー名編集</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">ユーザー名</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('name', old('name',$user->name), ['class' => 'form-control']) }}
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary pull-right btn-submit" id="btnSend">編集</button>
          <button type="button" class="btn btn-default pull-right" data-dismiss="modal" id="btnCancel">閉じる</button>
        </div>
      </div>
    </div>
    {{ Form::close() }}
  </div>

  <div class="modal fade" id="user-email-edit-modal">
    {{ Form::open(['route' => ['admin.user.edit.email', $user], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>メールアドレス編集</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">メールアドレス</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('email', old('email',$user->email), ['class' => 'form-control']) }}
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary pull-right btn-submit" id="btnSend">編集</button>
          <button type="button" class="btn btn-default pull-right" data-dismiss="modal" id="btnCancel">閉じる</button>
        </div>
      </div>
    </div>
    {{ Form::close() }}
  </div>

  <div class="modal fade" id="user-password-edit-modal">
    {{ Form::open(['route' => ['admin.user.edit.password', $user], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>パスワード更新</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">パスワード</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('password', '', ['class' => 'form-control']) }}
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary pull-right btn-submit" id="btnSend">更新</button>
          <button type="button" class="btn btn-default pull-right" data-dismiss="modal" id="btnCancel">閉じる</button>
        </div>
      </div>
    </div>
    {{ Form::close() }}
  </div>

  {{ Form::open(['route' => 'admin.user.stop', 'method' => 'POST', 'id' => 'stopForm']) }}
      {{ Form::hidden('id', '', ['id' => 'stopUserId']) }}
  {{ Form::close() }}

  {{ Form::open(['route' => 'admin.user.start', 'method' => 'POST', 'id' => 'startForm']) }}
      {{ Form::hidden('id', '', ['id' => 'startUserId']) }}
  {{ Form::close() }}
@stop


@section('scripts')
<script>
$('.stop').click(function() {
  var id = $(this).data('id');
  toastr.fire({
      html: "このユーザの利用を停止しますか？",
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
          $('#stopUserId').val(id);
          $('#stopForm').submit();
          $('#showLoading').click();
      }
  })
})
$('.start').click(function() {
  var id = $(this).data('id');
  toastr.fire({
      html: "このユーザの利用を継続しますか？",
      showDenyButton: false,
      showCancelButton: true,
      showConfirmButton: true,
      confirmButtonText: "継続",
      cancelButtonText: "キャンセル",
      confirmButtonColor: "#dc3545",
      allowOutsideClick: false,
      allowEscapeKey: false,
      timer: undefined
  }).then((result) => {
      if (result.isConfirmed) {
          $('#startUserId').val(id);
          $('#startForm').submit();
          $('#showLoading').click();
      }
  })
})

</script>
@stop
