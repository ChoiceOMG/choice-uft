# Data Model: CI Release Preparation

## Core Entities

### Release Artifact
**Purpose**: WordPress-compatible plugin package ready for distribution

**Attributes**:
- `filename`: String - Always "choice-uft.zip" (fixed, no version suffix)
- `version`: String - Semantic version (e.g., "3.9.0") extracted from git tag
- `size`: Number - Package size in bytes for validation
- `checksum`: String - SHA256 hash for integrity verification
- `created_at`: DateTime - Package creation timestamp
- `git_tag`: String - Source git tag (e.g., "v3.9.0")
- `git_commit`: String - Source commit hash

**Validation Rules**:
- `filename` MUST be exactly "choice-uft.zip"
- `version` MUST match semantic versioning pattern (X.Y.Z)
- `size` MUST be between 1MB and 50MB (reasonable plugin size)
- `checksum` MUST be 64-character hex string
- `git_tag` MUST start with 'v' followed by version number

### Version Metadata
**Purpose**: Version information synchronized across multiple plugin files

**Attributes**:
- `tag_version`: String - Version extracted from git tag
- `header_version`: String - Version from plugin header comment
- `constant_version`: String - Version from CUFT_VERSION constant
- `package_version`: String - Version from package.json (if present)
- `is_consistent`: Boolean - All versions match
- `mismatched_files`: Array[String] - Files with incorrect versions

**Validation Rules**:
- All version strings MUST match semantic versioning format
- `is_consistent` MUST be true before packaging proceeds
- `tag_version` is authoritative source of truth
- Version comparison is exact string match (no normalization)

### Package Structure
**Purpose**: Directory and file organization for WordPress compatibility

**Attributes**:
- `root_directory`: String - Always "choice-uft" (matches plugin directory)
- `included_files`: Array[String] - List of files included in package
- `excluded_patterns`: Array[String] - Patterns used for file exclusion
- `critical_files`: Array[String] - Files that MUST be present
- `total_files`: Number - Count of files in package
- `directory_structure`: Object - Nested structure representation

**Critical Files** (MUST be present):
- `choice-universal-form-tracker.php` (main plugin file)
- `README.md` (documentation)
- `CHANGELOG.md` (version history)
- `assets/` (directory with plugin assets)
- `includes/` (directory with PHP classes)

**Excluded Patterns**:
- `.git/` and `.github/` (version control)
- `node_modules/` (npm dependencies)
- `.env*` (environment files)
- `*.zip` (prevent nested packages)
- `.specify/` (development specifications)

**Validation Rules**:
- Root directory name MUST match expected plugin directory
- All critical files MUST be present
- No excluded patterns MUST be in package
- Directory structure MUST preserve relative paths

### Build Configuration
**Purpose**: CI pipeline settings and validation rules

**Attributes**:
- `trigger_event`: String - Git event that triggered build
- `workflow_file`: String - Path to GitHub Actions workflow
- `runner_os`: String - CI runner operating system
- `validation_steps`: Array[String] - List of validation steps performed
- `status`: Enum - BUILD_STARTED | VALIDATION_FAILED | PACKAGING_FAILED | UPLOAD_FAILED | COMPLETED
- `error_message`: String - Detailed error if build failed
- `duration_seconds`: Number - Total build time

**Status Transitions**:
```
BUILD_STARTED → VALIDATION_FAILED (version mismatch)
BUILD_STARTED → PACKAGING_FAILED (zip creation error)
BUILD_STARTED → UPLOAD_FAILED (GitHub release error)
BUILD_STARTED → COMPLETED (success)
```

**Validation Rules**:
- `trigger_event` MUST be "tag_push"
- `status` transitions are unidirectional (no rollback)
- `error_message` MUST be present if status indicates failure
- `duration_seconds` MUST be positive number

## Entity Relationships

### Release Artifact → Version Metadata (1:1)
- Each release artifact has exactly one version metadata record
- Version metadata validates before artifact creation
- Artifact creation fails if version metadata shows inconsistencies

### Release Artifact → Package Structure (1:1)
- Each release artifact has exactly one package structure definition
- Package structure validates before artifact creation
- Artifact uses package structure rules for file inclusion/exclusion

### Build Configuration → Release Artifact (1:1)
- Each build configuration produces at most one release artifact
- Failed builds have configuration but no artifact
- Configuration tracks build status throughout process

## State Transitions

### Build Process Flow
```
1. Tag Push Event
   ↓
2. Build Configuration (BUILD_STARTED)
   ↓
3. Version Metadata Validation
   ↓ (success)
4. Package Structure Validation
   ↓ (success)
5. Release Artifact Creation
   ↓ (success)
6. GitHub Release Upload
   ↓ (success)
7. Build Configuration (COMPLETED)
```

### Error States
- **Version Validation Failure**: Build stops, no artifact created
- **Package Validation Failure**: Build stops, no artifact created
- **Artifact Creation Failure**: Build stops, partial cleanup required
- **Upload Failure**: Artifact exists locally but not in GitHub release

## Data Validation Matrix

| Entity | Field | Validation Type | Error Handling |
|--------|-------|----------------|----------------|
| Release Artifact | filename | Fixed string match | Fail build immediately |
| Release Artifact | version | Regex pattern | Fail build immediately |
| Release Artifact | size | Range check | Fail build immediately |
| Version Metadata | is_consistent | Boolean assertion | Fail build, show mismatches |
| Package Structure | critical_files | Existence check | Fail build, list missing |
| Package Structure | excluded_patterns | Pattern match | Fail build, list violations |
| Build Configuration | status | Enum validation | Log error, continue cleanup |

## Performance Considerations

### Data Size Estimates
- Version Metadata: <1KB (small text fields)
- Package Structure: <10KB (file lists and paths)
- Release Artifact: 1-50MB (actual zip file)
- Build Configuration: <5KB (status and timing data)

### Processing Constraints
- Version validation: <5 seconds (file parsing)
- Package creation: <60 seconds (zip compression)
- Upload process: <120 seconds (network dependent)
- Total pipeline: <300 seconds (5 minutes target)

## Security Considerations

### Data Protection
- No sensitive data stored in any entity
- All file paths are relative (no system paths exposed)
- Version strings validated against injection patterns
- Zip file contents validated before creation

### Access Control
- All entities are ephemeral (exist only during build)
- No persistent storage beyond GitHub releases
- CI environment variables protected by GitHub Actions
- No credentials stored in data models