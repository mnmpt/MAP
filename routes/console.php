<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('agriculture:employee {name} {email} {sector}', function (): int {
    $validator = Validator::make([
        'name' => $this->argument('name'),
        'email' => $this->argument('email'),
        'sector' => $this->argument('sector'),
        'password' => $this->secret('Initial password (at least 12 characters)'),
    ], [
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users',
        'sector' => ['required', Rule::in(['head', ...array_keys(config('agriculture'))])],
        'password' => 'required|string|min:12',
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $data = $validator->validated();
    $employee = new User;
    $employee->name = $data['name'];
    $employee->email = $data['email'];
    $employee->password = $data['password'];
    $employee->sector = $data['sector'];
    $employee->save();
    $this->info('Employee account created.');

    return 0;
})->purpose('Create an agriculture employee account with a sector or head role');
