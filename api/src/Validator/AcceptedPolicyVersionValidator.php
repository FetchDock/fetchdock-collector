<?php

namespace App\Validator;

use App\Policy\CollectorPolicy;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AcceptedPolicyVersionValidator extends ConstraintValidator
{
    public function __construct(private readonly CollectorPolicy $policy)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AcceptedPolicyVersion) {
            throw new UnexpectedTypeException($constraint, AcceptedPolicyVersion::class);
        }
        // NotBlank reports missing values
        if (null === $value || '' === $value) {
            return;
        }
        if (!\is_string($value) || !$this->policy->isAccepted($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', \is_scalar($value) ? (string) $value : get_debug_type($value))
                ->setParameter('{{ accepted }}', implode(', ', $this->policy->acceptedVersions))
                ->addViolation();
        }
    }
}
