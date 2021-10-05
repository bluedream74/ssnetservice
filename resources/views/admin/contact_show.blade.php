@extends('layouts.admin.app')
<style>
  .bd-example-modal-lg .modal-dialog{
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
  }
  .essential {
      color:red;
      font-size:16px;
  }
  .bd-example-modal-lg .modal-dialog .modal-content{
    background-color: transparent;
    border: none;
  }
  select {
      padding :.375rem 0.25rem !important;
  }
  </style>
@section('content_header_label')
    <h3 class="m-0">フォーム一覧</h3>
@stop

@section('content')
    {{ Form::open(['route' => ['admin.contact.show', $contact], 'method' => 'GET']) }}
    <div class="card">
        <div class="card-body table-responsive">
            <div class="row mb-3">
                <label class="col-sm-4">名前</label>
                <div class="col-sm-8 pre-wrap">{!! $contact->surname !!}&nbsp;{!! $contact->lastname !!}</div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-4">フリガナ</label>
                <div class="col-sm-8 pre-wrap">
                    @if (isset($contact->fu_surname))
                        {!! $contact->fu_surname !!} &nbsp; {!! $contact->fu_lastname !!}
                    @else
                        なし
                    @endif
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-4">会社名</label>
                <div class="col-sm-8 pre-wrap">{!! $contact->company !!}</div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-4">メールアドレス</label>
                <div class="col-sm-8 pre-wrap">{!! $contact->email !!}</div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-4">題名</label>
                <div class="col-sm-8 pre-wrap">{{ $contact->title }}</div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-4">内容</label>
                <div class="col-sm-8 pre-wrap">{!! nl2br($contact->content) !!}</div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-4">ホームページURL</label>
                <div class="col-sm-8 pre-wrap">{!! nl2br($contact->homepageUrl) !!}</div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-4">都道府県</label>
                <div class="col-sm-8 pre-wrap">{!! nl2br($contact->area) !!}</div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-4">郵便番号</label>
                <div class="col-sm-8 pre-wrap">
                    @if (isset($contact->postalCode1))
                        {!! $contact->postalCode1 !!}&nbsp;{!! $contact->postalCode2 !!}
                    @else
                        なし
                    @endif
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-4">住所</label>
                <div class="col-sm-8 pre-wrap">
                    @if (isset($contact->address))
                        {!! $contact->address !!}
                    @else
                        なし
                    @endif
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-4">電話番号</label>
                <div class="col-sm-8 pre-wrap">
                    @if (isset($contact->phoneNumber1))
                        {!! $contact->phoneNumber1 !!}&nbsp;{!! $contact->phoneNumber2 !!}&nbsp;{!! $contact->phoneNumber3 !!}
                    @else
                        なし
                    @endif
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-sm-12 text-right">
                    <a href="{{ route('admin.contact.export', ['status' => Request::get('status'), 'contact' => $contact->id]) }}" class="btn btn-sm btn-success pl-3 pr-3" target="_blank">CSVダウンロード</a>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-4">ステータス</label>
                <div class="col-sm-4">
                    {{ Form::select('status', ['2' => '配信済み', '1' => '送信失敗'], Request::get('status'), ['class' => 'form-control', 'placeholder' => 'すべて', 'id' => 'status']) }}
                </div>
                <div class="col-sm-4">
                    {{ Form::submit('検索', ['class' => 'btn btn-sm btn-primary']) }}
                    <button type="button" class="btn btn-sm btn-primary ml-3" data-toggle="modal" data-target="#email-modal">フォーム作成</button>
                </div>
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th style="max-width: 400px;">会社名</th>
                        <th style="max-width: 400px;">お問い合わせフォームのURL</th>
                        <th style="max-width: 400px;">電話番号</th>
                        <th style="max-width: 400px;">ステータス</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($companies as $companyContact)
                    @php
                        $company = $companyContact->company;
                    @endphp
                    <tr>
                        <td style="max-width: 400px;">{{ $company->name }}</td>
                        <td style="max-width: 400px;"><a href="{{ $company->contact_form_url }}" target="_blank">{{ $company->contact_form_url }}</a></td>
                        <td style="max-width: 400px; width: 200px;">
                            @foreach ($company->phones as $phone)
                                <div>{{ $phone->phone }}</div>
                            @endforeach
                        </td>
                        <td style="max-width: 400px; width: 200px;">
                            @if (($companyContact->is_delivered === 1))
                                <span class="badge badge-danger">送信失敗</span>
                            @elseif (($companyContact->is_delivered === 2))
                                <span class="badge badge-success">送信済み</span>
                            @else
                                <span class="badge badge-warning">送信予定</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-sm-12">
            {{ $companies->appends(Request::all())->render() }}
        </div>
    </div>
    {{ Form::close() }}

    <div class="modal fade" id="email-modal" style="z-index: 9999;">
            {{ Form::open(['route' => ['admin.contact.show.send', $contact], 'method' => 'POST', 'id' => 'contactForm', 'files' => true]) }}
            {{ Form::hidden('status', Request::get('status'), ['id' => 'status_id']) }}
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <label>フォーム作成</label>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <label class="col-sm-12">名前<span class="essential">*</span></label>
                            <div class="col-sm-12 form-group row">
                                <div class="col-sm-6">{{ Form::text('surname', old('surname'), ['class' => 'form-control', 'id' => 'surname']) }}</div>
                                <div class="col-sm-6">{{ Form::text('lastname', old('lastname'), ['class' => 'form-control', 'id' => 'lastname']) }}</div>
                            </div>

                            <label class="col-sm-12">フリガナ</label>
                            <div class="col-sm-12 form-group row">
                                <div class="col-sm-6">{{ Form::text('fu_surname', old('fu_surname'), ['class' => 'form-control', 'id' => 'fu_surname']) }}</div>
                                <div class="col-sm-6">{{ Form::text('fu_lastname', old('fu_lastname'), ['class' => 'form-control', 'id' => 'fu_lastname']) }}</div>
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
                                {{ Form::select('zone', $prefectures, Request::get('zone'), ['class' => 'form-control', 'placeholder' => 'すべて']) }}
                            </div>

                            <label class="col-sm-12">郵便番号</label>
                            <div class="col-sm-12 form-group row">
                                <div class="col-sm-6">{{ Form::text('postalCode1', old('postalcode1'), ['class' => 'form-control','id' => 'address1']) }}</div>
                                <div class="col-sm-6">{{ Form::text('postalCode2', old('postalcode2'), ['class' => 'form-control','id' => 'address1']) }}</div>
                            </div>

                            <label class="col-sm-12">住所</label>
                            <div class="col-sm-12 form-group">
                                {{ Form::text('address', old('address'), ['class' => 'form-control','id' => 'address']) }}
                            </div>

                            <label class="col-sm-12">電話番号</label>
                            <div class="col-sm-12 form-group row">
                                <div class="col-sm-4">{{ Form::number('phoneNumber1', old('phoneNumber1'), ['class' => 'form-control','id' => 'phoneNumber1']) }}</div>
                                <div class="col-sm-4">{{ Form::number('phoneNumber2', old('phoneNumber2'), ['class' => 'form-control','id' => 'phoneNumber2']) }}</div>
                                <div class="col-sm-4">{{ Form::number('phoneNumber3', old('phoneNumber3'), ['class' => 'form-control','id' => 'phoneNumber3']) }}</div>
                            </div>
                           
                            <!-- <label class="col-sm-12">添付ファイル</label>
                            <div class="col-sm-12 form-group">
                                <input type="file" name="attachment" class="form-control" />
                            </div> -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary pull-right" id="btnSend">送信</button>
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
        $('#status').change(function() {
            $('#status_id').val($(this).val());
        })

        $('#btnSend').click(function() {
            if ( $("#surname").val() ==='' || $("#lastname").val() ==='' || $("#mailaddress").val() ==='' || $("#title").val() === '' || $('#content').val() === '' ) {
                alert('内容を入力してください。')
                return;
            }

            $('#contactForm').submit();
            $('#showLoading').click();
            $('#btnCancel').click();
        })
    })
</script>
@stop