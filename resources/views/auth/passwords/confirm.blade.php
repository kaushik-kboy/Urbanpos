@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Confirm Password')

@section('css')
    <style>body.login-page, body.register-page { height: 100vh !important; min-height: 100vh !important; }</style>
@stop

@section('auth_header', 'Confirm Password')

@section('auth_body')
    <p class="login-box-msg">{{ __('Please confirm your password before continuing.') }}</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="input-group mb-3">
            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                   name="password" placeholder="{{ __('Password') }}" required autocomplete="current-password">
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-lock"></span></div>
            </div>
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block">{{ __('Confirm Password') }}</button>
    </form>
@stop

@section('auth_footer')
    @if (Route::has('password.request'))
        <a href="{{ route('password.request') }}" class="text-center">{{ __('Forgot Your Password?') }}</a>
    @endif
@stop
