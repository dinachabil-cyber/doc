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
use Twig\Environment;

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
        private readonly Environment $twig,
        private readonly string $appEmailFrom,
        private readonly string $appBaseUrl
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
        $resetUrl = $this->appBaseUrl . '/reset-password/' . $token;

        // In development, also log the reset URL for testing without email
        if (strpos($this->appBaseUrl, 'localhost') !== false || strpos($this->appBaseUrl, 'docmanager.ddev') !== false) {
            $this->logger->info('PASSWORD RESET URL (DEV MODE): ' . $resetUrl, [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
        }

        try {
            $htmlContent = $this->twig->render('emails/reset_password.html.twig', [
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expiryMinutes' => self::TOKEN_EXPIRY_MINUTES
            ]);

            $email = (new Email())
                ->from($this->appEmailFrom)
                ->to($user->getEmail())
                ->subject('Password Reset Request - DocManager')
                ->html($htmlContent);

            $this->mailer->send($email);
            
            $this->logger->info('Password reset email sent', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
        } catch (\Exception $e) {
            // Log the error but don't break the flow - in dev, mailer might not work
            $this->logger->error('Failed to send password reset email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
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
