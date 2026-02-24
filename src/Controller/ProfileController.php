<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ProfileEditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'isEditMode' => false,
            'showPasswordForm' => false,
            'passwordErrors' => [],
            'passwordSubmitted' => false,
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $hasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        
        // Store original values for cancel
        $originalData = [
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
        ];

        $errors = [];
        
        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->get('current_password', '');
            $email = $request->request->get('email');
            $username = $request->request->get('username');
            
            // Validate current password is required for any profile changes
            if (empty($currentPassword)) {
                $errors['current_password'] = 'Current password is required to make changes.';
            } elseif (!$hasher->isPasswordValid($user, $currentPassword)) {
                $errors['current_password'] = 'Current password is incorrect.';
            }
            
            // Validate email
            if (empty($email)) {
                $errors['email'] = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address.';
            } else {
                // Check if email is already used by another user
                $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $errors['email'] = 'This email is already in use.';
                }
            }
            
            if (empty($errors)) {
                $user->setEmail($email);
                $user->setUsername($username);
                
                $em->flush();
                
                $this->addFlash('success', 'Profile updated successfully ✅');
                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'isEditMode' => true,
            'errors' => $errors,
            'submittedData' => $request->isMethod('POST') ? [
                'email' => $request->request->get('email'),
                'username' => $request->request->get('username'),
            ] : null,
            'showPasswordForm' => false,
            'passwordErrors' => [],
            'passwordSubmitted' => false,
        ]);
    }

    #[Route('/profile/password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ValidatorInterface $validator
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $errors = [];
        $showPasswordForm = $request->query->get('show') === 'true';

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->get('current_password', '');
            $newPassword = $request->request->get('new_password', '');
            $confirmPassword = $request->request->get('confirm_password', '');

            // Validate current password
            if (empty($currentPassword)) {
                $errors['current_password'] = 'Current password is required.';
            } elseif (!$hasher->isPasswordValid($user, $currentPassword)) {
                $errors['current_password'] = 'Current password is incorrect.';
            }

            // Validate new password
            if (empty($newPassword)) {
                $errors['new_password'] = 'New password is required.';
            } else {
                $passwordViolations = $validator->validate($newPassword, [
                    new \Symfony\Component\Validator\Constraints\Length([
                        'min' => 8,
                        'minMessage' => 'Password must be at least 8 characters.'
                    ]),
                    new \Symfony\Component\Validator\Constraints\Regex([
                        'pattern' => '/[A-Z]/',
                        'message' => 'Password must contain at least one uppercase letter.'
                    ]),
                    new \Symfony\Component\Validator\Constraints\Regex([
                        'pattern' => '/[a-z]/',
                        'message' => 'Password must contain at least one lowercase letter.'
                    ]),
                    new \Symfony\Component\Validator\Constraints\Regex([
                        'pattern' => '/[0-9]/',
                        'message' => 'Password must contain at least one number.'
                    ])
                ]);

                foreach ($passwordViolations as $violation) {
                    $errors['new_password'] = $violation->getMessage();
                }
            }

            // Validate confirm password
            if (empty($confirmPassword)) {
                $errors['confirm_password'] = 'Please confirm your new password.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                // Hash and set new password
                $user->setPassword($hasher->hashPassword($user, $newPassword));
                $em->flush();

                $this->addFlash('success', 'Password updated successfully 🔒');
                
                // Redirect to profile view
                return $this->redirectToRoute('app_profile');
            }
            
            $showPasswordForm = true;
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'isEditMode' => false,
            'showPasswordForm' => $showPasswordForm,
            'passwordErrors' => $errors,
            'passwordSubmitted' => $request->isMethod('POST'),
        ]);
    }
}
