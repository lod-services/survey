<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        AuditService $auditService
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $error = null;

        if ($request->isMethod('POST')) {
            $username = $request->request->get('username');
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            // Basic validation
            if (empty($username) || empty($email) || empty($password)) {
                $error = 'All fields are required.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } elseif (!$this->isPasswordStrong($password)) {
                $error = 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
            } else {
                $user->setUsername($username);
                $user->setEmail($email);
                
                // Check if username or email already exists
                $existingUserByUsername = $entityManager->getRepository(User::class)->findOneBy([
                    'username' => $username
                ]);
                
                $existingUserByEmail = $entityManager->getRepository(User::class)->findOneBy([
                    'email' => $email
                ]);
                
                if ($existingUserByUsername || $existingUserByEmail) {
                    $error = 'A user with these credentials already exists.';
                } else {
                    // Validate the entity
                    $violations = $validator->validate($user);
                    
                    if (count($violations) > 0) {
                        $error = (string) $violations[0]->getMessage();
                    } else {
                        // Hash the password and save the user
                        $user->setPassword(
                            $userPasswordHasher->hashPassword($user, $password)
                        );
                        
                        $entityManager->persist($user);
                        $entityManager->flush();
                        
                        // Log the registration
                        $auditService->logRegistration($user, $request);
                        
                        $this->addFlash('success', 'Account created successfully! You can now log in.');
                        return $this->redirectToRoute('app_login');
                    }
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'error' => $error,
            'user' => $user,
        ]);
    }

    private function isPasswordStrong(string $password): bool
    {
        // At least 8 characters long
        if (strlen($password) < 8) {
            return false;
        }

        // Must contain at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // Must contain at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        // Must contain at least one number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        // Must contain at least one special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }

        return true;
    }
}