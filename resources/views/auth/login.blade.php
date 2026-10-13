@extends('adminlte::master')

@section('title', 'Login - Urban Pets')

@section('classes_body', 'login-page up-login-screen')

@section('adminlte_css')
    <link rel="stylesheet" href="{{ asset('css/urbanpets-theme.css') }}?v=20260921_3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html, body.up-login-screen {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
            background: #fff5ec url('{{ asset("images/login-bg.jpg") }}') no-repeat center center fixed !important;
            background-size: cover !important;
            overflow-x: hidden;
        }

        .up-auth-overlay {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            padding: 2rem 5% 2rem 6%;
            box-sizing: border-box;
            background: radial-gradient(circle at 82% 40%, rgba(255, 255, 255, 0.15) 0%, rgba(255, 245, 235, 0.05) 100%);
        }

        .up-auth-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 2;
        }

        /* --- Left Side Hero Showcase --- */
        .up-hero-col {
            flex: 1;
            max-width: 780px;
            padding-right: 2rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            z-index: 2;
        }

        .up-hero-logo {
            height: 52px;
            width: auto;
            object-fit: contain;
            margin-bottom: 0.85rem;
            align-self: flex-start;
        }

        .up-hero-title {
            font-size: 2.45rem;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.025em;
            line-height: 1.18;
            margin: 0 0 0.85rem 0;
        }

        .up-hero-subtitle {
            font-size: 1.08rem;
            color: #4B5563;
            line-height: 1.55;
            max-width: 520px;
            margin: 0 0 1.85rem 0;
            font-weight: 500;
        }

        /* Feature Pills Layout */
        .up-pills-wrap {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .up-pills-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.55rem;
            align-items: center;
        }

        .up-feature-pill {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.95);
            border-radius: 14px;
            padding: 5px 11px 5px 7px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03), 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            user-select: none;
        }

        .up-feature-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
            background: #ffffff;
        }

        .up-pill-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .up-pill-text {
            display: flex;
            flex-direction: column;
            line-height: 1.18;
        }

        .up-pill-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: #1F2937;
        }

        .up-pill-sub {
            font-size: 0.67rem;
            color: #6B7280;
            font-weight: 500;
        }

        .up-pill-arrow {
            font-size: 0.60rem;
            color: #9CA3AF;
            margin-left: 2px;
        }

        /* Specific Pill Themes matching mockup */
        .pill-pos .up-pill-icon { background: #FFF0EB; color: #F05623; }
        .pill-pos { border-left: 2px solid #F05623; }

        .pill-inv .up-pill-icon { background: #F3EFFE; color: #7C3AED; }
        .pill-inv { border-left: 2px solid #7C3AED; }

        .pill-pur .up-pill-icon { background: #E6FBF2; color: #059669; }
        .pill-pur { border-left: 2px solid #059669; }

        .pill-sales .up-pill-icon { background: #EBF4FE; color: #2563EB; }
        .pill-sales { border-left: 2px solid #2563EB; }

        .pill-rep .up-pill-icon { background: #FEF7E6; color: #D97706; }
        .pill-rep { border-left: 2px solid #D97706; }

        .pill-crm .up-pill-icon { background: #FDF0F3; color: #DB2777; }
        .pill-crm { border-left: 2px solid #DB2777; }

        .pill-del .up-pill-icon { background: #E6FAF8; color: #0D9488; }
        .pill-del { border-left: 2px solid #0D9488; }

        .up-slogan-wrap {
            margin-top: 1.5rem;
            align-self: center;
            padding-left: 8rem;
        }

        .up-cursive-slogan {
            font-family: 'Caveat', cursive, serif;
            font-size: 1.7rem;
            font-weight: 700;
            color: #EA580C;
            letter-spacing: 0.02em;
            transform: rotate(-2deg);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .up-cursive-slogan .heart-icon {
            font-size: 1.3rem;
            line-height: 1;
        }

        /* --- Right Side Login Card --- */
        .up-card-col {
            width: 440px;
            max-width: 100%;
            flex-shrink: 0;
            z-index: 2;
        }

        .up-login-card {
            background: #FFFFFF;
            border-radius: 28px;
            padding: 2.5rem 2.4rem 2.2rem 2.4rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08), 0 4px 16px rgba(234, 88, 12, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.95);
            position: relative;
        }

        .up-card-logo-wrap {
            text-align: center;
            margin-bottom: 0.85rem;
        }

        .up-card-logo {
            height: 52px;
            width: auto;
            object-fit: contain;
        }

        .up-card-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: #111827;
            text-align: center;
            letter-spacing: -0.02em;
            margin: 0 0 0.25rem 0;
        }

        .up-card-sub {
            font-size: 0.92rem;
            color: #6B7280;
            text-align: center;
            margin: 0 0 1.85rem 0;
            font-weight: 500;
        }

        .up-form-group {
            margin-bottom: 1.25rem;
        }

        .up-form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 0.45rem;
        }

        .up-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .up-input-icon {
            position: absolute;
            left: 15px;
            color: #9CA3AF;
            font-size: 0.95rem;
            pointer-events: none;
            z-index: 3;
        }

        .up-form-input {
            width: 100%;
            height: 48px;
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 12px;
            padding: 0 42px 0 44px;
            font-size: 0.95rem;
            color: #111827;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .up-form-input:focus {
            border-color: #F97316;
            box-shadow: 0 0 0 3.5px rgba(249, 115, 22, 0.15);
        }

        .up-form-input.is-invalid {
            border-color: #EF4444;
            box-shadow: 0 0 0 3.5px rgba(239, 68, 68, 0.15);
        }

        .up-pw-toggle {
            position: absolute;
            right: 14px;
            color: #9CA3AF;
            font-size: 0.95rem;
            cursor: pointer;
            padding: 4px;
            z-index: 3;
            transition: color 0.15s ease;
        }

        .up-pw-toggle:hover {
            color: #4B5563;
        }

        .up-form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1.15rem 0 1.5rem 0;
            font-size: 0.86rem;
        }

        .up-remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4B5563;
            cursor: pointer;
            user-select: none;
            font-weight: 500;
            margin: 0;
        }

        .up-remember-checkbox {
            width: 17px;
            height: 17px;
            accent-color: #EA580C;
            cursor: pointer;
            border-radius: 4px;
        }

        .up-forgot-link {
            color: #EA580C;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.15s ease;
        }

        .up-forgot-link:hover {
            color: #C2410C;
            text-decoration: underline;
        }

        .up-submit-btn {
            width: 100%;
            height: 48px;
            background: linear-gradient(135deg, #FF6B00 0%, #EA580C 100%);
            border: none;
            border-radius: 12px;
            color: #FFFFFF;
            font-size: 1.02rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 6px 18px rgba(234, 88, 12, 0.32);
            transition: all 0.2s ease;
        }

        .up-submit-btn:hover {
            background: linear-gradient(135deg, #EA580C 0%, #C2410C 100%);
            box-shadow: 0 8px 24px rgba(234, 88, 12, 0.42);
            transform: translateY(-1px);
        }

        .up-submit-btn:active {
            transform: translateY(1px);
        }

        .up-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0 1.25rem 0;
            color: #9CA3AF;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .up-divider::before,
        .up-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #E5E7EB;
        }

        .up-divider span {
            padding: 0 12px;
        }

        .up-card-footer-msg {
            text-align: center;
            font-size: 0.82rem;
            color: #6B7280;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .up-invalid-msg {
            display: block;
            color: #DC2626;
            font-size: 0.8rem;
            margin-top: 0.35rem;
            font-weight: 500;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1120px) {
            .up-hero-col {
                display: none;
            }
            .up-auth-container {
                justify-content: center;
            }
            .up-card-col {
                margin: 0 auto;
            }
        }

        @media (max-width: 480px) {
            .up-auth-overlay {
                padding: 1.25rem 1rem;
            }
            .up-login-card {
                padding: 2rem 1.4rem;
                border-radius: 20px;
            }
        }
    </style>
@stop

@section('body')
<div class="up-auth-overlay">
    <div class="up-auth-container">

        {{-- Left Hero Section --}}
        <div class="up-hero-col">
            <img src="{{ asset('images/urbanpets-logo.png') }}?v=20261013_trans" alt="urban pets" class="up-hero-logo">
            
            <h1 class="up-hero-title">Smart retail system for pet business</h1>
            <p class="up-hero-subtitle">Everything you need to manage, grow and automate your pet business — in one powerful system.</p>

            {{-- Feature Badges Showcase --}}
            <div class="up-pills-wrap">
                {{-- Row 1 --}}
                <div class="up-pills-row">
                    <div class="up-feature-pill pill-pos">
                        <div class="up-pill-icon"><i class="fas fa-cash-register"></i></div>
                        <div class="up-pill-text">
                            <span class="up-pill-title">Point of Sale</span>
                            <span class="up-pill-sub">Fast & Flexible</span>
                        </div>
                        <i class="fas fa-chevron-right up-pill-arrow"></i>
                    </div>

                    <div class="up-feature-pill pill-inv">
                        <div class="up-pill-icon"><i class="fas fa-cubes"></i></div>
                        <div class="up-pill-text">
                            <span class="up-pill-title">Inventory</span>
                            <span class="up-pill-sub">Track with Ease</span>
                        </div>
                        <i class="fas fa-chevron-right up-pill-arrow"></i>
                    </div>

                    <div class="up-feature-pill pill-pur">
                        <div class="up-pill-icon"><i class="fas fa-shopping-cart"></i></div>
                        <div class="up-pill-text">
                            <span class="up-pill-title">Purchase</span>
                            <span class="up-pill-sub">Smarter Buying</span>
                        </div>
                        <i class="fas fa-chevron-right up-pill-arrow"></i>
                    </div>
                </div>

                {{-- Row 2 --}}
                <div class="up-pills-row">
                    <div class="up-feature-pill pill-sales">
                        <div class="up-pill-icon"><i class="fas fa-chart-bar"></i></div>
                        <div class="up-pill-text">
                            <span class="up-pill-title">Sales</span>
                            <span class="up-pill-sub">Boost Revenue</span>
                        </div>
                        <i class="fas fa-chevron-right up-pill-arrow"></i>
                    </div>

                    <div class="up-feature-pill pill-rep">
                        <div class="up-pill-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="up-pill-text">
                            <span class="up-pill-title">Reports</span>
                            <span class="up-pill-sub">Clear Insights</span>
                        </div>
                        <i class="fas fa-chevron-right up-pill-arrow"></i>
                    </div>

                    <div class="up-feature-pill pill-crm">
                        <div class="up-pill-icon"><i class="fas fa-users"></i></div>
                        <div class="up-pill-text">
                            <span class="up-pill-title">CRM</span>
                            <span class="up-pill-sub">Build Loyalty</span>
                        </div>
                        <i class="fas fa-chevron-right up-pill-arrow"></i>
                    </div>

                    <div class="up-feature-pill pill-del">
                        <div class="up-pill-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="up-pill-text">
                            <span class="up-pill-title">Delivery</span>
                            <span class="up-pill-sub">Faster Service</span>
                        </div>
                        <i class="fas fa-chevron-right up-pill-arrow"></i>
                    </div>
                </div>
            </div>

            {{-- Slogan above resting pets --}}
            <div class="up-slogan-wrap">
                <span class="up-cursive-slogan">
                    Happier Pets, Brighter Businesses <span class="heart-icon">♡</span>
                </span>
            </div>
        </div>

        {{-- Right Side Login Card --}}
        <div class="up-card-col">
            <div class="up-login-card">

                <div class="up-card-logo-wrap">
                    <img src="{{ asset('images/urbanpets-logo.png') }}?v=20261013_trans" alt="urban pets" class="up-card-logo">
                </div>

                <h2 class="up-card-title">Welcome Back</h2>
                <p class="up-card-sub">Log in to your Urban Pets account</p>

                <form method="POST" action="{{ route('login') }}" autocomplete="on">
                    @csrf

                    {{-- Email Input --}}
                    <div class="up-form-group">
                        <label class="up-form-label" for="email">Email Address</label>
                        <div class="up-input-wrapper">
                            <i class="far fa-envelope up-input-icon"></i>
                            <input id="email" 
                                   type="email" 
                                   class="up-form-input @error('email') is-invalid @enderror" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   placeholder="you@yourcompany.com" 
                                   required 
                                   autocomplete="email" 
                                   autofocus>
                        </div>
                        @error('email')
                            <span class="up-invalid-msg">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    {{-- Password Input --}}
                    <div class="up-form-group">
                        <label class="up-form-label" for="password">Password</label>
                        <div class="up-input-wrapper">
                            <i class="fas fa-lock up-input-icon"></i>
                            <input id="password" 
                                   type="password" 
                                   class="up-form-input @error('password') is-invalid @enderror" 
                                   name="password" 
                                   placeholder="Enter your password" 
                                   required 
                                   autocomplete="current-password">
                            <i class="far fa-eye up-pw-toggle" id="pw-toggle-btn" title="Toggle password visibility"></i>
                        </div>
                        @error('password')
                            <span class="up-invalid-msg">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    {{-- Remember Me & Forgot Password --}}
                    <div class="up-form-options">
                        <label class="up-remember-label" for="remember">
                            <input class="up-remember-checkbox" 
                                   type="checkbox" 
                                   name="remember" 
                                   id="remember" 
                                   {{ old('remember') ? 'checked' : '' }}>
                            <span>Remember me</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="up-forgot-link">
                                Forgot Password?
                            </a>
                        @endif
                    </div>

                    {{-- Sign In Button --}}
                    <button type="submit" class="up-submit-btn">
                        Sign In <i class="fas fa-arrow-right"></i>
                    </button>

                    {{-- Divider --}}
                    <div class="up-divider">
                        <span>OR</span>
                    </div>

                    {{-- Footer Message --}}
                    <div class="up-card-footer-msg">
                        <span style="font-size: 1rem;">🐾</span> Let's make pet business better, together.
                    </div>
                </form>

            </div>
        </div>

    </div>
</div>
@stop

@section('adminlte_js')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('pw-toggle-btn');
        const pwInput = document.getElementById('password');
        if (toggleBtn && pwInput) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = pwInput.getAttribute('type') === 'password';
                pwInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleBtn.classList.toggle('fa-eye', !isPassword);
                toggleBtn.classList.toggle('fa-eye-slash', isPassword);
            });
        }
    });
</script>
@stop
