@extends('layouts.admin.app')

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
                <div class="col-sm-8 pre-wrap">{!! $contact->content !!}</div>
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
                    {{ Form::select('status', ['Delivery' => '配信済み', 'Failed' => '送信失敗'], Request::get('status'), ['class' => 'form-control', 'placeholder' => 'すべて', 'id' => 'status']) }}
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
                        <th style="max-width: 400px;">URL</th>
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
                    <label>メール作成</label>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <label class="col-sm-12">タイトル</label>
                        <div class="col-sm-12 form-group">
                            {{ Form::text('title', old('title'), ['class' => 'form-control', 'id' => 'title']) }}
                        </div>
                        <label class="col-sm-12">内容</label>
                        <div class="col-sm-12 form-group">
                            {{ Form::textarea('content', old('content'), ['class' => 'form-control', 'rows' => 10, 'id' => 'content']) }}
                        </div>
                        <label class="col-sm-12">添付ファイル</label>
                        <div class="col-sm-12 form-group">
                            <input type="file" name="attachment" class="form-control" />
                        </div>
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
            if ($("#title").val() === '' || $('#content').val() === '') {
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