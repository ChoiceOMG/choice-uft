# CLAUDE.md - Development Guidelines for Choice Universal Form Tracker

## CRITICAL: Always Reference Specifications First

### Before ANY Code Changes
1. **MANDATORY**: Read and understand relevant specifications:
   - [.specify/memory/constitution.md](.specify/memory/constitution.md) - Core principles and standards
   - [specs/core/dataLayer.spec.md](specs/core/dataLayer.spec.md) - DataLayer event requirements
   - [specs/core/tracking-params.spec.md](specs/core/tracking-params.spec.md) - UTM/Click ID handling
   - Framework-specific specs in [specs/frameworks/](specs/frameworks/)
   - [specs/testing/test-suite.spec.md](specs/testing/test-suite.spec.md) - Testing requirements
   - [.specify/memory/agents.md](.specify/memory/agents.md) - AI development guidelines
   - [.specify/memory/review-checklist.md](.specify/memory/review-checklist.md) - Code review checklist

2. **VALIDATE**: Ensure proposed changes align with constitutional principles
3. **CHECK**: Verify compatibility with existing implementations
4. **PLAN**: Reference implementation plan templates if creating new features

### Implementation and Migration Templates
When implementing new features or migrating existing code:
- **New Features**: Use [.specify/templates/implementation-plan-template.md](.specify/templates/implementation-plan-template.md)
- **Code Updates**: Use [.specify/templates/migration-plan-template.md](.specify/templates/migration-plan-template.md)
- **All Changes**: Follow the constitutional compliance checklist
- **Risk Assessment**: Always include risk mitigation strategies

### Mandatory Pre-Commit Validation
Before committing any code changes, ALWAYS verify using [.specify/memory/review-checklist.md](.specify/memory/review-checklist.md):
