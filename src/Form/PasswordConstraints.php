<?php

namespace App\Form;

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
                message: 'Please enter a password',
            ),
            new Length(
                min: $isDevelopment ? 6 : 12,
                minMessage: 'Your password should be at least {{ limit }} characters',
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
