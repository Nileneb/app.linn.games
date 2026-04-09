<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'forschungsfrage' => ['required', 'string', 'max:2000'],
            'forschungsbereich' => [
                'required',
                'string',
                Rule::in([
                    'Gesundheit & Medizin',
                    'Psychologie & Sozialwissenschaften',
                    'Bildung & Pädagogik',
                    'Informatik & Technologie',
                    'Wirtschaft & Management',
                    'Umwelt & Nachhaltigkeit',
                    'Sonstiges',
                ]),
            ],
            'erfahrung' => [
                'required',
                'string',
                Rule::in([
                    'Nein, das wäre mein erstes Mal',
                    'Ja, 1–2 Mal',
                    'Ja, regelmäßig',
                ]),
            ],
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'status' => 'waitlisted',
            'forschungsfrage' => $input['forschungsfrage'],
            'forschungsbereich' => $input['forschungsbereich'],
            'erfahrung' => $input['erfahrung'],
        ]);
    }
}
