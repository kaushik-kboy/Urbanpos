@extends('adminlte::auth.auth-page', ['authType' => 'register'])

@section('title', 'Register')

@section('css')
    <style>body.login-page, body.register-page { height: 100vh !important; min-height: 100vh !important; }</style>
@stop

@section('auth_header', 'Create an Account')

@section('auth_body')
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="input-group mb-3">
            <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                   name="name" value="{{ old('name') }}" placeholder="{{ __('Name') }}"
                   required autocomplete="name" autofocus>
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-user"></span></div>
            </div>
            @error('name')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="input-group mb-3">
            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                   name="email" value="{{ old('email') }}" placeholder="{{ __('Email Address') }}"
                   required autocomplete="email">
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-envelope"></span></div>
            </div>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="input-group mb-3">
            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                   name="password" placeholder="{{ __('Password') }}" required autocomplete="new-password">
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-lock"></span></div>
            </div>
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="input-group mb-3">
            <input id="password-confirm" type="password" class="form-control" name="password_confirmation"
                   placeholder="{{ __('Confirm Password') }}" required autocomplete="new-password">
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-lock"></span></div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">{{ __('Register') }}</button>
    </form>
@stop

@section('auth_footer')
    <a href="{{ route('login') }}" class="text-center">{{ __('I already have a membership') }}</a>
@stop
