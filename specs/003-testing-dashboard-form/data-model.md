# Data Model: Testing Dashboard Form Builder

## Entity Definitions

### 1. Test Form Template
Represents a reusable form configuration for generating test forms.

**Attributes**:
- `id` (string): Unique identifier (e.g., "basic_contact_form")
- `name` (string): Display name (e.g., "Basic Contact Form")
- `description` (string): Purpose of the template
- `fields` (array): List of field configurations
  - `name` (string): Field identifier
  - `type` (string): Field type (text, email, phone, textarea)
  - `label` (string): Display label
  - `required` (boolean): Is field required
  - `placeholder` (string): Placeholder text
- `framework_support` (array): List of supported frameworks
- `created_at` (timestamp): Template creation time
- `updated_at` (timestamp): Last modification time

**Constraints**:
- Templates must include at least email and phone fields for tracking validation
- Field types limited to: text, email, phone, textarea
- All templates must work across multiple frameworks

### 2. Test Data Set (Existing - Reference Only)
Collection of field values used to populate forms.

**Location**: Managed by existing CUFT_Testing_Dashboard class
**Access**: `CUFT_Testing_Dashboard::get_test_data()`

**Standard Fields**:
- `name`: "Test User"
- `email`: Randomly generated test email
- `phone`: Randomly generated phone number
- `message`: "This is a test submission from CUFT Testing Dashboard"
- `utm_source`, `utm_medium`, `utm_campaign`: Test tracking parameters
- `click_id`, `gclid`, `fbclid`: Test click identifiers

### 3. Generated Form Instance
The actual form created within a framework.

**Attributes**:
- `instance_id` (string): Unique identifier (e.g., "cuft_test_123456")
- `framework` (string): Framework used (elementor, cf7, gravity, ninja, avada)
- `post_id` (int): WordPress post ID of the form
- `form_id` (string): Framework-specific form identifier
- `template_id` (string): Source template ID
- `status` (string): Current status (active, deleted)
- `test_url` (string): URL to access the test form
- `iframe_url` (string): URL for iframe embedding
- `created_by` (int): WordPress user ID who created it
- `created_at` (timestamp): Creation time
- `last_tested` (timestamp): Last submission time
- `test_count` (int): Number of test submissions

**Constraints**:
- Only WordPress administrators can create instances
- Forms persist until manually deleted
- Each instance has a unique test URL

### 4. Framework Adapter
Interface between the form builder and each specific framework.

**Interface Methods**:
- `is_available()`: Check if framework is installed and active
- `get_capabilities()`: Return supported features
- `create_form($template)`: Generate form from template
- `delete_form($form_id)`: Remove test form
- `get_form_url($form_id)`: Get form access URL
- `prepare_test_mode($form_id)`: Configure form for test mode

**Implementations**:
- `CUFT_Elementor_Adapter`
- `CUFT_CF7_Adapter`
- `CUFT_Gravity_Adapter`
- `CUFT_Ninja_Adapter`
- `CUFT_Avada_Adapter`

**Adapter Registry**:
```php
$adapters = [
    'elementor' => 'CUFT_Elementor_Adapter',
    'cf7' => 'CUFT_CF7_Adapter',
    'gravity' => 'CUFT_Gravity_Adapter',
    'ninja' => 'CUFT_Ninja_Adapter',
    'avada' => 'CUFT_Avada_Adapter'
];
```

### 5. Test Session
Context linking generated forms, test data, and captured tracking events.

**Attributes**:
- `session_id` (string): Unique session identifier
- `form_instance_id` (string): Associated form instance
- `test_data` (object): Data used for population
- `events_captured` (array): Tracking events from submission
  - `timestamp` (timestamp): When event was captured
  - `event_type` (string): Type of event (form_submit, generate_lead)
  - `event_data` (object): Complete dataLayer event
- `validation_results` (object): Tracking validation outcomes
  - `has_cuft_tracked` (boolean): Required field present
  - `has_cuft_source` (boolean): Source field present
  - `uses_snake_case` (boolean): Naming convention followed
  - `click_ids_tracked` (array): Which click IDs were captured
- `started_at` (timestamp): Session start time
- `completed_at` (timestamp): Session completion time

**Constraints**:
- Sessions are ephemeral (not persisted to database)
- Used for real-time validation display
- Cleared when dashboard is closed

## Relationships

```
Test Form Template (1) → (*) Generated Form Instance
    - One template can generate multiple form instances

Generated Form Instance (1) → (1) Framework Adapter
    - Each instance is created by exactly one adapter

Generated Form Instance (1) → (*) Test Session
    - One form can have multiple test sessions

Test Session (1) → (1) Test Data Set
    - Each session uses one set of test data

Test Session (1) → (*) Tracking Events
    - One session can capture multiple events
```

## State Transitions

### Generated Form Instance States
```
[Created] → [Active] → [Tested] → [Deleted]
    ↑           ↓          ↓
    └───────────┴──────────┘
         (can retest)
```

### Test Session States
```
[Initialized] → [Form Loaded] → [Data Populated] → [Submitted] → [Validated]
                      ↓                ↓                ↓
                 [Error State] ← ← ← ← ┘                ↓
                                                   [Complete]
```

## Storage Strategy

### WordPress Database Tables

**wp_options** (Configuration):
- `cuft_form_templates`: JSON array of available templates
- `cuft_form_builder_settings`: Feature configuration

**wp_posts** (Form Storage):
- Test forms stored as posts with appropriate post_type
- Post meta stores CUFT-specific metadata

**wp_postmeta** (Instance Metadata):
- `_cuft_test_form`: Marks post as test form
- `_cuft_instance_id`: Unique instance identifier
- `_cuft_template_id`: Source template reference
- `_cuft_framework`: Framework identifier
- `_cuft_test_count`: Submission counter

### Transient Storage

**wp_transients** (Session Data):
- `cuft_test_session_{id}`: Active test session data
- Expires after 1 hour of inactivity

## Data Access Patterns

### Create Test Form
```php
$instance = CUFT_Form_Builder::create_form(
    $template_id,
    $framework,
    $user_id
);
```

### Get Active Test Forms
```php
$forms = CUFT_Form_Builder::get_test_forms([
    'status' => 'active',
    'user_id' => get_current_user_id()
]);
```

### Delete Test Form
```php
CUFT_Form_Builder::delete_form($instance_id);
```

### Record Test Submission
```php
CUFT_Test_Session::record_submission(
    $instance_id,
    $event_data
);
```

## Validation Rules

### Template Validation
- Must include email and phone fields
- Field types restricted to allowed set
- Labels and names must be non-empty

### Instance Validation
- Framework must be installed and active
- User must have admin capabilities
- Instance ID must be unique

### Session Validation
- Form instance must exist and be active
- Test data must match field requirements
- Events must follow dataLayer standards

## Security Considerations

1. **Access Control**: All operations require `manage_options` capability
2. **Nonce Verification**: All AJAX requests validated with nonces
3. **Data Sanitization**: All user input sanitized before storage
4. **Test Mode Isolation**: Test submissions never trigger real actions
5. **Private Visibility**: Test forms not publicly accessible