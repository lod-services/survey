# AI-Powered Survey Builder

## Project Overview
This is a Symfony 7.3 application that implements an AI-powered survey builder with smart question suggestions and bias detection capabilities. The system leverages OpenAI's GPT models to assist survey creators in building professional, unbiased surveys.

## Core Features Implemented

### 1. Smart Question Generator
- **Location**: `src/Service/AiQuestionGeneratorService.php`
- **Purpose**: Generates 5-8 relevant survey questions based on survey objectives and target audience
- **Features**:
  - OpenAI GPT-4 integration for intelligent question generation
  - Caching system for performance optimization
  - Fallback to manual questions when AI services are unavailable
  - Quality scoring with confidence metrics
  - Support for multiple question types (text, multiple choice, scale, etc.)

### 2. Bias Detection & Analysis
- **Location**: `src/Service/AiBiasDetectionService.php`
- **Purpose**: Real-time analysis of questions to identify and correct potential bias
- **Features**:
  - Pattern-based bias detection for immediate feedback
  - AI-enhanced analysis for comprehensive bias assessment
  - Detection of leading questions, double-barreled questions, loaded language
  - Automatic suggestions for neutral alternatives
  - Confidence scoring for bias detection accuracy

### 3. Survey Management System
- **Entities**: `Survey`, `Question`, `AiSuggestion`
- **Database**: SQLite for development, configurable for production
- **Features**:
  - CRUD operations for surveys and questions
  - AI suggestion tracking and user feedback collection
  - Survey versioning and publishing workflow

### 4. Interactive Web Interface
- **Templates**: Bootstrap 5 + Stimulus.js integration
- **Real-time Features**:
  - Live bias checking as users type questions
  - AJAX-powered AI suggestion generation
  - Interactive suggestion acceptance/rejection
  - Progress indicators and loading states

## Technical Architecture

### Dependencies Added
```json
{
  "symfony/http-client": "^7.3",
  "symfony/cache": "^7.3", 
  "symfony/messenger": "^7.3",
  "openai-php/client": "^0.8"
}
```

### Environment Configuration
```env
# Database (SQLite for development)
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"

# OpenAI API Integration
OPENAI_API_KEY=your_openai_api_key_here
```

### Service Configuration
- OpenAI client factory setup in `config/services.yaml`
- Dependency injection for AI services
- Caching configuration for performance

## Database Schema

### Core Tables
1. **surveys**: Main survey information, objectives, target audience
2. **questions**: Individual survey questions with type and metadata
3. **ai_suggestions**: AI-generated suggestions with user feedback tracking

### Key Relationships
- Survey → Questions (1:N)
- Survey → AI Suggestions (1:N)
- AI suggestions include confidence scores and user actions

## API Endpoints

### Survey Management
- `GET /survey/` - List all surveys
- `GET /survey/new` - Create new survey form
- `GET /survey/{id}/edit` - Edit survey and manage questions

### AI Integration
- `POST /survey/{id}/ai-suggestions` - Generate AI question suggestions
- `POST /survey/{id}/questions/new` - Add new question with bias checking
- `POST /survey/{id}/bias-check` - Real-time bias analysis
- `POST /survey/suggestions/{id}/apply` - Apply AI suggestion

## Quality Assurance

### Testing
- Unit tests for AI services with mocked dependencies
- Fallback behavior testing for service outages
- Bias detection accuracy validation

### Performance Considerations
- Response caching for AI suggestions (1 hour TTL)
- Async processing indicators for better UX
- Rate limiting considerations for API usage

### Security Measures
- API key management through Symfony Secrets
- Input sanitization and validation
- CSRF protection on all forms
- Secure handling of user survey data

## Usage Workflow

1. **Survey Creation**: User defines survey title, objective, and target audience
2. **AI Question Generation**: System generates relevant questions using GPT-4
3. **Bias Detection**: Real-time analysis flags potentially biased questions
4. **Manual Editing**: Users can add, modify, or remove questions manually
5. **Quality Review**: Confidence scores and suggestions help ensure question quality

## Deployment Notes

### Development Setup
1. Configure OpenAI API key in `.env.local`
2. Run database migrations: `php bin/console doctrine:migrations:migrate`
3. Install dependencies: `composer install && npm install`
4. Build assets: `npm run build`

### Production Considerations
- Use PostgreSQL or MySQL for production database
- Configure Redis for caching and session storage
- Set up proper logging and monitoring
- Implement rate limiting for AI API calls
- Monitor API usage costs

## Future Enhancements (Phase 2+)

### Planned Features
- Flow optimization algorithms for question ordering
- Advanced language enhancement suggestions
- Template intelligence based on successful surveys
- Analytics dashboard for survey performance
- Multi-language support for international surveys

### Technical Improvements
- WebSocket integration for real-time collaboration
- Advanced caching strategies
- Machine learning model fine-tuning
- A/B testing framework for question effectiveness

## Troubleshooting

### Common Issues
1. **OpenAI API failures**: System gracefully falls back to manual creation
2. **Cache issues**: Clear cache with `php bin/console cache:clear`
3. **Database migrations**: Ensure migrations are up to date
4. **Asset compilation**: Rebuild assets if UI issues occur

### Debugging
- Web Profiler available in development mode
- Comprehensive logging for AI service interactions
- Error handling with user-friendly fallbacks

## Code Standards
- PSR-12 coding standards
- Symfony best practices
- Comprehensive PHPDoc comments
- Type declarations throughout codebase
- Consistent error handling patterns