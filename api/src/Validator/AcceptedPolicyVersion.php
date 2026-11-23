<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * The value is one of the collector's accepted policy versions (CollectorPolicy).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AcceptedPolicyVersion extends Constraint
{
    public string $message = 'Policy version "{{ value }}" is not accepted. Accepted versions: {{ accepted }}.';
}
