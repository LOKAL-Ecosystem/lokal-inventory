<!DOCTYPE html>
<html lang="id" data-theme="lokal">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Lokal Stock</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-200 min-h-screen flex items-center justify-center p-4">
    <main class="w-full max-w-sm">
        <div class="flex flex-col items-center mb-6">
            <div class="w-14 h-14 rounded-2xl bg-orange-500 text-white flex items-center justify-center text-2xl font-extrabold shadow-md">L</div>
            <h1 class="mt-3 text-xl font-extrabold text-neutral">Lokal Stock</h1>
            <p class="text-xs text-base-content/60 font-medium">Masuk untuk mengelola stok bahan baku</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="card-premium p-6 space-y-4">
            @csrf

            <x-field label="Email" name="email">
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="input w-full min-h-11 text-sm rounded-xl" />
            </x-field>

            <x-field label="Kata sandi" name="password">
                <input id="password" type="password" name="password" required autocomplete="current-password"
                    class="input w-full min-h-11 text-sm rounded-xl" />
            </x-field>

            <label class="flex items-center gap-2 text-xs font-semibold text-base-content/70 min-h-11">
                <input type="checkbox" name="remember" value="1" class="checkbox checkbox-sm checkbox-primary" />
                Ingat saya
            </label>

            <button type="submit" class="btn btn-primary w-full min-h-11">Masuk</button>
        </form>
    </main>
</body>

</html>
