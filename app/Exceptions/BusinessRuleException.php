<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A rule from the PRD was violated (e.g. "Kelas sudah final."). Rendered as a toast for
 * Inertia requests and as the uniform JSON error for API calls (see bootstrap/app.php).
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public string $errorCode = 'RULE_VIOLATION', public int $status = 422)
    {
        parent::__construct($message);
    }

    public static function classLocked(string $className): self
    {
        return new self(
            "Kelas {$className} sudah final. Batalkan finalisasi untuk mengubah data kelas.",
            'CLASS_FINAL',
            423,
        );
    }
}
