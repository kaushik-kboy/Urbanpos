@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('title', 'Verify Email')

@section('css')
    <style>body.login-page, body.register-page { height: 100vh !important; min-height: 100vh !important; }</style>
@stop

@section('auth_header', 'Verify Your Email Address')

@section('auth_body')
    @if (session('resent'))
        <div class="alert alert-success" role="alert">
            {{ __('A fresh verification link has been sent to your email address.') }}
        </div>
    @endif

    <p>
        {{ __('Before proceeding, please check your email for a verification link.') }}
        {{ __('If you did not receive the email') }},
        <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="btn btn-link p-0 m-0 align-baseline">{{ __('click here to request another') }}</button>.
        </form>
    </p>
@stop
