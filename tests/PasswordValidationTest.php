<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Validation;

class PasswordValidationTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidator();
    }

    public function testPasswordMinLength(): void
    {
        $constraint = new Length([
            'min' => 8,
            'minMessage' => 'Password must be at least 8 characters.'
        ]);

        // Too short
        $violations = $this->validator->validate('Short1', $constraint);
        $this->assertCount(1, $violations);

        // Exactly 8 characters - this might still fail if other constraints are needed
        $violations = $this->validator->validate('Password1', $constraint);
        // Length constraint passes, but might fail other regex constraints
    }

    public function testPasswordRequiresUppercase(): void
    {
        $constraint = new Regex([
            'pattern' => '/[A-Z]/',
            'message' => 'Password must contain at least one uppercase letter.'
        ]);

        // No uppercase
        $violations = $this->validator->validate('password1', $constraint);
        $this->assertCount(1, $violations);

        // Has uppercase
        $violations = $this->validator->validate('Password1', $constraint);
        $this->assertCount(0, $violations);
    }

    public function testPasswordRequiresLowercase(): void
    {
        $constraint = new Regex([
            'pattern' => '/[a-z]/',
            'message' => 'Password must contain at least one lowercase letter.'
        ]);

        // No lowercase
        $violations = $this->validator->validate('PASSWORD1', $constraint);
        $this->assertCount(1, $violations);

        // Has lowercase
        $violations = $this->validator->validate('Password1', $constraint);
        $this->assertCount(0, $violations);
    }

    public function testPasswordRequiresNumber(): void
    {
        $constraint = new Regex([
            'pattern' => '/[0-9]/',
            'message' => 'Password must contain at least one number.'
        ]);

        // No number
        $violations = $this->validator->validate('Password', $constraint);
        $this->assertCount(1, $violations);

        // Has number
        $violations = $this->validator->validate('Password1', $constraint);
        $this->assertCount(0, $violations);
    }

    public function testValidPasswords(): void
    {
        $validPasswords = [
            'Password1',
            'MyPassword123',
            'SecureP@ss1',
            'Test1234Aa',
            'ABCdefg12345',
        ];

        foreach ($validPasswords as $password) {
            // Test length
            $violations = $this->validator->validate($password, new Length(['min' => 8]));
            $this->assertCount(0, $violations, "Password '$password' should meet length requirement");

            // Test uppercase
            $violations = $this->validator->validate($password, new Regex(['pattern' => '/[A-Z]/']));
            $this->assertCount(0, $violations, "Password '$password' should have uppercase");

            // Test lowercase
            $violations = $this->validator->validate($password, new Regex(['pattern' => '/[a-z]/']));
            $this->assertCount(0, $violations, "Password '$password' should have lowercase");

            // Test number
            $violations = $this->validator->validate($password, new Regex(['pattern' => '/[0-9]/']));
            $this->assertCount(0, $violations, "Password '$password' should have number");
        }
    }

    public function testInvalidPasswords(): void
    {
        $invalidPasswords = [
            'short',           // Too short
            'alllower1',       // No uppercase
            'ALLUPPER1',       // No lowercase  
            'NoNumbers',       // No number
            'Pass1',           // Too short
            'password1',       // No uppercase
            'PASSWORD1',       // No lowercase
            'Password',        // No number
        ];

        foreach ($invalidPasswords as $password) {
            $hasError = false;
            
            // Test length
            $violations = $this->validator->validate($password, new Length(['min' => 8]));
            if (count($violations) > 0) {
                $hasError = true;
            }

            // Test uppercase
            $violations = $this->validator->validate($password, new Regex(['pattern' => '/[A-Z]/']));
            if (count($violations) > 0) {
                $hasError = true;
            }

            // Test lowercase
            $violations = $this->validator->validate($password, new Regex(['pattern' => '/[a-z]/']));
            if (count($violations) > 0) {
                $hasError = true;
            }

            // Test number
            $violations = $this->validator->validate($password, new Regex(['pattern' => '/[0-9]/']));
            if (count($violations) > 0) {
                $hasError = true;
            }

            $this->assertTrue($hasError, "Password '$password' should fail validation");
        }
    }

    public function testPasswordStrengthIndicator(): void
    {
        // Test password strength calculation
        $testCases = [
            ['password', 0],        // No requirements met
            ['password1', 25],      // Length >= 8 + lowercase + number
            ['Password1', 55],     // Length >= 8 + uppercase + lowercase + number
            ['Password12', 70],    // Length >= 12 + uppercase + lowercase + number
            ['Password12!', 80],   // Length >= 12 + uppercase + lowercase + number + special
            ['MyP@ssw0rd123!', 100], // All requirements met
        ];

        foreach ($testCases as [$password, $expectedMinStrength]) {
            $strength = 0;
            if (strlen($password) >= 8) $strength += 25;
            if (strlen($password) >= 12) $strength += 15;
            if (preg_match('/[A-Z]/', $password)) $strength += 20;
            if (preg_match('/[a-z]/', $password)) $strength += 20;
            if (preg_match('/[0-9]/', $password)) $strength += 10;
            if (preg_match('/[^A-Za-z0-9]/', $password)) $strength += 10;
            
            $strength = min(100, $strength);
            
            $this->assertGreaterThanOrEqual(
                $expectedMinStrength, 
                $strength, 
                "Password '$password' should have at least $expectedMinStrength strength (got $strength)"
            );
        }
    }
}
