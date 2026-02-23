<?php

namespace App\Tests;

use App\Entity\PasswordReset;
use App\Entity\User;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetServiceTest extends TestCase
{
    private PasswordResetService $service;
    private $entityManager;
    private $userRepository;
    private $passwordResetRepository;
    private $mailer;
    private $urlGenerator;
    private $logger;
    private string $appEmailFrom = 'noreply@docmanager.com';
    private string $appBaseUrl = 'http://localhost';

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordResetRepository = $this->createMock(PasswordResetRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new PasswordResetService(
            $this->entityManager,
            $this->userRepository,
            $this->passwordResetRepository,
            $this->mailer,
            $this->urlGenerator,
            $this->logger,
            $this->appEmailFrom,
            $this->appBaseUrl
        );
    }

    public function testRequestResetForNonExistentEmail(): void
    {
        // Arrange
        $email = 'nonexistent@example.com';
        
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Password reset requested for non-existent email', ['email' => $email]);

        // Act
        $result = $this->service->requestReset($email);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('If an account exists', $result['message']);
    }

    public function testRequestResetForExistingUser(): void
    {
        // Arrange
        $email = 'user@example.com';
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn($email);

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($user);

        $this->passwordResetRepository->expects($this->once())
            ->method('invalidateAllForUser')
            ->with(1);

        $this->passwordResetRepository->expects($this->once())
            ->method('findValidTokensByUser')
            ->with(1)
            ->willReturn([]);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('/reset-password/token123');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($user) {
                return $email->getTo()[0]->getAddress() === 'user@example.com';
            }));

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Password reset requested', ['user_id' => 1, 'email' => $email]);

        // Act
        $result = $this->service->requestReset($email);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('If an account exists', $result['message']);
    }

    public function testRequestResetRateLimiting(): void
    {
        // Arrange
        $email = 'user@example.com';
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn($email);

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($user);

        $this->passwordResetRepository->expects($this->once())
            ->method('findValidTokensByUser')
            ->with(1)
            ->willReturn([1, 2, 3]); // 3 requests already

        // No further DB operations should occur due to rate limiting
        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Password reset rate limit exceeded', ['user_id' => 1, 'count' => 3]);

        // Act
        $result = $this->service->requestReset($email);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('If an account exists', $result['message']);
    }

    public function testResetPasswordWithInvalidToken(): void
    {
        // Arrange
        $token = 'invalid_token';
        
        $this->passwordResetRepository->expects($this->once())
            ->method('findValidToken')
            ->with(hash('sha256', $token))
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Invalid or expired password reset token used');

        // Act
        $result = $this->service->resetPassword($token, 'newPassword123');

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('invalid or expired', $result['message']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testResetPasswordWithValidToken(): void
    {
        // Arrange
        $token = 'valid_token';
        $newPassword = 'newPassword123';
        
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('setPassword')
            ->with($newPassword);

        $passwordReset = $this->createMock(PasswordReset::class);
        $passwordReset->method('getUser')
            ->willReturn($user);

        $this->passwordResetRepository->expects($this->once())
            ->method('findValidToken')
            ->with(hash('sha256', $token))
            ->willReturn($passwordReset);

        $this->passwordResetRepository->expects($this->once())
            ->method('invalidateAllForUser')
            ->with($user);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Password reset successful', ['user_id' => null]);

        // Act
        $result = $this->service->resetPassword($token, $newPassword);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('successfully', $result['message']);
    }

    public function testValidateTokenWithInvalidToken(): void
    {
        // Arrange
        $token = 'invalid_token';
        
        $this->passwordResetRepository->expects($this->once())
            ->method('findValidToken')
            ->with(hash('sha256', $token))
            ->willReturn(null);

        // Act
        $result = $this->service->validateToken($token);

        // Assert
        $this->assertFalse($result);
    }

    public function testValidateTokenWithValidToken(): void
    {
        // Arrange
        $token = 'valid_token';
        
        $passwordReset = $this->createMock(PasswordReset::class);
        
        $this->passwordResetRepository->expects($this->once())
            ->method('findValidToken')
            ->with(hash('sha256', $token))
            ->willReturn($passwordReset);

        // Act
        $result = $this->service->validateToken($token);

        // Assert
        $this->assertTrue($result);
    }

    public function testTokenIsSecureRandom(): void
    {
        // Test that multiple token generations produce unique tokens
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            // Use reflection to call the private method
            $reflection = new \ReflectionClass($this->service);
            $method = $reflection->getMethod('generateSecureToken');
            $method->setAccessible(true);
            $tokens[] = $method->invoke($this->service);
        }

        // All tokens should be unique
        $this->assertCount(10, array_unique($tokens));
        
        // Each token should be 64 characters (32 bytes = 64 hex chars)
        foreach ($tokens as $token) {
            $this->assertEquals(64, strlen($token));
        }
    }
}
