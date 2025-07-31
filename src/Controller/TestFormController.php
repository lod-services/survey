<?php

namespace App\Controller;

use App\Service\ValidationService;
use App\Constraint\SafeSurveyText;
use App\Constraint\ValidSurveyEmail;
use App\Constraint\InputLengthLimits;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Psr\Log\LoggerInterface;

class TestFormController extends AbstractController
{
    public function __construct(
        private ValidationService $validationService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/test-form', name: 'app_test_form')]
    public function testForm(Request $request): Response
    {
        // Define validation constraints for each field
        $validationConstraints = [
            'name' => [
                new Assert\NotBlank(['message' => 'Name is required']),
                new SafeSurveyText(['maxLength' => 1000]),
                new InputLengthLimits(['textLimit' => 1000])
            ],
            'email' => [
                new Assert\NotBlank(['message' => 'Email is required']),
                new ValidSurveyEmail(['allowDisposable' => true]),
                new InputLengthLimits(['emailLimit' => 254])
            ],
            'message' => [
                new Assert\NotBlank(['message' => 'Message is required']),
                new SafeSurveyText(['maxLength' => 10000, 'allowHtml' => false]),
                new InputLengthLimits(['textareaLimit' => 10000])
            ]
        ];

        $form = $this->createFormBuilder()
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => true,
                'attr' => [
                    'maxlength' => 1000,
                    'placeholder' => 'Enter your full name'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'maxlength' => 254,
                    'placeholder' => 'Enter your email address'
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'required' => true,
                'attr' => [
                    'maxlength' => 10000,
                    'rows' => 5,
                    'placeholder' => 'Enter your message or feedback'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit Test Form',
                'attr' => ['class' => 'btn btn-primary']
            ])
            ->getForm();

        $form->handleRequest($request);

        $submitResult = null;
        $validationResult = null;
        $circuitBreakerStatus = null;

        if ($form->isSubmitted()) {
            // Get circuit breaker status for monitoring
            $circuitBreakerStatus = $this->validationService->getCircuitBreakerStatus();
            
            if ($form->isValid()) {
                $data = $form->getData();
                
                // Use comprehensive validation framework
                $validationResult = $this->validationService->validateAndSanitize(
                    $data,
                    $validationConstraints,
                    ['allow_extra_fields' => false, 'allow_missing_fields' => false]
                );
                
                if ($validationResult['valid']) {
                    $submitResult = [
                        'status' => 'success',
                        'message' => 'Form submitted successfully with comprehensive security validation!',
                        'data' => $validationResult['sanitized'],
                        'validation_method' => 'comprehensive_framework'
                    ];
                    
                    // Log successful validation for monitoring
                    $this->logger->info('Form validation successful', [
                        'form_type' => 'test_form',
                        'circuit_breaker_state' => $circuitBreakerStatus['state'],
                        'data_size' => strlen(serialize($data))
                    ]);
                } else {
                    $submitResult = [
                        'status' => 'error',
                        'message' => 'Security validation failed. Please check your input and try again.',
                        'errors' => $validationResult['errors']
                    ];
                    
                    // Log validation failure with security context
                    $this->logger->warning('Form security validation failed', [
                        'form_type' => 'test_form',
                        'error_count' => count($validationResult['errors']),
                        'circuit_breaker_state' => $circuitBreakerStatus['state'],
                        'client_ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent')
                    ]);
                }
            } else {
                // Handle Symfony form validation errors
                $submitResult = [
                    'status' => 'error',
                    'message' => 'Form validation failed. Please check your input and try again.',
                ];
                
                // In development, show detailed form errors
                if ($this->getParameter('kernel.environment') === 'dev') {
                    $formErrors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $formErrors[] = $error->getMessage();
                    }
                    $submitResult['debug_errors'] = $formErrors;
                }
                
                $this->logger->info('Symfony form validation failed', [
                    'form_type' => 'test_form',
                    'error_count' => count($form->getErrors(true))
                ]);
            }
        }

        return $this->render('test_form/index.html.twig', [
            'form' => $form->createView(),
            'submitResult' => $submitResult,
            'validationResult' => $validationResult,
            'circuitBreakerStatus' => $circuitBreakerStatus,
        ]);
    }
}