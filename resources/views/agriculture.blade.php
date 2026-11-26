@php
    $modules = config('agriculture');
    $user = auth()->user();
    $isHead = $user->sector === 'head';
    $report = request()->routeIs('reports');
    $access = request()->routeIs('access');
    $directory = request()->routeIs('farmers.directory');
    $tilapiaRegister = $tilapiaRegister ?? false;
    $livestockRegister = $livestockRegister ?? null;
    $livestockRegisters = $modules['livestock'][4];
    $crop = $crop ?? null;
    $monthlyAccomplished = $monthlyAccomplished ?? false;
    $cropLabels = $modules['high-value-crops'][4];
    $title = $livestockRegisters[$livestockRegister][0] ?? ($tilapiaRegister ? 'Tilapia Dispersal' : (isset($sector) ? $modules[$sector][0] : match (true) {
        $report => 'Statistical Reports',
        $access => 'Access Requests',
        $directory => 'Farmers Masterlist',
        default => 'Office Overview',
    }));
    $period = isset($year) ? ['year' => $year, 'quarter' => $quarter] : [];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · MAP | LGU Sulop</title>
    <link rel="stylesheet" href="{{ asset('agriculture.css') }}">
    <script src="{{ asset('agriculture.js') }}" defer></script>
    <script src="{{ asset('auth-ui.js') }}" defer></script>
