@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Reset Password')

@section('css')
    <style>body.login-page, body.register-page { height: 100vh !important; min-height: 100vh !important; }</style>
@stop

@section('auth_header', 'Forgot Password')

@section('auth_body')
    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <p class="login-box-msg">{{ __("Enter your email and we'll send you a password reset link.") }}</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="input-group mb-3">
            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                   name="email" value="{{ old('email') }}" placeholder="{{ __('Email Address') }}"
                   required autocomplete="email" autofocus>
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-envelope"></span></div>
            </div>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block">{{ __('Send Password Reset Link') }}</button>
    </form>
@stop

@section('auth_footer')
    <a href="{{ route('login') }}" class="text-center">{{ __('Back to login') }}</a>
@stop
