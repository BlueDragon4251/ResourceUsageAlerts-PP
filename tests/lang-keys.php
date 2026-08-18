<?php

declare(strict_types=1);

function flatten(array $values, string $prefix = ''): array
{
    $flat = [];
    foreach ($values as $key => $value) {
        $path = ltrim($prefix.'.'.$key, '.');
        $flat += is_array($value) ? flatten($value, $path) : [$path => $value];
    }

    return $flat;
}

$english = flatten(require dirname(__DIR__).'/lang/en/strings.php');
$german = flatten(require dirname(__DIR__).'/lang/de/strings.php');
$missingGerman = array_diff_key($english, $german);
$missingEnglish = array_diff_key($german, $english);
$mojibake = array_filter($english + $german, fn (mixed $value): bool => is_string($value) && preg_match('/Ã.|Â.|â€/', $value) === 1);

if ($missingGerman !== [] || $missingEnglish !== [] || $mojibake !== []) {
    fwrite(STDERR, 'Translation check failed.'.PHP_EOL);
    fwrite(STDERR, 'Missing German: '.implode(', ', array_keys($missingGerman)).PHP_EOL);
    fwrite(STDERR, 'Missing English: '.implode(', ', array_keys($missingEnglish)).PHP_EOL);
    fwrite(STDERR, 'Encoding issues: '.implode(', ', array_keys($mojibake)).PHP_EOL);
    exit(1);
}

echo 'Language keys are aligned.'.PHP_EOL;
