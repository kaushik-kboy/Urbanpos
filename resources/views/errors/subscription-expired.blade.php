<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expired — UrbanPOS by Chandak Infotech</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full text-center">
        <!-- Brand Icon -->
        <div class="inline-flex w-20 h-20 rounded-3xl bg-rose-500/10 border border-rose-500/20 text-rose-500 items-center justify-center text-3xl mb-6 shadow-2xl shadow-rose-500/10">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>

        <!-- Main Card -->
        <div class="bg-slate-800/90 backdrop-blur-md rounded-3xl p-8 border border-slate-700/80 shadow-2xl text-left space-y-6">
            <div>
                <div class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-500/10 text-rose-400 border border-rose-500/30">
                    <i class="fa-solid fa-circle-exclamation mr-1.5"></i> Subscription Expired
                </div>
                <h1 class="text-xl font-extrabold text-white mt-2">Renewal Required</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Your UrbanPOS software license has reached its renewal date.
                </p>
            </div>

            <!-- Data Safety Guarantee Banner -->
            <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-700 text-xs text-slate-300 flex items-start space-x-3">
                <i class="fa-solid fa-shield-halved text-emerald-400 text-base mt-0.5"></i>
                <div>
                    <span class="font-bold text-white">Your Data is 100% Safe:</span>
                    <p class="text-[11px] text-slate-400 mt-0.5">All products, customer records, and billing data are completely secure. Access will resume instantly upon renewal.</p>
                </div>
            </div>

            <!-- Support & Renewal Helpline Box -->
            <div class="p-4 rounded-xl bg-indigo-950/40 border border-indigo-500/30 space-y-2.5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-300">
                    <i class="fa-solid fa-headset mr-1"></i> Chandak Infotech Renewal Desk
                </p>
                <div class="space-y-1.5 text-xs text-slate-200">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Helpline:</span>
                        <a href="tel:+919876543210" class="font-bold text-white hover:text-indigo-300">
                            <i class="fa-solid fa-phone mr-1 text-emerald-400"></i> +91-9876543210
                        </a>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Email:</span>
                        <a href="mailto:support@chandakinfotech.com" class="font-bold text-white hover:text-indigo-300">
                            support@chandakinfotech.com
                        </a>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-2.5 pt-2">
                <!-- Check Again / Refresh License Button -->
                <a href="{{ url()->current() }}?refresh_license=1" class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition flex items-center justify-center space-x-2 shadow-lg shadow-indigo-600/30">
                    <i class="fa-solid fa-rotate mr-1"></i>
                    <span>Check Again / I Have Renewed</span>
                </a>

                <!-- Sign Out / Switch User -->
                @if(auth()->check())
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="w-full py-2.5 px-4 bg-slate-700/60 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl transition flex items-center justify-center space-x-1.5">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>Sign Out of Account</span>
                    </button>
                </form>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <p class="text-[11px] text-slate-500 mt-6">
            UrbanPOS Powered by <span class="text-slate-400 font-semibold">Chandak Infotech</span> • All Rights Reserved
        </p>
    </div>

</body>
</html>
