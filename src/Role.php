<?php
declare(strict_types=1);

namespace App;

/** The four kinds of account. Using an enum means a typo like 'techer' is an error, not a silent bug. */
enum Role: string
{
    case Student = 'student';
    case Parent  = 'parent';
    case Teacher = 'teacher';
    case Curator = 'curator';

    public function label(): string
    {
        return t('role.' . $this->value);
    }
}
