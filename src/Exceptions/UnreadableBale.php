<?php

declare(strict_types=1);

namespace Sammyjo20\LaravelHaystack\Exceptions;

use Exception;
use __PHP_Incomplete_Class;
use Sammyjo20\LaravelHaystack\Models\HaystackBale;

class UnreadableBale extends Exception
{
    /**
     * Constructor
     */
    public function __construct(HaystackBale $bale, mixed $job)
    {
        $class = $job instanceof __PHP_Incomplete_Class
            ? ((array) $job)['__PHP_Incomplete_Class_Name']
            : get_debug_type($job);

        parent::__construct(sprintf('Haystack bale [%s] holds a job that can no longer be unserialized into a stackable job: %s', $bale->getKey(), $class));
    }
}
