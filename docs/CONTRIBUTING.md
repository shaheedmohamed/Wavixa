# Contributing to Wavixa

Thank you for your interest in contributing to Wavixa! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Help others learn and grow
- Report issues responsibly
- Respect privacy and confidentiality

## Getting Started

### 1. Fork the Repository

```bash
git clone https://github.com/wavixa/wavixa.git
cd wavixa
```

### 2. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

Use descriptive branch names:
- `feature/add-user-authentication`
- `fix/session-timeout-issue`
- `docs/update-api-documentation`
- `refactor/optimize-database-queries`

### 3. Set Up Development Environment

Follow the setup guide in `docs/SETUP.md`

## Development Workflow

### Code Style

#### JavaScript/Vue.js
- Use ESLint configuration
- Follow Vue.js style guide
- Use 2-space indentation
- Use semicolons
- Use single quotes for strings

```javascript
// Good
const message = 'Hello, World!';
const handleClick = () => {
  console.log(message);
};

// Bad
const message = "Hello, World!"
const handleClick = () => console.log(message)
```

#### PHP/Laravel
- Follow PSR-12 coding standard
- Use 4-space indentation
- Use meaningful variable names
- Add type hints

```php
// Good
public function getUserById(int $id): User
{
    return User::findOrFail($id);
}

// Bad
public function getUserById($id)
{
    return User::find($id);
}
```

#### Node.js
- Use ESLint configuration
- Follow Airbnb style guide
- Use 2-space indentation
- Use async/await over callbacks

```javascript
// Good
async function fetchData() {
  try {
    const response = await api.get('/data');
    return response.data;
  } catch (error) {
    console.error('Error:', error);
    throw error;
  }
}

// Bad
function fetchData(callback) {
  api.get('/data', (err, data) => {
    if (err) callback(err);
    else callback(null, data);
  });
}
```

### Testing

#### Frontend Tests
```bash
cd frontend
npm run test
```

#### Backend Tests
```bash
cd backend
php artisan test
```

#### Service Tests
```bash
cd services
npm run test
```

### Linting

#### Frontend
```bash
cd frontend
npm run lint
```

#### Backend
```bash
cd backend
composer lint
```

#### Services
```bash
cd services
npm run lint
```

## Commit Guidelines

### Commit Message Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types
- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation only changes
- `style`: Changes that don't affect code meaning
- `refactor`: Code change that neither fixes a bug nor adds a feature
- `perf`: Code change that improves performance
- `test`: Adding missing tests
- `chore`: Changes to build process or dependencies

### Examples

```
feat(auth): add two-factor authentication

Implement TOTP-based 2FA for user accounts.
- Add 2FA setup in user settings
- Add 2FA verification during login
- Store TOTP secrets securely

Closes #123
```

```
fix(whatsapp): handle session disconnection gracefully

Previously, disconnected sessions would cause errors.
Now they are properly cleaned up and users are notified.

Fixes #456
```

```
docs(api): update authentication endpoints documentation

Added detailed examples for JWT token usage.
```

## Pull Request Process

### 1. Create a Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a PR on GitHub.

### 2. PR Title and Description

**Title**: Follow commit message format
```
feat(dashboard): add user activity chart
```

**Description**: Include:
- What changes were made
- Why the changes were made
- How to test the changes
- Related issues/PRs

```markdown
## Description
Added a new activity chart to the user dashboard showing login history.

## Changes
- Created ActivityChart.vue component
- Added GET /api/dashboard/activity endpoint
- Added activity logging to auth module

## Testing
1. Login to the application
2. Navigate to dashboard
3. Verify activity chart displays correctly

## Related Issues
Closes #789
```

### 3. Code Review

- Address all comments
- Request re-review after changes
- Be open to feedback
- Discuss disagreements respectfully

### 4. Merge

Once approved:
```bash
git checkout main
git pull origin main
git merge feature/your-feature-name
git push origin main
```

## Issue Reporting

### Bug Reports

Include:
- Clear description of the bug
- Steps to reproduce
- Expected behavior
- Actual behavior
- Environment details (OS, browser, versions)
- Screenshots/logs if applicable

```markdown
## Description
Login button doesn't work on mobile devices.

## Steps to Reproduce
1. Open the app on iPhone
2. Click the login button
3. Nothing happens

## Expected Behavior
Login modal should appear.

## Actual Behavior
No response when clicking the button.

## Environment
- OS: iOS 16
- Browser: Safari
- Device: iPhone 14
```

### Feature Requests

Include:
- Clear description of the feature
- Use case and benefit
- Possible implementation approach
- Related features

```markdown
## Feature Request
Add email notifications for new messages.

## Use Case
Users want to be notified when they receive new WhatsApp messages.

## Benefit
Improves user engagement and message response time.

## Possible Implementation
- Add notification preferences to user settings
- Send email via queue worker
- Include message preview in email
```

## Documentation

### Code Comments

```javascript
/**
 * Validates user email format
 * @param {string} email - The email address to validate
 * @returns {boolean} True if email is valid
 */
function validateEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}
```

### API Documentation

Document all endpoints:

```markdown
### POST /api/auth/login

Authenticate user with email and password.

**Request**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGc...",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com"
    }
  }
}
```

**Errors**
- 400: Invalid email or password
- 429: Too many login attempts
```

### README Updates

Keep README.md updated with:
- New features
- Changed dependencies
- Updated setup instructions
- New configuration options

## Performance Guidelines

### Frontend
- Keep bundle size < 500KB
- Lazy load routes
- Optimize images
- Use code splitting
- Minimize API calls

### Backend
- Use database indexes
- Implement caching
- Optimize queries
- Use pagination
- Implement rate limiting

### Services
- Handle errors gracefully
- Implement timeouts
- Use connection pooling
- Monitor memory usage
- Log important events

## Security Guidelines

### Code Security
- Never commit secrets
- Validate all inputs
- Use parameterized queries
- Implement CSRF protection
- Use HTTPS in production

### Dependency Security
- Keep dependencies updated
- Check for vulnerabilities
- Use npm audit / composer audit
- Review dependency licenses

### Data Security
- Hash passwords
- Encrypt sensitive data
- Use secure session management
- Implement proper access control
- Log security events

## Release Process

### Version Numbering
Follow Semantic Versioning (MAJOR.MINOR.PATCH):
- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes

### Release Steps
1. Update version in package.json/composer.json
2. Update CHANGELOG.md
3. Create git tag
4. Push to main branch
5. Create GitHub release
6. Deploy to production

## Getting Help

- **Documentation**: Check `docs/` directory
- **Issues**: Search existing issues
- **Discussions**: Use GitHub Discussions
- **Email**: support@wavixa.com

## Recognition

Contributors will be recognized in:
- CONTRIBUTORS.md file
- Release notes
- GitHub contributors page

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

**Last Updated**: July 3, 2026
**Version**: 1.0.0
