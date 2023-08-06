<div class="row mt-3">
    <div class="col-12">
        @foreach (['info', 'success', 'danger', 'warning'] as $msg)
            @if (Session::has('system.message.' . $msg))
                <flash type="{{$msg}}" message="{{ Session::get('system.message.' . $msg) }}"></flash>
            @endif
        @endforeach
    </div>
</div>