# AI-Powered Survey Builder - Technical Documentation

## Project Overview

This project implements a comprehensive AI-powered survey builder that assists users in creating high-quality, unbiased surveys through intelligent assistance, bias detection, and optimization features.

## Architecture Overview

### Core Components

#### 1. Database Schema
- **User**: Survey creators with authentication and role management
- **Survey**: Main survey entity with AI metadata and predictions
- **Question**: Individual questions with AI analysis data
- **Response**: User responses with session tracking

#### 2. API Infrastructure
- **RESTful API endpoints** for CRUD operations
- **JSON-based communication** for frontend integration
- **Rate limiting** for AI service calls
- **Comprehensive error handling** with graceful degradation

#### 3. AI Services
- **AIServiceInterface**: Abstraction for different AI providers
- **MockAIService**: Development implementation with realistic mock data
- **Extensible design** for integrating real AI services (OpenAI, Anthropic, etc.)

### Key Features Implemented

#### Smart Question Suggestions
- **Endpoint**: `POST /api/ai/suggest-questions`
- **Functionality**: Generates 3-5 relevant questions based on survey topic
- **Context-aware**: Adapts suggestions based on survey type (employee satisfaction, customer feedback, etc.)
- **Quality metrics**: Confidence scores and rationale for each suggestion

#### Bias Detection & Correction
- **Endpoint**: `POST /api/ai/analyze-bias`
- **Functionality**: Analyzes question text for potential bias patterns
- **Detection categories**:
  - Leading questions ("Don't you think...", "Surely you...")
  - Loaded language (emotionally charged words)
  - Double-barrel questions (multiple concepts in one question)
  - Assumptions ("When you...", "Since you...")
- **Output**: Bias score (0-1), severity level, specific suggestions

#### Inclusive Language Assistant
- **Endpoint**: `POST /api/ai/check-inclusive-language`
- **Functionality**: Identifies non-inclusive language and suggests alternatives
- **Categories checked**:
  - Gender-neutral language
  - Accessibility-friendly terms
  - Age-inclusive language
  - Cultural sensitivity
- **Output**: Inclusivity score, flagged terms with suggestions

#### Completion Rate Prediction
- **Endpoint**: `POST /api/ai/predict-completion-rate`
- **Functionality**: Estimates survey completion rate based on structure
- **Factors considered**:
  - Question count and complexity
  - Estimated completion time
  - Question types and flow
- **Output**: Predicted completion rate with confidence and recommendations

#### Question Flow Optimization
- **Endpoint**: `POST /api/ai/optimize-question-flow`
- **Functionality**: Suggests optimal question ordering for engagement
- **Optimization criteria**:
  - Engagement potential
  - Question complexity
  - Logical grouping
- **Output**: Recommended question order with improvement estimates

## Technical Implementation

### Backend (Symfony 7.3)

#### Entities
```php
// Core entities with proper relationships
User -> Survey (One-to-Many)
Survey -> Question (One-to-Many, cascade)
Survey -> Response (One-to-Many)
Question -> Response (One-to-Many)
```

#### API Controllers
- **SurveyController**: CRUD operations for surveys
- **QuestionController**: Question management with reordering
- **AIAssistantController**: All AI-powered features

#### Services
- **AIServiceInterface**: Contract for AI providers
- **MockAIService**: Development implementation with realistic data

### Frontend (Bootstrap 5 + Vanilla JavaScript)

#### Survey Builder Interface
- **Real-time AI analysis** as users type questions
- **Interactive suggestion system** for quick question addition
- **Live bias and inclusivity feedback** with visual indicators
- **Completion rate predictions** with recommendations
- **Responsive design** optimized for desktop and tablet use

#### Key JavaScript Features
- **Debounced AI analysis** (2-second delay after typing stops)
- **Dynamic question management** with drag-and-drop capability
- **Real-time feedback display** with color-coded indicators
- **AJAX integration** with comprehensive error handling

### Database Configuration

#### Supported Databases
- **MySQL** (primary, configured)
- **PostgreSQL** (alternative)
- **SQLite** (development option)

#### Migration Strategy
- **Manual migration file** created for initial schema
- **Doctrine ORM** for entity management
- **Foreign key constraints** for data integrity

### Security Features

#### CSRF Protection
- **Enabled** in framework configuration
- **Token validation** on all form submissions

#### Rate Limiting
- **AI request limiting**: 60 requests per minute per user
- **Configurable** through Symfony Rate Limiter

#### Content Safety
- **Content filtering** for inappropriate AI suggestions
- **Input validation** on all API endpoints
- **SQL injection protection** through Doctrine ORM

