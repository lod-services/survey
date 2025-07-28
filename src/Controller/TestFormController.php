<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex;

class TestFormController extends AbstractController
{
    #[Route('/test-form', name: 'app_test_form')]
    public function testForm(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 2, 'max' => 100]),
                    new Regex([
                        'pattern' => '/^[\p{L}\p{M}\s\'-\.]+$/u',
                        'message' => 'Name may only contain letters, spaces, hyphens, apostrophes, and periods'
                    ])
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Email(['mode' => Email::VALIDATION_MODE_STRICT])
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 10, 'max' => 1000])
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit Test Form',
            ])
            ->getForm();

        $form->handleRequest($request);

        $submitResult = null;
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Use validated data directly - no manual sanitization needed
                $data = $form->getData();
                
                $submitResult = [
                    'status' => 'success',
                    'message' => 'Form submitted successfully with valid CSRF token!',
                    'data' => $data
                ];
            } else {
                // Don't expose detailed error messages to users for security
                $submitResult = [
                    'status' => 'error',
                    'message' => 'Form validation failed. Please check your input and try again.',
                ];
                
                // Log detailed errors for debugging (in production, use proper logging)
                if ($this->getParameter('kernel.environment') === 'dev') {
                    $submitResult['debug_errors'] = (string)$form->getErrors(true);
                }
            }
        }

        return $this->render('test_form/index.html.twig', [
            'form' => $form->createView(),
            'submitResult' => $submitResult,
        ]);
    }
}