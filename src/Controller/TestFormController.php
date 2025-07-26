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

class TestFormController extends AbstractController
{
    #[Route('/test-form', name: 'app_test_form')]
    public function testForm(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'required' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit Test Form',
            ])
            ->getForm();

        $form->handleRequest($request);

        $submitResult = null;
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Sanitize and validate data before processing
                $data = $form->getData();
                $sanitizedData = [
                    'name' => htmlspecialchars($data['name'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'email' => filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL),
                    'message' => htmlspecialchars($data['message'] ?? '', ENT_QUOTES, 'UTF-8'),
                ];
                
                $submitResult = [
                    'status' => 'success',
                    'message' => 'Form submitted successfully with valid CSRF token!',
                    'data' => $sanitizedData
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