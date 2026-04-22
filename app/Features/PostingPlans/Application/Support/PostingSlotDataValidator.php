<?php

declare(strict_types=1);

namespace App\Features\PostingPlans\Application\Support;

use App\Models\PostingSlot;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostingSlotDataValidator
{
    /**
     * @throws ValidationException
     */
    public function validate(PostingSlot $postingSlot): void
    {
        $validator = Validator::make([
            'posting_plan_id' => $postingSlot->posting_plan_id,
            'weekday' => $postingSlot->weekday,
            'time_local' => $postingSlot->time_local,
        ], [
            'posting_plan_id' => ['required', 'integer', 'exists:posting_plans,id'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'time_local' => ['required', 'string'],
        ]);

        $validator->after(function ($validator) use ($postingSlot): void {
            if (! $this->isValidTimeValue($postingSlot->time_local)) {
                $validator->errors()->add('time_local', 'Local time must be a valid time value.');

                return;
            }

            $conflictExists = PostingSlot::query()
                ->where('posting_plan_id', $postingSlot->posting_plan_id)
                ->where('weekday', $postingSlot->weekday)
                ->where('time_local', $this->normalizeTimeValue((string) $postingSlot->time_local))
                ->when($postingSlot->exists, fn ($query) => $query->whereKeyNot($postingSlot->getKey()))
                ->exists();

            if ($conflictExists) {
                $validator->errors()->add('time_local', 'A slot for this weekday and local time already exists.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function isValidTimeValue(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) === 1;
    }

    private function normalizeTimeValue(string $value): string
    {
        $segments = explode(':', $value);

        return sprintf('%02d:%02d:00', (int) ($segments[0] ?? 0), (int) ($segments[1] ?? 0));
    }
}
