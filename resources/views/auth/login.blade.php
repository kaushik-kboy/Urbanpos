@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Login')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/urbanpets-theme.css') }}?v=20260921_1">
    <style>
        body.login-page {
            background: #fff4ea url('{{ asset("images/login-bg.jpg") }}') no-repeat center center fixed !important;
            background-size: cover !important;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }

        body.login-page::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 80% 50%, rgba(255, 255, 255, 0.2) 0%, rgba(255, 245, 235, 0.08) 100%);
            pointer-events: none;
            z-index: 0;
        }

        .login-box {
            position: relative;
            z-index: 2;
            width: 430px;
            max-width: 92vw;
            margin: 2rem auto;
        }

        @media (min-width: 992px) {
            body.login-page {
                align-items: flex-end !important;
                padding-right: 10% !important;
            }
            .login-box {
                margin: 0 !important;
            }
        }

        .login-box .card {
            border-radius: 20px !important;
            border: 1px solid rgba(255, 255, 255, 0.85) !important;
            background: rgba(255, 255, 255, 0.92) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            box-shadow: 0 20px 45px rgba(230, 115, 15, 0.16), 0 8px 25px rgba(0, 0, 0, 0.06) !important;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .login-box .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 50px rgba(230, 115, 15, 0.22), 0 10px 30px rgba(0, 0, 0, 0.08) !important;
        }

        .login-logo {
            margin-bottom: 1.25rem !important;
        }

        .login-logo a {
            color: #1769E8 !important;
            font-weight: 800 !important;
            font-size: 2.1rem !important;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 8px rgba(255, 255, 255, 0.9);
        }

        .login-logo a b {
            color: #F28C28 !important;
        }

        .login-box-msg {
            font-weight: 600 !important;
            color: #536581 !important;
            font-size: 1.05rem !important;
            padding-bottom: 1.25rem !important;
        }

        .login-card-body {
            padding: 2.2rem !important;
            background: transparent !important;
        }

        .login-card-body .input-group .form-control {
            border-radius: 10px 0 0 10px !important;
            border: 1.5px solid #dce6f5 !important;
            border-right: none !important;
            padding: 12px 15px !important;
            font-size: 0.95rem !important;
            background: #ffffff !important;
            height: 46px !important;
            transition: all 0.2s ease !important;
        }

        .login-card-body .input-group .form-control:focus {
            border-color: #F28C28 !important;
            box-shadow: 0 0 0 3px rgba(242, 140, 40, 0.15) !important;
        }

        .login-card-body .input-group .input-group-text {
            border-radius: 0 10px 10px 0 !important;
            border: 1.5px solid #dce6f5 !important;
            border-left: none !important;
            background: #ffffff !important;
            color: #74839b !important;
            height: 46px !important;
        }

        .login-card-body .btn-primary {
            background: linear-gradient(135deg, #F28C28 0%, #E07314 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            font-weight: 700 !important;
            height: 46px !important;
            font-size: 1rem !important;
            box-shadow: 0 6px 16px rgba(242, 140, 40, 0.35) !important;
            transition: all 0.2s ease !important;
        }

        .login-card-body .btn-primary:hover {
            background: linear-gradient(135deg, #E07314 0%, #C96008 100%) !important;
            box-shadow: 0 8px 22px rgba(242, 140, 40, 0.45) !important;
            transform: translateY(-1px);
        }
    </style>
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