</head>
<body data-user="{{ $user->id }}" data-sync="{{ route('sync.session') }}">
<div class="shell">
    <aside class="sidebar" id="workspace-sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="map-logo" aria-hidden="true"></span><span>MAP<small>Management of Agriculture Production</small></span></a>
        <nav aria-label="Main navigation">
            <span class="group-label">WORKSPACE</span>
            @if ($isHead)<a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><span aria-hidden="true">▦</span> Office Overview</a>@endif
            <a @class(['active' => $report]) href="{{ route('reports') }}"><span aria-hidden="true">▤</span> Statistical Reports</a>
            <a @class(['active' => $access]) href="{{ route('access') }}"><span aria-hidden="true">◈</span> Access Requests</a>
            <a @class(['active' => $directory]) href="{{ route('farmers.directory') }}"><span aria-hidden="true">👥</span> Farmers Masterlist</a>
            @if (count($visible)) <span class="group-label">SECTORS</span> @endif
            @foreach ($visible as $key => [$label, $icon])
                @continue($key === 'farmers')
                @continue($key === 'vaccine' && isset($visible['livestock']))
                @continue($key === 'fertilizer' && (isset($visible['rice']) || isset($visible['corn'])))
                @if ($key === 'vaccine')
                    <span class="group-label">Poultry &amp; Livestock</span>
                @endif
                <a @class(['subnav' => $key === 'vaccine', 'active' => ($sector ?? null) === $key && !$tilapiaRegister && $livestockRegister === null && $crop === null && !$monthlyAccomplished]) href="{{ route('sector', $key) }}"><span aria-hidden="true">{{ $icon }}</span>{{ $label }}</a>
                @if ($key === 'fisheries')
                    <a @class(['subnav', 'active' => $tilapiaRegister]) href="{{ route('sector', ['sector' => 'fisheries', 'register' => 'tilapia']) }}">Tilapia Dispersal</a>
                @elseif ($key === 'corn')
                    <a class="subnav" href="{{ route('sector', $key) }}#monthly-planting-accomplishment">Monthly Planting Accomplishment</a>
                @elseif ($key === 'livestock')
                    @foreach ($livestockRegisters as $registerKey => [$registerLabel])
                        <a @class(['subnav', 'active' => $livestockRegister === $registerKey]) href="{{ route('sector', ['sector' => $key, 'register' => $registerKey]) }}">{{ $registerLabel }}</a>
                    @endforeach
                    @if (isset($visible['vaccine']))
                        <a @class(['subnav', 'active' => ($sector ?? null) === 'vaccine']) href="{{ route('sector', 'vaccine') }}">Pet Vaccination</a>
                    @endif
                @elseif ($key === 'high-value-crops')
                    @foreach ($cropLabels as $cropKey => $cropLabel)
                        <a @class(['subnav', 'active' => $crop === $cropKey && !$monthlyAccomplished]) href="{{ route('sector', ['sector' => $key, 'crop' => $cropKey]) }}">{{ $cropLabel }} Production Report</a>
                    @endforeach
                    <a @class(['subnav', 'active' => $monthlyAccomplished]) href="{{ route('sector', ['sector' => $key, 'report' => 'monthly-accomplished']) }}">Monthly Accomplished Report</a>
                @endif
                @if (in_array($key, ['rice', 'corn'], true) && isset($visible['fertilizer']))
                    <a @class(['subnav', 'active' => ($sector ?? null) === 'fertilizer' && request()->query('from') === $key]) href="{{ route('sector', ['sector' => 'fertilizer', 'from' => $key]) }}">Fertilizer / Abuno</a>
                @endif
            @endforeach
        </nav>
        <div class="account"><span class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><div><strong>{{ $user->name }}</strong><small>{{ $isHead ? 'Agriculture Head' : ($modules[$user->sector][0] ?? 'Employee') }}</small></div></div>
        <form class="logout" method="post" action="{{ route('logout') }}" data-auth-form>@csrf <button class="quiet">Sign out</button></form>
    </aside>
    <main>
        <header class="topbar">
            <div class="topbar-title">
                <button type="button" class="secondary sidebar-toggle" aria-label="Toggle sidebar" aria-controls="workspace-sidebar" aria-expanded="true" hidden>
                    <span aria-hidden="true">☰</span>
                </button>
                <span>Agriculture Office <span class="muted">/</span> {{ $title }}</span>
            </div>
            <button type="button" class="sync-control" data-open="sync-dialog"><span class="dot"></span><span data-sync-label>Online</span></button>
        </header>
        <div class="content">
            @if (session('notice'))<div class="notice" role="status">{{ session('notice') }}</div>@endif
            @if ($errors->any())<div class="notice error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <section @class(['banner', 'banner-overview' => request()->routeIs('dashboard') && $isHead])>
                <div><p class="kicker"><span class="map-logo" aria-hidden="true"></span>{{ isset($sector) ? 'SECTOR WORKSPACE' : 'LGU SULOP · MUNICIPAL AGRICULTURE OFFICE' }}</p>
                <h1>{{ $title }}</h1>
                <p>{{ isset($sector) ? $modules[$sector][2] : match (true) {
                    $report => 'Generate quarterly and annual summaries from recorded data.',
                    $access => 'Manage access to agricultural sector records.',
                    $directory => 'Find farmers and view service history from the sectors you can access.',
                    default => 'Monitor your office’s agricultural activities in one workspace.',
                } }}</p></div>
                @if ($canWrite ?? false)<button type="button" class="light" data-open="new-record">+ New record</button>
                @elseif (!$report && !$access && !$directory && !isset($sector))<a class="button light" href="{{ route('reports', $period) }}">View reports</a>@endif
            </section>

            @unless ($access || $directory || $tilapiaRegister || $livestockRegister !== null || $crop !== null || $monthlyAccomplished)
                <form class="filters" method="get">
                    @if (($sector ?? null) === 'fertilizer' && in_array(request()->query('from'), ['rice', 'corn'], true))
                        <input type="hidden" name="from" value="{{ request()->query('from') }}">
                    @endif
                    @if (($sector ?? null) === 'corn')
                        <input type="hidden" name="month" value="{{ $plantingMonth->format('Y-m') }}">
                        @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                    @endif
                    <label>Year <input type="number" name="year" min="1900" max="2200" value="{{ $year }}" required></label>
                    <label>Reporting period <select name="quarter"><option value="0">Annual</option>@foreach (range(1, 4) as $q)<option value="{{ $q }}" @selected($quarter === $q)>Quarter {{ $q }}</option>@endforeach</select></label>
                    @if ($report)
                        <label>Sector <select name="sector"><option value="">All accessible sectors</option>@foreach ($visible as $key => [$label])<option value="{{ $key }}" @selected($selected === $key)>{{ $label }}</option>@endforeach</select></label>
                    @endif
                    <button class="secondary">Apply</button>
                    @if ($report && count($summary))
                        @php
                            $export = $period + ['sector' => $selected];
                        @endphp
                        <a class="button secondary" href="{{ route('reports', $export + ['format' => 'pdf']) }}">Download PDF</a>
                        <a class="button secondary" href="{{ route('reports', $export + ['format' => 'docx']) }}">Download DOCX</a>
                    @endif
                </form>
                @if ($report)<h2>{{ $reportTitle }}</h2>@endif
                @if (isset($sector))
                    <div class="metric-grid">
                    @foreach ($summary[$sector] as $label => $value)
                        <article class="panel metric"><span>{{ $label }}</span><strong>{{ is_numeric($value) ? number_format($value, fmod((float) $value, 1) === 0.0 ? 0 : 2) : $value }}</strong></article>
                    @endforeach
                    </div>
                @else
                    <div class="cards">
                    @forelse ($summary as $key => $metrics)
                        <article class="panel sector-card">
                            <span class="tile-icon" aria-hidden="true">{{ $modules[$key][1] }}</span>
                            <h2><a href="{{ route('sector', ['sector' => $key] + $period) }}">{{ $modules[$key][0] }}</a></h2>
                            <dl>@foreach ($metrics as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
                        </article>
                    @empty <div class="panel">No sectors are available for your account. Use Access Requests to ask for access.</div>
                    @endforelse
                    </div>
                @endif

                @if (isset($summary['rice']))
                    @php
                        $currentRice = $summary['rice']['Harvest (kg)'];
                    @endphp
                    <section class="panel trend">
                        <h2>Rice harvest comparison</h2>
                        @if ($previous !== null && $summary['rice']['Records'] > 0)
                            @php
                                $change = round($currentRice - $previous, 2);
                            @endphp
                            <p>{{ $change > 0 ? 'Increased' : ($change < 0 ? 'Decreased' : 'No change') }}{{ $change != 0 ? ' by '.number_format(abs($change), 2).' kg' : '' }} compared with the previous {{ $quarter ? 'quarter' : 'year' }}.</p>
                            @foreach (['Current period' => $currentRice, 'Previous period' => $previous] as $label => $amount)
                                <div class="bar-row"><span>{{ $label }}</span><meter min="0" max="{{ max(1, $currentRice, $previous) }}" value="{{ $amount }}">{{ $amount }}</meter><strong>{{ number_format($amount, 2) }} kg</strong></div>
                            @endforeach
                        @else <p>Record harvests in both periods to see the change.</p>@endif
                    </section>
                @endif
            @endunless

            @if ($directory)
                @include('agriculture-farmer-directory')
            @endif

            @if (isset($sector))
                @if ($sector === 'rice' && $canWrite)
                    <details class="panel import">
                        <summary>Import rice harvest CSV</summary>
                        <p>Use this header and supply harvest dates as YYYY-MM-DD and quantities in kilograms. Reusing a source ID updates that harvest.</p>
                        <code>source_id,record_date,name,area,hectares,quantity</code>
                        <form class="filters" method="post" enctype="multipart/form-data" action="{{ route('rice.import') }}">
                            @csrf <label>CSV file <input name="csv" type="file" accept=".csv,text/csv" required></label><button>Import records</button>
                        </form>
                    </details>
                @endif
                <section class="panel data" @if ($sector === 'corn') id="monthly-planting-accomplishment" aria-labelledby="monthly-planting-title" @endif>
                    <div class="section-head">
                    @if ($sector === 'corn')
                        <div><h2 id="monthly-planting-title">Monthly Planting Accomplishment</h2><p>Sulop, Davao del Sur · {{ $plantingMonth->format('F Y') }} · Area planted in hectares</p></div>
                    @elseif ($livestockRegister !== null)
                        <div><h2>{{ $livestockRegister === 'vaccination' ? 'Vaccination Report' : $title }}</h2><p>Municipal Agriculture Office · Sulop, Davao del Sur · {{ $livestockRegister === 'vaccination' ? $plantingMonth->format('F Y') : 'CY '.$year }}</p></div>
                    @elseif ($tilapiaRegister)
                        <div><h2>Tilapia Fingerlings Dispersal and Sales</h2><p>Provincial Tilapia Nursery and Hatchery · Bansalan, Davao del Sur</p></div>
                    @elseif ($monthlyAccomplished)
                        <div><h2>Monthly Accomplished Report</h2><p>Municipality of Sulop, Davao del Sur · {{ $plantingMonth->format('F Y') }} · {{ $barangay ?: 'All barangays' }}</p></div>
                    @elseif ($crop !== null)
                        <div><h2>{{ $cropLabels[$crop] }} Production Report</h2><p>{{ $plantingMonth->format('F Y') }} · {{ $barangay ?: 'All barangays' }}</p></div>
                    @else
                        <h2>{{ $modules[$sector][0] }} Records</h2>
                    @endif
                    <form class="filters" method="get">
                        @if ($tilapiaRegister)<input type="hidden" name="register" value="tilapia">@endif
                        @if ($sector === 'farmers')<input type="hidden" name="profiles" value="1">@endif
                        @if ($livestockRegister !== null)
                            <input type="hidden" name="register" value="{{ $livestockRegister }}">
                            @if ($livestockRegister === 'vaccination')
                                <label>Month <input type="month" name="month" value="{{ $plantingMonth->format('Y-m') }}" required></label>
                            @else
                                <label>Calendar year <input type="number" name="year" min="1900" max="2200" value="{{ $year }}" required></label>
                            @endif
                        @else
                            <input type="hidden" name="year" value="{{ $year }}">
                        @endif
                        @if ($crop !== null || $monthlyAccomplished)
                            @if ($monthlyAccomplished)<input type="hidden" name="report" value="monthly-accomplished">@endif
                            @if ($crop !== null)<input type="hidden" name="crop" value="{{ $crop }}">@endif
                            <label>Month <input type="month" name="month" value="{{ $plantingMonth->format('Y-m') }}" required></label>
                            <label>Barangay <input name="barangay" value="{{ $barangay }}" maxlength="100" placeholder="All barangays"></label>
                        @endif
                        <input type="hidden" name="quarter" value="{{ $quarter }}">
                        @if ($sector === 'corn')
                            <label>Planting month <input type="month" name="month" value="{{ $plantingMonth->format('Y-m') }}" required></label>
                        @endif
                        <label class="sr-only" for="search">Search records</label><input id="search" name="q" value="{{ $search }}" placeholder="Search name or area">
                        @if ($sector === 'fertilizer')
                            @if (in_array(request()->query('from'), ['rice', 'corn'], true))
                                <input type="hidden" name="from" value="{{ request()->query('from') }}">
                            @endif
                            <label class="sr-only" for="status-filter">Collection status</label><select id="status-filter" name="status"><option value="">All statuses</option>@foreach (['Collected', 'Pending'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
                        @endif
                        <button class="secondary">Search</button>
                    </form></div>
                    @if ($sector === 'corn')
                        @include('agriculture-corn')
                    @elseif ($tilapiaRegister)
                        @include('agriculture-tilapia')
                    @else
                    @php
                        $recordColumns = $modules[$sector][3];
                        if ($livestockRegister !== null) {
                            $recordColumns = $livestockRegisters[$livestockRegister][1];
                            if (!$canWrite) {
                                unset($recordColumns['sex'], $recordColumns['birth_date'], $recordColumns['contact'], $recordColumns['signature']);
                                if ($livestockRegister === 'vaccination') {
                                    unset($recordColumns['address'], $recordColumns['purok'], $recordColumns['reason'], $recordColumns['remarks']);
                                }
                            }
                        }
                        if ($sector === 'farmers') {
                            unset($recordColumns['contact'], $recordColumns['area'], $recordColumns['assets']);
                        }
                        if ($sector === 'high-value-crops') {
                            $expenses = $recordColumns['expenses'];
                            unset($recordColumns['expenses'], $recordColumns['remarks']);
                            $recordColumns += ['gross_income' => ['Gross income (PHP)'], 'expenses' => $expenses, 'net_income' => ['Net income (PHP)']];
                            if ($crop !== null) {
                                unset($recordColumns['crop']);
                                $recordColumns['commodity'][0] = $modules['high-value-crops'][5][$crop];
                                if ($crop !== 'banana') {
                                    unset($recordColumns['expansion']);
                                }
                            }
                        }
                        if ($monthlyAccomplished) {
                            $recordColumns = [
                                'name' => ['Name'],
                                'hectares' => ['Area (ha)'],
                                'commodity' => ['Commodity'],
                                'quantity' => ['Total production (kg)'],
                                'price' => ['Price per kilo (PHP)'],
                                'remarks' => ['Remarks'],
                            ] + ($barangay === '' ? ['area' => ['Barangay']] : []);
                        }
                    @endphp
                    <div class="table-wrap" tabindex="0" role="region" aria-label="{{ $title }} records"><table>
                        <thead><tr>@unless ($monthlyAccomplished)<th>{{ $poultryTotals !== null ? 'No.' : 'Date' }}</th>@endunless @foreach ($recordColumns as [$label])<th>{{ $label }}</th>@endforeach @if ($canWrite)<th>Action</th>@endif</tr></thead>
                        <tbody>
                        @forelse ($records as $entry)
                            @php
                                $values = json_decode($entry->details, true);
                            @endphp
                            <tr>@unless ($monthlyAccomplished)<td>{{ $poultryTotals !== null ? $records->firstItem() + $loop->index : $entry->record_date }}</td>@endunless
                            @foreach ($recordColumns as $key => [$label])
                                <td>{{ match (true) {
                                    $sector === 'high-value-crops' && $key === 'crop' => $cropLabels[$values[$key]] ?? '—',
                                    $sector === 'high-value-crops' && in_array($key, ['price', 'expenses', 'gross_income', 'net_income'], true) => isset($values[$key]) ? number_format($values[$key], 2) : '—',
                                    default => $values[$key] ?? '—',
                                } }}</td>
                            @endforeach
                            @if ($canWrite)<td><button class="quiet" data-open="record-{{ $entry->id }}">Edit</button></td>@endif</tr>
                        @empty <tr><td colspan="{{ count($recordColumns) + (int) $canWrite + (int) !$monthlyAccomplished }}" class="empty">No records found.</td></tr>
                        @endforelse
                        </tbody>
                        @if ($poultryTotals !== null)
                            <tfoot><tr><th scope="row" colspan="5">Total</th><td>{{ number_format($poultryTotals[0]) }}</td><td>{{ number_format($poultryTotals[1]) }}</td><td></td>@if ($canWrite)<td></td>@endif</tr></tfoot>
                        @endif
                    </table></div>
                    @endif
                    @if ($records->hasPages())<div class="pagination">{{ $records->links('pagination::simple-bootstrap-4') }}</div>@endif
                </section>
                @if ($canWrite)
                    <dialog id="new-record">
                        <h2>{{ $crop !== null && !$monthlyAccomplished ? 'New '.$cropLabels[$crop].' production record' : 'New '.strtolower($modules[$sector][0]).' record' }}</h2>
                        <form method="post" action="{{ route('records.store', $sector) }}" data-record class="stack">
                            @csrf <input type="hidden" name="submission_id" value="{{ Illuminate\Support\Str::uuid() }}">
                            @include($tilapiaRegister ? 'agriculture-tilapia-fields' : 'agriculture-fields', ['entry' => null])
                            <div class="dialog-actions"><button type="button" class="secondary" data-close>Cancel</button><button>Save record</button></div>
                        </form>
                    </dialog>
                    @foreach ($records as $entry)
                        <dialog id="record-{{ $entry->id }}">
                            <h2>{{ $crop !== null && !$monthlyAccomplished ? 'Edit '.$cropLabels[$crop].' production record' : 'Edit record' }}</h2>
                            <form method="post" action="{{ route('records.update', [$sector, $entry->id]) }}" data-record class="stack">
                                @csrf @method('PATCH') <input type="hidden" name="revision" value="{{ $entry->updated_at }}">
                                @php
                                    $editTilapia = $sector === 'fisheries' && strcasecmp(json_decode($entry->details, true)['species'] ?? '', 'Tilapia') === 0;
                                @endphp
                                @include($editTilapia ? 'agriculture-tilapia-fields' : 'agriculture-fields', ['entry' => $entry])
                                <div class="dialog-actions"><button type="button" class="secondary" data-close>Cancel</button><button>Save changes</button></div>
                            </form>
                        </dialog>
                    @endforeach
                @endif
            @endif

            @if (isset($recent))
                <section class="panel data"><div class="section-head"><h2>Recent Activity</h2></div>
                    <div class="table-wrap" tabindex="0" role="region" aria-label="Recent activity"><table><thead><tr><th>Record</th><th>Sector</th><th>Date</th></tr></thead><tbody>
                        @forelse ($recent as $entry)<tr><td>{{ json_decode($entry->details, true)['name'] }}</td><td><a href="{{ route('sector', $entry->sector) }}">{{ $modules[$entry->sector][0] }}</a></td><td>{{ $entry->record_date }}</td></tr>
                        @empty <tr><td colspan="3" class="empty">Activity will appear after the first record is saved.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            @endif

            @if ($access)
                @if (!$head && count($available = array_diff_key($modules, $visible)))
                    <section class="panel"><h2>Request sector access</h2>
                        <form method="post" action="{{ route('access.request') }}" class="stack">
                            @csrf <label>Sector <select name="sector">@foreach ($available as $key => [$label])<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                            <label>Reason <textarea name="reason" maxlength="500" required></textarea></label><div><button>Submit request</button></div>
                        </form>
                    </section>
                @endif
                <section class="panel data"><div class="section-head"><h2>{{ $head ? 'Sector access requests' : 'My requests' }}</h2></div>
                    <div class="table-wrap" tabindex="0" role="region" aria-label="Access requests"><table><thead><tr><th>Employee</th><th>Sector</th><th>Reason</th><th>Status</th>@if ($head)<th>Action</th>@endif</tr></thead><tbody>
                        @forelse ($requests as $item)
                            <tr><td>{{ $item->name }}@if ($head)<br><small>{{ $item->email }}</small>@endif</td><td>{{ $modules[$item->sector][0] }}</td><td>{{ $item->reason }}</td><td><span class="badge {{ $item->status }}">{{ ucfirst($item->status) }}</span></td>
                            @if ($head)<td>
                                @if ($item->status !== 'denied' && $item->assigned_sector !== $item->sector)
                                    <form class="actions" method="post" action="{{ route('access.decide', $item->id) }}">
                                        @csrf @method('PATCH')
                                        @if ($item->status === 'pending')<button name="status" value="approved">Approve</button>@endif
                                        <button class="secondary" name="status" value="denied">{{ $item->status === 'approved' ? 'Revoke' : 'Deny' }}</button>
                                    </form>
                                @endif
                            </td>@endif</tr>
                        @empty <tr><td colspan="5" class="empty">No access requests yet.</td></tr>@endforelse
                    </tbody></table></div>
                    @if ($requests->hasPages())<div class="pagination">{{ $requests->links('pagination::simple-bootstrap-4') }}</div>@endif
                </section>
            @endif
        </div>
    </main>
</div>
<dialog id="sync-dialog">
    <h2>Record sync</h2>
    <p>Entries queued from an open sector workspace sync when the connection returns and you’re signed in to the same account.</p>
    <div data-sync-items></div>
    <div class="dialog-actions"><button type="button" class="secondary" data-close>Close</button><button type="button" data-retry>Sync now</button></div>
</dialog>
<div class="toast" role="status" hidden></div>
@include('agriculture-auth-loading', ['message' => 'Signing out…'])
</body>
</html>
