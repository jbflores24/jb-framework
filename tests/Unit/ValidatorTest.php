<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\Tests\BaseTestCase;
use Jb\Validation\Validator;

final class ValidatorTest extends BaseTestCase
{
    public function testValidationPassesWithValidData(): void
    {
        $validator = new Validator();

        $isValid = $validator->validate(
            ['nombre' => 'Juan', 'email' => 'juan@example.com', 'rol' => 'admin'],
            ['nombre' => 'required|string|min:3', 'email' => 'required|email', 'rol' => 'in:admin,user']
        );

        $this->assertTrue($isValid);
        $this->assertSame([], $validator->errors());
    }

    public function testValidationCollectsErrorsByField(): void
    {
        $validator = new Validator();

        $isValid = $validator->validate(
            ['nombre' => 'Jo', 'email' => 'correo-invalido'],
            ['nombre' => 'required|min:3', 'email' => 'required|email']
        );

        $errors = $validator->errors();

        $this->assertFalse($isValid);
        $this->assertArrayHasKey('nombre', $errors);
        $this->assertArrayHasKey('email', $errors);
    }
}
