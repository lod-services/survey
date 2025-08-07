<?php

namespace App\Command;

use App\Entity\Template;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-templates',
    description: 'Seed the database with industry-specific survey templates',
)]
class SeedTemplatesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $templates = $this->getTemplateData();
        
        $io->progressStart(count($templates));

        foreach ($templates as $templateData) {
            $template = new Template();
            $template->setName($templateData['name']);
            $template->setDescription($templateData['description']);
            $template->setIndustry($templateData['industry']);
            $template->setCategory($templateData['category']);
            $template->setStructure($templateData['structure']);
            $template->setMetadata($templateData['metadata']);
            $template->setComplianceFlags($templateData['complianceFlags']);
            $template->setTags($templateData['tags']);
            $template->setUsageCount($templateData['usageCount'] ?? 0);
            $template->setAverageRating($templateData['averageRating'] ?? null);

            $this->entityManager->persist($template);
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf('Successfully seeded %d templates into the database.', count($templates)));

        return Command::SUCCESS;
    }

    private function getTemplateData(): array
    {
        return [
            [
                'name' => 'Employee Satisfaction Survey',
                'description' => 'Comprehensive employee satisfaction survey for measuring workplace engagement, culture, and employee experience.',
                'industry' => 'Human Resources',
                'category' => 'Employee Engagement',
                'structure' => [
                    'sections' => [
                        [
                            'title' => 'Overall Job Satisfaction',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How satisfied are you with your current role?',
                                    'scale' => '1-5',
                                    'required' => true
                                ],
                                [
                                    'type' => 'rating',
                                    'question' => 'How likely are you to recommend this company as a great place to work?',
                                    'scale' => '1-10',
                                    'required' => true
                                ]
                            ]
                        ],
                        [
                            'title' => 'Work Environment',
                            'questions' => [
                                [
                                    'type' => 'multiple_choice',
                                    'question' => 'Which aspect of your work environment do you value most?',
                                    'options' => ['Flexible hours', 'Remote work options', 'Office amenities', 'Team collaboration', 'Management support'],
                                    'required' => true
                                ],
                                [
                                    'type' => 'text',
                                    'question' => 'What improvements would you suggest for our workplace?',
                                    'required' => false
                                ]
                            ]
                        ]
                    ],
                    'branding' => [
                        'primaryColor' => '#007bff',
                        'logo' => null,
                        'companyName' => '{{COMPANY_NAME}}'
                    ]
                ],
                'metadata' => [
                    'estimatedTime' => 5,
                    'defaultSettings' => [
                        'anonymous' => true,
                        'multipleResponses' => false,
                        'responseLimit' => null
                    ]
                ],
                'complianceFlags' => ['GDPR'],
                'tags' => ['engagement', 'satisfaction', 'hr', 'workplace'],
                'usageCount' => 245,
                'averageRating' => 4.6
            ],
            [
                'name' => 'Customer Feedback Survey',
                'description' => 'Post-purchase customer feedback survey to measure satisfaction and gather improvement suggestions.',
                'industry' => 'Retail',
                'category' => 'Customer Experience',
                'structure' => [
                    'sections' => [
                        [
                            'title' => 'Purchase Experience',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How would you rate your overall shopping experience?',
                                    'scale' => '1-5',
                                    'required' => true
                                ],
                                [
                                    'type' => 'rating',
                                    'question' => 'How satisfied are you with the product quality?',
                                    'scale' => '1-5',
                                    'required' => true
                                ]
                            ]
                        ],
                        [
                            'title' => 'Service Quality',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How helpful was our customer service team?',
                                    'scale' => '1-5',
                                    'required' => false
                                ],
                                [
                                    'type' => 'text',
                                    'question' => 'Any suggestions for improving our service?',
                                    'required' => false
                                ]
                            ]
                        ]
                    ],
                    'branding' => [
                        'primaryColor' => '#28a745',
                        'logo' => null,
                        'companyName' => '{{COMPANY_NAME}}'
                    ]
                ],
                'metadata' => [
                    'estimatedTime' => 3,
                    'defaultSettings' => [
                        'anonymous' => false,
                        'multipleResponses' => false,
                        'responseLimit' => null
                    ]
                ],
                'complianceFlags' => ['GDPR', 'CCPA'],
                'tags' => ['customer', 'feedback', 'retail', 'satisfaction'],
                'usageCount' => 189,
                'averageRating' => 4.4
            ],
            [
                'name' => 'Patient Satisfaction Survey',
                'description' => 'Healthcare patient satisfaction survey compliant with HIPAA regulations.',
                'industry' => 'Healthcare',
                'category' => 'Patient Experience',
                'structure' => [
                    'sections' => [
                        [
                            'title' => 'Care Quality',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How would you rate the quality of care you received?',
                                    'scale' => '1-5',
                                    'required' => true
                                ],
                                [
                                    'type' => 'rating',
                                    'question' => 'How clearly did your healthcare provider explain your treatment?',
                                    'scale' => '1-5',
                                    'required' => true
                                ]
                            ]
                        ],
                        [
                            'title' => 'Facility Experience',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How clean and comfortable were the facilities?',
                                    'scale' => '1-5',
                                    'required' => true
                                ],
                                [
                                    'type' => 'text',
                                    'question' => 'Additional comments about your visit (optional)',
                                    'required' => false
                                ]
                            ]
                        ]
                    ],
                    'compliance' => [
                        'hipaa' => true,
                        'dataRetention' => '7 years',
                        'encryptionRequired' => true
                    ],
                    'branding' => [
                        'primaryColor' => '#6c757d',
                        'logo' => null,
                        'companyName' => '{{HEALTHCARE_PROVIDER_NAME}}'
                    ]
                ],
                'metadata' => [
                    'estimatedTime' => 4,
                    'defaultSettings' => [
                        'anonymous' => true,
                        'multipleResponses' => false,
                        'responseLimit' => null
                    ]
                ],
                'complianceFlags' => ['HIPAA', 'GDPR'],
                'tags' => ['healthcare', 'patient', 'satisfaction', 'medical'],
                'usageCount' => 156,
                'averageRating' => 4.7
            ],
            [
                'name' => 'Course Evaluation Survey',
                'description' => 'Student course evaluation survey for educational institutions to improve teaching quality.',
                'industry' => 'Education',
                'category' => 'Academic Assessment',
                'structure' => [
                    'sections' => [
                        [
                            'title' => 'Course Content',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How relevant was the course content to your learning objectives?',
                                    'scale' => '1-5',
                                    'required' => true
                                ],
                                [
                                    'type' => 'rating',
                                    'question' => 'How well-organized was the course material?',
                                    'scale' => '1-5',
                                    'required' => true
                                ]
                            ]
                        ],
                        [
                            'title' => 'Instructor Effectiveness',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How effective was the instructor in delivering the content?',
                                    'scale' => '1-5',
                                    'required' => true
                                ],
                                [
                                    'type' => 'text',
                                    'question' => 'What aspects of the course could be improved?',
                                    'required' => false
                                ]
                            ]
                        ]
                    ],
                    'branding' => [
                        'primaryColor' => '#17a2b8',
                        'logo' => null,
                        'companyName' => '{{INSTITUTION_NAME}}'
                    ]
                ],
                'metadata' => [
                    'estimatedTime' => 6,
                    'defaultSettings' => [
                        'anonymous' => true,
                        'multipleResponses' => false,
                        'responseLimit' => null
                    ]
                ],
                'complianceFlags' => ['FERPA', 'GDPR'],
                'tags' => ['education', 'course', 'evaluation', 'academic'],
                'usageCount' => 312,
                'averageRating' => 4.5
            ],
            [
                'name' => 'Financial Services Feedback',
                'description' => 'Client satisfaction survey for financial institutions with SOX compliance considerations.',
                'industry' => 'Finance',
                'category' => 'Client Satisfaction',
                'structure' => [
                    'sections' => [
                        [
                            'title' => 'Service Quality',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How satisfied are you with our financial services?',
                                    'scale' => '1-5',
                                    'required' => true
                                ],
                                [
                                    'type' => 'rating',
                                    'question' => 'How would you rate the professionalism of our advisors?',
                                    'scale' => '1-5',
                                    'required' => true
                                ]
                            ]
                        ],
                        [
                            'title' => 'Digital Experience',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How user-friendly is our online banking platform?',
                                    'scale' => '1-5',
                                    'required' => false
                                ],
                                [
                                    'type' => 'text',
                                    'question' => 'How can we improve our digital services?',
                                    'required' => false
                                ]
                            ]
                        ]
                    ],
                    'compliance' => [
                        'sox' => true,
                        'dataClassification' => 'confidential',
                        'auditRequired' => true
                    ],
                    'branding' => [
                        'primaryColor' => '#dc3545',
                        'logo' => null,
                        'companyName' => '{{FINANCIAL_INSTITUTION_NAME}}'
                    ]
                ],
                'metadata' => [
                    'estimatedTime' => 4,
                    'defaultSettings' => [
                        'anonymous' => false,
                        'multipleResponses' => false,
                        'responseLimit' => null
                    ]
                ],
                'complianceFlags' => ['SOX', 'GDPR', 'CCPA'],
                'tags' => ['finance', 'banking', 'client', 'satisfaction'],
                'usageCount' => 98,
                'averageRating' => 4.3
            ],
            [
                'name' => 'Product Research Survey',
                'description' => 'Market research survey for gathering customer insights about new product development.',
                'industry' => 'Technology',
                'category' => 'Market Research',
                'structure' => [
                    'sections' => [
                        [
                            'title' => 'Product Preferences',
                            'questions' => [
                                [
                                    'type' => 'multiple_choice',
                                    'question' => 'Which features are most important to you in a new product?',
                                    'options' => ['Ease of use', 'Advanced features', 'Price', 'Design', 'Customer support'],
                                    'multiple' => true,
                                    'required' => true
                                ],
                                [
                                    'type' => 'rating',
                                    'question' => 'How likely are you to try a new product in this category?',
                                    'scale' => '1-10',
                                    'required' => true
                                ]
                            ]
                        ],
                        [
                            'title' => 'Demographics',
                            'questions' => [
                                [
                                    'type' => 'multiple_choice',
                                    'question' => 'What is your age range?',
                                    'options' => ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'],
                                    'required' => true
                                ],
                                [
                                    'type' => 'text',
                                    'question' => 'Any additional feedback or suggestions?',
                                    'required' => false
                                ]
                            ]
                        ]
                    ],
                    'branding' => [
                        'primaryColor' => '#6f42c1',
                        'logo' => null,
                        'companyName' => '{{TECH_COMPANY_NAME}}'
                    ]
                ],
                'metadata' => [
                    'estimatedTime' => 7,
                    'defaultSettings' => [
                        'anonymous' => false,
                        'multipleResponses' => false,
                        'responseLimit' => 1000
                    ]
                ],
                'complianceFlags' => ['GDPR', 'CCPA'],
                'tags' => ['technology', 'research', 'product', 'market'],
                'usageCount' => 134,
                'averageRating' => 4.2
            ],
            [
                'name' => 'Event Feedback Survey',
                'description' => 'Post-event feedback survey for conferences, workshops, and corporate events.',
                'industry' => 'Events',
                'category' => 'Event Management',
                'structure' => [
                    'sections' => [
                        [
                            'title' => 'Overall Experience',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How would you rate the overall event experience?',
                                    'scale' => '1-5',
                                    'required' => true
                                ],
                                [
                                    'type' => 'rating',
                                    'question' => 'How likely are you to attend similar events in the future?',
                                    'scale' => '1-10',
                                    'required' => true
                                ]
                            ]
                        ],
                        [
                            'title' => 'Content and Organization',
                            'questions' => [
                                [
                                    'type' => 'rating',
                                    'question' => 'How relevant was the content to your interests?',
                                    'scale' => '1-5',
                                    'required' => true
                                ],
                                [
                                    'type' => 'text',
                                    'question' => 'What topics would you like to see in future events?',
                                    'required' => false
                                ]
                            ]
                        ]
                    ],
                    'branding' => [
                        'primaryColor' => '#fd7e14',
                        'logo' => null,
                        'companyName' => '{{EVENT_ORGANIZER_NAME}}'
                    ]
                ],
                'metadata' => [
                    'estimatedTime' => 3,
                    'defaultSettings' => [
                        'anonymous' => false,
                        'multipleResponses' => false,
                        'responseLimit' => null
                    ]
                ],
                'complianceFlags' => ['GDPR'],
                'tags' => ['event', 'feedback', 'conference', 'workshop'],
                'usageCount' => 167,
                'averageRating' => 4.4
            ]
        ];
    }
}