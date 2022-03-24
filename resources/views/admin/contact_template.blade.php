@extends('layouts.admin.app')

@section('content_header_label')
<h3 class="m-0">テンプレート一覧</h3>
@stop
@php($isEdit = 0)

@section('content')
@if (sizeof($templates) > 0)
<div class="card">
  <div class="card-body table-responsive">
    <div class="add_button">
      <button type="button" class="btn btn-sm btn-primary mr-3 btn-primary" data-toggle="modal"
        data-target="#contact-template-modal">テンプレートを追加</button>
    </div>
    <table class="table table-striped">
      <thead>
        <tr>
          <th style="max-width: 800px;">タイトル</th>
          <th style="width: 200px;"></th>
        </tr>
      </thead>
      <tbody>
        @foreach($templates as $template)
        <tr>
          <td>
            {{ $template->template_title }}
          </td>
          <td>
            <a href="{{ route('admin.contactTemplates.edit', $template->id) }}"
              class="btn btn-sm btn-block btn-primary">編集</a>
            <button type="button" class="btn btn-sm btn-danger btn-remove mt-2 btn-block"
              data-id="{{ $template->id }}">削除</button>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{ Form::open(['route' => 'admin.contactTemplates.delete', 'id' => 'deleteForm', 'method' => 'DELETE']) }}
{{ Form::hidden('id', '', ['id' => 'delete_id']) }}
{{ Form::close() }}

<div class="modal fade" id="contact-template-modal" style="z-index: 9999;">
  {{ Form::open(['route' => 'admin.contactTemplates.add', 'method' => 'POST', 'id' => 'contactForm', 'files' => true])
  }}
  @foreach (Request::all() as $key => $value)
  {{ Form::hidden($key, $value) }}
  @endforeach
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <label>フォーム作成</label>
      </div>
      <div class="modal-body">
        <div class="row">
          <label class="col-sm-12">テンプレート名<span class="essential">*</span></label>
          <div class="col-sm-12 form-group">
            {{ Form::text('template_title', old('template_title'), ['class' => 'form-control','id' => 'template_title']) }}
          </div>

          <label class="col-sm-12">名前<span class="essential">*</span></label>
          <div class="col-sm-12 form-group row">
            <div class="col-sm-6">{{ Form::text('surname', old('surname'), ['class' => 'form-control', 'id' =>
              'surname']) }}</div>
            <div class="col-sm-6">{{ Form::text('lastname', old('lastname'), ['class' => 'form-control', 'id' =>
              'lastname']) }}</div>
          </div>

          <label class="col-sm-12">フリガナ</label>
          <div class="col-sm-12 form-group row">
            <div class="col-sm-6">{{ Form::text('fu_surname', old('fu_surname'), ['class' => 'form-control', 'id' =>
              'fu_surname']) }}</div>
            <div class="col-sm-6">{{ Form::text('fu_lastname', old('fu_lastname'), ['class' => 'form-control', 'id' =>
              'fu_lastname']) }}</div>
          </div>

          <label class="col-sm-12">会社名<span class="essential">*</span></label>
          <div class="col-sm-12 form-group">
            {{ Form::text('company', old('company'), ['class' => 'form-control','id' => 'company']) }}
          </div>

          <label class="col-sm-12">メールアドレス<span class="essential">*</span></label>
          <div class="col-sm-12 form-group">
            {{ Form::email('email', old('email'), ['class' => 'form-control','id' => 'email']) }}
          </div>

          <label class="col-sm-12">題名<span class="essential">*</span></label>
          <div class="col-sm-12 form-group">
            {{ Form::text('title', old('title'), ['class' => 'form-control','id' => 'title']) }}
          </div>

          <label class="col-sm-12">MY URL %myurl%</label>
          <div class="col-sm-12 form-group">
            {{ Form::text('myurl', old('myurl'), ['class' => 'form-control','id' => 'myurl']) }}
          </div>

          <label class="col-sm-12">内容<span class="essential">*</span></label>
          <div class="col-sm-12 form-group">
            {{ Form::textarea('content', old('content'), ['class' => 'form-control', 'rows' => 7, 'id' => 'content']) }}
          </div>

          <label class="col-sm-12">ホームページURL</label>
          <div class="col-sm-12 form-group">
            {{ Form::text('homepageUrl', old('homepageUrl'), ['class' => 'form-control','id' => 'homepageUrl']) }}
          </div>

          <label class="col-sm-12">都道府県</label>
          <div class="col-sm-8 form-group">
            {{ Form::select('area', $prefectures, Request::get('area'), ['class' => 'form-control', 'placeholder' =>
            'すべて']) }}
          </div>

          <label class="col-sm-12">郵便番号</label>
          <div class="col-sm-12 form-group row">
            <div class="col-sm-6">{{ Form::text('postalCode1', old('postalcode1'), ['class' => 'form-control','id' =>
              'postalCode1']) }}</div>
            <div class="col-sm-6">{{ Form::text('postalCode2', old('postalcode2'), ['class' => 'form-control','id' =>
              'postalCode2']) }}</div>
          </div>

          <label class="col-sm-12">住所</label>
          <div class="col-sm-12 form-group">
            {{ Form::text('address', old('address'), ['class' => 'form-control','id' => 'address']) }}
          </div>

          <label class="col-sm-12">電話番号</label>
          <div class="col-sm-12 form-group row">
            <div class="col-sm-4">{{ Form::number('phoneNumber1', old('phoneNumber1'), ['class' => 'form-control','id'
              => 'phoneNumber1']) }}</div>
            <div class="col-sm-4">{{ Form::number('phoneNumber2', old('phoneNumber2'), ['class' => 'form-control','id'
              => 'phoneNumber2']) }}</div>
            <div class="col-sm-4">{{ Form::number('phoneNumber3', old('phoneNumber3'), ['class' => 'form-control','id'
              => 'phoneNumber3']) }}</div>
          </div>

          <!-- <label class="col-sm-12">添付ファイル</label>
          <div class="col-sm-12 form-group">
              <input type="file" name="attachment" class="form-control" />
          </div> -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary pull-right" id="btnSubmit">送信</button>
        <button type="button" class="btn btn-default pull-right" data-dismiss="modal" id="btnCancel">閉じる</button>
      </div>
    </div>
  </div>
  {{ Form::close() }}
</div>

@else
<div class="card">
  <div class="card-body">
    <h4 class="text-center mt-3">テンプレートはありません。</h4>
  </div>
</div>
@endif
@stop

@section('scripts')
<script>
  $(document).ready(function() {
    $('#btnSubmit').click(function() {
      if ($("#template_title").val() === '' || $("#surname").val() === '' || $("#lastname").val() === '' || $(
          "#email").val() === '' || $("#title").val() === '' || $('#content').val() === '' || $('#company')
        .val() === '') {
        alert('内容を入力してください。')
        return;
      }
      $('#showLoading').click();
      $('#contactForm').submit();
      $('#btnCancel').click();
    })

    $('.btn-remove').click(function() {
      $('#delete_id').val($(this).data('id'));
      toastr.fire({
        html: "削除しますか？",
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
          $('#showLoading').click();
          $('#deleteForm').submit();
        }
      })
    })
  })
</script>
@stop

<style type="text/css">
  .add_button {
    display: flex;
    float: right;
  }
  .essential {
      color:red;
      font-size:16px;
  }
</style>