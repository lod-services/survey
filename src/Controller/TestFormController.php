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
                $data = $form->getData();
                $submitResult = [
                    'status' => 'success',
                    'message' => 'Form submitted successfully with valid CSRF token!',
                    'data' => $data
                ];
            } else {
                $submitResult = [
                    'status' => 'error',
                    'message' => 'Form validation failed. Please check your input.',
                ];
            }
        }

        return $this->render('test_form/index.html.twig', [
            'form' => $form->createView(),
            'submitResult' => $submitResult,
        ]);
    }
}