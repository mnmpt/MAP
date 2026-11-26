@php
    $areas[] = ['yellow' => array_sum(array_column($areas, 'yellow')), 'white' => array_sum(array_column($areas, 'white'))];
@endphp
@foreach ($areas as $area)
    <td>{{ number_format($area['yellow'], 4) }}</td>
    <td>{{ number_format($area['white'], 4) }}</td>
    <td class="corn-total">{{ number_format(array_sum($area), 4) }}</td>
@endforeach
