<?php

namespace App\Controller;

use App\Service\TemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TemplateLibraryController extends AbstractController
{
    public function __construct(
        private TemplateService $templateService
    ) {
    }

    #[Route('/templates', name: 'template_library', methods: ['GET'])]
    public function library(Request $request): Response
    {
        $filters = [
            'industry' => $request->query->get('industry'),
            'category' => $request->query->get('category'),
            'search' => $request->query->get('search'),
            'orderBy' => $request->query->get('orderBy', 'popularity')
        ];

        // Remove empty filters
        $filters = array_filter($filters, fn($value) => !empty($value));

        $templates = $this->templateService->getTemplates($filters);
        $industries = $this->templateService->getAvailableIndustries();
        $categories = $this->templateService->getAvailableCategories();

        return $this->render('template_library/index.html.twig', [
            'templates' => $templates,
            'industries' => $industries,
            'categories' => $categories,
            'currentFilters' => $filters
        ]);
    }

    #[Route('/templates/{id}', name: 'template_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function preview(int $id): Response
    {
        $template = $this->templateService->getTemplate($id);

        if (!$template) {
            throw $this->createNotFoundException('Template not found');
        }

        return $this->render('template_library/preview.html.twig', [
            'template' => $template
        ]);
    }

    #[Route('/templates/{id}/deploy-form', name: 'template_deploy_form', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function deployForm(int $id): Response
    {
        $template = $this->templateService->getTemplate($id);

        if (!$template) {
            throw $this->createNotFoundException('Template not found');
        }

        return $this->render('template_library/deploy_form.html.twig', [
            'template' => $template
        ]);
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $popularTemplates = $this->templateService->getPopularTemplates(6);
        $industries = $this->templateService->getAvailableIndustries();
        
        return $this->render('dashboard/index.html.twig', [
            'popularTemplates' => $popularTemplates,
            'industries' => $industries
        ]);
    }
}