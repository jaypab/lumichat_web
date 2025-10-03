<?php

namespace App\Support;

final class Sql
{
    public static function escapeLike(string $value): string
    {
        // escape backslash first, then SQL LIKE wildcards
        return str_replace(['\\','%','_'], ['\\\\','\%','\_'], $value);
    }
}
