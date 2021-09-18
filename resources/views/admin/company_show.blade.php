@extends('layouts.admin.app')

@section('content_header_label')
    <h3 class="m-0">会社詳細</h3>
@stop

@section('content')
  <div class="card">
    <div class="card-body table-responsive">
      <div class="row mb-3">
        <label class="col-sm-4">会社名</label>
        <div class="col-sm-8 pre-wrap">{{ $company->name }}</div>
      </div>
      <div class="row mb-3">
        <label class="col-sm-4">URL</label>
        <div class="col-sm-8 pre-wrap">{{ $company->url }}</div>
      </div>
      <div class="row mb-3">
        <label class="col-sm-4">カテゴリ</label>
        <div class="col-sm-8 pre-wrap">{{ $company->source }}</div>
      </div>
      <div class="row mb-3">
        <label class="col-sm-4">エリア</label>
        <div class="col-sm-8 pre-wrap">{{ $company->area }}</div>
      </div>
      
      <div class="row mb-3">
        <label class="col-sm-4">電話番号</label>
        <div class="col-sm-8 pre-wrap">
          <div class="text-right mb-3"><button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#phone-modal">新規追加</button></div>
          <div class="table-responsive">
            <table class="table table-striped">
              @foreach ($company->phones as $companyPhone)
                <tr id="phone_{{ $companyPhone->id }}">
                  <td>{{ $companyPhone->phone }}</td>
                  <td class="text-right"><button type="button" class="btn btn-sm btn-danger btnRemovePhone" data-id="{{ $companyPhone->id }}">削除</button></td>
                </tr>
              @endforeach
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  

  <div class="modal fade" id="phone-modal">
    {{ Form::open(['route' => ['admin.company.add.phone', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>電話番号追加</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">電話番号</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('phone', old('phone'), ['class' => 'form-control']) }}
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
@stop

@section('scripts')
<script>
  $(document).ready(function() {
    $('.btnRemoveEmail').click(function() {
      var id = $(this).data('id');
      toastr.fire({
        html: "このメールを削除してもよろしいでしょうか？",
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
          $.post("{{ route('admin.company.remove.email', $company) }}", {
            _token: "{{ csrf_token() }}",
            id: id
          });
          $('#email_' + id).attr('style', 'display: none;');
          return;
        }
      })
    })

    $('.btnRemovePhone').click(function() {
      var id = $(this).data('id');
      toastr.fire({
        html: "この電話番号を削除してもよろしいでしょうか？",
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
          $.post("{{ route('admin.company.remove.phone', $company) }}", {
            _token: "{{ csrf_token() }}",
            id: id
          });
          $('#phone_' + id).attr('style', 'display: none;');
          return;
        }
      })
    })
  })
</script>
@stop