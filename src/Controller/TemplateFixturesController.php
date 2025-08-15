<?php

namespace App\Controller;

use App\Entity\Template;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TemplateFixturesController extends AbstractController
{
    #[Route('/demo/templates', name: 'app_template_demo')]
    public function demo(): Response
    {
        $templates = $this->createSampleTemplates();

        $industries = array_unique(array_map(fn($t) => $t->getIndustry(), $templates));
        $categories = array_unique(array_map(fn($t) => $t->getCategory(), $templates));

        return $this->render('template/marketplace.html.twig', [
            'templates' => $templates,
            'industries' => $industries,
            'categories' => $categories,
            'selectedIndustry' => null,
            'selectedCategory' => null,
            'searchTerm' => null,
        ]);
    }

    #[Route('/demo/template/{id}', name: 'app_template_demo_detail')]
    public function demoDetail(int $id): Response
    {
        $templates = $this->createSampleTemplates();
        $template = $templates[$id - 1] ?? null;

        if (!$template) {
            throw $this->createNotFoundException('Template not found');
        }

        return $this->render('template/detail.html.twig', [
            'template' => $template,
            'ratings' => [],
        ]);
    }

    #[Route('/demo/template/{id}/use', name: 'app_template_demo_use')]
    public function demoUse(int $id): Response
    {
        $templates = $this->createSampleTemplates();
        $template = $templates[$id - 1] ?? null;

        if (!$template) {
            throw $this->createNotFoundException('Template not found');
        }

        return $this->render('template/use.html.twig', [
            'template' => $template,
        ]);
    }

    private function createSampleTemplates(): array
    {
        $templates = [];

        $template1 = new Template();
        $template1->setTitle('Employee Satisfaction Survey');
        $template1->setDescription('Comprehensive survey to measure employee satisfaction, engagement, and workplace culture. Includes questions about management, work-life balance, compensation, and career development opportunities.');
        $template1->setIndustry('Human Resources');
        $template1->setCategory('Employee Feedback');
        $template1->setPublic(true);
        $template1->setVerified(true);
        $template1->setUsageCount(847);
        $template1->setAverageRating('4.6');
        $template1->setComplianceRequirements(['GDPR Consent', 'Data Anonymization', 'Employee Privacy Rights']);
        $template1->setQuestionSchema([
            ['type' => 'select', 'label' => 'How satisfied are you with your current role?', 'options' => ['Very Satisfied', 'Satisfied', 'Neutral', 'Dissatisfied', 'Very Dissatisfied'], 'required' => true],
            ['type' => 'textarea', 'label' => 'What do you like most about working here?', 'placeholder' => 'Please share your thoughts...'],
            ['type' => 'select', 'label' => 'How would you rate your direct manager?', 'options' => ['Excellent', 'Good', 'Average', 'Poor', 'Very Poor']],
            ['type' => 'radio', 'label' => 'Would you recommend this company as a place to work?', 'options' => ['Definitely Yes', 'Probably Yes', 'Neutral', 'Probably No', 'Definitely No'], 'required' => true],
            ['type' => 'textarea', 'label' => 'Any suggestions for improvement?', 'placeholder' => 'Your feedback is valuable to us...']
        ]);
        $templates[] = $template1;

        $template2 = new Template();
        $template2->setTitle('Customer Satisfaction Survey');
        $template2->setDescription('Measure customer satisfaction with your products or services. Track Net Promoter Score (NPS), customer loyalty, and identify areas for improvement.');
        $template2->setIndustry('Retail');
        $template2->setCategory('Customer Experience');
        $template2->setPublic(true);
        $template2->setVerified(true);
        $template2->setUsageCount(623);
        $template2->setAverageRating('4.4');
        $template2->setComplianceRequirements(['GDPR Consent', 'Customer Data Protection']);
        $template2->setQuestionSchema([
            ['type' => 'select', 'label' => 'How satisfied are you with our product/service?', 'options' => ['Extremely Satisfied', 'Very Satisfied', 'Somewhat Satisfied', 'Not Very Satisfied', 'Not at All Satisfied'], 'required' => true],
            ['type' => 'select', 'label' => 'How likely are you to recommend us to others?', 'options' => ['10 - Extremely Likely', '9', '8', '7', '6', '5', '4', '3', '2', '1', '0 - Not at All Likely'], 'required' => true],
            ['type' => 'textarea', 'label' => 'What did you like most about your experience?'],
            ['type' => 'textarea', 'label' => 'How can we improve?'],
            ['type' => 'email', 'label' => 'Email (optional for follow-up)', 'required' => false]
        ]);
        $templates[] = $template2;

        $template3 = new Template();
        $template3->setTitle('Patient Feedback Survey');
        $template3->setDescription('HIPAA-compliant survey for healthcare providers to collect patient feedback on care quality, staff interactions, and facility experience.');
        $template3->setIndustry('Healthcare');
        $template3->setCategory('Patient Experience');
        $template3->setPublic(true);
        $template3->setVerified(true);
        $template3->setUsageCount(324);
        $template3->setAverageRating('4.8');
        $template3->setComplianceRequirements(['HIPAA Compliance', 'PHI Protection', 'Patient Privacy Rights', 'Secure Data Transmission']);
        $template3->setQuestionSchema([
            ['type' => 'select', 'label' => 'How would you rate the quality of care you received?', 'options' => ['Excellent', 'Very Good', 'Good', 'Fair', 'Poor'], 'required' => true],
            ['type' => 'select', 'label' => 'How satisfied were you with the staff communication?', 'options' => ['Very Satisfied', 'Satisfied', 'Neutral', 'Dissatisfied', 'Very Dissatisfied']],
            ['type' => 'radio', 'label' => 'Were your questions answered clearly?', 'options' => ['Yes, completely', 'Mostly', 'Somewhat', 'Not really', 'Not at all']],
            ['type' => 'textarea', 'label' => 'Additional comments about your visit', 'placeholder' => 'Please share any additional feedback...']
        ]);
        $templates[] = $template3;

        $template4 = new Template();
        $template4->setTitle('Market Research Survey');
        $template4->setDescription('Comprehensive market research template for gathering consumer insights, preferences, and market trends. Suitable for product development and marketing strategy.');
        $template4->setIndustry('Technology');
        $template4->setCategory('Market Research');
        $template4->setPublic(true);
        $template4->setVerified(false);
        $template4->setUsageCount(156);
        $template4->setAverageRating('4.2');
        $template4->setComplianceRequirements(['GDPR Consent', 'Data Processing Agreement']);
        $template4->setQuestionSchema([
            ['type' => 'select', 'label' => 'What is your age group?', 'options' => ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'], 'required' => true],
            ['type' => 'checkbox', 'label' => 'Which products do you currently use?', 'options' => ['Smartphones', 'Laptops', 'Tablets', 'Smart Watches', 'Smart Home Devices', 'Gaming Consoles']],
            ['type' => 'select', 'label' => 'How much do you typically spend on technology per year?', 'options' => ['Under $500', '$500-$1000', '$1000-$2000', '$2000-$5000', 'Over $5000']],
            ['type' => 'textarea', 'label' => 'What features are most important to you in a new product?']
        ]);
        $templates[] = $template4;

        $template5 = new Template();
        $template5->setTitle('Financial Services Compliance Survey');
        $template5->setDescription('SOX-compliant survey for financial institutions to assess internal controls, risk management, and regulatory compliance measures.');
        $template5->setIndustry('Finance');
        $template5->setCategory('Compliance & Risk');
        $template5->setPublic(true);
        $template5->setVerified(true);
        $template5->setUsageCount(89);
        $template5->setAverageRating('4.7');
        $template5->setComplianceRequirements(['SOX Compliance', 'Financial Data Protection', 'Audit Trail Requirements', 'Access Control Documentation']);
        $template5->setQuestionSchema([
            ['type' => 'radio', 'label' => 'Are internal controls adequately documented?', 'options' => ['Yes, fully documented', 'Mostly documented', 'Partially documented', 'Poorly documented', 'Not documented'], 'required' => true],
            ['type' => 'select', 'label' => 'How often are risk assessments conducted?', 'options' => ['Monthly', 'Quarterly', 'Semi-annually', 'Annually', 'As needed']],
            ['type' => 'textarea', 'label' => 'Describe any identified control deficiencies', 'required' => true],
            ['type' => 'radio', 'label' => 'Is segregation of duties properly maintained?', 'options' => ['Always', 'Usually', 'Sometimes', 'Rarely', 'Never']]
        ]);
        $templates[] = $template5;

        $template6 = new Template();
        $template6->setTitle('Educational Program Evaluation');
        $template6->setDescription('Comprehensive evaluation survey for educational programs, courses, and training sessions. Assess effectiveness, engagement, and learning outcomes.');
        $template6->setIndustry('Education');
        $template6->setCategory('Program Evaluation');
        $template6->setPublic(true);
        $template6->setVerified(true);
        $template6->setUsageCount(267);
        $template6->setAverageRating('4.3');
        $template6->setComplianceRequirements(['FERPA Compliance', 'Student Privacy Protection']);
        $template6->setQuestionSchema([
            ['type' => 'select', 'label' => 'How would you rate the overall quality of this program?', 'options' => ['Excellent', 'Very Good', 'Good', 'Fair', 'Poor'], 'required' => true],
            ['type' => 'select', 'label' => 'How relevant was the content to your needs?', 'options' => ['Extremely Relevant', 'Very Relevant', 'Moderately Relevant', 'Slightly Relevant', 'Not Relevant']],
            ['type' => 'textarea', 'label' => 'What was the most valuable aspect of this program?'],
            ['type' => 'textarea', 'label' => 'How could this program be improved?'],
            ['type' => 'radio', 'label' => 'Would you recommend this program to others?', 'options' => ['Definitely Yes', 'Probably Yes', 'Might or Might Not', 'Probably No', 'Definitely No']]
        ]);
        $templates[] = $template6;

        return $templates;
    }
}