### Caching Strategy

#### AI Response Caching
- **Filesystem cache** for AI responses
- **1-hour TTL** for question suggestions and analysis
- **Cache invalidation** on question text changes

#### Performance Optimization
- **Debounced API calls** to reduce server load
- **Pagination** for large survey lists
- **Lazy loading** for question analysis

## Development Workflow

### Required Commands

#### Setup
```bash
composer install
npm install
npm run build
```

#### Database
```bash
php bin/console doctrine:migrations:migrate
```

#### Development
```bash
symfony serve  # Start development server
npm run watch  # Watch asset changes
```

#### Testing
```bash
php bin/console lint:twig templates/
php bin/console lint:yaml config/
vendor/bin/phpunit
```

### Environment Configuration

#### Required Environment Variables
```env
APP_SECRET=64-character-secure-secret
DATABASE_URL=mysql://user:pass@host:port/dbname
```

#### AI Service Configuration
```yaml
# Future: Add real AI service credentials
OPENAI_API_KEY=your-api-key
ANTHROPIC_API_KEY=your-api-key
```

## API Documentation

### Survey Endpoints
- `GET /api/surveys` - List surveys with pagination
- `POST /api/surveys` - Create new survey
- `GET /api/surveys/{id}` - Get survey details
- `PUT /api/surveys/{id}` - Update survey
- `DELETE /api/surveys/{id}` - Delete survey

### Question Endpoints
- `GET /api/surveys/{surveyId}/questions` - List questions
- `POST /api/surveys/{surveyId}/questions` - Create question
- `PUT /api/surveys/{surveyId}/questions/{id}` - Update question
- `DELETE /api/surveys/{surveyId}/questions/{id}` - Delete question
- `POST /api/surveys/{surveyId}/questions/reorder` - Reorder questions

### AI Assistant Endpoints
- `POST /api/ai/suggest-questions` - Generate question suggestions
- `POST /api/ai/analyze-bias` - Analyze question for bias
- `POST /api/ai/check-inclusive-language` - Check language inclusivity
- `POST /api/ai/predict-completion-rate` - Predict completion rate
- `POST /api/ai/optimize-question-flow` - Optimize question order

## Deployment Considerations

### Production Requirements
- **PHP 8.1+** with required extensions
- **MySQL 8.0+** or **PostgreSQL 13+**
- **Web server** (Apache/Nginx) with URL rewriting
- **SSL certificate** for HTTPS
- **Real AI service** API credentials

### Performance Tuning
- **Enable OPcache** for PHP
- **Configure Doctrine query cache** for production
- **Set up Redis** for session storage and caching
- **Implement CDN** for static assets

### Security Hardening
- **Use real APP_SECRET** (32+ characters)
- **Enable HTTPS** everywhere
- **Configure rate limiting** per environment
- **Set up monitoring** for AI service costs
- **Implement proper logging** for security events

## Future Enhancements

### Real AI Integration
- **OpenAI GPT-4** for advanced question generation
- **Anthropic Claude** for bias detection
- **Custom models** for domain-specific surveys

### Advanced Features
- **Multi-language support** for international surveys
- **A/B testing** for question variations
- **Response analytics** with AI insights
- **Survey templates** with industry best practices

### Scalability Improvements
- **Microservices architecture** for AI services
- **Async processing** with Symfony Messenger
- **Horizontal scaling** with load balancers
- **Database sharding** for large datasets

## Troubleshooting

### Common Issues

#### Database Connection
```bash
# Check database connectivity
php bin/console doctrine:database:create
php bin/console doctrine:schema:validate
```

#### AI Service Errors
- **Check rate limits** in logs
- **Verify API endpoints** are accessible
- **Test with mock service** for development

#### Frontend Issues
- **Clear browser cache** after asset changes
- **Check console** for JavaScript errors
- **Verify API responses** in Network tab

### Debug Commands
```bash
php bin/console debug:router  # Check routes
php bin/console debug:container  # Check services
php bin/console cache:clear  # Clear cache
```

## Contributing

### Code Standards
- **PSR-12** for PHP code style
- **ESLint** for JavaScript linting
- **Twig coding standards** for templates

### Testing Requirements
- **Unit tests** for all services
- **Integration tests** for API endpoints
- **Functional tests** for UI components

### Documentation
- **Update CLAUDE.md** for architectural changes
- **Document new endpoints** in API section
- **Add inline comments** for complex logic

---

*Last updated: 2025-08-18*  
*Project version: 1.0.0*  
*Symfony version: 7.3*