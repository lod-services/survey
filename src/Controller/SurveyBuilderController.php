<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SurveyBuilderController extends AbstractController
{
    #[Route('/survey-builder', name: 'survey_builder')]
    public function index(): Response
    {
        return $this->render('survey_builder/index.html.twig', [
            'title' => 'AI-Powered Survey Builder',
        ]);
    }

    #[Route('/survey-builder/{id}', name: 'survey_builder_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        return $this->render('survey_builder/index.html.twig', [
            'title' => 'Edit Survey',
            'surveyId' => $id,
        ]);
    }
}