# GTM Web Container Template

**Template Name**: CUFT - Web Defaults
**Version**: Compatible with Choice Universal Form Tracker v3.14.0+
**Container Type**: Google Tag Manager Web Container

## Overview

This JSON file contains a pre-configured Google Tag Manager Web Container with all the necessary tags, triggers, and variables for client-side tracking with the Choice Universal Form Tracker WordPress plugin.

## What's Included

### Tags
- **Google Analytics 4 Configuration**
- **GA4 Form Submit Event**
- **GA4 Generate Lead Event**
- **Google Tag (gtag.js)**
- **Conversion tracking tags**

### Triggers
- **All Pages** - Fires on all page views
- **Form Submit** - Custom event trigger for form_submit
- **Generate Lead** - Custom event trigger for generate_lead

### Variables
- **GA4 Measurement ID**
- **DataLayer Variables** for form tracking
- **UTM Parameters**
- **Click IDs** (gclid, fbclid, etc.)

## Installation Instructions

### 1. Import Container

1. Log into [Google Tag Manager](https://tagmanager.google.com/)
2. Navigate to **Admin → Import Container**
3. Select your **Web Container**
4. Upload `CUFT - Web Defaults.json`
5. Choose **Merge** (recommended) or **Overwrite**
6. Select or create a workspace
7. Click **Confirm**

### 2. Update Placeholder IDs

**CRITICAL**: Before publishing, you MUST replace these placeholder values:

#### Find and Replace in GTM UI:

**Container Information**:
- `GTM-XXXXX3` → Your actual Web GTM Container ID
- `1234567890` → Your Google Tag Manager Account ID
- `987654321` → Your Container ID (numeric)

**Measurement IDs**:
- `G-XXXXXXXXX` → Your actual Google Analytics 4 Measurement ID
  - Example: `G-ABC123XYZ`
  - Find this in GA4: Admin → Data Streams → Web Stream Details

### 3. Configure Google Analytics 4

#### Update GA4 Configuration Tag

1. Go to **Tags** → **GA4 Configuration**
2. Update **Measurement ID**:
   - Replace `G-XXXXXXXXX` with your actual GA4 property ID
3. Configure **Configuration Parameters** (optional):
   ```
   send_page_view: true
   cookie_domain: auto
   cookie_flags: secure;samesite=none
   ```

4. If using **Server-Side Tagging**:
   - Enable **Send to server container**
   - Set **Server Container URL**: `https://your-server.example.com`

#### Update GA4 Event Tags

##### Form Submit Event Tag
- **Tag Name**: GA4 - Form Submit
- **Event Name**: `form_submit`
- **Event Parameters**:
  - `form_type`: `{{DL - Form Type}}`
  - `form_id`: `{{DL - Form ID}}`
  - `form_name`: `{{DL - Form Name}}`
  - `user_email`: `{{DL - User Email}}`
  - `user_phone`: `{{DL - User Phone}}`
  - `utm_source`: `{{DL - UTM Source}}`
  - `utm_medium`: `{{DL - UTM Medium}}`
  - `utm_campaign`: `{{DL - UTM Campaign}}`
  - `click_id`: `{{DL - Click ID}}`

##### Generate Lead Event Tag
- **Tag Name**: GA4 - Generate Lead
- **Event Name**: `generate_lead`
- **Event Parameters**: Same as Form Submit +
  - `currency`: `USD`
  - `value`: `0` (or dynamic value)

### 4. Configure DataLayer Variables

Verify these variables are properly configured:

#### Form Tracking Variables
- **DL - Form Type**: `form_type`
- **DL - Form ID**: `form_id`
- **DL - Form Name**: `form_name`
- **DL - User Email**: `user_email`
- **DL - User Phone**: `user_phone`
- **DL - Submitted At**: `submitted_at`

#### CUFT Tracking Variables
- **DL - CUFT Tracked**: `cuft_tracked`
- **DL - CUFT Source**: `cuft_source`

#### UTM Parameter Variables
- **DL - UTM Source**: `utm_source`
- **DL - UTM Medium**: `utm_medium`
- **DL - UTM Campaign**: `utm_campaign`
- **DL - UTM Term**: `utm_term`
- **DL - UTM Content**: `utm_content`

#### Click ID Variables
- **DL - Click ID**: `click_id` (generic)
- **DL - GCLID**: `gclid` (Google Ads)
- **DL - FBCLID**: `fbclid` (Facebook)
- **DL - MSCLKID**: `msclkid` (Microsoft Ads)

### 5. Configure Triggers

#### Form Submit Trigger
- **Type**: Custom Event
- **Event Name**: `form_submit`
- **This trigger fires on**: All Custom Events
- **Fire this trigger when**: Event equals `form_submit`

#### Generate Lead Trigger
- **Type**: Custom Event
- **Event Name**: `generate_lead`
- **Additional Conditions**:
  - `{{DL - User Email}}` does not equal `undefined`
  - `{{DL - User Phone}}` does not equal `undefined`
  - `{{DL - Click ID}}` does not equal `undefined`

### 6. Optional: Configure Conversion Tags

#### Google Ads Conversion Tracking

1. Create new tag: **Google Ads Conversion Tracking**
2. Set **Conversion ID** and **Conversion Label**
3. Set trigger: **Generate Lead**
4. Add conversion value if applicable

#### Facebook Pixel

1. Create new tag: **Facebook Pixel - Lead**
2. Set **Pixel ID**
3. Set **Event Name**: `Lead`
4. Map parameters:
   - `em`: `{{DL - User Email}}`
   - `ph`: `{{DL - User Phone}}`
5. Set trigger: **Generate Lead**

#### LinkedIn Insight Tag

1. Create new tag: **LinkedIn Insight Tag**
2. Set **Partner ID**
3. Add conversion: `form_submission`
4. Set trigger: **Form Submit**

### 7. Test Your Setup

#### Using GTM Preview Mode

1. Click **Preview** in GTM workspace
2. Enter your website URL
3. Submit a test form
4. Verify in **Tag Manager** debugger:
   - ✅ Form Submit event fires
   - ✅ GA4 tags fire correctly
   - ✅ All variables populate with correct values
   - ✅ Generate Lead fires when conditions met

#### Using GA4 DebugView

1. Enable debug mode:
   ```javascript
   // Add to URL or localStorage
   ?gtm_debug=1
   ```
2. Open GA4 → **Configure** → **DebugView**
3. Submit test form
4. Verify events appear:
   - `form_submit` event with parameters
   - `generate_lead` event (if email + phone + click_id present)

#### Browser Console Testing

```javascript
// Check if GTM is loaded
console.log(window.google_tag_manager);

// View dataLayer
console.log(window.dataLayer);

// Filter for form events
window.dataLayer.filter(e => e.event === 'form_submit');

// Manually push test event
window.dataLayer.push({
  event: 'form_submit',
  form_type: 'test',
  form_id: 'test-123',
  user_email: 'test@example.com',
  cuft_tracked: true
});
```

### 8. Publish Container

Once tested and verified:

1. Click **Submit** in workspace
2. Add **Version Name**: e.g., "CUFT v3.14.0 Integration"
3. Add **Version Description**: Describe what was configured
4. Click **Publish**

## Expected DataLayer Events

The Choice Universal Form Tracker plugin pushes events in this format:

### Form Submit Event
```javascript
{
  "event": "form_submit",
  "form_type": "elementor",           // Framework identifier
  "form_id": "elementor-form-abc123", // Unique form ID
  "form_name": "Contact Form",        // Human-readable name
  "user_email": "user@example.com",   // Email field value
  "user_phone": "555-0123",           // Phone field value
  "submitted_at": "2025-01-10T10:30:00Z", // ISO timestamp
  "cuft_tracked": true,               // CUFT tracking marker
  "cuft_source": "elementor_pro",     // Framework source
  "click_id": "abc123",               // Generic click ID
  "gclid": "xyz789",                  // Google Ads click ID
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "summer_sale",
  "utm_term": "contact_form",
  "utm_content": "sidebar"
}
```

### Generate Lead Event
```javascript
{
  "event": "generate_lead",
  "currency": "USD",
  "value": 0,
  "cuft_tracked": true,
  "cuft_source": "elementor_pro_lead",
  // ... all form_submit fields also included
}
```

**Note**: `generate_lead` only fires when ALL three conditions are met:
1. Email field has a value
2. Phone field has a value
3. Any click ID is present (click_id, gclid, fbclid, etc.)

## Customization

### Adding Custom Event Parameters

To track additional form fields:

1. **Add DataLayer Variable**:
   - Variables → New → Data Layer Variable
   - Name: `DL - Company Name`
   - Data Layer Variable Name: `company_name`

2. **Add to GA4 Event Tag**:
   - Open GA4 Form Submit tag
   - Event Parameters → Add Row
   - Parameter Name: `company_name`
   - Value: `{{DL - Company Name}}`

### Adding New Advertising Platforms

#### TikTok Events

1. Create new tag: **TikTok Events - Lead**
2. Set **Pixel Code**
3. Set **Event Name**: `SubmitForm`
4. Map parameters:
   - `email`: `{{DL - User Email}}`
   - `phone_number`: `{{DL - User Phone}}`
5. Trigger: Form Submit

#### Twitter/X Events

1. Create new tag: **Twitter Universal Website Tag**
2. Set **Pixel ID**
3. Add conversion event: `tw-xxxxx-yyyyy`
4. Trigger: Generate Lead

### Creating Custom Triggers

#### Trigger for Specific Form Types

```
Trigger Type: Custom Event
Event Name: form_submit
Fire When: DL - Form Type equals "elementor"
```

#### Trigger for High-Value Leads

```
Trigger Type: Custom Event
Event Name: generate_lead
Fire When:
  - DL - UTM Source equals "google"
  - DL - UTM Medium equals "cpc"
```

## Troubleshooting

### Events Not Firing

**Check**:
1. GTM container is installed on site
2. CUFT plugin is active and configured
3. Form framework plugin is active
4. DataLayer is defined: `window.dataLayer`
5. Events are being pushed (check browser console)

**Common Issues**:
- GTM container not published
- Wrong container ID in WordPress
- JavaScript errors blocking GTM
- AdBlocker blocking tags

### Variables Not Populating

**Check**:
1. Variable names match dataLayer event properties
2. DataLayer event includes the expected fields
3. Variable version is set to "Version 2"
4. No typos in variable configuration

**Debug**:
```javascript
// Check what's in dataLayer
console.log(window.dataLayer);

// Check specific event
window.dataLayer.filter(e => e.event === 'form_submit')[0];

// Check variable value in GTM Preview
// Look in Variables tab of debugger
```

### GA4 Events Not Showing

**Check**:
1. GA4 Measurement ID is correct
2. GA4 Configuration tag fires first
3. Event tag has correct measurement ID
4. Wait 24-48 hours for data to appear in standard reports
5. Use DebugView for real-time validation

**Common Issues**:
- Wrong measurement ID
- Configuration tag not firing
- Event parameters exceed limits
- Data filters blocking events

### Generate Lead Not Firing

**Verify Conditions**:
```javascript
// All three must be true:
const event = window.dataLayer.filter(e => e.event === 'form_submit')[0];
console.log('Has email:', !!event.user_email);
console.log('Has phone:', !!event.user_phone);
console.log('Has click_id:', !!event.click_id);
```

If any are false, `generate_lead` won't fire (this is intentional).

## Performance Considerations

### Minimize Tag Firing

- Use specific triggers instead of "All Pages"
- Limit tags to necessary events only
- Avoid duplicate tags

### Optimize Loading

- Use async loading (default for GTM)
- Defer non-critical tags
- Use trigger exceptions to prevent unnecessary fires

### Monitor Tag Performance

```javascript
// Check GTM load time
performance.getEntriesByType('resource')
  .filter(r => r.name.includes('googletagmanager'))
  .forEach(r => console.log(`${r.name}: ${r.duration}ms`));
```

## Security & Privacy

### GDPR Compliance

1. Add **Consent Mode v2** variables
2. Update tags to respect consent:
   - `analytics_storage`
   - `ad_storage`
   - `ad_user_data`
   - `ad_personalization`

### Data Redaction

To redact sensitive data:

1. Create **Custom JavaScript Variable**:
   ```javascript
   function() {
     var email = {{DL - User Email}};
     if (!email) return undefined;
     // Hash or redact as needed
     return email.replace(/(.{2}).*(@.*)/, '$1***$2');
   }
   ```

2. Use redacted variable in tags instead of raw email

## Integration with WordPress

### WordPress Plugin Settings

In WordPress admin, configure:

1. **Settings** → **Universal Form Tracker**
2. **GTM Container ID**: Enter `GTM-XXXXX3` (your actual ID)
3. **Enable Tracking**: Check all desired frameworks
4. **Save Settings**

### Verify Integration

```php
// WordPress admin
// Settings → Universal Form Tracker → Testing Dashboard
// Create test form, submit, verify events
```

## Support & Resources

- **GTM Help Center**: https://support.google.com/tagmanager
- **GA4 Documentation**: https://support.google.com/analytics/answer/9744165
- **DataLayer Best Practices**: https://developers.google.com/tag-platform/tag-manager/datalayer
- **CUFT Plugin Docs**: `/docs/FORM-BUILDER.md`, `/docs/TESTING.md`

## Version History

- **v3.14.0** (2025-10-02): Initial template with sanitized IDs
  - Pre-configured GA4 event tracking
  - Form Submit and Generate Lead events
  - UTM and click ID support
  - Compatible with CUFT plugin v3.14.0+

---

**Last Updated**: 2025-10-02
**Maintained By**: Choice Universal Form Tracker Team
**License**: Proprietary
