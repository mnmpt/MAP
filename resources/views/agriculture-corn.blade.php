@php
    $varieties = $modules['corn'][4];
@endphp
<div class="table-wrap" tabindex="0" role="region" aria-label="Monthly corn planting table">
    <table class="corn-report">
        <thead>
            <tr>
                @foreach (['AT', 'Barangay', 'No.', 'Farmer', 'Gender', 'Association'] as $heading)
                    <th rowspan="2" scope="col">{{ $heading }}</th>
                @endforeach
                @foreach ([...array_values($varieties), 'All varieties'] as $heading)
                    <th colspan="3">{{ $heading }}</th>
                @endforeach
                @if ($canWrite)<th rowspan="2" scope="col">Action</th>@endif
            </tr>
            <tr>
                @foreach (range(1, count($varieties) + 1) as $group)
                    <th scope="col">Yellow</th><th scope="col">White</th><th scope="col">Total</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $entry)
                @php
                    $details = json_decode($entry->details, true);
                    $areas = [];
                    foreach ($varieties as $key => $label) {
                        $areas[$key] = ['yellow' => (float) ($details[$key.'_yellow'] ?? 0), 'white' => (float) ($details[$key.'_white'] ?? 0)];
                    }
                @endphp
                <tr>
                    <td>{{ $details['technician'] }}</td>
                    <td>{{ $details['area'] }}</td>
                    <td>{{ $records->firstItem() + $loop->index }}</td>
                    <th scope="row">{{ $details['name'] }}</th>
                    <td>{{ $details['gender'] ?? '—' }}</td>
                    <td>{{ $details['association'] ?? '—' }}</td>
                    @include('agriculture-corn-areas', ['areas' => $areas])
                    @if ($canWrite)<td><button class="quiet" data-open="record-{{ $entry->id }}">Edit</button></td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $canWrite ? 25 : 24 }}" class="empty">No planting records found for this month.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6" scope="row">{{ $search === '' ? 'Monthly totals (ha)' : 'Filtered totals (ha)' }}</th>
                @include('agriculture-corn-areas', ['areas' => $plantingTotals])
                @if ($canWrite)<td></td>@endif
            </tr>
        </tfoot>
    </table>
</div>
