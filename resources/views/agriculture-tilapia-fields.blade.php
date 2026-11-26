@php
    $details = isset($entry) ? json_decode($entry->details, true) : [];
@endphp
<input name="species" type="hidden" value="Tilapia">
<label>Date <input name="record_date" type="date" value="{{ $entry->record_date ?? now('Asia/Manila')->toDateString() }}" required></label>
<label>Farmer name <input name="name" value="{{ $details['name'] ?? '' }}" maxlength="160" required></label>
<label>Barangay <input name="area" value="{{ $details['area'] ?? '' }}" maxlength="100" required></label>
<label>Transaction
    <select name="transaction" required>
        <option value="">Select a transaction</option>
        @foreach (['Dispersal', 'Sold'] as $transaction)
            <option @selected(($details['transaction'] ?? '') === $transaction)>{{ $transaction }}</option>
        @endforeach
    </select>
</label>
<label>Area (sq. m.) <input name="area_sqm" type="number" min="0" max="1000000000000" step="any" value="{{ isset($details['hectares']) ? $details['hectares'] * 10000 : '' }}" required></label>
<label>Number of fingerlings received <input name="quantity" type="number" min="1" max="100000000" value="{{ $details['quantity'] ?? '' }}" required></label>
<label>Gender
    <select name="gender">
        <option value="">Unspecified</option>
        <option value="M" @selected(($details['gender'] ?? '') === 'M')>Male</option>
        <option value="F" @selected(($details['gender'] ?? '') === 'F')>Female</option>
    </select>
</label>
<label>Date of birth <input name="birth_date" type="date" value="{{ $details['birth_date'] ?? '' }}"></label>
<fieldset class="tilapia-flags">
    <legend>Beneficiary categories</legend>
    @foreach (['ips' => 'IPs', 'four_ps' => '4Ps', 'pwd' => 'PWD', 'osy' => 'OSY', 'elderly' => 'Elderly'] as $key => $label)
        <label><input name="{{ $key }}" type="checkbox" value="Yes" @checked(($details[$key] ?? '') === 'Yes')> {{ $label }}</label>
    @endforeach
</fieldset>
<label>Signature / acknowledgment reference <input name="signature" value="{{ $details['signature'] ?? '' }}" maxlength="160"></label>
