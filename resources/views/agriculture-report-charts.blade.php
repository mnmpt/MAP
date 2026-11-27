<div class="report-charts">
    @foreach ($charts as $key => $chart)
        @php
            $monthly = in_array($key, ['rice', 'corn', 'livestock', 'fisheries', 'vaccine'], true);
            $maximum = (float) max([0, ...array_values($chart['values'])]);
        @endphp
        <section class="panel report-chart" aria-labelledby="chart-{{ $key }}">
            <h2 id="chart-{{ $key }}">{{ $chart['title'] }}</h2>
            @if ($summary[$key]['Records'] === 0)
                <p>No records for this period.</p>
            @else
                @if ($key === 'farmers' && $summary[$key]['Farmer types'] > count($chart['values']))
                    <p>Top {{ count($chart['values']) }} types by profile count.</p>
                @elseif ($key === 'fertilizer')
                    <p>Collection status of allocations recorded during this period.</p>
                    @php
                        $allocated = array_sum($chart['values']);
                        $collectedShare = $allocated > 0 ? round($chart['values']['Collected'] / $allocated * 100, 1) : null;
                    @endphp
                    @if ($collectedShare !== null)
                        <div class="chart-donut" style="--collected-share: {{ $collectedShare }}%" aria-hidden="true">
                            <span>{{ $collectedShare }}%<small>collected</small></span>
                        </div>
                        <span class="sr-only">{{ $collectedShare }} percent collected</span>
                    @endif
                @endif
                <div @class(['chart-scroll' => $monthly]) @if ($monthly) tabindex="0" role="region" aria-label="{{ $chart['title'] }} chart" @endif>
                    <ol @class(['chart-bars', 'chart-bars-monthly' => $monthly])>
                        @foreach ($chart['values'] as $label => $value)
                            @php
                                $scale = $maximum > 0 ? round(($value ?? 0) / $maximum * 100, 3) : 0;
                            @endphp
                            <li @class(['chart-bar', 'chart-bar-alert' => $key === 'livestock' || $label === 'Pending']) style="--bar-size: {{ $scale }}%">
                                <span class="chart-bar-label">{{ $label }}</span>
                                <span class="chart-bar-track" aria-hidden="true"><span></span></span>
                                <strong class="chart-bar-value">{{ $value === null ? '—' : rtrim(rtrim(number_format($value, 4), '0'), '.') }}<span class="{{ $monthly ? 'sr-only' : '' }}">{{ $value === null ? ' No data' : ($chart['unit'] === '%' ? '%' : ' '.$chart['unit']) }}</span></strong>
                            </li>
                        @endforeach
                    </ol>
                </div>
                @if ($monthly)<small>{{ $chart['unit'] }} @if ($key === 'livestock') · A dash means no animals were loaded that month. @endif</small>@endif
            @endif
        </section>
    @endforeach
</div>
