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
@stop
