<?php

namespace App\Rules;

use App\Services\PinService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{4}$/', $value)) {
            $fail('PIN harus terdiri dari 4 angka.');

            return;
        }

        if (PinService::isWeak($value)) {
            $fail('PIN terlalu mudah ditebak. Pilih kombinasi lain.');
        }
    }
}
