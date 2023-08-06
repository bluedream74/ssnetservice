@extends('layouts.admin.app')

@section('content_header_label')
    <h3 class="m-0">会社詳細</h3>
@stop
@section('content')
  <div class="card">
    <div class="card-body table-responsive">
      <div class="row mb-3">
        <label class="col-sm-4">会社名</label>
        <div class="col-sm-8 pre-wrap">
          <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                  <td>{{ $company->name }}</td>
                  <td class="text-right"><button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#company-name-edit-modal" data-id="{{ $company->id }}">編集</button></td>
                </tr>
            </table>
          </div>
        </div>
      </div>
      <div class="row mb-3">
        <label class="col-sm-4">URL</label>
        <div class="col-sm-8 pre-wrap">
          @if(isset($company->url)&&($company->url !== ""))
          <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                  <td>{{ $company->url }}</td>
                  <td class="text-right"><button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#url-edit-modal" data-id="{{ $company->id }}">編集</button></td>
                </tr>
            </table>
          </div>
          @else
          <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                  <td></td>
                  <td class="text-right"><button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#url-modal" data-id="{{ $company->id }}">追加</button></td>
                </tr>
            </table>
          </div>
          @endif
        </div>
      </div>
     
      <div class="row mb-3">
        <label class="col-sm-4">問い合わせURL</label>
        <div class="col-sm-8 pre-wrap">
          @if(isset($company->contact_form_url)&&($company->contact_form_url !== ""))
          <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                  <td>{{ $company->contact_form_url }}</td>
                  <td class="text-right"><button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#contact-form-edit-modal" data-id="{{ $company->id }}">編集</button></td>
                </tr>
            </table>
          </div>
          @else
          <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                  <td></td>
                  <td class="text-right"><button type="button" class="btn btn-sm btn-primary " data-toggle="modal" data-target="#contact-form-modal" data-id="{{ $company->id }}">追加</button></td>
                </tr>
            </table>
          </div>
          @endif
        </div>
      </div>
      <div class="row mb-3">
        <label class="col-sm-4">カテゴリ</label>
        <div class="col-sm-8 pre-wrap">
          @if(isset($company->source)&&($company->source !== ""))
            <div class="table-responsive">
              <table class="table table-striped">
                  <tr>
                    <td>{{ $company->source }}</td>
                    <td class="text-right"><button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#category-edit-modal" data-id="{{ $company->id }}">編集</button></td>
                  </tr>
              </table>
            </div>
            @else
            <div class="table-responsive">
              <table class="table table-striped">
                  <tr>
                    <td></td>
                    <td class="text-right"><button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#category-modal" data-id="{{ $company->id }}">追加</button></td>
                  </tr>
              </table>
            </div>
            @endif
          </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-4">子カテゴリ</label>
        <div class="col-sm-8 pre-wrap">
          @if(isset($company->subsource)&&($company->subsource !== ""))
            <div class="table-responsive">
              <table class="table table-striped">
                  <tr>
                    <td>{{ $company->subsource }}</td>
                    <td class="text-right"><button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#subcategory-edit-modal" data-id="{{ $company->id }}">編集</button></td>
                  </tr>
              </table>
            </div>
            @else
            <div class="table-responsive">
              <table class="table table-striped">
                  <tr>
                    <td></td>
                    <td class="text-right"><button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#subcategory-modal" data-id="{{ $company->id }}">追加</button></td>
                  </tr>
              </table>
            </div>
            @endif
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-4">エリア</label>
        <div class="col-sm-8 pre-wrap">
          <div class="table-responsive">
            <table class="table table-striped">
              <tr>
                <td>{{ $company->area}}</td>
                <td class="text-right"><button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#area-update-modal" data-id="{{ $company->id }}">更新</button></td>
              </tr>
            </table>
          </div>
        </div>
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
  
  <div class="modal fade" id="company-name-edit-modal">
    {{ Form::open(['route' => ['admin.company.edit.name', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>会社名編集</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">会社名</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('name', old('name',$company->name), ['class' => 'form-control']) }}
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

  <div class="modal fade" id="url-edit-modal">
    {{ Form::open(['route' => ['admin.company.edit.url', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>URL編集</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">URL</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('url', old('url',$company->url), ['class' => 'form-control']) }}
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

  <div class="modal fade" id="url-modal">
    {{ Form::open(['route' => ['admin.company.add.url', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>URL追加</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">URL</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('url', old('url',$company->url), ['class' => 'form-control']) }}
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

  <div class="modal fade" id="contact-form-edit-modal">
    {{ Form::open(['route' => ['admin.company.edit.contacturl', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>問い合わせURL編集</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">URL</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('contact_form_url', old('contact_form_url',$company->contact_form_url), ['class' => 'form-control']) }}
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

  <div class="modal fade" id="contact-form-modal">
    {{ Form::open(['route' => ['admin.company.add.contacturl', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>問い合わせURL追加</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">URL</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('contact_form_url', old('contact_form_url',$company->contact_form_url), ['class' => 'form-control']) }}
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

  <div class="modal fade" id="category-edit-modal">
    {{ Form::open(['route' => ['admin.company.edit.category', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>カテゴリ編集</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">カテゴリ</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('source', old('source',$company->source), ['class' => 'form-control']) }}
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

  <div class="modal fade" id="category-modal">
    {{ Form::open(['route' => ['admin.company.add.category', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>カテゴリ追加</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">カテゴリ</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('source', old('source',$company->source), ['class' => 'form-control']) }}
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

  <div class="modal fade" id="subcategory-edit-modal">
    {{ Form::open(['route' => ['admin.company.edit.subcategory', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>子カテゴリ編集</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">子カテゴリ</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('subsource', old('subsource',$company->subsource), ['class' => 'form-control']) }}
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

  <div class="modal fade" id="subcategory-modal">
    {{ Form::open(['route' => ['admin.company.add.subcategory', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>カテゴリ追加</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">カテゴリ</label>
            <div class="col-sm-12 form-group">
              {{ Form::text('subsource', old('subsource',$company->subsource), ['class' => 'form-control']) }}
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

  <div class="modal fade" id="area-update-modal">
    {{ Form::open(['route' => ['admin.company.update.area', $company], 'method' => 'POST']) }}
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <label>エリア更新</label>
        </div>
        <div class="modal-body">
          <div class="row">
            <label class="col-sm-12">エリア</label>
            <div class="col-sm-12 form-group">
              {{ Form::select('area', $prefectures, $company->area, ['class' => 'form-control', 'placeholder' => 'すべて']) }}
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