<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Installer — ICP CEFR Report</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #1F3A68; color: #111827; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .card { background: #fff; border-radius: 14px; max-width: 560px; width: 100%; padding: 28px; box-shadow: 0 20px 40px rgba(0,0,0,.25); border-top: 6px solid #E3A82B; }
        h1 { margin: 0 0 4px; font-size: 22px; color: #1F3A68; }
        p { color: #4B5563; line-height: 1.5; }
        ul { list-style: none; padding: 0; margin: 16px 0; }
        li { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #E2E8F0; font-size: 14px; }
        .ok { color: #1E7B43; font-weight: 600; } .no { color: #B42318; font-weight: 600; }
        button { background: #1F3A68; color: #fff; border: 0; border-radius: 10px; padding: 12px 18px; font-size: 15px; font-weight: 600; cursor: pointer; width: 100%; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 16px; margin: 6px 0 14px; letter-spacing: .4em; text-align: center; }
        pre { background: #F3F4F6; border-radius: 8px; padding: 12px; font-size: 12px; max-height: 240px; overflow: auto; white-space: pre-wrap; }
        .alert { border-radius: 10px; padding: 12px; margin: 12px 0; font-size: 14px; }
        .alert.err { background: #FDECEA; color: #B42318; } .alert.done { background: #E7F6EC; color: #1E7B43; }
        a { color: #1F3A68; font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <h1>ICP CEFR Report — Installer</h1>
    <p>{{ $installed ? 'Aplikasi sudah terpasang. Halaman ini hanya untuk memperbarui struktur database setelah mengunggah versi baru.' : 'Memasang tabel database dan data awal (level CEFR & deskriptor). Jalankan sekali saja.' }}</p>

    <ul>
        @foreach ($checks as $label => $ok)
            <li><span>{{ $label }}</span><span class="{{ $ok ? 'ok' : 'no' }}">{{ $ok ? '✓ OK' : '✗ Belum' }}</span></li>
        @endforeach
    </ul>

    @if ($error)<div class="alert err">{{ $error }}</div>@endif
    @if (! empty($done))<div class="alert done">{{ $done }} <a href="{{ url('/') }}">Buka aplikasi →</a></div>@endif
    @if ($output)<pre>{{ $output }}</pre>@endif

    @php $ready = ! in_array(false, $checks, true); @endphp
    @if (! $installed)
        <form method="post" action="{{ url('/install') }}">
            <input type="hidden" name="token" value="{{ $token }}">
            <button type="submit" @disabled(! $ready)>Pasang sekarang</button>
        </form>
    @elseif (empty($done))
        <form method="post" action="{{ url('/install/migrate') }}">
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="pin">PIN sekolah</label>
            <input id="pin" name="pin" type="password" inputmode="numeric" maxlength="4" autocomplete="off" required>
            <button type="submit">Perbarui database</button>
        </form>
    @endif
    <p style="font-size:12px;margin-top:16px">Setelah selesai, kosongkan <code>INSTALL_TOKEN</code> di file <code>.env</code> agar halaman ini tidak dapat dibuka.</p>
</div>
</body>
</html>
