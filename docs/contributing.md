# Contributing

We welcome contributions to the ELP Parser library! Here's how you can help:

## Bug Reports

- Use the GitHub issue tracker to report bugs
- Describe what you expected to happen
- Describe what actually happened
- Include code samples and steps to reproduce the issue

## Feature Requests

- Use the GitHub issue tracker to submit feature requests
- Explain your use case
- Be patient - we'll review your request as soon as possible

## Pull Requests

1. Fork the repository
2. Create a new branch for your feature
3. Write tests for your changes
4. Ensure all tests pass
5. Submit a pull request with a clear description of your changes

## Development Setup

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Run tests:
   ```bash
   ./vendor/bin/phpunit
   ```

## Code Style

- Follow PSR-12 coding standards
- Use PHP CS Fixer to maintain code style
- Run PHP CS Fixer before submitting:
  ```bash
  ./vendor/bin/php-cs-fixer fix
  ```

## Testing

- Write tests for new features
- Ensure existing tests pass
- Aim for high code coverage
