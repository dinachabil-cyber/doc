<?php

namespace App\Controller;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function request(
        Request $request,
        PasswordResetService $passwordResetService,
        ValidatorInterface $validator,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        // Redirect logged-in users to profile
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        $email = $request->request->get('email', '');
        $errors = [];

        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $csrfToken = $request->request->get('_csrf_token');
            if (!$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('forgot_password', $csrfToken))) {
                $this->addFlash('error', 'Invalid security token. Please try again.');
                return $this->redirectToRoute('app_forgot_password');
            }

            // Validate email
            $violations = $validator->validate($email, [
                new NotBlank(['message' => 'Email is required.']),
                new Email(['message' => 'Please enter a valid email address.'])
            ]);

            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $errors['email'] = $violation->getMessage();
                }
            } else {
                // Process password reset request
                $result = $passwordResetService->requestReset($email);
                
                // Add flash message
                $this->addFlash('success', $result['message']);
                
                // Redirect to avoid Turbo Drive form submission error
                // Turbo Drive requires form submissions to redirect
                return $this->redirectToRoute('app_forgot_password', [], Response::HTTP_FOUND);
            }
        }

        return $this->render('forgot_password/index.html.twig', [
            'email' => $email,
            'errors' => $errors
        ]);
    }
}
