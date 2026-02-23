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

class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function request(
        Request $request,
        PasswordResetService $passwordResetService,
        ValidatorInterface $validator
    ): Response {
        // Redirect logged-in users to profile
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        $email = $request->request->get('email', '');
        $errors = [];
        $successMessage = null;

        if ($request->isMethod('POST')) {
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
                
                // Always show success message to prevent email enumeration
                $successMessage = $result['message'];
                
                // Clear email field
                $email = '';
            }
        }

        return $this->render('forgot_password/index.html.twig', [
            'email' => $email,
            'errors' => $errors,
            'successMessage' => $successMessage
        ]);
    }
}
