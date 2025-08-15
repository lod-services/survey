<?php

namespace App\Controller;

use App\Entity\Template;
use App\Entity\TemplateRating;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/templates', name: 'app_template_marketplace')]
    public function marketplace(Request $request): Response
    {
        $industry = $request->query->get('industry');
        $category = $request->query->get('category');
        $search = $request->query->get('search');

        $queryBuilder = $this->entityManager->getRepository(Template::class)
            ->createQueryBuilder('t')
            ->where('t.isPublic = :isPublic')
            ->setParameter('isPublic', true)
            ->orderBy('t.averageRating', 'DESC')
            ->addOrderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if ($industry) {
            $queryBuilder->andWhere('t.industry = :industry')
                ->setParameter('industry', $industry);
        }

        if ($category) {
            $queryBuilder->andWhere('t.category = :category')
                ->setParameter('category', $category);
        }

        if ($search) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    't.title LIKE :search',
                    't.description LIKE :search'
                )
            )->setParameter('search', '%' . $search . '%');
        }

        $templates = $queryBuilder->getQuery()->getResult();

        $industries = $this->entityManager->getRepository(Template::class)
            ->createQueryBuilder('t')
            ->select('DISTINCT t.industry')
            ->where('t.isPublic = :isPublic')
            ->setParameter('isPublic', true)
            ->orderBy('t.industry', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        $categories = $this->entityManager->getRepository(Template::class)
            ->createQueryBuilder('t')
            ->select('DISTINCT t.category')
            ->where('t.isPublic = :isPublic')
            ->setParameter('isPublic', true)
            ->orderBy('t.category', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('template/marketplace.html.twig', [
            'templates' => $templates,
            'industries' => $industries,
            'categories' => $categories,
            'selectedIndustry' => $industry,
            'selectedCategory' => $category,
            'searchTerm' => $search,
        ]);
    }

    #[Route('/template/{id}', name: 'app_template_detail')]
    public function detail(Template $template): Response
    {
        if (!$template->isPublic()) {
            throw $this->createNotFoundException('Template not found');
        }

        $ratings = $this->entityManager->getRepository(TemplateRating::class)
            ->findBy(['template' => $template], ['createdAt' => 'DESC'], 10);

        return $this->render('template/detail.html.twig', [
            'template' => $template,
            'ratings' => $ratings,
        ]);
    }

    #[Route('/template/{id}/use', name: 'app_template_use')]
    public function useTemplate(Template $template): Response
    {
        if (!$template->isPublic()) {
            throw $this->createNotFoundException('Template not found');
        }

        $template->incrementUsageCount();
        $this->entityManager->flush();

        return $this->render('template/use.html.twig', [
            'template' => $template,
        ]);
    }

    #[Route('/template/{id}/rate', name: 'app_template_rate', methods: ['POST'])]
    public function rateTemplate(Template $template, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $rating = (int) $request->request->get('rating');
        $review = $request->request->get('review');

        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'Invalid rating. Please select a rating between 1 and 5.');
            return $this->redirectToRoute('app_template_detail', ['id' => $template->getId()]);
        }

        $user = $this->getUser();
        $existingRating = $this->entityManager->getRepository(TemplateRating::class)
            ->findOneBy(['user' => $user, 'template' => $template]);

        if ($existingRating) {
            $existingRating->setRating($rating);
            $existingRating->setReview($review);
        } else {
            $templateRating = new TemplateRating();
            $templateRating->setUser($user);
            $templateRating->setTemplate($template);
            $templateRating->setRating($rating);
            $templateRating->setReview($review);
            $this->entityManager->persist($templateRating);
        }

        $this->entityManager->flush();

        $template->calculateAverageRating();
        $this->entityManager->flush();

        $this->addFlash('success', 'Your rating has been saved.');
        return $this->redirectToRoute('app_template_detail', ['id' => $template->getId()]);
    }
}