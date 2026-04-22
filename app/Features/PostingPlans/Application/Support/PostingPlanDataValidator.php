<?php

declare(strict_types=1);

namespace App\Features\PostingPlans\Application\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostingPlanDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'timezone' => ['required', 'string', 'max:255', 'timezone:all'],
            'quiet_hours_from' => ['nullable', 'required_with:quiet_hours_to', 'string'],
            'quiet_hours_to' => ['nullable', 'required_with:quiet_hours_from', 'string'],
        ]);

        $validator->after(function ($validator) use ($data): void {
            $from = $data['quiet_hours_from'] ?? null;
            $to = $data['quiet_hours_to'] ?? null;

            if (! $this->isValidTimeValue($from) && $from !== null) {
                $validator->errors()->add('quiet_hours_from', 'Quiet hours start must be a valid local time.');
            }

            if (! $this->isValidTimeValue($to) && $to !== null) {
                $validator->errors()->add('quiet_hours_to', 'Quiet hours end must be a valid local time.');
            }

            if ($from !== null && $to !== null && $this->normalizeTimeValue($from) === $this->normalizeTimeValue($to)) {
                $validator->errors()->add('quiet_hours_to', 'Quiet hours end must differ from quiet hours start.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function isValidTimeValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $value = (string) $value;

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) === 1;
    }

    private function normalizeTimeValue(string $value): string
    {
        $segments = explode(':', $value);

        return sprintf('%02d:%02d', (int) ($segments[0] ?? 0), (int) ($segments[1] ?? 0));
    }
}
