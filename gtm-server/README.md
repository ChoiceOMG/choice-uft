# GTM Server Container Template

**Template Name**: CUFT - Server Defaults
**Version**: Compatible with Choice Universal Form Tracker v3.14.0+
**Container Type**: Google Tag Manager Server-Side

## Overview

This JSON file contains a pre-configured Google Tag Manager Server-Side container with all the necessary tags, triggers, and variables for tracking form submissions with the Choice Universal Form Tracker plugin.

## What's Included

### Tags
- **Conversion Linker** - Links ad clicks to conversions
- **GA4 Event Tag** - Sends events to Google Analytics 4
- **Facebook Conversions API** - Server-side Facebook tracking
- **Server-side tracking configurations**

### Triggers
- **All Events** - Fires on every incoming event
- **Form Submit** - Fires on form_submit events
- **Generate Lead** - Fires on generate_lead events

### Variables
- **Event Name** - Captures the event name from the request
- **User Email** - Extracts user email from event data
- **User Phone** - Extracts user phone from event data
- **Click ID** - Generic click ID variable
- **UTM Parameters** - Source, medium, campaign, term, content

## Installation Instructions

### 1. Import Container

1. Log into [Google Tag Manager](https://tagmanager.google.com/)
2. Navigate to **Admin → Import Container**
3. Upload `CUFT - Server Defaults.json`
4. Choose **Merge** or **Overwrite** based on your preference
5. Rename imported workspace if needed

### 2. Update Placeholder IDs

**IMPORTANT**: Before publishing, you MUST replace these placeholder values:

#### Replace Container IDs
- `GTM-XXXXX1` → Your actual Server GTM Container ID
- `GTM-XXXXX2` → Your Web Container ID (if referenced)
- `1234567890` → Your Google Tag Manager Account ID
- `987654321` → Your Container ID (numeric)

#### Replace Measurement IDs
- `G-XXXXXXXXX` → Your actual Google Analytics 4 Measurement ID (e.g., `G-ABC123XYZ`)

#### Replace URLs
- `tagging-server.example.com` → Your actual server-side tagging URL

### 3. Configure Destinations

After import, configure these destinations:

#### Google Analytics 4
1. Open the **GA4 Configuration** tag
2. Update **Measurement ID** to your actual GA4 property ID
3. Configure **Server Container URL** if using server-side tracking

#### Facebook Conversions API
1. Open the **Facebook CAPI** tag
2. Update **Pixel ID** to your Facebook Pixel ID
3. Add your **API Access Token**
4. Configure event mapping

#### Other Ad Platforms
Configure tags for:
- Google Ads Conversion Tracking
- Microsoft Ads (Bing)
- TikTok Events API
- LinkedIn Insight Tag
- Twitter/X Events

### 4. Test Your Setup

1. **Preview Mode**: Use GTM's Preview mode
2. **Test Events**: Submit a test form on your site
3. **Verify**: Check that events appear in:
   - GTM Debug Console
   - GA4 DebugView
   - Facebook Events Manager

### 5. Publish Container

Once tested, publish your container:
1. Click **Submit** in workspace
2. Add version name and description
3. Click **Publish**

## Template Contents

### Pre-configured Tags

#### 1. Conversion Linker
- **Type**: Google Ads Conversion Linker
- **Purpose**: Links ad clicks to conversions
- **Trigger**: All Pages

#### 2. GA4 Event Tag
- **Type**: Google Analytics 4
- **Purpose**: Sends form_submit and generate_lead events to GA4
- **Trigger**: Form Submit, Generate Lead
- **Parameters**:
  - form_type
  - form_id
  - user_email
  - user_phone
  - click_id
  - utm_campaign
  - utm_source
  - utm_medium

#### 3. Facebook Conversions API
- **Type**: Facebook CAPI
- **Purpose**: Server-side conversion tracking
- **Events**: Lead, Contact
- **Parameters**:
  - em (hashed email)
  - ph (hashed phone)
  - fbp (Facebook browser ID)
  - fbc (Facebook click ID)

### Pre-configured Variables

#### Event Data Variables
- `{{Event Name}}` - Event name from dataLayer
- `{{User Email}}` - Email from form submission
- `{{User Phone}}` - Phone from form submission
- `{{Form Type}}` - Framework identifier (elementor, cf7, etc.)
- `{{Form ID}}` - Unique form identifier

#### Tracking Parameters
- `{{Click ID}}` - Generic click ID
- `{{GCLID}}` - Google Ads Click ID
- `{{FBCLID}}` - Facebook Click ID
- `{{UTM Source}}` - Campaign source
- `{{UTM Medium}}` - Campaign medium
- `{{UTM Campaign}}` - Campaign name
- `{{UTM Term}}` - Campaign keyword
- `{{UTM Content}}` - Ad content identifier

### Pre-configured Triggers

#### All Events Trigger
- **Type**: Custom Event
- **Event Name**: `.*` (regex - matches all)
- **Use Regex**: Yes

#### Form Submit Trigger
- **Type**: Custom Event
- **Event Name**: `form_submit`
- **Fires On**: Events where event name equals `form_submit`

#### Generate Lead Trigger
- **Type**: Custom Event
- **Event Name**: `generate_lead`
- **Fires On**: Events where event name equals `generate_lead`
- **Conditions**:
  - Email is not empty
  - Phone is not empty
  - Click ID is present

## Expected Event Format

Events sent to this container should match this format:

```javascript
{
  "event": "form_submit",
  "form_type": "elementor",
  "form_id": "elementor-form-abc123",
  "form_name": "Contact Form",
  "user_email": "user@example.com",
  "user_phone": "555-0123",
  "submitted_at": "2025-01-10T10:30:00Z",
  "cuft_tracked": true,
  "cuft_source": "elementor_pro",
  "click_id": "abc123",
  "gclid": "xyz789",
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "summer_sale"
}
```

## Customization

### Adding New Destinations

To add a new advertising platform:

1. **Create New Tag**:
   - Click **New** in Tags section
   - Choose platform template (e.g., LinkedIn Insight Tag)
   - Configure platform-specific settings

2. **Map Event Parameters**:
   - Use existing variables or create new ones
   - Map to platform's required format

3. **Add Trigger**:
   - Use existing Form Submit or Generate Lead triggers
   - Or create custom trigger with specific conditions

### Modifying Event Mapping

To change how events are mapped:

1. Open the **GA4 Event Tag**
2. Modify **Event Parameters** section
3. Add/remove parameters as needed
4. Update variable mappings

### Adding Custom Variables

To extract additional data from events:

1. **Variables** → **New**
2. Choose **Data Layer Variable**
3. Set **Variable Name** to event property (e.g., `user_company`)
4. Use in tags as needed

## Server-Side Tagging Setup

If using Google Cloud or custom server:

### 1. Set Up Tagging Server

```bash
# Google Cloud Run deployment
gcloud run deploy tagging-server \
  --image gcr.io/cloud-tagging-10302018/gtm-cloud-image:stable \
  --platform managed \
  --region us-central1
```

### 2. Configure Custom Domain

1. Point `tagging-server.example.com` to your server
2. Update container URL in GTM
3. Configure SSL certificate

### 3. Update Web Container

In your web container, set:
- **Server Container URL**: `https://tagging-server.example.com`
- **Transport URL**: Same as above

## Troubleshooting

### Events Not Appearing

**Check**:
1. GTM Preview mode shows incoming events
2. Event format matches expected structure
3. Triggers are firing correctly
4. Tags have no errors in debug console

**Common Issues**:
- Wrong container URL
- Missing or incorrect measurement IDs
- Trigger conditions not met
- CORS issues (server-side)

### Missing Parameters

**Check**:
1. Variables are defined correctly
2. Data layer has the expected fields
3. Variable names match event properties
4. No typos in variable configuration

### Server-Side Issues

**Check**:
1. Server is reachable (curl test)
2. DNS records correct
3. SSL certificate valid
4. Firewall allows traffic
5. Container published, not in preview

## Support & Resources

- **GTM Documentation**: https://developers.google.com/tag-manager
- **Server-Side Guide**: https://developers.google.com/tag-platform/tag-manager/server-side
- **CUFT Plugin Docs**: See `/docs/FORM-BUILDER.md`

## Security Notes

- Never commit files with real IDs to public repositories
- Keep API tokens and access tokens secure
- Use environment variables for sensitive data
- Rotate access tokens regularly
- Monitor for unauthorized access

## Version History

- **v3.14.0** (2025-10-02): Initial template with sanitized IDs
  - Pre-configured GA4, Facebook CAPI
  - Standard form tracking events
  - UTM and click ID support

---

**Last Updated**: 2025-10-02
**Maintained By**: Choice Universal Form Tracker Team
**License**: Proprietary
