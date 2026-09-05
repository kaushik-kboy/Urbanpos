@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Login')

@section('css')
    <style>body.login-page, body.register-page { height: 100vh !important; min-height: 100vh !important; }</style>
@stop

@section('auth_header', 'Urbanpos Login')

@section('auth_body')
    <form method="POST" action="{{ route('login') }}">
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

        <div class="row">
            <div class="col-7">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember"
                           {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">{{ __('Remember Me') }}</label>
                </div>
            </div>
            <div class="col-5 text-right">
                <button type="submit" class="btn btn-primary btn-block">{{ __('Login') }}</button>
            </div>
        </div>
    </form>
@stop

@section('auth_footer')
    @if (Route::has('password.request'))
        <a href="{{ route('password.request') }}">{{ __('Forgot Your Password?') }}</a>
    @endif
    @if (Route::has('register'))
        <br>
        <a href="{{ route('register') }}" class="text-center">{{ __('Register a new membership') }}</a>
    @endif
@stop
