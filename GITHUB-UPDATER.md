# GitHub Auto-Updater

The Choice Universal Form Tracker plugin includes a GitHub-based auto-updater that allows you to receive updates directly from the public GitHub repository instead of WordPress.org.

## Features

- **Automatic Updates**: Checks for new releases on GitHub twice daily
- **Seamless Integration**: Works with WordPress's built-in update system
- **Public Repository Access**: No authentication required
- **Rate Limit Management**: Built-in API rate limiting and caching
- **Fallback Support**: Falls back to WordPress.org if GitHub updates are disabled

## Configuration

### Enable GitHub Updates

1. Go to **Settings > Universal Form Tracker** in your WordPress admin
2. Scroll down to the **GitHub Auto-Updates** section
3. Check **Enable automatic updates from GitHub repository**
4. Click **Save Settings**

That's it! No additional configuration is needed since the repository is public.

## How It Works

### Update Detection

The plugin uses WordPress's built-in update system by hooking into:

- `pre_set_site_transient_update_plugins` - Checks for available updates
- `plugins_api` - Provides plugin information for the update screen
- `upgrader_pre_download` - Handles downloading from GitHub

### API Endpoints

The updater uses these GitHub API endpoints:

- `GET /repos/ChoiceOMG/choice-uft/releases/latest` - Get latest version
- `GET /repos/ChoiceOMG/choice-uft/releases` - Get changelog information

### Download Process

When an update is found:

1. WordPress displays the update notification
2. User clicks "Update Now"
3. Plugin downloads the zip file directly from GitHub
4. WordPress installs the update normally

### Caching

To prevent API rate limiting:

- Version information is cached for 1 hour
- Changelog data is cached for 1 hour
- Cache is cleared when plugin is updated

## Repository Structure

The updater expects releases to be tagged in the format:

- `v3.3.0` - Standard semantic versioning with 'v' prefix
- `3.3.0` - Will also work, 'v' prefix is automatically stripped

## Rate Limits

GitHub allows 60 API requests per hour for unauthenticated requests, which is sufficient since the plugin only checks twice daily (2 requests per day).

## Security

- All API requests are made over HTTPS
- Download URLs are validated before processing
- All data is sanitized before storage

## Troubleshooting

### Updates Not Showing

1. **Check Settings**: Ensure GitHub updates are enabled
2. **Network Issues**: Verify your server can reach api.github.com
3. **Cache Issues**: Try clearing transients by deactivating/reactivating the plugin

### Debug Information

Enable debug logging in the plugin settings to see detailed information about:

- API requests and responses
- Version comparison results
- Download and installation process

### Common Issues

**"Could not retrieve version information"**

- Check if GitHub API is accessible from your server
- Verify the repository exists and is public

**"Download failed"**

- Verify the release has downloadable assets
- Check if GitHub's download servers are accessible

**"Update appears available but won't install"**

- Check WordPress file permissions
- Verify tmp directory is writable

## Manual Fallback

If GitHub updates fail, you can always:

1. Disable GitHub updates in settings
2. Upload the plugin manually via WordPress admin
3. Download releases directly from [GitHub](https://github.com/ChoiceOMG/choice-uft/releases)

## Development

### Testing the Updater

To test the updater during development:

1. Create a test release on GitHub
2. Temporarily change the version number in the main plugin file to an older version
3. Enable GitHub updates and check for updates in WordPress

### Custom Repository

To use with a fork or different repository:

1. Edit `choice-universal-form-tracker.php`
2. Change the GitHub username and repository name in the updater initialization:
   ```php
   new CUFT_GitHub_Updater(
       __FILE__,
       CUFT_VERSION,
       'YourUsername',     // Change this
       'your-repo-name'    // Change this
   );
   ```

## API Reference

### CUFT_GitHub_Updater Class

**Constructor Parameters:**

- `$plugin_file` - Main plugin file path
- `$version` - Current plugin version
- `$github_username` - GitHub username/organization
- `$github_repo` - Repository name

**Static Methods:**

- `updates_enabled()` - Check if GitHub updates are enabled
- `set_updates_enabled($enabled)` - Enable/disable GitHub updates

## Support

For issues with the GitHub updater:

1. Check the plugin's debug logs
2. Verify GitHub API access from your server
3. Create an issue on the [GitHub repository](https://github.com/ChoiceOMG/choice-uft/issues)
