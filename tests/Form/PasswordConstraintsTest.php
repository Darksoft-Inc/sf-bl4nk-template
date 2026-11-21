<?php

namespace App\Tests\Form;

use App\Form\PasswordConstraints;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

final class PasswordConstraintsTest extends TestCase
{
    public function testDevelopmentUsesShorterPasswordsWithoutExtraChecks(): void
    {
        $constraints = (new PasswordConstraints('dev'))->forNewPassword();

        $length = $this->findConstraint($constraints, Length::class);
        self::assertInstanceOf(Length::class, $length);
        self::assertSame(6, $length->min);
        self::assertNull($this->findConstraint($constraints, PasswordStrength::class));
        self::assertNull($this->findConstraint($constraints, NotCompromisedPassword::class));
    }

    public function testTestAndProductionUseStrictRules(): void
    {
        foreach (['test', 'prod'] as $environment) {
            $constraints = (new PasswordConstraints($environment))->forNewPassword();

            $length = $this->findConstraint($constraints, Length::class);
            self::assertInstanceOf(Length::class, $length);
            self::assertSame(12, $length->min);
            self::assertNotNull($this->findConstraint($constraints, PasswordStrength::class));
            self::assertNotNull($this->findConstraint($constraints, NotCompromisedPassword::class));
        }
    }

    /**
     * @param list<Constraint> $constraints
     * @param class-string     $class
     */
    private function findConstraint(array $constraints, string $class): ?object
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof $class) {
                return $constraint;
            }
        }

        return null;
    }
}
