<?php
declare(strict_types=1);

namespace App;

/** A business rule was broken ("slot is full"). Carries a translation key so the page can explain why. */
final class RuleException extends \RuntimeException
{
    public function __construct(public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($key);
    }
}
