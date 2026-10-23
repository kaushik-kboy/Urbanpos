@extends('adminlte::page')

@section('title', 'POS Settings')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold"><i class="fas fa-cash-register mr-2 text-primary"></i> POS Screen Settings</h1>
            <p class="text-muted small mb-0">Configure global POS behavior, cashier lock option, and terminal controls.</p>
        </div>
        <a href="{{ route('pos.terminal') }}" class="btn btn-primary font-weight-bold shadow-sm" target="_blank">
            <i class="fas fa-external-link-alt mr-1"></i> Open POS Terminal
        </a>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8 col-md-10">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-lock mr-2 text-warning"></i> POS Terminal Lock Configuration</h3>
                </div>
                <form action="{{ route('tools.pos-settings.update') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="p-3 mb-4 rounded border {{ $posLockEnabled ? 'bg-light' : 'bg-white' }}">
                            <div class="custom-control custom-switch custom-switch-lg mb-2">
                                <input type="checkbox" class="custom-control-input" id="pos_lock_enabled" name="pos_lock_enabled" value="1" {{ $posLockEnabled ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-bold h5 text-dark" for="pos_lock_enabled" style="cursor: pointer;">
                                    Enable POS Screen Lock Option
                                </label>
                            </div>
                            <p class="text-muted mb-0 pl-1 small">
                                <strong>Admin Control:</strong> Agar aap ise <strong>ON</strong> karenge, to sabhi POS terminals par Lock Screen button, 4-digit PIN lock aur inactivity auto-lock active rahega. 
                                Agar aap ise <strong>OFF</strong> karenge, to sabhi POS terminals se lock option turant hat jayega aur screens kabhi lock nahi hongi.
                            </p>
                        </div>

                        <div class="alert alert-info py-2 px-3 small border-0 shadow-none mb-0">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Real-time Synchronized:</strong> Turning this setting OFF removes the "Lock (Ctrl+L)" button, cancels idle timers, and prevents cashiers from locking POS screens across all branch terminals.
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Status: <strong class="{{ $posLockEnabled ? 'text-success' : 'text-danger' }}">{{ $posLockEnabled ? 'Currently ENABLED' : 'Currently DISABLED' }}</strong></span>
                        <button type="submit" class="btn btn-success px-4 font-weight-bold shadow-sm">
                            <i class="fas fa-save mr-1"></i> Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
