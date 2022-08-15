@extends('layouts.admin.app')

@section('content_header_label')
<h3 class="m-0">フォーム編集</h3>
@stop
@section('content')
<div class="card">
  <div class="card-body table-responsive">
    {{ Form::open(['route' => ['admin.contact.update', $contact->id], 'method' => 'POST', 'id' =>
    'contactForm', 'files' => true]) }}
    <div class="modal-body">
      <div class="row">
        <label class="col-sm-12">フォーム名<span class="essential">*</span></label>
        <div class="col-sm-12 form-group">
          {{ Form::text('title', $contact->title, ['class' => 'form-control','id' =>
          'title']) }}
        </div>

        <label class="col-sm-12">名前<span class="essential">*</span></label>
        <div class="col-sm-12 form-group row">
          <div class="col-sm-6">{{ Form::text('surname', $contact->surname, ['class' => 'form-control', 'id' =>
            'surname']) }}</div>
          <div class="col-sm-6">{{ Form::text('lastname', $contact->lastname, ['class' => 'form-control', 'id'
            =>
            'lastname']) }}</div>
        </div>

        <label class="col-sm-12">フリガナ</label>
        <div class="col-sm-12 form-group row">
          <div class="col-sm-6">{{ Form::text('fu_surname', $contact->fu_surname, ['class' => 'form-control',
            'id' =>
            'fu_surname']) }}</div>
          <div class="col-sm-6">{{ Form::text('fu_lastname', $contact->fu_lastname, ['class' => 'form-control',
            'id' =>
            'fu_lastname']) }}</div>
        </div>

        <label class="col-sm-12">会社名<span class="essential">*</span></label>
        <div class="col-sm-12 form-group">
          {{ Form::text('company', $contact->company, ['class' => 'form-control','id' => 'company']) }}
        </div>

        <label class="col-sm-12">メールアドレス<span class="essential">*</span></label>
        <div class="col-sm-12 form-group">
          {{ Form::email('email', $contact->email, ['class' => 'form-control','id' => 'email']) }}
        </div>

        <label class="col-sm-12">題名<span class="essential">*</span></label>
        <div class="col-sm-12 form-group">
          {{ Form::text('title', $contact->title, ['class' => 'form-control','id' => 'title']) }}
        </div>

        <label class="col-sm-12">MY URL %myurl%</label>
        <div class="col-sm-12 form-group">
          {{ Form::text('myurl', $contact->myurl, ['class' => 'form-control','id' => 'myurl']) }}
        </div>

        <label class="col-sm-12">内容<span class="essential">*</span></label>
        <div class="col-sm-12 form-group">
          {{ Form::textarea('content', $contact->content, ['class' => 'form-control', 'rows' => 7, 'id' =>
          'content']) }}
        </div>

        <label class="col-sm-12">ホームページURL</label>
        <div class="col-sm-12 form-group">
          {{ Form::text('homepageUrl', $contact->homepageUrl, ['class' => 'form-control','id' => 'homepageUrl'])
          }}
        </div>

        <label class="col-sm-12">都道府県</label>
        <div class="col-sm-8 form-group">
          {{ Form::select('area', $prefectures, $contact->area, ['class' => 'form-control', 'placeholder' =>
          'すべて']) }}
        </div>

        <label class="col-sm-12">郵便番号</label>
        <div class="col-sm-12 form-group row">
          <div class="col-sm-6">{{ Form::text('postalCode1', $contact->postalCode1, ['class' =>
            'form-control','id' =>
            'postalCode1']) }}</div>
          <div class="col-sm-6">{{ Form::text('postalCode2', $contact->postalCode2, ['class' =>
            'form-control','id' =>
            'postalCode2']) }}</div>
        </div>

        <label class="col-sm-12">住所</label>
        <div class="col-sm-12 form-group">
          {{ Form::text('address', $contact->address, ['class' => 'form-control','id' => 'address']) }}
        </div>

        <label class="col-sm-12">電話番号</label>
        <div class="col-sm-12 form-group row">
          <div class="col-sm-4">{{ Form::number('phoneNumber1', $contact->phoneNumber1, ['class' =>
            'form-control','id'
            => 'phoneNumber1']) }}</div>
          <div class="col-sm-4">{{ Form::number('phoneNumber2', $contact->phoneNumber2, ['class' =>
            'form-control','id'
            => 'phoneNumber2']) }}</div>
          <div class="col-sm-4">{{ Form::number('phoneNumber3', $contact->phoneNumber3, ['class' =>
            'form-control','id'
            => 'phoneNumber3']) }}</div>
        </div>

        <label class="col-sm-12">予約投稿</label>
        <div class="col-sm-12 form-group row">
            <div class="col-sm-4">{{Form::date('date',$contact->date, ['class' => 'form-control'])}}</div>
            <div class="col-sm-4">{{Form::time('time',$contact->time, ['class' => 'form-control'])}}</div>
        </div>

        <!-- <label class="col-sm-12">添付ファイル</label>
          <div class="col-sm-12 form-group">
              <input type="file" name="attachment" class="form-control" />
          </div> -->
      </div>
    </div>
    {{ Form::close() }}
  </div>
  <div class="card-footer">
    <button class="btn btn-sm btn-update btn-primary" id="btnSubmit">
      保存
    </button>
    <button class="btn btn-sm btn-return" id="btnCancel">
      閉じる
    </button>
  </div>
</div>

{{ Form::open(['route' => 'admin.contact', 'method' => 'GET','id' => 'indexForm']) }}
{{ Form::close() }}

@stop

@section('scripts')
<script>
  $(document).ready(function() {
    $('#btnSubmit').click(function() {
      if ($("#title").val() === '' || $("#surname").val() === '' || $("#lastname").val() === '' || $(
          "#email").val() === '' || $("#title").val() === '' || $('#content').val() === '' || $('#company')
        .val() === '') {
        alert('内容を入力してください。')
        return;
      }
      $('#showLoading').click();
      $('#contactForm').submit();
    })

    $('#btnCancel').click(function() {
      $('#showLoading').click();
      $('#indexForm').submit();
    })
  })
</script>
@stop

<style type="text/css">
  .card-footer {
    background-color: #fff !important;
    border-top: 1px solid #ced4da !important;
    text-align: right !important;
  }

  .btn-return {
    background-color: #c7cfd7 !important;
  }

  .essential {
    color: red;
    font-size: 16px;
  }
</style>