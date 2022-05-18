@extends('layouts.admin.app')

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/1.1.1/css/bootstrap-multiselect.min.css" />
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
    <h3 class="m-0">会社一覧</h3>
@stop

@section('content')
    {{ Form::open(['route' => 'admin.dashboard', 'method' => 'GET','id' => 'searchForm']) }}
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <label class="d-block">キーワード</label>
                        {{ Form::text('q', Request::get('q'), ['class' => 'form-control', 'placeholder' => 'キーワード']) }}
                    </div>
                    <div class="col-sm-3">
                        <label>エリア</label>
                        <br />
                        {{ Form::select('area[]', $prefectures, Request::get('area'), ['class' => 'form-control', 'id' => 'area_multi_select', 'multiple' => 'multiple',]) }}
                    </div>
                    <div class="col-sm-3">
                        <label>ステータス</label>
                        {{ Form::select('status', getCompanyStatuses(), Request::get('status'), ['class' => 'form-control', 'placeholder' => 'すべて']) }}
                    </div>
                    <div class="col-sm-3">
                        <label>カテゴリ</label>
                        {{ Form::select('source', getSources(true), Request::get('source'), ['class' => 'form-control', 'placeholder' => 'すべて','id' => 'source']) }}
                    </div>

                    <div class="col-sm-3">
                        <label>子カテゴリ</label>
                        @if(isset($subsources))
                          {{ Form::select('subsource', $subsources, Request::get('subsource'), ['class' => 'form-control', 'placeholder' => 'すべて','id' => 'subsource']) }}
                        @else
                          {{ Form::select('subsource', getSubSources(), Request::get('subsource'), ['class' => 'form-control', 'placeholder' => 'すべて','id' => 'subsource']) }}
                        @endif
                    </div>
                    
                    <div class="col-sm-3">
                        <label>電話番号</label>
                        {{ Form::select('phone', [1 => 'ある', 2 => '無い'], Request::get('phone'), ['class' => 'form-control', 'placeholder' => 'すべて']) }}
                    </div>
                    <div class="col-sm-3">
                        <label>問い合わせURL</label>
                        {{ Form::select('origin', [1 => 'ある', 2 => '無い'], Request::get('origin'), ['class' => 'form-control', 'placeholder' => 'すべて']) }}
                    </div>
                    <div class="col-sm-12 mt-3">
                        {{ Form::submit('検索する', ['class' => 'btn btn-primary pl-4 pr-4 mr-3']) }}
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-warning">リセット</a>
                    </div>
                </div>
            </div>
        </div>
    {{ Form::close() }}

    
        <div class="card">
            <div class="card-body table-responsive">
                {{ Form::open(['route' => 'admin.reset.company', 'method' => 'POST', 'id' => 'resetForm']) }}
                    @foreach (Request::except('_token', 'area') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                    @endforeach
                    <div class="row">
                        <label class="col-sm-2">Total: {{ $companies->total() }}</label>
                        <div class="col-sm-10 text-right">
                            <!-- <button type="button" class="btn btn-sm btn-default mr-3 btn-delete-email">無効なメールアドレスを一括削除</button> -->
                            <button type="button" class="btn btn-sm btn-danger mr-3" id="deleteCompanies">リストを一括削除</button>
                            @if ($config == 1)
                                <button type="button" class="btn btn-sm btn-danger mr-3" id="batchCheck">問い合わせフォームを一括チェック中を中断</button>
                            @else
                                <button type="button" class="btn btn-sm btn-primary mr-3" id="batchCheck">問い合わせフォームを一括チェック</button>
                            @endif
                            <button type="button" class="btn btn-sm btn-primary mr-3 btn-warning" data-toggle="modal" data-target="#email-modal">フォーム作成</button>
                            <button type="button" class="btn btn-sm btn-info mr-3 btn-duplicate-delete">重複をチェックして削除</button>
                            <button type="button" class="btn btn-sm btn-warning mr-3 btn-reset">送信済みを一括リセット</button>
                            <a href="{{ route('admin.companies.export', Request::except('area')) }}" class="btn btn-sm btn-success mr-3" target="_blank">CSV出力</a>
                            <button type="button" class="btn btn-sm btn-warning" id="btnImport"><i class="fas fa-file-csv mr-2"></i>{{ __('アップロード(CSV)') }}</button>
                        </div>
                    </div>
                {{ Form::close() }}

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th style="max-width: 400px;">@sortablelink('name', '会社名')</th>
                            <th style="max-width: 400px;">@sortablelink('url', 'URL')</th>
                            <th style="max-width: 400px;">@sortablelink('source', 'カテゴリ')</th>
                            <th style="max-width: 400px;">@sortablelink('subsource', '子カテゴリ')</th>
                            <th style="max-width: 400px;">@sortablelink('area', 'エリア')</th>
                            <th style="max-width: 400px;">@sortablelink('status', 'ステータス')</th>
                            <!-- <th style="max-width: 400px;">メールアドレス</th> -->
                            <!-- <th style="max-width: 400px;">オリジナル?</th> -->
                            <th style="max-width: 400px;">電話番号</th>
                            <th style="width: 100px; max-width: 100px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($companies as $company)
                        <tr>
                            <td style="max-width: 400px;">{{ $company->name }}</td>
                            <td style="max-width: 400px;">
                                <a href="{{ $company->url }}" target="_blank">{{ $company->url }}</a><br><br>
                                <a href="{{ $company->contact_form_url }}" target="_blank">{{ $company->contact_form_url }}</a>
                            </td>
                            <td style="max-width: 400px;">{{ $company->source }}</td>
                            <td style="max-width: 400px;">{{ $company->subsource }}</td>
                            
                            <td style="max-width: 400px;">{{ $company->area }}</td>
                            <td style="max-width: 400px; width: 120px;">
                                {{ Form::select('status', getCompanyStatuses(), $company->status, ['class' => 'form-control status-select', 'data-id' => $company->id]) }}
                            </td>
                            <td style="max-width: 400px;">
                                @foreach ($company->phones as $phone)
                                    <div>{{ $phone->phone }}</div>
                                @endforeach</td>
                            <td> 
                                <a href="{{ route('admin.companies.show', $company) }}" class="btn btn-sm btn-block btn-primary">詳細</a>
                                <button type="button" class="btn btn-sm btn-block mt-2 btn-danger btn-delete-company" data-id="{{ $company->id }}">削除</button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7"><h4 class="text-center mt-3">会社はヒットしませんでした。</h4></td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-sm-12">
                {{ $companies->appends(Request::except('area'))->render() }}
            </div>
        </div>
        <div class="modal fade" id="email-modal" style="z-index: 9999;">
            {{ Form::open(['route' => 'admin.contact.send', 'method' => 'POST', 'id' => 'contactForm', 'files' => true]) }}
            @foreach (Request::except('area') as $key => $value)
                {{ Form::hidden($key, $value) }}
            @endforeach
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <label>フォーム作成</label>
                        {{ Form::select('template_id', $contactTemplates->pluck('template_title'), '', ['class' => 'form-control select-template', 'placeholder' => 'テンプレートを選択', 'id' => 'contact_template_select']) }}
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
                                {{ Form::select('zone', $prefectures, Request::get('zone'), ['class' => 'form-control', 'placeholder' => 'すべて', 'id' => 'area']) }}
                            </div>

                           
                            <label class="col-sm-12">郵便番号</label>
                            <div class="col-sm-12 form-group row">
                                <div class="col-sm-6">{{ Form::text('postalCode1', old('postalcode1'), ['class' => 'form-control','id' => 'postalCode1']) }}</div>
                                <div class="col-sm-6">{{ Form::text('postalCode2', old('postalcode2'), ['class' => 'form-control','id' => 'postalCode2']) }}</div>
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

                            <label class="col-sm-12">予約投稿</label>
                            <div class="col-sm-12 form-group row">
                                <div class="col-sm-4">{{Form::date('date','', ['class' => 'form-control'])}}</div>
                                <div class="col-sm-4">{{Form::time('time','', ['class' => 'form-control'])}}</div>
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
        <div class="modal fade" style="z-index: 9999;">
            {{ Form::open(['route' => 'admin.batchCheck', 'method' => 'POST', 'id' => 'batchCheck_Form', 'files' => true]) }}
            @foreach (Request::except('area') as $key => $value)
                {{ Form::hidden($key, $value) }}
            @endforeach
            {{ Form::close() }}
        </div>
    {{ Form::open(['route' => 'admin.deleteCompanies', 'method' => 'POST', 'id' => 'deleteCompanies_Form', 'files' => true]) }}
        @foreach (Request::except('area') as $key => $value)
            {{ Form::hidden($key, $value) }}
        @endforeach
    {{ Form::close() }}

    {{ Form::open(['route' => 'admin.delete.duplicate', 'method' => 'POST', 'id' => 'duplicateForm']) }}
        @foreach (Request::except('area') as $key => $value)
                {{ Form::hidden($key, $value) }}
            @endforeach
    {{ Form::close() }}

    {{ Form::open(['route' => 'admin.delete.email', 'method' => 'POST', 'id' => 'deleteEmailForm']) }}
    {{ Form::close() }}


    {{ Form::open(['route' => 'admin.companies.import', 'method' => 'POST', 'id' => 'importForm', 'files' => true]) }}
        <input type="file" name="file" style="display: none;" id="customFile" accept="text/csv" />
    {{ Form::close() }}

    {{ Form::open(['route' => 'admin.company.delete', 'method' => 'POST', 'id' => 'deleteCompanyForm']) }}
        {{ Form::hidden('id', '', ['id' => 'deleteCompanyId']) }}
    {{ Form::close() }}
@stop

<!-- <div class="modal fade bd-example-modal-lg" id="loading" data-backdrop="static" data-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <span class="fa fa-spinner fa-pulse fa-3x fa-fw"></span>
        </div>
    </div>
</div> -->
@section('scripts')
<script>
    $(document).ready(function() {
        const contactTemplates = <?php echo $contactTemplates; ?>;
        $('#contact_template_select').on('change', function() {
            let index = $(this).val();
            if (index || index === 0) {
                Object.entries(contactTemplates[index]).forEach(([key, value]) => {
                    if (value && typeof $('#' + key).val() !== 'undefined') {
                        $('#' + key).val(value);
                    }
                });
            }
        })

        $('#area_multi_select').multiselect({
            includeSelectAllOption: true,
            nonSelectedText: '都道府県を選択',
            allSelectedText: 'すべて',
            nSelectedText: '件を選択した',
            selectAllValue: '',
            selectAllText: 'すべて'
        });

        $('#btnSend').click(function() {
            if ( $("#surname").val() ==='' || $("#lastname").val() ==='' || $("#mailaddress").val() ==='' || $("#title").val() === '' || $('#content').val() === '' ) {
                alert('内容を入力してください。')
                return;
            }
            $('#showLoading').click();
            $('#contactForm').submit();
            $('#btnCancel').click();
        })

        $('#batchCheck').click(function() {
            $('#batchCheck_Form').submit();
        })
        $('#deleteCompanies').click(function() {
            toastr.fire({
                html: "一度削除したデータは復元できません。" + {{ $companies->total() }} + "件のリストを削除しますか？",
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
                    $('#deleteCompanies_Form').submit();
                    $('#showLoading').click();
                }
            })
        })

        $('.email-delete').click(function() {
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
                    $.post("{{ route('admin.email.delete') }}", {
                        _token: "{{ csrf_token() }}",
                        id: id
                    });
                    $('#email_' + id).attr('style', 'display: none;');
                    return;
                }
            })
        })

        $("#source").change(function(){
            $("#searchForm").submit();
        })
        $('.status-select').change(function() {
            $.post("{{ route('admin.update.company.status') }}", {
                _token: "{{ csrf_token() }}",
                id: $(this).data('id'),
                status: $(this).val()
            })
        })

        $('.btn-reset').click(function() {
            toastr.fire({
                html: "本当にリセットしますか？",
                showDenyButton: false,
                showCancelButton: true,
                showConfirmButton: true,
                confirmButtonText: "リセット",
                cancelButtonText: "キャンセル",
                confirmButtonColor: "#dc3545",
                allowOutsideClick: false,
                allowEscapeKey: false,
                timer: undefined
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#resetForm').submit();
                    $('#showLoading').click();
                }
            })
        })

        $('.btn-duplicate-delete').click(function() {
            toastr.fire({
                html: "重複をチェックして削除しますか？",
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
                    $('#duplicateForm').submit();
                    $('#showLoading').click();
                }
            })
        })

        $('.btn-delete-email').click(function() {
            toastr.fire({
                html: "無効なメールアドレスを一括削除しますか？",
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
                    $('#deleteEmailForm').submit();
                    $('#showLoading').click();
                }
            })
        })

        $('#btnImport').click(function() {
            $('#customFile').click();
        })

        $('#customFile').change(function(e) {
            var file = e.target.files[0];
            if (file === undefined) return;
            
            $('#importForm').submit();
            $('#showLoading').click();
        })

        $('.btn-delete-company').click(function() {
            var id = $(this).data('id');
            toastr.fire({
                html: "この会社を削除しますか？",
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
                    $('#deleteCompanyId').val(id);
                    $('#deleteCompanyForm').submit();
                    $('#showLoading').click();
                }
            })
        })
    })
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/1.1.1/js/bootstrap-multiselect.min.js" integrity="sha512-fp+kGodOXYBIPyIXInWgdH2vTMiOfbLC9YqwEHslkUxc8JLI7eBL2UQ8/HbB5YehvynU3gA3klc84rAQcTQvXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@stop

<style type="text/css">
    .select-template {
        max-width: 400px;
    }

    .multiselect-container.dropdown-menu {
        max-height: 400px !important;
        overflow-y: auto !important;
    }
</style>
