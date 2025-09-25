# Research: CI Release Preparation for WordPress Installation

## GitHub Actions for WordPress Plugin Releases

### Decision: GitHub Actions with release workflow

**Rationale**:

- Native GitHub integration for releases and artifacts
- Built-in support for triggering on tag creation
- Extensive marketplace of pre-built actions for common tasks
- Free for public repositories with generous limits

**Alternatives considered**:

- Jenkins: More complex setup, requires self-hosted infrastructure
- GitLab CI: Would require repository migration
- CircleCI: Limited free tier, additional service dependency

## WordPress Plugin Packaging Standards

### Decision: Follow WordPress.org plugin directory structure

**Rationale**:

- Ensures compatibility with WordPress auto-updater
- Matches user expectations for plugin installation
- Supports both manual and automatic plugin updates
- Required for WordPress.org directory submission

**Key Requirements**:

- Plugin directory name must match zip filename (choice-uft)
- Main plugin file must be in root of plugin directory
- No development files (.git, .github, node_modules, etc.)
- Preserve directory structure for assets, includes, etc.

**Alternatives considered**:

- Custom packaging: Would break WordPress conventions
- Include development files: Would bloat package size unnecessarily

## Release Triggering Strategy

### Decision: Tag-based release automation

**Rationale**:

- Semantic versioning alignment (v1.2.3 tags)
- Clear release intent separation from regular commits
- Enables automated version validation
- Standard practice in open source projects

**Workflow**:

1. Developer creates annotated tag (e.g., `git tag -a v3.9.0 -m "Release v3.9.0"`)
2. Tag push triggers GitHub Actions workflow
3. CI validates version consistency across files
4. CI creates WordPress-compatible zip package
5. CI uploads zip as GitHub release asset

**Alternatives considered**:

- Manual release creation: Prone to human error, inconsistent
- Branch-based releases: Less clear release intent
- Webhook-based: More complex setup, additional moving parts

## Version Consistency Validation

### Decision: Multi-file version synchronization check

**Rationale**:

- WordPress plugins have version numbers in multiple locations
- Inconsistent versions break auto-updater functionality
- Early detection prevents broken releases

**Files to validate**:

- `choice-universal-form-tracker.php` (plugin header comment)
- `choice-universal-form-tracker.php` (CUFT_VERSION constant)

**Validation approach**:

- Extract version from Git tag (strip 'v' prefix)
- Parse each file for version number
- Fail CI if any version mismatches

## File Inclusion/Exclusion Strategy

### Decision: Explicit exclusion list with verification

**Rationale**:

- Safer than inclusion lists (less likely to miss new files)
- Clear documentation of what's excluded
- Post-packaging verification ensures completeness

**Exclusion patterns**:

- `.git/` and `.github/` (version control and CI files)
- `node_modules/` (npm dependencies not needed in production)
- `.env*` (environment configuration files)
- `*.zip` (prevent nested zip files)
- Development tools (webpack configs, etc.)

**Inclusion verification**:

- Check for main plugin file existence
- Verify assets directory preservation
- Confirm includes directory structure
- Validate readme and changelog presence

## Zip File Naming Convention

### Decision: Fixed name "choice-uft.zip" without version

**Rationale**:

- WordPress extracts to directory matching zip filename
- Version-suffixed names create wrong directory names
- Consistent naming simplifies download links
- Matches WordPress.org plugin directory conventions

**Critical requirement**:

- Zip MUST be named `choice-uft.zip` (not `choice-uft-v3.9.0.zip`)
- This ensures WordPress extracts to `/wp-content/plugins/choice-uft/`
- Version-suffixed names would create erroneous `/wp-content/plugins/choice-uft-v3.9.0/`

## Package Validation Strategy

### Decision: Multi-layer validation before release

**Rationale**:

- Prevents broken releases from reaching users
- Early detection of packaging issues
- Builds confidence in automated process

**Validation steps**:

1. **Structure validation**: Check directory layout matches expectations
2. **File presence**: Verify all critical files included
3. **File integrity**: Ensure no corruption during packaging
4. **WordPress compatibility**: Validate plugin header format
5. **Size verification**: Ensure package size is reasonable

## GitHub Release Integration

### Decision: Automatic release asset attachment

**Rationale**:

- Centralizes downloads in GitHub Releases UI
- Provides versioned artifact storage
- Enables automated deployment workflows
- Supports WordPress auto-updater queries

**Implementation**:

- Use GitHub CLI (`gh release upload`) for asset attachment
- Overwrite existing assets with `--clobber` flag
- Verify upload success before marking CI complete
- Include checksum for download verification

## Error Handling and Rollback

### Decision: Fail-fast with clear error messages

**Rationale**:

- Prevents partial releases that confuse users
- Clear diagnostics for quick issue resolution
- Maintains release quality standards

**Error scenarios**:

- Version validation failures: Stop immediately with mismatch details
- File packaging errors: Fail with specific file/directory issues
- Upload failures: Retry once, then fail with upload diagnostics
- Validation failures: Provide specific validation error details

**No automatic rollback**: Manual intervention required for failed releases to prevent accidental reverts

## Performance Considerations

### Decision: Optimize for reliability over speed

**Rationale**:

- Release packaging is infrequent operation
- Correctness more important than speed
- Still target sub-5 minute total pipeline time

**Optimizations**:

- Cache-friendly operations where possible
- Parallel validation steps when safe
- Efficient zip compression settings
- Minimal external dependencies

## Security Considerations

### Decision: Strict file filtering and validation

**Rationale**:

- Prevent accidental secrets inclusion
- Ensure only production files reach users
- Maintain plugin security standards

**Security measures**:

- Explicit exclusion of sensitive file patterns (`.env*`, `.key`, etc.)
- Post-packaging content scan for common secret patterns
- No inclusion of development database dumps or config files
- Verification that plugin loads without errors in clean WordPress install
