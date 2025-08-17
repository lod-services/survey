<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SecureEmail extends Constraint
{
    public string $message = 'This email address contains potentially dangerous characters.';
    public string $nullByteMessage = 'Email address cannot contain null bytes.';
    public string $suspiciousMessage = 'Email address contains suspicious patterns.';
}