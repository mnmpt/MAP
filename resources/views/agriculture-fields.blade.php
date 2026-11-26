@php
    $details = isset($entry) ? json_decode($entry->details, true) : [];
    $selectedCrop = $details['crop'] ?? $crop ?? null;
    $fields = $livestockRegister === null ? config('agriculture')[$sector][3] : $livestockRegisters[$livestockRegister][1];
    if ($sector === 'high-value-crops' && $selectedCrop !== null) {
        $fields['commodity'][0] = config('agriculture.high-value-crops')[5][$selectedCrop];
        if ($crop !== null && !$monthlyAccomplished) {
            unset($fields['remarks']);
        }
    }
    $date = now('Asia/Manila');
    if ($livestockRegister !== null && $date->year !== $year) {
        $date->setDate($year, 1, 1);
    }
    $dateLabel = match ($livestockRegister) {
        'routine-services' => 'Service date',
        'vaccination' => 'Vaccination date',
        'poultry-farms' => 'Reporting date',
        default => match ($sector) { 'livestock' => 'Loading date', 'rice' => 'Harvest date', 'corn' => 'Planting date', 'high-value-crops' => 'Production date', 'fertilizer' => 'Allocation date', 'vaccine' => 'Vaccination date', 'fisheries' => 'Distribution date', default => 'Registration date' },
    };
@endphp
@if ($livestockRegister !== null)<input type="hidden" name="register" value="{{ $livestockRegister }}">@endif
<label>{{ $dateLabel }}
    <input name="record_date" type="date" value="{{ $entry->record_date ?? ($plantingMonth?->toDateString() ?? $date->toDateString()) }}" required>
</label>
@foreach ($fields as $key => [$label, $rule])
    @if ($sector === 'high-value-crops' && $key === 'expenses' && $crop !== null && !$monthlyAccomplished)
        <label>Gross income (PHP) <output class="crop-income" data-crop-gross>—</output></label>
    @endif
    @if ($sector === 'high-value-crops' && $key === 'crop' && $selectedCrop !== null)
        <input type="hidden" name="crop" value="{{ $selectedCrop }}">
        @continue
    @endif
    @continue($sector === 'high-value-crops' && $key === 'expansion' && $selectedCrop !== null && $selectedCrop !== 'banana')
    <label>{{ $label }}
    @if ($key === 'crop')
        <select name="crop" required>
            <option value="">Select a crop</option>
            @foreach ($cropLabels as $cropKey => $cropLabel)
                <option value="{{ $cropKey }}">{{ $cropLabel }}</option>
            @endforeach
        </select>
    @elseif (in_array($key, ['government', 'sex', 'animal_sex'], true))
        <select name="{{ $key }}" @if ($key === 'government') required @endif>
            <option value="">{{ $key === 'government' ? 'Select Yes or No' : 'Unspecified' }}</option>
            @foreach ($key === 'government' ? ['Yes' => 'Yes', 'No' => 'No'] : ['M' => 'Male', 'F' => 'Female'] as $option => $optionLabel)
                <option value="{{ $option }}" @selected(($details[$key] ?? '') === $option)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif ($key === 'status')
        <select name="status">@foreach (['Pending', 'Collected'] as $status)
            <option @selected(($details['status'] ?? 'Pending') === $status)>{{ $status }}</option>
        @endforeach</select>
    @elseif (in_array($key, ['assets', 'remarks'], true))
        <textarea name="{{ $key }}" maxlength="500">{{ $details[$key] ?? '' }}</textarea>
    @else
        <input name="{{ $key }}" value="{{ $details[$key] ?? match (true) {
            $key === 'deaths' => 0,
            $key === 'area' && $sector === 'high-value-crops' => $barangay,
            default => '',
        } }}"
            type="{{ match(true) { $key === 'unloaded_at' => 'datetime-local', in_array($key, ['collected_at', 'birth_date', 'animal_birth_date'], true) => 'date', str_contains($rule, '|integer') || str_contains($rule, '|numeric') => 'number', default => 'text' } }}"
            @if (str_contains($rule, '|numeric')) step="any" @endif
            @if (preg_match('/\|max:(\d+)/', $rule, $limit)) {{ str_contains($rule, '|string') ? 'maxlength' : 'max' }}="{{ $limit[1] }}" @endif
            @if (str_starts_with($rule, 'required')) required @endif>
    @endif
    </label>
@endforeach
@if ($sector === 'high-value-crops' && $crop !== null && !$monthlyAccomplished)
    <label>Net income (PHP) <output class="crop-income" data-crop-net>—</output></label>
@endif
