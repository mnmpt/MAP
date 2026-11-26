<div class="table-wrap" tabindex="0" role="region" aria-label="Tilapia fingerlings register">
    <table class="tilapia-register">
        <thead><tr>
            <th>Date</th><th>Farmer</th>
            @if ($canWrite)<th>Gender</th><th>Date of birth</th><th>Age at transaction</th>@endif
            <th>Barangay</th><th>Dispersal</th><th>Sold</th>
            @if ($canWrite)<th>IPs</th><th>4Ps</th><th>PWD</th><th>OSY</th><th>Elderly</th>@endif
            <th>Area (sq. m.)</th><th>Fingerlings received</th>
            @if ($canWrite)<th>Signature reference</th><th>Action</th>@endif
        </tr></thead>
        <tbody>
            @forelse ($records as $entry)
                @php
                    $details = json_decode($entry->details, true);
                    $birthDate = $details['birth_date'] ?? null;
                    $transaction = $details['transaction'] ?? null;
                @endphp
                <tr>
                    <td>{{ $entry->record_date }}</td><th scope="row">{{ $details['name'] }}</th>
                    @if ($canWrite)
                        <td>{{ $details['gender'] ?? '—' }}</td><td>{{ $birthDate ?? '—' }}</td>
                        <td>{{ $birthDate ? Illuminate\Support\Carbon::parse($birthDate)->diff(Illuminate\Support\Carbon::parse($entry->record_date))->y : '—' }}</td>
                    @endif
                    <td>{{ $details['area'] }}</td>
                    <td>{{ $transaction === 'Dispersal' ? 'Yes' : '—' }}</td><td>{{ $transaction === 'Sold' ? 'Yes' : '—' }}</td>
                    @if ($canWrite)
                        @foreach (['ips', 'four_ps', 'pwd', 'osy', 'elderly'] as $flag)
                            <td>{{ $details[$flag] ?? '—' }}</td>
                        @endforeach
                    @endif
                    <td>{{ $details['hectares'] * 10000 }}</td><td>{{ number_format($details['quantity']) }}</td>
                    @if ($canWrite)
                        <td>{{ $details['signature'] ?? '—' }}</td>
                        <td><button type="button" class="quiet" data-open="record-{{ $entry->id }}">Edit</button></td>
                    @endif
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ $canWrite ? 17 : 7 }}">No tilapia records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
