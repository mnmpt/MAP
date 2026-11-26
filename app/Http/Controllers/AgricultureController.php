<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class AgricultureController extends Controller
{
    private const REGISTRATION_SECTORS = [
        'corn' => 'Corn',
        'rice' => 'Rice',
        'livestock' => 'Poultry and Livestock',
        'high-value-crops' => 'High Value Crops',
        'fisheries' => 'Fisheries',
    ];

    public function loginPage(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('agriculture-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (! Auth::attemptWhen($credentials, fn (User $user): bool => $user->hasApprovedWorkspace())) {
            throw ValidationException::withMessages(['email' => 'Check your email and password. New accounts require approval before signing in.']);
        }

        $request->session()->regenerate();

        $sector = $request->user()->sector;
        $destination = $sector === 'head' ? route('dashboard') : route('sector', $sector);

        return redirect()->intended($destination);
    }

    public function registrationPage(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('agriculture-login', ['sectors' => self::REGISTRATION_SECTORS]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => ['bail', 'required', 'string', 'min:12', 'confirmed', function (string $attribute, string $password, \Closure $fail): void {
                if (strlen($password) > 72 || str_contains($password, "\0")) {
                    $fail('Use a password of at most 72 bytes without null characters.');
                }
            }],
            'sector' => ['required', 'string', Rule::in(array_keys(self::REGISTRATION_SECTORS))],
        ]);

        try {
            DB::transaction(function () use ($data): void {
                $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
                DB::table('sector_requests')->insert([
                    'user_id' => $user->id,
                    'sector' => $data['sector'],
                    'reason' => 'New employee registration',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'The email has already been taken.']);
        }

        return redirect()->route('login')->with('notice', 'Registration submitted. The Agriculture Head must approve your sector before you can sign in.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function syncSession(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->id, 'token' => csrf_token()])
            ->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->sector !== 'head') {
            return redirect()->route('sector', $user->sector);
        }

        $visible = $this->visible($request);
        [$year, $quarter] = $this->period($request);
        $summary = $this->summary(array_keys($visible), $year, $quarter);
        $previous = $this->previousRice($visible, $year, $quarter);
        $recent = $this->records()->whereIn('sector', array_keys($visible))->latest()->limit(6)->get();

        return view('agriculture', compact('visible', 'summary', 'recent', 'year', 'quarter', 'previous'));
    }

    public function sector(Request $request, string $sector): View
    {
        $visible = $this->visible($request);
        abort_unless(isset(config('agriculture')[$sector]), 404);
        abort_unless(isset($visible[$sector]), 403);
        [$year, $quarter] = $this->period($request);
        $summary = $this->summary([$sector], $year, $quarter);
        $previous = $this->previousRice(array_intersect_key($visible, [$sector => true]), $year, $quarter);
        $search = $request->validate(['q' => 'nullable|string|max:160'])['q'] ?? '';
        $query = $this->records()->where('sector', $sector);
        $livestockRegister = null;
        $poultryTotals = null;
        if ($sector === 'livestock') {
            $livestockRegister = $request->validate([
                'register' => ['nullable', 'string', Rule::in(array_keys(config('agriculture.livestock')[4]))],
            ])['register'] ?? null;
            if ($livestockRegister === null) {
                $query->whereNull('details->register');
            } else {
                $query->where('details->register', $livestockRegister);
                if ($livestockRegister !== 'vaccination') {
                    $query->where('record_date', '>=', Carbon::create($year, 1, 1)->toDateString())
                        ->where('record_date', '<', Carbon::create($year + 1, 1, 1)->toDateString());
                }
            }
        }
        $tilapiaRegister = $sector === 'fisheries' && $request->query('register') === 'tilapia';
        if ($tilapiaRegister) {
            $query->whereLike('details->species', 'Tilapia');
        }
        $plantingMonth = null;
        $plantingTotals = [];
        $crop = null;
        $barangay = '';
        $monthlyAccomplished = $sector === 'high-value-crops' && $request->query('report') === 'monthly-accomplished';
        if ($sector === 'high-value-crops') {
            $filters = $request->validate([
                'crop' => ['nullable', Rule::in(array_keys(config('agriculture.high-value-crops')[4]))],
                'barangay' => 'nullable|string|max:100',
            ]);
            $crop = $filters['crop'] ?? null;
            $barangay = $filters['barangay'] ?? '';
            if ($crop !== null) {
                $query->where('details->crop', $crop);
            }
            if ($barangay !== '') {
                $query->where('details->area', $barangay);
            }
        }
        if ($sector === 'corn' || $crop !== null || $monthlyAccomplished || $livestockRegister === 'vaccination') {
            $month = $request->validate(['month' => 'nullable|date_format:Y-m'])['month']
                ?? now('Asia/Manila')->format('Y-m');
            $plantingMonth = Carbon::createFromFormat('!Y-m', $month);
            $query->where('record_date', '>=', $plantingMonth->toDateString())
                ->where('record_date', '<', $plantingMonth->copy()->addMonth()->toDateString());
        }
        if ($search !== '') {
            $query->where(fn (Builder $query) => $query->whereLike('details->name', '%'.$search.'%')
                ->orWhereLike($sector === 'farmers' ? 'details->barangay' : 'details->area', '%'.$search.'%')
                ->orWhereLike('details->farmer_name', '%'.$search.'%'));
        }
        if ($sector === 'fertilizer' && in_array($request->query('status'), ['Collected', 'Pending'], true)) {
            $query->where('details->status', $request->query('status'));
        }
        if ($sector === 'corn') {
            $plantingTotals = $this->cornAreas((clone $query)->select('id', 'details')->lazyById()
                ->map(fn (object $record) => json_decode($record->details, true)));
        }
        if ($livestockRegister === 'poultry-farms') {
            $poultryTotals = (clone $query)->select('id', 'details')->lazyById()->reduce(
                function (array $totals, object $record): array {
                    $details = json_decode($record->details, true);

                    return [$totals[0] + ($details['heads'] ?? 0), $totals[1] + ($details['eggs'] ?? 0)];
                },
                [0, 0],
            );
        }
        $records = $query->orderByDesc('record_date')->orderByDesc('id')->paginate(15)->withQueryString();
        $canWrite = in_array($request->user()->sector, ['head', $sector], true);

        return view('agriculture', compact('visible', 'summary', 'sector', 'records', 'canWrite', 'year', 'quarter', 'previous', 'search', 'plantingMonth', 'plantingTotals', 'tilapiaRegister', 'crop', 'barangay', 'monthlyAccomplished', 'livestockRegister', 'poultryTotals'));
    }

    public function store(Request $request, string $sector): JsonResponse|RedirectResponse
    {
        $this->authorizeWrite($request, $sector);
        $data = $this->validateRecord($request->all(), $sector, true);
        $this->records()->insertOrIgnore($this->recordValues($data, $request->user()->id, $sector, $data['submission_id']));

        return $request->expectsJson() ? response()->json(['message' => 'Record saved.']) : back()->with('notice', 'Record saved.');
    }

    public function update(Request $request, string $sector, int $record): JsonResponse|RedirectResponse
    {
        $this->authorizeWrite($request, $sector);
        $query = $this->records()->where('sector', $sector)->where('id', $record);
        $current = $query->first();
        abort_if($current === null, 404);
        $data = $this->validateRecord($request->all(), $sector);
        if ($sector === 'livestock' && ($data['register'] ?? null) !== (json_decode($current->details, true)['register'] ?? null)) {
            throw ValidationException::withMessages(['register' => 'A livestock record must stay in its original register.']);
        }
        $revision = $request->validate(['revision' => 'required|string|max:80'])['revision'];
        $values = $this->recordValues($data, $current->user_id, $sector, $current->submission_id);
        unset($values['created_at']);
        $updated = $query->where('updated_at', $revision)->update($values);
        if (! $updated) {
            $latest = $this->records()->where('sector', $sector)->where('id', $record)->first();
            abort_unless($latest && $latest->details === $values['details'] && $latest->record_date === $values['record_date'], 409, 'This record changed. Reopen it before editing.');
        }

        return $request->expectsJson() ? response()->json(['message' => 'Record updated.']) : back()->with('notice', 'Record updated.');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeWrite($request, 'rice');
        $file = $request->validate(['csv' => 'required|file|extensions:csv|mimes:csv,txt|max:2048'])['csv'];
        $csv = new \SplFileObject($file->getRealPath());
        $csv->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $csv->setCsvControl(',', '"', '');
        $header = $csv->fgetcsv();
        if (! is_array($header)) {
            throw ValidationException::withMessages(['csv' => 'The file contains no harvest records.']);
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
        $expected = ['source_id', 'record_date', 'name', 'area', 'hectares', 'quantity'];
        if ($header !== $expected) {
            throw ValidationException::withMessages(['csv' => 'Use the column names shown in the import panel.']);
        }

        $rows = [];
        while (! $csv->eof()) {
            $row = $csv->fgetcsv();
            if ($row === false || $row === [null]) {
                continue;
            }
            if (count($rows) >= 5000 || count($row) !== count($expected)) {
                throw ValidationException::withMessages(['csv' => 'Use up to 5,000 rows, with six columns on each row.']);
            }
            $input = array_combine($expected, array_map('trim', $row));
            if ($input['source_id'] === '' || strlen($input['source_id']) > 160 || isset($rows[$input['source_id']])) {
                throw ValidationException::withMessages(['csv' => 'Every row must have a unique source ID of up to 160 bytes.']);
            }
            $rows[$input['source_id']] = $this->validateRecord($input, 'rice');
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['csv' => 'The file contains no harvest records.']);
        }

        DB::transaction(function () use ($rows, $request): void {
            foreach ($rows as $source => $data) {
                $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'agriculture-rice:'.$source)->toString();
                $values = $this->recordValues($data, $request->user()->id, 'rice', $id);
                $values['source_key'] = (string) $source;
                $this->records()->upsert([$values], ['sector', 'source_key'], ['record_date', 'details', 'updated_at']);
            }
        });

        return back()->with('notice', count($rows).' rice records imported.');
    }

    public function reports(Request $request): View|Response
    {
        $visible = $this->visible($request);
        [$year, $quarter] = $this->period($request);
        $filters = $request->validate(['sector' => ['nullable', 'string', Rule::in(array_keys(config('agriculture')))], 'format' => 'nullable|in:pdf,docx']);
        $selected = $filters['sector'] ?? null;
        abort_if($selected !== null && ! isset($visible[$selected]), 403);
        $summary = $this->summary($selected ? [$selected] : array_keys($visible), $year, $quarter);
        $previous = $this->previousRice(array_intersect_key($visible, $summary), $year, $quarter);
        $format = $filters['format'] ?? null;
        $reportTitle = ($quarter ? 'Quarter '.$quarter.' · ' : 'Annual · ').$year;

        if ($format === 'docx') {
            return $this->wordReport($reportTitle, $summary, $previous);
        }
        if ($format === 'pdf') {
            return $this->pdfReport($reportTitle, $summary, $previous);
        }

        return view('agriculture', compact('visible', 'summary', 'year', 'quarter', 'selected', 'previous', 'reportTitle'));
    }

    public function farmerDirectory(Request $request): Response
    {
        $visible = $this->visible($request);
        $search = $request->validate(['q' => 'nullable|string|max:160'])['q'] ?? '';
        $needle = Str::lower(Str::squish($search));
        $directory = [];
        $records = $this->records()->whereIn('sector', array_keys($visible))
            ->select('id', 'sector', 'record_date', 'details')->lazyById();

        foreach ($records as $record) {
            $details = json_decode($record->details, true);
            [$name, $key] = $this->farmerIdentity($record->sector, $details);
            if ($name === '' || ! str_contains(Str::lower($name), $needle)) {
                continue;
            }

            $directory[$key] ??= ['name' => $name, 'history' => [], 'restricted' => []];
            $directory[$key]['history'][] = [
                'date' => $record->record_date,
                'sector' => $record->sector,
                'service' => match ($record->sector) {
                    'livestock' => match ($details['register'] ?? null) {
                        'routine-services' => 'Routine animal health service',
                        'vaccination' => 'Animal vaccination',
                        default => 'Livestock loading',
                    },
                    'vaccine' => 'Pet vaccination',
                    'fisheries' => ($details['transaction'] ?? null) === 'Sold' ? 'Tilapia fingerling sale' : 'Fingerling distribution',
                    'fertilizer' => ($details['status'] ?? null) === 'Collected' ? 'Fertilizer allocation (collected)' : 'Fertilizer allocation (pending collection)',
                    'rice' => 'Rice harvest recorded',
                    'corn' => 'Corn planting recorded',
                    'high-value-crops' => 'High value crop production recorded',
                    default => 'Farmer registration',
                },
            ];
            if ($record->sector === 'fertilizer' && ($details['status'] ?? null) === 'Collected' && ! empty($details['collected_at'])) {
                $directory[$key]['history'][] = ['date' => $details['collected_at'], 'sector' => 'fertilizer', 'service' => 'Fertilizer collected'];
            }
            if ($record->sector === 'livestock' && ! empty($details['unloaded_at'])) {
                $directory[$key]['history'][] = [
                    'date' => substr($details['unloaded_at'], 0, 10),
                    'sector' => 'livestock',
                    'service' => 'Livestock unloading / harvest',
                ];
            }
        }

        ksort($directory, SORT_NATURAL);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $entries = array_slice($directory, ($page - 1) * 15, 15, true);
        $restrictedSectors = array_diff_key(config('agriculture'), $visible);
        if ($entries !== [] && $restrictedSectors !== []) {
            $pending = DB::table('sector_requests')->where('user_id', $request->user()->id)
                ->where('status', 'pending')->pluck('sector')->flip();
            $restrictedRecords = $this->records()->whereIn('sector', array_keys($restrictedSectors))
                ->select('id', 'sector', 'details->name as name', 'details->farmer_name as farmer_name',
                    'details->area as area', 'details->barangay as barangay')->lazyById();
            foreach ($restrictedRecords as $record) {
                [$name, $key] = $this->farmerIdentity($record->sector, (array) $record);
                if ($name !== '' && isset($entries[$key])) {
                    $entries[$key]['restricted'][$record->sector] = $pending->has($record->sector);
                }
            }
        }
        foreach ($entries as &$farmer) {
            $farmer['history'] = collect($farmer['history'])->sortByDesc('date')->values();
        }
        unset($farmer);

        $farmerList = new LengthAwarePaginator(array_values($entries), count($directory), 15, $page, ['path' => $request->url()]);
        $farmerList->withQueryString();

        return response()->view('agriculture', compact('visible', 'farmerList', 'search'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function farmerIdentity(string $sector, array $details): array
    {
        $name = Str::squish(in_array($sector, ['livestock', 'vaccine', 'rice'], true)
            ? ($details['farmer_name'] ?? '')
            : ($details['name'] ?? ''));
        $area = Str::lower(Str::squish($details['barangay'] ?? $details['area'] ?? ''));

        return [$name, Str::lower($name)."\n".$area];
    }

    public function access(Request $request): View
    {
        $visible = $this->visible($request);
        $head = $request->user()->sector === 'head';
        $requests = DB::table('sector_requests')->join('users', 'users.id', '=', 'sector_requests.user_id')
            ->select('sector_requests.*', 'users.name', 'users.email', 'users.sector as assigned_sector')
            ->when(! $head, fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->latest('sector_requests.updated_at')->paginate(15);

        return view('agriculture', compact('visible', 'requests', 'head'));
    }

    public function requestAccess(Request $request): RedirectResponse
    {
        abort_if(in_array($request->user()->sector, ['head', null], true), 403);
        $data = $request->validate([
            'sector' => ['required', Rule::in(array_keys(array_diff_key(config('agriculture'), $this->visible($request))))],
            'reason' => 'required|string|max:500',
        ]);
        $values = ['user_id' => $request->user()->id, 'sector' => $data['sector']];
        $query = DB::table('sector_requests')->where($values)->where('status', 'denied');
        $query->update(['reason' => $data['reason'], 'status' => 'pending', 'updated_at' => now()]);
        DB::table('sector_requests')->insertOrIgnore($values + ['reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('notice', 'Access request submitted.');
    }

    public function decideAccess(Request $request, int $grant): RedirectResponse
    {
        abort_unless($request->user()->sector === 'head', 403);
        $data = $request->validate(['status' => 'required|in:approved,denied']);
        DB::transaction(function () use ($grant, $data): void {
            $query = DB::table('sector_requests')->where('id', $grant);
            $access = DB::table('sector_requests')
                ->join('users', 'users.id', '=', 'sector_requests.user_id')
                ->where('sector_requests.id', $grant)
                ->select('sector_requests.user_id', 'sector_requests.sector', 'sector_requests.status', 'users.sector as assigned_sector')
                ->lockForUpdate()->first();
            abort_if($access === null, 404);
            if ($access->status === $data['status']) {
                return;
            }
            abort_if($access->assigned_sector === $access->sector, 409, 'This is the employee’s assigned sector.');
            $updated = $query->whereIn('status', $data['status'] === 'approved' ? ['pending'] : ['pending', 'approved'])
                ->update(['status' => $data['status'], 'updated_at' => now()]);
            abort_unless($updated, 409, 'The request was already updated.');
            if ($data['status'] === 'approved' && $access->assigned_sector === null) {
                User::query()->whereKey($access->user_id)->update(['sector' => $access->sector]);
            }
        });

        return back()->with('notice', 'Access updated.');
    }

    private function visible(Request $request): array
    {
        $user = $request->user();
        $sectors = config('agriculture');
        if ($user->sector === 'head') {
            return $sectors;
        }

        $approved = DB::table('sector_requests')->where('user_id', $user->id)->where('status', 'approved')->pluck('sector')->all();

        return array_intersect_key($sectors, array_flip([$user->sector ?? '', ...$approved]));
    }

    private function authorizeWrite(Request $request, string $sector): void
    {
        abort_unless(isset(config('agriculture')[$sector]), 404);
        abort_unless(in_array($request->user()->sector, ['head', $sector], true), 403);
    }

    private function records(): Builder
    {
        return DB::table('agriculture_records');
    }

    private function validateRecord(array $input, string $sector, bool $submission = false): array
    {
        $rules = ['record_date' => 'required|date_format:Y-m-d'];
        $fields = config('agriculture')[$sector][3];
        $register = null;
        if ($sector === 'livestock') {
            $registers = config('agriculture.livestock')[4];
            $rules['register'] = ['nullable', 'string', Rule::in(array_keys($registers))];
            $register = is_string($input['register'] ?? null) ? $input['register'] : null;
            $fields = $registers[$register][1] ?? $fields;
        }
        foreach ($fields as $field => [$label, $rule]) {
            $rules[$field] = $rule;
        }
        $isTilapia = $sector === 'fisheries' && is_string($input['species'] ?? null)
            && strcasecmp(trim($input['species']), 'Tilapia') === 0;
        if ($isTilapia) {
            $rules['hectares'] = 'nullable|required_without:area_sqm|numeric|min:0|max:100000000';
            $rules['area_sqm'] = 'nullable|numeric|min:0|max:1000000000000';
            foreach (config('agriculture.fisheries')[4] as $field => [$label, $rule]) {
                $rules[$field] = $rule;
            }
        }
        if ($submission) {
            $rules['submission_id'] = 'required|uuid';
        }
        $data = Validator::make($input, $rules)->validate();
        if (in_array($register, ['routine-services', 'vaccination'], true)) {
            $data['name'] = Str::squish($data['first_name'].' '.$data['last_name']);
            $data['farmer_name'] = $data['name'];
        }
        if ($sector === 'high-value-crops') {
            $data['gross_income'] = round($data['quantity'] * $data['price'], 2);
            $data['net_income'] = round($data['gross_income'] - $data['expenses'], 2);
        }
        if ($isTilapia && isset($data['area_sqm'])) {
            $data['hectares'] = $data['area_sqm'] / 10000;
            unset($data['area_sqm']);
        }
        if ($sector === 'corn' && $this->totalCornArea($this->cornAreas([$data])) <= 0) {
            throw ValidationException::withMessages(['gmo_yellow' => 'Enter a planted area greater than zero for at least one corn variety.']);
        }
        if ($sector === 'livestock' && $register === null && ($data['harvested'] ?? 0) + $data['deaths'] > $data['loaded']) {
            throw ValidationException::withMessages(['harvested' => 'Harvested animals plus deaths cannot exceed animals loaded.']);
        }

        return $data;
    }

    private function recordValues(array $data, int $user, string $sector, string $submission): array
    {
        $date = $data['record_date'];
        unset($data['record_date'], $data['submission_id']);
        $timestamp = now()->format('Y-m-d H:i:s.u');

        return [
            'user_id' => $user, 'sector' => $sector, 'submission_id' => $submission,
            'record_date' => $date, 'details' => json_encode($data, JSON_THROW_ON_ERROR),
            'updated_at' => $timestamp, 'created_at' => $timestamp,
        ];
    }

    private function period(Request $request): array
    {
        $values = $request->validate(['year' => 'nullable|integer|between:1900,2200', 'quarter' => 'nullable|integer|between:0,4']);

        return [(int) ($values['year'] ?? now('Asia/Manila')->year), (int) ($values['quarter'] ?? 0)];
    }

    private function previousRice(array $visible, int $year, int $quarter): ?float
    {
        if (! isset($visible['rice'])) {
            return null;
        }
        $previousYear = $quarter <= 1 ? $year - 1 : $year;
        $previousQuarter = $quarter === 1 ? 4 : max(0, $quarter - 1);
        $previous = $this->summary(['rice'], $previousYear, $previousQuarter)['rice'];

        return $previous['Records'] > 0 ? (float) $previous['Harvest (kg)'] : null;
    }

    private function summary(array $sectors, int $year, int $quarter): array
    {
        $start = Carbon::create($year, $quarter ? $quarter * 3 - 2 : 1, 1)->toDateString();
        $end = Carbon::parse($start)->addMonths($quarter ? 3 : 12)->toDateString();
        $results = [];
        foreach ($sectors as $sector) {
            $records = $this->records()->where('sector', $sector)
                ->when($sector === 'livestock', fn (Builder $query) => $query->whereNull('details->register'))
                ->where('record_date', '>=', $start)
                ->where('record_date', '<', $end)->pluck('details')->map(fn (string $json) => json_decode($json, true));
            $results[$sector] = ['Records' => $records->count()] + $this->metrics($sector, $records);
        }

        return $results;
    }

    private function metrics(string $sector, Collection $records): array
    {
        $quantity = $records->sum('quantity');

        return match ($sector) {
            'corn' => ['Area planted (ha)' => $this->totalCornArea($this->cornAreas($records)), 'Barangays served' => $records->pluck('area')->unique()->count()],
            'high-value-crops' => ['Production (kg)' => round($quantity, 3), 'Area (ha)' => round($records->sum('hectares'), 4)],
            'livestock' => [
                'Animals loaded' => $loaded = $records->sum('loaded'),
                'Animals harvested' => $records->sum('harvested'),
                'Deaths' => $deaths = $records->sum('deaths'),
                'Mortality rate' => $loaded ? round($deaths / $loaded * 100, 2).'%' : '—',
            ],
            'vaccine' => ['Doses administered' => $quantity, 'Barangays served' => $records->pluck('area')->unique()->count()],
            'fisheries' => ['Fingerlings distributed' => $quantity, 'Farm area (ha)' => round($records->sum('hectares'), 2), 'Areas served' => $records->pluck('area')->unique()->count()],
            'fertilizer' => ['Bags allocated' => $quantity, 'Bags collected' => $collected = $records->where('status', 'Collected')->sum('quantity'), 'Bags pending' => $quantity - $collected],
            'rice' => ['Harvest (kg)' => round($quantity, 2), 'Area harvested (ha)' => $area = round($records->sum('hectares'), 4), 'Yield (kg/ha)' => $area ? round($quantity / $area, 2) : '—'],
            'farmers' => ['Farmer types' => $records->pluck('type')->map(fn (string $type) => mb_strtolower($type))->unique()->count()],
        };
    }

    private function cornAreas(iterable $records): array
    {
        $totals = [];
        foreach (config('agriculture.corn')[4] as $key => $label) {
            $totals[$key] = ['yellow' => 0.0, 'white' => 0.0];
        }
        foreach ($records as $record) {
            foreach ($totals as $key => $colors) {
                foreach ($colors as $color => $area) {
                    $totals[$key][$color] += (float) ($record[$key.'_'.$color] ?? 0);
                }
            }
        }

        return $totals;
    }

    private function totalCornArea(array $totals): float
    {
        return round(array_sum(array_map('array_sum', $totals)), 4);
    }

    private function reportLines(string $title, array $summary, ?float $previous): array
    {
        $lines = ['Agriculture Office Statistical Report', $title, ''];
        foreach ($summary as $sector => $metrics) {
            $lines[] = config('agriculture')[$sector][0];
            foreach ($metrics as $label => $value) {
                $lines[] = $label.': '.$value;
            }
            if ($sector === 'rice' && $previous !== null && $metrics['Records'] > 0) {
                $lines[] = 'Harvest change from previous period (kg): '.round($metrics['Harvest (kg)'] - $previous, 2);
            }
            $lines[] = '';
        }

        return $lines;
    }

    private function pdfReport(string $title, array $summary, ?float $previous): Response
    {
        $content = "BT /F1 11 Tf 15 TL 48 785 Td\n";
        foreach ($this->reportLines($title, $summary, $previous) as $line) {
            $literal = strtr(mb_convert_encoding($line, 'Windows-1252', 'UTF-8'), ['\\' => '\\\\', '(' => '\(', ')' => '\)']);
            $content .= '('.$literal.") Tj T*\n";
        }
        $content .= 'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = "0000000000 65535 f \n";
        foreach ($objects as $index => $object) {
            $offsets .= sprintf("%010d 00000 n \n", strlen($pdf));
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n".$offsets."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="agriculture-report.pdf"', 'Cache-Control' => 'no-store']);
    }

    private function wordReport(string $title, array $summary, ?float $previous): Response
    {
        $file = tempnam(sys_get_temp_dir(), 'agriculture-');
        abort_if($file === false, 500);
        $zip = new ZipArchive;
        abort_unless($zip->open($file, ZipArchive::OVERWRITE) === true, 500);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $paragraphs = '';
        foreach ($this->reportLines($title, $summary, $previous) as $line) {
            $paragraphs .= '<w:p><w:r><w:t>'.htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>';
        }
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$paragraphs.'</w:body></w:document>');
        $zip->close();

        return response()->download($file, 'agriculture-report.docx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Cache-Control' => 'no-store'])->deleteFileAfterSend();
    }
}
