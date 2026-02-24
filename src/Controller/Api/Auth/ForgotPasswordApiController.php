<?php

namespace App\Controller\Api\Auth;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/api/auth', name: 'api_auth_')]
class ForgotPasswordApiController extends AbstractController
{
    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function requestPasswordReset(
        Request $request,
        PasswordResetService $passwordResetService,
        ValidatorInterface $validator
    ): JsonResponse {
        // Get JSON data
        $data = json_decode($request->getContent(), true);
        
        $email = $data['email'] ?? '';

        // Validate email
        $constraints = new Assert\Collection([
            'email' => [
                new Assert\NotBlank(['message' => 'Email is required.']),
                new Assert\Email(['message' => 'Please enter a valid email address.'])
            ]
        ]);

        $violations = $validator->validate($data ?? [], $constraints);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage()
                ];
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        // Process password reset request
        // Always return the same response to prevent email enumeration
        $result = $passwordResetService->requestReset($email);

        return new JsonResponse([
            'success' => true,
            'message' => 'If the email exists, a reset link has been sent.'
        ], Response::HTTP_OK);
    }
}
