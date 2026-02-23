<?php

namespace App\Service;

use App\Entity\PasswordReset;
use App\Entity\User;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service for handling password reset functionality
 * - Generates secure random tokens
 * - Stores hashed tokens in database
 * - Sends reset emails
 * - Validates tokens on reset
 */
class PasswordResetService
{
    private const TOKEN_LENGTH = 32;
    private const TOKEN_EXPIRY_MINUTES = 30;
    private const MAX_REQUESTS_PER_HOUR = 3;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly PasswordResetRepository $passwordResetRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $appEmailFrom = 'noreply@docmanager.com',
        private readonly string $appBaseUrl = 'http://localhost:8000'
    ) {}

    /**
     * Request a password reset for the given email
     * Returns always the same message to prevent email enumeration
     */
    public function requestReset(string $email): array
    {
        // Always return success message to prevent email enumeration
        $result = [
            'success' => true,
            'message' => 'If an account exists with this email, you will receive a password reset link shortly.'
        ];

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user === null) {
            $this->logger->info('Password reset requested for non-existent email', ['email' => $email]);
            return $result;
        }

        // Check rate limiting
        $recentRequests = $this->getRecentRequestCount($user->getId());
        if ($recentRequests >= self::MAX_REQUESTS_PER_HOUR) {
            $this->logger->warning('Password reset rate limit exceeded', [
                'user_id' => $user->getId(),
                'count' => $recentRequests
            ]);
            return $result;
        }

        // Invalidate any existing valid tokens
        $this->passwordResetRepository->invalidateAllForUser($user->getId());

        // Generate and store token
        $token = $this->generateSecureToken();
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable())->modify('+' . self::TOKEN_EXPIRY_MINUTES . ' minutes');

        $passwordReset = new PasswordReset();
        $passwordReset->setUser($user);
        $passwordReset->setTokenHash($tokenHash);
        $passwordReset->setExpiresAt($expiresAt);

        $this->em->persist($passwordReset);
        $this->em->flush();

        // Send email
        $this->sendResetEmail($user, $token);

        $this->logger->info('Password reset requested', [
            'user_id' => $user->getId(),
            'email' => $email
        ]);

        return $result;
    }

    /**
     * Reset password using the provided token
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        $tokenHash = hash('sha256', $token);
        $passwordReset = $this->passwordResetRepository->findValidToken($tokenHash);

        if ($passwordReset === null) {
            $this->logger->warning('Invalid or expired password reset token used');
            return [
                'success' => false,
                'message' => 'Invalid or expired password reset token.',
                'errors' => [['field' => 'token', 'message' => 'This password reset link is invalid or has expired.']]
            ];
        }

        $user = $passwordReset->getUser();
        
        // Update user password
        $user->setPassword($newPassword);
        
        // Mark token as used
        $passwordReset->setUsedAt(new \DateTimeImmutable());
        
        // Invalidate all other tokens for security
        $this->passwordResetRepository->invalidateAllForUser($user->getId());
        
        $this->em->flush();

        $this->logger->info('Password reset successful', [
            'user_id' => $user->getId()
        ]);

        return [
            'success' => true,
            'message' => 'Your password has been reset successfully. You can now log in with your new password.'
        ];
    }

    /**
     * Validate a reset token without using it
     */
    public function validateToken(string $token): bool
    {
        $tokenHash = hash('sha256', $token);
        $passwordReset = $this->passwordResetRepository->findValidToken($tokenHash);
        
        return $passwordReset !== null;
    }

    /**
     * Generate a cryptographically secure random token
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Send the password reset email
     */
    private function sendResetEmail(User $user, string $token): void
    {
        $resetUrl = $this->urlGenerator->generate('app_reset_password', [
            'token' => $token
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $fullResetUrl = $this->appBaseUrl . $resetUrl;

        $email = (new Email())
            ->from($this->appEmailFrom)
            ->to($user->getEmail())
            ->subject('Password Reset Request - DocManager')
            ->html($this->getResetEmailHtml($user, $fullResetUrl))
            ->text($this->getResetEmailText($user, $fullResetUrl));

        $this->mailer->send($email);
    }

    /**
     * Get HTML email template
     */
    private function getResetEmailHtml(User $user, string $resetUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Password Reset Request</h2>
        <p>Hello,</p>
        <p>We received a request to reset your password. Click the button below to create a new password:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{$resetUrl}" class="button">Reset Password</a>
        </p>
        <p>Or copy and paste this link in your browser:</p>
        <p style="word-break: break-all; color: #667eea;">{$resetUrl}</p>
        <p>This link will expire in 30 minutes.</p>
        <p>If you didn't request this, please ignore this email or contact support if you have concerns.</p>
        <div class="footer">
            <p>Best regards,<br>The DocManager Team</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get plain text email template
     */
    private function getResetEmailText(User $user, string $resetUrl): string
    {
        return <<<TEXT
Password Reset Request

Hello,

We received a request to reset your password. Use the link below to create a new password:

{$resetUrl}

This link will expire in 30 minutes.

If you didn't request this, please ignore this email or contact support if you have concerns.

Best regards,
The DocManager Team
TEXT;
    }

    /**
     * Get recent request count for rate limiting
     */
    private function getRecentRequestCount(int $userId): int
    {
        $oneHourAgo = (new \DateTimeImmutable())->modify('-1 hour');
        
        return $this->em->createQueryBuilder()
            ->select('COUNT(pr)')
            ->from(PasswordReset::class, 'pr')
            ->where('pr.user = :userId')
            ->andWhere('pr.createdAt > :oneHourAgo')
            ->setParameter('userId', $userId)
            ->setParameter('oneHourAgo', $oneHourAgo)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
