<section class="panel data">
    <div class="section-head">
        <h2>Farmers</h2>
        @if (isset($visible['farmers']))
            <a class="button secondary" href="{{ route('sector', ['sector' => 'farmers', 'profiles' => 1]) }}">Registered farmer profiles</a>
        @endif
        <form class="filters" method="get" action="{{ route('farmers.directory') }}">
            <label class="sr-only" for="farmer-search">Farmer name</label>
            <input id="farmer-search" name="q" value="{{ $search }}" maxlength="160" placeholder="Search farmer name">
            <button class="secondary">Search</button>
        </form>
    </div>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Farmers Masterlist">
        <table class="farmer-masterlist">
            <thead><tr><th scope="col">Farmer</th><th scope="col">Barangay</th><th scope="col">Latest service</th><th scope="col">History</th></tr></thead>
            <tbody>
                @forelse ($farmerList as $farmer)
                    <tr>
                        <th scope="row"><button type="button" class="quiet" data-open="farmer-{{ $loop->index }}">{{ $farmer['name'] }}</button></th>
                        <td>{{ $farmer['barangays'] ?: '—' }}</td>
                        <td>{{ $farmer['history']->first()['date'] }}</td>
                        <td>{{ $farmer['history']->count() }} {{ Illuminate\Support\Str::plural('entry', $farmer['history']->count()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No matching farmers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($farmerList->hasPages())<div class="pagination">{{ $farmerList->links('pagination::simple-bootstrap-4') }}</div>@endif
</section>

@foreach ($farmerList as $farmer)
    <dialog id="farmer-{{ $loop->index }}" aria-labelledby="farmer-title-{{ $loop->index }}">
        <h2 id="farmer-title-{{ $loop->index }}">{{ $farmer['name'] }}</h2>
        @if ($farmer['barangays'] !== '')<p><strong>Recorded barangays</strong> · {{ $farmer['barangays'] }}</p>@endif
        <p>Service history</p>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Farmer service history">
            <table>
                <thead><tr><th scope="col">Date</th><th scope="col">Sector</th><th scope="col">Barangay</th><th scope="col">Service</th></tr></thead>
                <tbody>
                    @foreach ($farmer['history'] as $service)
                        <tr>
                            <td>{{ $service['date'] }}</td><td>{{ $modules[$service['sector']][0] }}</td>
                            <td>{{ $service['barangay'] ?? '—' }}</td>
                            <td>
                                @if (!empty($service['details']))
                                    <details>
                                        <summary>{{ $service['service'] }}</summary>
                                        <dl>
                                            @foreach ($service['details'] as $label => $value)
                                                <dt><strong>{{ $label }}</strong></dt><dd>{{ $value }}</dd>
                                            @endforeach
                                        </dl>
                                    </details>
                                @else
                                    {{ $service['service'] }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($farmer['restricted'] !== [])
            <h3>History requiring access</h3>
            <ul class="stack">
                @foreach ($farmer['restricted'] as $sector => $pending)
                    <li>
                        {{ $modules[$sector][0] }}
                        @if ($pending)
                            <span class="badge pending">Request pending</span>
                        @else
                            <form method="post" action="{{ route('access.request') }}">
                                @csrf
                                <input type="hidden" name="sector" value="{{ $sector }}">
                                <input type="hidden" name="reason" value="Review cross-sector service history in the Farmers Masterlist">
                                <button type="submit" class="secondary" aria-label="Request access to {{ $modules[$sector][0] }}">Request access</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="dialog-actions"><button type="button" class="secondary" data-close>Close</button></div>
    </dialog>
@endforeach
