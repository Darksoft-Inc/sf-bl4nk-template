<?php

namespace App\Form\Support;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

final class PasswordConstraints
{
    public function __construct(
        #[Autowire(param: 'kernel.environment')] private readonly string $environment,
    ) {
    }

    /**
     * @return list<Constraint>
     */
    public function forNewPassword(): array
    {
        // Development accounts are created often. The breach check also needs a network call.
        $isDevelopment = 'dev' === $this->environment;

        $constraints = [
            new NotBlank(
                message: 'app.password.not_blank',
            ),
            new Length(
                min: $isDevelopment ? 6 : 12,
                minMessage: 'app.password.min_length',
                // Symfony limits the length of hashed passwords for security reasons.
                max: 4096,
            ),
        ];

        if (!$isDevelopment) {
            $constraints[] = new PasswordStrength();
            $constraints[] = new NotCompromisedPassword();
        }

        return $constraints;
    }
}
