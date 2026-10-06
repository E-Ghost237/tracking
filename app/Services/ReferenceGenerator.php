<?php

namespace App\Services;

/**
 * Human-friendly random references (payment references, quote references).
 * Uses an alphabet without ambiguous characters (0/O, 1/I/L).
 */
class ReferenceGenerator
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function make(string $prefix, int $length = 6): string
    {
        $chars = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return $prefix.'-'.$chars;
    }

    /**
     * @param  callable(string): bool  $exists
     */
    public function unique(string $prefix, callable $exists, int $length = 6): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference = $this->make($prefix, $length + intdiv($attempt, 3));
            if (! $exists($reference)) {
                return $reference;
            }
        }

        throw new \RuntimeException('Could not generate a unique reference.');
    }
}
