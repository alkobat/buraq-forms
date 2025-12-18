<?php

declare(strict_types=1);

namespace BuraqForms\Core\Exceptions;

/**
 * Thrown when user input or submission data fails validation.
 */
class ValidationException extends ServiceException
{
    /** @var array<string, list<string>> */
    private array $errors;

    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(string $message, array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
