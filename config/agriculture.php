<?php

$cornVarieties = [
    'gmo' => 'GMO',
    'hybrid' => 'Hybrid',
    'opv' => 'OPV',
    'sige_sige' => 'Sige-Sige',
    'traditional' => 'Traditional',
];

$cornAreas = [];
foreach ($cornVarieties as $key => $label) {
    $cornAreas[$key.'_yellow'] = [$label.' yellow area (ha)', 'nullable|numeric|min:0|max:100000000|decimal:0,4'];
    $cornAreas[$key.'_white'] = [$label.' white area (ha)', 'nullable|numeric|min:0|max:100000000|decimal:0,4'];
}

return [
    'livestock' => ['Poultry & Livestock', '🐓', 'Track loading, unloading, harvests and mortality by batch.', [
        'name' => ['Batch / farm name', 'required|string|max:160'],
        'farmer_name' => ['Farmer name', 'nullable|string|max:160|required_with:area'],
        'area' => ['Farmer barangay', 'nullable|string|max:100|required_with:farmer_name'],
        'species' => ['Animal type', 'required|string|max:100'],
        'loaded' => ['Animals loaded', 'required|integer|min:1|max:100000000'],
        'unloaded_at' => ['Unloading / harvest time', 'nullable|date_format:Y-m-d\TH:i|after_or_equal:record_date'],
        'harvested' => ['Animals unloaded / harvested', 'nullable|integer|min:0|lte:loaded'],
        'deaths' => ['Mortality count', 'required|integer|min:0|lte:loaded'],
    ], [
        'routine-services' => ['Routine Services Monitoring Report', [
            'area' => ['Barangay', 'required|string|max:100'],
            'first_name' => ['First name', 'required|string|max:80'],
            'last_name' => ['Last name', 'required|string|max:80'],
            'sex' => ['Client sex', 'nullable|in:M,F'],
            'birth_date' => ['Birthday', 'nullable|date_format:Y-m-d|before_or_equal:record_date'],
            'contact' => ['Contact number', 'nullable|string|max:40'],
            'species' => ['Species', 'required|string|max:100'],
            'deworming_m' => ['Deworming (male heads)', 'nullable|integer|min:0|max:100000000'],
            'deworming_f' => ['Deworming (female heads)', 'nullable|integer|min:0|max:100000000'],
            'vitamins_m' => ['Vitamin supplement (male heads)', 'nullable|integer|min:0|max:100000000'],
            'vitamins_f' => ['Vitamin supplement (female heads)', 'nullable|integer|min:0|max:100000000'],
            'treatment_m' => ['Treatment (male heads)', 'nullable|integer|min:0|max:100000000'],
            'treatment_f' => ['Treatment (female heads)', 'nullable|integer|min:0|max:100000000'],
            'signature' => ['Signature / acknowledgment reference', 'nullable|string|max:160'],
        ]],
        'poultry-farms' => ['List of Poultry Farms', [
            'name' => ['Poultry farm name', 'required|string|max:160'],
            'government' => ['Government', 'required|in:Yes,No'],
            'purok' => ['Purok', 'nullable|string|max:100'],
            'area' => ['Barangay', 'required|string|max:100'],
            'heads' => ['Number of heads', 'nullable|integer|min:0|max:100000000'],
            'eggs' => ['Number of eggs', 'nullable|integer|min:0|max:1000000000'],
            'remarks' => ['Remarks', 'nullable|string|max:500'],
        ]],
    ]],
    'vaccine' => ['Pet Vaccination', '🐾', 'Manually record vaccinations and track doses administered.', [
        'name' => ['Owner / pet name', 'required|string|max:160'],
        'farmer_name' => ['Farmer name (if applicable)', 'nullable|string|max:160'],
        'species' => ['Animal type', 'required|string|max:100'],
        'vaccine' => ['Vaccine', 'required|string|max:100'],
        'quantity' => ['Doses administered', 'required|integer|min:1|max:100000000'],
        'area' => ['Barangay', 'required|string|max:100'],
    ]],
    'fisheries' => ['Fisheries', '🐟', 'Record fingerling distributions and the areas served.', [
        'name' => ['Farmer name', 'required|string|max:160'],
        'area' => ['Barangay / area served', 'required|string|max:100'],
        'species' => ['Fingerling species', 'required|string|max:100'],
        'quantity' => ['Fingerlings distributed', 'required|integer|min:1|max:100000000'],
        'hectares' => ['Farm area (ha)', 'required|numeric|min:0|max:100000000'],
    ], [
        'transaction' => ['Transaction', 'nullable|in:Dispersal,Sold'],
        'gender' => ['Gender', 'nullable|in:M,F'],
        'birth_date' => ['Date of birth', 'nullable|date_format:Y-m-d|before_or_equal:record_date'],
        'ips' => ['IPs', 'nullable|in:Yes'],
        'four_ps' => ['4Ps', 'nullable|in:Yes'],
        'pwd' => ['PWD', 'nullable|in:Yes'],
        'osy' => ['OSY', 'nullable|in:Yes'],
        'elderly' => ['Elderly', 'nullable|in:Yes'],
        'signature' => ['Signature / acknowledgment reference', 'nullable|string|max:160'],
    ]],
    'fertilizer' => ['Fertilizer / Abuno', '🌱', 'Track farmer allocations and collection status.', [
        'name' => ['Farmer name', 'required|string|max:160'],
        'area' => ['Barangay', 'required|string|max:100'],
        'type' => ['Fertilizer type', 'required|string|max:100'],
        'quantity' => ['Bags allocated', 'required|integer|min:1|max:100000000'],
        'status' => ['Collection status', 'required|in:Pending,Collected'],
        'collected_at' => ['Collection date', 'exclude_unless:status,Collected|required|date_format:Y-m-d|after_or_equal:record_date'],
    ]],
    'rice' => ['Rice Production', '🌾', 'Consolidate harvest statistics and compare production over time.', [
        'name' => ['Farm / source reference', 'required|string|max:160'],
        'farmer_name' => ['Farmer name (if applicable)', 'nullable|string|max:160'],
        'area' => ['Barangay', 'required|string|max:100'],
        'hectares' => ['Area harvested (ha)', 'required|numeric|gt:0|max:100000000'],
        'quantity' => ['Harvest quantity (kg)', 'required|numeric|min:0|max:100000000'],
    ]],
    'corn' => ['Corn Program', '🌽', 'Track monthly corn planting by farmer, barangay and variety.', [
        'technician' => ['AT / agricultural technician', 'required|string|max:160'],
        'area' => ['Barangay', 'required|string|max:100'],
        'name' => ['Farmer name', 'required|string|max:160'],
        'gender' => ['Gender', 'nullable|string|max:60'],
        'association' => ['Association', 'nullable|string|max:160'],
        ...$cornAreas,
    ], $cornVarieties],
    'high-value-crops' => ['High Value Crops', '🌿', 'Track vegetable, cacao, and banana production by month and barangay.', [
        'crop' => ['Crop', 'required|in:vegetable,cacao,banana'],
        'name' => ['Farmer name', 'required|string|max:160'],
        'area' => ['Barangay', 'required|string|max:100'],
        'commodity' => ['Commodity / variety', 'required|string|max:100'],
        'hectares' => ['Area (ha)', 'required|numeric|min:0|max:1000000|decimal:0,4'],
        'expansion' => ['Expansion (ha)', 'exclude_unless:crop,banana|nullable|numeric|min:0|max:1000000|decimal:0,4'],
        'frequency' => ['Frequency of harvest', 'required|integer|min:0|max:10000'],
        'quantity' => ['Production harvested (kg)', 'required|numeric|min:0|max:1000000|decimal:0,3'],
        'price' => ['Price per kg (PHP)', 'required|numeric|min:0|max:1000000|decimal:0,2'],
        'expenses' => ['Expenses (PHP)', 'required|numeric|min:0|max:1000000000000|decimal:0,2'],
        'remarks' => ['Remarks', 'nullable|string|max:500'],
    ], ['vegetable' => 'Vegetable', 'cacao' => 'Cacao', 'banana' => 'Banana']],
    'farmers' => ['Farmers Masterlist', '👥', 'Maintain personal, farm ownership and farming activity records.', [
        'name' => ['Full name', 'required|string|max:160'],
        'area' => ['Barangay / address', 'required|string|max:200'],
        'barangay' => ['Barangay (for matching service records)', 'nullable|string|max:100'],
        'contact' => ['Contact number', 'nullable|string|max:40'],
        'type' => ['Farmer type (corn, rice, etc.)', 'required|string|max:100'],
        'assets' => ['Land, livestock, equipment or other assets owned', 'nullable|string|max:500'],
    ]],
];
