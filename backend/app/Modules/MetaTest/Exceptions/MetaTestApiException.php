<?php

namespace App\Modules\MetaTest\Exceptions;

use Exception;
use Throwable;

class MetaTestApiException extends Exception
{
    protected ?array $metaError;

    public function __construct(
        string $message,
        int $code = 0,
        ?array $metaError = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->metaError = $metaError;
    }

    public function getMetaError(): ?array
    {
        return $this->metaError;
    }
}
