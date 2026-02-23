<?php

namespace App\Controller;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function reset(
        string $token,
        Request $request,
        PasswordResetService $passwordResetService,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): Response {
        // Redirect logged-in users to profile
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        // Validate token first
        if (!$passwordResetService->validateToken($token)) {
            $this->addFlash('error', 'This password reset link is invalid or has expired. Please request a new one.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $errors = [];
        $password = '';
        $confirmPassword = '';

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirm_password', '');

            // Validate password
            $passwordViolations = $validator->validate($password, [
                new NotBlank(['message' => 'Password is required.']),
                new Length([
                    'min' => 8,
                    'minMessage' => 'Password must be at least 8 characters.'
                ]),
                new Regex([
                    'pattern' => '/[A-Z]/',
                    'message' => 'Password must contain at least one uppercase letter.'
                ]),
                new Regex([
                    'pattern' => '/[a-z]/',
                    'message' => 'Password must contain at least one lowercase letter.'
                ]),
                new Regex([
                    'pattern' => '/[0-9]/',
                    'message' => 'Password must contain at least one number.'
                ])
            ]);

            foreach ($passwordViolations as $violation) {
                $errors['password'] = $violation->getMessage();
            }

            // Validate confirm password
            if (empty($confirmPassword)) {
                $errors['confirm_password'] = 'Please confirm your password.';
            } elseif ($password !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                // Hash the password
                $hashedPassword = $passwordHasher->hashPassword(
                    new \App\Entity\User(),
                    $password
                );

                // Reset the password
                $result = $passwordResetService->resetPassword($token, $hashedPassword);

                if ($result['success']) {
                    $this->addFlash('success', $result['message']);
                    return $this->redirectToRoute('app_login');
                } else {
                    $this->addFlash('error', $result['message']);
                    return $this->redirectToRoute('app_forgot_password');
                }
            }
        }

        return $this->render('reset_password/index.html.twig', [
            'token' => $token,
            'password' => $password,
            'confirmPassword' => $confirmPassword,
            'errors' => $errors
        ]);
    }
}
