<!DOCTYPE html>
<html lang="id" data-theme="lokal">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
    <title>Masuk - {{ 'Lokal Stock' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-base-100">
    <div id="flash-data" hidden data-error="{{ $errors->first() }}"></div>

    <main class="min-h-screen grid lg:grid-cols-2">
        {{-- Panel brand (disembunyikan di layar kecil) --}}
        <aside class="hidden lg:flex relative overflow-hidden flex-col justify-between p-12 text-white bg-linear-to-br from-[#1a57c8] via-primary to-[#6ea8ff]">
            <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-32 -left-16 w-md h-112 rounded-full bg-black/10 blur-3xl"></div>
            <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 22px 22px;"></div>

            <div class="relative flex items-center gap-3">
                <span class="w-11 h-11 rounded-xl bg-white/95 flex items-center justify-center shadow-lg">
                    <img src="{{ asset('logo.svg') }}" alt="" class="w-7 h-7">
                </span>
                <span class="text-xl font-extrabold tracking-widest">LOKAL</span>
            </div>

            <div class="relative max-w-md">
                <h2 class="text-4xl xl:text-5xl font-extrabold leading-tight">Stok bahan terkendali, dari belanja sampai HPP.</h2>
                <p class="mt-4 text-white/80 text-base">Pantau sisa bahan, potong stok otomatis dari penjualan, dan lihat margin tiap menu dalam satu tempat.</p>

                <ul class="mt-8 space-y-3 text-sm">
                    <li class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-white/15 backdrop-blur flex items-center justify-center"><i data-lucide="package-search" class="w-4 h-4"></i></span>
                        Stok terpotong otomatis sesuai resep
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-white/15 backdrop-blur flex items-center justify-center"><i data-lucide="clipboard-check" class="w-4 h-4"></i></span>
                        Stok masuk, waste, dan opname rapi
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-white/15 backdrop-blur flex items-center justify-center"><i data-lucide="bar-chart-3" class="w-4 h-4"></i></span>
                        HPP, margin, dan laporan bahan
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-white/60">&copy; {{ date('Y') }} {{ 'Lokal Stock' }}</p>
        </aside>

        {{-- Form --}}
        <section class="relative flex items-center justify-center p-6 sm:p-10 bg-base-200 lg:bg-base-100">
            <div class="absolute w-80 h-80 rounded-full bg-primary/10 blur-3xl -top-16 -right-16 lg:hidden"></div>

            <div class="relative w-full max-w-md bg-base-100 lg:bg-transparent rounded-2xl shadow-xl lg:shadow-none border border-base-300/60 lg:border-0 p-7 sm:p-9 lg:p-0">
                <div class="lg:hidden flex flex-col items-center mb-6">
                    <img src="{{ asset('logo.svg') }}" alt="Lokal" class="w-16 h-16 mb-2">
                    <h1 class="text-2xl font-extrabold tracking-widest">LOKAL</h1>
                </div>

                <div class="mb-8 text-center lg:text-left">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-base-content">Selamat datang kembali</h2>
                    <p class="mt-1.5 text-sm text-base-content/60">Masuk untuk melanjutkan ke {{ 'Lokal Stock' }}.</p>
                </div>

                @if(session('error'))
                    <p class="mb-5 text-sm font-semibold text-error bg-red-50 border border-red-200/60 rounded-xl px-4 py-3" role="alert">{{ session('error') }}</p>
                @endif

                @if($ssoUrl)
                    <a href="{{ $ssoUrl }}" class="btn btn-primary w-full h-12 rounded-xl text-base font-bold gap-2 shadow-lg shadow-primary/25 hover:shadow-primary/40 transition-shadow">
                        <i data-lucide="log-in" class="w-5 h-5" aria-hidden="true"></i>
                        Masuk lewat Lokal POS
                    </a>
                    <p class="mt-3 text-xs text-base-content/60 text-center lg:text-left">Pakai akun Lokal POS Anda; tidak perlu kata sandi lain.</p>
                @endif

                @if($ssoUrl && $passwordLogin)
                    <div class="flex items-center gap-3 my-6 text-[10px] font-extrabold uppercase tracking-widest text-base-content/50">
                        <span class="flex-1 border-t border-base-300"></span> Pengembangan lokal <span class="flex-1 border-t border-base-300"></span>
                    </div>
                @endif

                @if($passwordLogin)
                <form action="{{ route('login') }}" method="POST" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-semibold text-base-content/80 mb-1.5" for="email">Email</label>
                        <div class="relative">
                            <i data-lucide="mail" class="w-5 h-5 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none z-10"></i>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nama@lokal.com" autocomplete="username"
                                   class="input w-full h-12 pl-11 rounded-xl focus:outline-none" required autofocus>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-base-content/80 mb-1.5" for="password">Password</label>
                        <div class="relative">
                            <i data-lucide="lock" class="w-5 h-5 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none z-10"></i>
                            <input id="password" type="password" name="password" placeholder="Masukkan password" autocomplete="current-password"
                                   class="input w-full h-12 pl-11 pr-12 rounded-xl focus:outline-none" required>
                            <button type="button" id="toggle-password" aria-label="Tampilkan password" aria-pressed="false"
                                    class="absolute right-1 top-1/2 -translate-y-1/2 w-11 h-11 flex items-center justify-center rounded-lg text-base-content/50 hover:text-base-content z-10">
                                <i data-lucide="eye" class="w-5 h-5" data-eye></i>
                                <i data-lucide="eye-off" class="w-5 h-5 hidden" data-eye-off></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 cursor-pointer min-h-11">
                            <input type="checkbox" name="remember" value="1" class="checkbox checkbox-primary checkbox-sm rounded">
                            <span class="text-base-content/70">Ingat saya</span>
                        </label>
                        <button type="button" class="text-primary font-semibold hover:underline min-h-11"
                                onclick="Swal.fire({ icon: 'info', title: 'Bantuan', text: 'Hubungi Administrator / Owner untuk mereset password atau membuat akun baru.' })">Lupa password?</button>
                    </div>

                    <button type="submit" class="btn btn-primary w-full h-12 rounded-xl text-base font-bold gap-2 shadow-lg shadow-primary/25 hover:shadow-primary/40 transition-shadow">
                        <i data-lucide="log-in" class="w-5 h-5"></i>
                        Masuk
                    </button>
                </form>
                @elseif(! $ssoUrl)
                    <p class="text-sm text-base-content/60" role="status">Masuk lewat menu <strong>Stok</strong> di Lokal POS.</p>
                @endif

                <p class="mt-8 flex items-center justify-center lg:justify-start gap-1.5 text-xs text-base-content/50">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    Sesi Anda dilindungi dan khusus untuk outlet Anda.
                </p>
            </div>
        </section>
    </main>

    <script>
        (function () {
            var btn = document.getElementById('toggle-password');
            var input = document.getElementById('password');
            if (!btn || !input) return;
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                btn.querySelector('[data-eye]').classList.toggle('hidden', show);
                btn.querySelector('[data-eye-off]').classList.toggle('hidden', !show);
            });
        })();
    </script>
</body>
</html>
