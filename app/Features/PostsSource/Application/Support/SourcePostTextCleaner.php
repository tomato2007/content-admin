<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Application\Support;

final class SourcePostTextCleaner
{
    public function clean(string $content): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $content);
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        $lines = preg_split('/\n/', $text) ?: [];
        $cleaned = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($cleaned !== [] && end($cleaned) !== '') {
                    $cleaned[] = '';
                }

                continue;
            }

            if ($this->isSourceBanner($trimmed) || $this->isSourceTail($trimmed)) {
                continue;
            }

            $cleaned[] = preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
        }

        while ($cleaned !== [] && end($cleaned) === '') {
            array_pop($cleaned);
        }

        return trim(implode("\n", $cleaned));
    }

    private function isSourceBanner(string $line): bool
    {
        $upper = mb_strtoupper($line);

        return str_contains($upper, 'ЕВРЕЙСКИЙ ДВОРИК')
            || str_contains($upper, 'ТАКИ, МЫ В MAX')
            || str_contains($upper, 'ТАКИ, МЫ В МАХ')
            || str_contains($upper, 'МЫ В MAX')
            || str_contains($upper, 'МЫ В МАХ');
    }

    private function isSourceTail(string $line): bool
    {
        $upper = mb_strtoupper($line);

        return str_contains($upper, 'АНЕКДОТЫ ДЛЯ ВЗРОСЛЫХ');
    }
}
