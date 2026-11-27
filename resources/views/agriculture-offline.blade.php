<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline workspace · MAP | LGU Sulop</title>
    <link rel="stylesheet" href="{{ asset('agriculture.css') }}">
    <script src="{{ asset('agriculture.js') }}" defer></script>
</head>
<body data-user="{{ $user->id }}" data-sync="{{ route('sync.session') }}" data-offline-workspace>
<main class="content offline-workspace">
    <header class="banner">
        <div>
            <p class="kicker">LGU SULOP</p>
            <h1>Offline workspace</h1>
            <p>Save record drafts on this device. MAP syncs them once it can reach the server and confirm your sign-in.</p>
        </div>
        <a class="button light" href="{{ route('dashboard') }}">Open online dashboard</a>
    </header>
    <section class="panel" aria-labelledby="draft-heading">
        <div class="section-head">
            <h2 id="draft-heading">Record drafts</h2>
            <button class="sync-control" type="button" data-retry><span class="dot"></span><span data-sync-label>Checking connection</span></button>
        </div>
        <div data-sync-items></div>
    </section>
    <section class="panel" aria-labelledby="offline-forms">
        <h2 id="offline-forms">Add a record</h2>
        <div class="offline-form-list">
            @foreach ($forms as $key => $definition)
                <button type="button" class="secondary" data-open="draft-{{ $key }}">{{ $definition['label'] }}</button>
                <dialog id="draft-{{ $key }}" aria-labelledby="draft-title-{{ $key }}">
                    <h2 id="draft-title-{{ $key }}">{{ $definition['label'] }}</h2>
                    <form method="post" action="{{ route('records.store', $definition['sector']) }}" class="stack" data-record data-draft-form="{{ $key }}">
                        <input type="hidden" name="submission_id" value="">
                        @if ($key === 'tilapia')
                            @include('agriculture-tilapia-fields', ['entry' => null])
                        @else
                            @include('agriculture-fields', [
                                'entry' => null, 'sector' => $definition['sector'], 'livestockRegister' => $definition['register'] ?? null,
                                'livestockRegisters' => $modules['livestock'][4], 'crop' => $definition['crop'] ?? null,
                                'cropLabels' => $modules['high-value-crops'][4], 'year' => now('Asia/Manila')->year,
                                'plantingMonth' => null, 'monthlyAccomplished' => false, 'barangay' => '',
                            ])
                        @endif
                        <div class="dialog-actions"><button type="button" class="secondary" data-close>Cancel</button><button>Save draft</button></div>
                    </form>
                </dialog>
            @endforeach
        </div>
    </section>
</main>
<div class="toast" role="status" hidden></div>
</body>
</html>
