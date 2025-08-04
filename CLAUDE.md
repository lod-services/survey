# AI-Powered Dynamic Survey Branching System

This document provides an overview of the AI-powered survey branching system implemented in this application.

## 🚀 Overview

The system implements intelligent survey branching that adapts question flow based on respondent answers using a sophisticated rule engine. This allows for personalized survey experiences that improve completion rates and gather more relevant data.

## 🏗️ Architecture

### Core Components

1. **Entities** (`src/Entity/`)
   - `Survey` - Main survey with branching configuration
   - `Question` - Individual survey questions with types and options
   - `SurveyRule` - JSON-based conditional logic rules
   - `SurveySession` - Session management and progress tracking
   - `Response` - User responses to questions
   - `ResponseAudit` - Rule evaluation logging
   - `RuleDependency` - Rule relationship management

2. **Services** (`src/Service/`)
   - `SurveyManager` - Survey CRUD operations and validation
   - `SurveySessionManager` - Session lifecycle and progress tracking
   - `RuleEngine` - Core branching logic evaluation system

3. **Controllers** (`src/Controller/`)
   - `SurveyController` - Survey builder and management interface
   - `SurveyResponseController` - Survey taking and response handling

### Database Schema

The system uses the following main tables:
- `survey` - Survey metadata and configuration
- `question` - Questions with types, content, and options
- `survey_rule` - Conditional logic rules (JSON format)
- `survey_session` - User session tracking
- `response` - User responses
- `response_audit` - Rule evaluation audit trail
- `rule_dependency` - Rule relationship tracking

## 🎯 Key Features

### Dynamic Branching
- Real-time question flow based on previous responses
- JSON-based rule system for flexible conditional logic
- Support for complex operators (equals, greater_than, contains, etc.)
- Nested condition groups with AND/OR/NOT logic

### Rule Engine
- Priority-based rule evaluation
- Circular dependency detection
- Rule validation and error prevention
- Performance optimized with caching

### Question Types
- Text input (single and multi-line)
- Multiple choice (radio buttons, checkboxes, dropdowns)
- Rating scales
- Yes/No questions
- Custom options per question type

### Session Management
- Secure session tokens
- Progress tracking and persistence
- Session timeout handling
- Resume functionality
- Forward/backward navigation

### Security Features
- CSRF protection on all forms
- Input sanitization and validation
- Rule execution sandboxing
- Audit logging for compliance

## 🔧 Usage

### Creating Surveys

1. **Basic Survey Creation**
   ```php
   $survey = $surveyManager->createSurvey('Employee Satisfaction', 'Quarterly satisfaction survey');
   ```

2. **Adding Questions**
   ```php
   $question = $surveyManager->addQuestion(
       $survey, 
       'radio', 
       'How satisfied are you with work-life balance?',
       ['Very Satisfied', 'Satisfied', 'Neutral', 'Dissatisfied', 'Very Dissatisfied']
   );
   ```

3. **Adding Branching Rules**
   ```php
   $condition = [
       'operator' => 'and',
       'conditions' => [
           [
               'questionId' => $question->getId(),
               'operator' => 'in',
               'value' => ['Dissatisfied', 'Very Dissatisfied']
           ]
       ]
   ];
   
   $action = [
       'type' => 'show_question',
       'questionId' => $followUpQuestion->getId()
   ];
   
   $rule = $surveyManager->addRule($survey, $condition, $action, 1);
   ```

### Taking Surveys

Surveys are accessible via:
- `/survey/{id}/start` - Start taking a survey
- Session-based navigation with AJAX for dynamic question loading
- Real-time progress tracking
- Branching logic applied transparently

## 🎨 Frontend Components

### Survey Builder Interface
- Visual question management with drag-and-drop reordering
- Real-time rule builder (planned for future enhancement)
- Preview mode for testing survey flow
- Statistics dashboard

### Survey Taking Interface
- Responsive design for all devices
- Progress indicators
- Smooth transitions between questions
- Loading states for rule evaluation
- Back navigation support

## 🔍 Technical Specifications

### Performance
- Rule evaluation cache (5-minute TTL)
- Database indexes on critical query paths
- Lazy loading of survey questions
- Session-based progress storage

### Scalability
- Support for up to 50 rules per survey
- Concurrent session handling
- Automatic cleanup of expired sessions
- Efficient rule dependency resolution

### Compliance
- GDPR-ready with audit logging
- Anonymized data processing
- Configurable data retention
- Privacy-focused by design

## 🧪 Testing

### Manual Testing Checklist
- [ ] Create survey with multiple questions
- [ ] Enable branching and add conditional rules
- [ ] Test survey flow with different response paths
- [ ] Verify rule validation prevents infinite loops
- [ ] Test session management and recovery
- [ ] Verify responsive design on mobile devices

### Automated Testing (Future)
- Unit tests for rule engine logic
- Integration tests for survey flow
- Performance tests for rule evaluation
- Security tests for input validation

## 📊 Performance Considerations

### Rule Engine Optimization
- Rules cached per survey to avoid database queries
- Lazy evaluation of rule conditions
- Priority-based short-circuit evaluation
- Indexed database queries for rule lookup

### Session Management
- Automatic cleanup of expired sessions
- Efficient progress data serialization
- Minimal database writes during survey taking

## 🛠️ Development Commands

### Database
```bash
# Run migrations
php bin/console doctrine:migrations:migrate

# Clear cache
php bin/console cache:clear
```

### Development Server
```bash
# Start Symfony development server
symfony server:start

# Or use PHP built-in server
php -S localhost:8000 -t public/
```

## 🔮 Future Enhancements

### Planned Features
1. **AI Integration** - OpenAI API for smart question suggestions
2. **Visual Rule Builder** - Drag-and-drop interface for rule creation
3. **Advanced Analytics** - Completion rate tracking and branching effectiveness
4. **Export/Import** - Survey templates and data export
5. **Multi-language** - Internationalization support
6. **API Endpoints** - RESTful API for external integrations

### Technical Improvements
- Redis caching layer
- Queue system for heavy operations
- Advanced rule validation
- Performance monitoring
- Load testing and optimization

## 📝 Configuration

### Environment Variables
```bash
DATABASE_URL="mysql://user:pass@localhost:3306/survey_db"
```

### Caching (Future)
```bash
REDIS_URL="redis://localhost:6379"
```

## 🤝 Contributing

This system follows Symfony best practices and includes:
- PSR-4 autoloading
- Doctrine ORM for database operations
- Twig templating
- Stimulus for frontend interactions
- Bootstrap for responsive UI

All code is documented and follows consistent naming conventions for maintainability.