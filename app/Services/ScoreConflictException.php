<?php

namespace App\Services;

use RuntimeException;

/** The row was changed elsewhere since the client loaded it (HTTP 409, PRD-04 §4.4). */
class ScoreConflictException extends RuntimeException
{
    /** @param array{scores: array, comment: ?string, version: int} $current */
    public function __construct(public array $current)
    {
        parent::__construct('Nilai diubah di perangkat lain.');
    }
}
