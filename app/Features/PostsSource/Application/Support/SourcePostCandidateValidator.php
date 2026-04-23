<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Application\Support;

final class SourcePostCandidateValidator
{
    public function isEligible(string $content): bool
    {
        $text = trim($content);

        if ($text === '') {
            return false;
        }

        if (mb_strlen($text) < 12) {
            return false;
        }

        if (preg_match('/^(t\.me\/|https?:\/\/t\.me\/|@\w+$)/iu', $text) === 1) {
            return false;
        }

        if (preg_match('/^[\p{P}\p{S}\d\s]+$/u', $text) === 1) {
            return false;
        }

        return true;
    }
}
