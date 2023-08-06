@extends('layouts.admin.app')

@section('content_header_label')
    <h3 class="m-0">フォーム一覧</h3>
@stop

@section('content')
    @if (sizeof($contacts) > 0)
        <div class="card">
            <div class="card-body table-responsive">
                <div class="row">
                    <label class="col-sm-6">Total: {{ $contacts->total() }}</label>
                </div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th style="max-width: 500px;">タイトル</th>
                            <th style="max-width: 400px;">送信予定</th>
                            <th style="max-width: 400px;">送信済み</th>
                            <th style="max-width: 200px;width: 160px;">配信予定日時</th>
                            <th style="max-width: 140px;width: 140px;">登録日時</th>
                            <th style="width: 150px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($contacts as $contact)
                        <tr>
                            <td>
                                <a href="{{ route('admin.contact.show', [$contact, 'sort' => 'counts', 'direction' => 'desc']) }}">{{ $contact->title }}</a>
                            </td>
                            <td>{{ number_format($contact->stand_by_count) }}</td>
                            <td>{{ number_format($contact->sent_count) }}</td>
                            <!-- <td>{{ number_format($contact->logs()->count()) }}</td> -->
                            <td style="width: 140px;">{{ $contact->date . " " . $contact->time }}</td>
                            <td style="width: 140px;">{{ $contact->created_at->format("Y-m-d H:i") }}</td>
                            <td>
                                @if ($contact->isEditable == true)
                                    <a href="{{ route('admin.contact.edit', $contact->id) }}"
                                        class="btn btn-sm btn-primary">編集</a>
                                @endif
                                <button type="button" class="btn btn-sm btn-danger btn-remove" data-id="{{ $contact->id }}">削除</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-sm-12">
                {{ $contacts->appends(Request::all())->render() }}
            </div>
        </div>

        {{ Form::open(['route' => 'admin.contact.delete', 'id' => 'deleteForm', 'method' => 'POST']) }}
            {{ Form::hidden('id', '', ['id' => 'delete_id']) }}
        {{ Form::close() }}

    @else
        <div class="card">
            <div class="card-body">
                <h4 class="text-center mt-3">送信メールはありません。</h4> 
            </div>
        </div>
    @endif
@stop

@section('scripts')
<script>
    $(document).ready(function() {
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
                    $('#deleteForm').submit();
                    $('#showLoading').click();
                }
            })
        })
    })
</script>
@stop