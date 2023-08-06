@extends('layouts.web.no_header')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-lg-4 offset-lg-4 col-md-6 offset-md-3 col-sm-12 pt-5 pb-5">
            <h2 class="text-center mb-4" style="font-size: 1.5rem">管理画面</h2>
            {{ Form::open(['route' => 'admin.login', 'method' => 'POST']) }}
                <div class="form-group mb-3">
                    {{ Form::text('email', old('email'), ['class' => 'form-control ch-50' . ($errors->has('email') ? ' is-invalid' : ''), 'placeholder' => 'メールアドレス']) }}
                    @error('email')
                    <span class="invalid-feedback" role="alert">
                        {{ $message }}
                    </span>
                    @enderror
                </div>
                <div class="form-group mb-3">
                    {{ Form::password('password', ['class' => 'form-control ch-50' . ($errors->has('password') ? ' is-invalid' : ''), 'placeholder' => 'パスワード']) }}
                    @error('password')
                    <span class="invalid-feedback" role="alert">
                        {{ $message }}
                    </span>
                    @enderror
                </div>
                <div class="form-group mb-3">
                    {{ Form::checkbox('remember', true, old('remember'), ['class' => 'w-auto', 'id' => 'remember']) }}
                    <label for="remember" class="m-0">次回より自動ログイン</label>
                </div>
                <div class="form-group mb-4">
                    {{ Form::button('ログイン', ['class' => 'btn btn-primary btn-black ch-50 btn-block bold btn-submit', 'type' => 'submit']) }}
                </div>
            {{ Form::close() }}
        </div>
    </div>
</div>
@endsection
