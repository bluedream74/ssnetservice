@extends('layouts.admin.app')
<style>
  .add_button {
    text-align:right;
  }
</style>

@section('content_header_label')
    <h3 class="m-0">ユーザー一覧</h3>
@stop

@section('content')
    
    <div class="card">
        <div class="card-body table-responsive">
            <div class="add_button">
              <button type="button" class="btn btn-sm btn-primary mr-3 btn-primary" data-toggle="modal" data-target="#add-user-modal">ユーザーを追加</button>
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th style="max-width: 400px;">@sortablelink('name', 'ユーザー名')</th>
                        <th style="max-width: 400px;">@sortablelink('url', 'メールアドレス')</th>
                        <th style="width: 100px; max-width: 100px;"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td style="max-width: 400px;">{{ $user->name }}</td>
                        <td style="max-width: 400px;">{{ $user->email }}</td>
                        <td> 
                            <a href="{{ route('admin.user.edit', $user->id) }}" class="btn btn-sm btn-block btn-primary">編集</a>
                            <button type="button" class="btn btn-sm btn-block mt-2 btn-danger btn-delete-company" data-id="{{ $user->id }}">削除</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7"><h4 class="text-center mt-3">ユーザーがありません。</h4></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="modal fade" id="add-user-modal">
      {{ Form::open(['route' => ['admin.user.add'], 'method' => 'POST']) }}
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <label>ユーザーを追加</label>
          </div>
          <div class="modal-body">
            <div class="row">
              <label class="col-sm-12">名前</label>
              <div class="col-sm-12 form-group">
                {{ Form::text('name', '', ['class' => 'form-control']) }}
              </div>
              <label class="col-sm-12">メールアドレス</label>
              <div class="col-sm-12 form-group">
                {{ Form::text('email', '', ['class' => 'form-control']) }}
              </div>
              <label class="col-sm-12">パスワード</label>
              <div class="col-sm-12 form-group">
                {{ Form::text('password', '', ['class' => 'form-control']) }}
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary pull-right btn-submit" id="btnSend">追加</button>
            <button type="button" class="btn btn-default pull-right" data-dismiss="modal" id="btnCancel">閉じる</button>
          </div>
        </div>
      </div>
      {{ Form::close() }}
    </div>

    {{ Form::open(['route' => 'admin.user.delete', 'method' => 'POST', 'id' => 'deleteUserForm']) }}
        {{ Form::hidden('id', '', ['id' => 'deleteUserId']) }}
    {{ Form::close() }}
@stop

@section('scripts')
<script>
$('.btn-delete-company').click(function() {
  var id = $(this).data('id');
  toastr.fire({
      html: "このユーザーを削除しますか？",
      showDenyButton: false,
      showCancelButton: true,
      showConfirmButton: true,
      confirmButtonText: "削除",
      cancelButtonText: "キャンセル",
      confirmButtonColor: "#dc3545",
      allowOutsideClick: false,
      allowEscapeKey: false,
      timer: undefined
  }).then((result) => {
      if (result.isConfirmed) {
          $('#deleteUserId').val(id);
          $('#deleteUserForm').submit();
          $('#showLoading').click();
      }
  })
})

</script>
@stop