# VIN Decoder for Contact Form 7

A comprehensive WordPress plugin that automatically decodes Vehicle Identification Numbers (VINs) in Contact Form 7 submissions. Features include real-time VIN decoding, comprehensive vehicle data extraction, admin dashboard for managing decoded VINs, and seamless form integration.

## Features

### 🚗 **VIN Decoding**
- **Real-time decoding**: Automatically decodes VINs as users type
- **Multi-API support**: Uses NHTSA's free API with optional VinDecoder.eu integration
- **Comprehensive data**: Extracts 20+ vehicle attributes including make, model, engine specs, safety features, and manufacturing details
- **Caching system**: Stores decoded VINs to avoid redundant API calls

### 📊 **Admin Dashboard**
- **VIN Database**: View all decoded VINs with advanced filtering and search
- **Statistics**: Track decoding activity with charts and metrics
- **Management**: Delete old entries and manage data retention
- **Export ready**: Database structure supports easy data export

### 🎯 **Form Integration**
- **Auto-detection**: Automatically finds VIN fields in Contact Form 7 forms
- **Email integration**: Adds decoded vehicle information to form submission emails
- **Hidden storage**: Securely stores VIN data for processing
- **Validation**: Real-time VIN format validation

### 🔒 **Security & Performance**
- **WordPress security**: Nonces, sanitization, and validation
- **Rate limiting**: Built-in API call management
- **Error handling**: Graceful failure handling with user feedback
- **Caching**: Reduces API calls and improves performance

## Installation

### Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Contact Form 7**: Latest version (required plugin)
- **MySQL/MariaDB**: 5.6 or higher
- **Memory**: Minimum 128MB PHP memory limit recommended

### Compatibility
- ✅ **Tested with WordPress 6.8.3**
- ✅ **Compatible with PHP 7.4 - 8.3**
- ✅ **Works with Contact Form 7 v5.0+**
- ✅ **Supports MySQL 5.6+ and MariaDB 10.0+**

### Installation Steps

#### Method 1: Upload via WordPress Admin (Recommended)

1. **Download the plugin**
   - Download the latest release from GitHub
   - Or clone the repository: `git clone https://github.com/chriswdixon/wordpress_vin_decoder.git`

2. **Install Contact Form 7 first**
   - Go to WordPress Admin > Plugins > Add New
   - Search for "Contact Form 7"
   - Install and activate Contact Form 7

3. **Install VIN Decoder plugin**
   - Go to WordPress Admin > Plugins > Add New > Upload Plugin
   - Choose the `vin-decoder.zip` file (or upload the entire plugin folder)
   - Click "Install Now"
   - Activate the plugin

4. **Verify installation**
   - Look for "VIN Decoder" menu in WordPress admin sidebar
   - Check for any activation notices

#### Method 2: Manual Upload via FTP

1. **Download and extract**
   ```bash
   wget https://github.com/chriswdixon/wordpress_vin_decoder/archive/main.zip
   unzip main.zip
   ```

2. **Upload via FTP**
   - Connect to your server via FTP/SFTP
   - Upload the `wordpress_vin_decoder-main` folder to `/wp-content/plugins/`
   - Rename the folder to `vin-decoder` (optional)

3. **Activate plugins**
   - Go to WordPress Admin > Plugins
   - Activate Contact Form 7 first
   - Then activate "VIN Decoder for Contact Form 7"

#### Method 3: Composer Installation (Advanced)

```bash
composer require chriswdixon/wordpress-vin-decoder:dev-main
```

### Post-Installation Setup

1. **Database tables**: Automatically created on plugin activation
2. **Default settings**: Plugin comes with sensible defaults
3. **File permissions**: Ensure plugin folder has proper permissions (755 recommended)

### Troubleshooting Installation

- **Plugin not appearing**: Clear browser cache and check file permissions
- **Database errors**: Check MySQL user permissions
- **PHP errors**: Verify PHP version and memory limits
- **Contact Form 7 missing**: Plugin requires CF7 to function

## Configuration

### Basic Setup

1. **Access Settings**
   - Go to WordPress Admin > VIN Decoder > Settings

2. **API Configuration**
   - **API Timeout**: Set timeout for API requests (5-30 seconds)
   - **Secondary API**: Enable VinDecoder.eu for additional data
   - **API Key**: Enter your VinDecoder.eu API key (if using secondary API)

### Form Integration

The plugin automatically detects VIN fields in Contact Form 7 forms. Create a form field with:
- **Name**: `vin`, `vehicle-vin`, or any name containing "vin"
- **Type**: Text input
- **Placeholder**: "Enter VIN" or similar

Example Contact Form 7 form:
```
<label> Vehicle VIN
    [text* vin placeholder "Enter 17-character VIN"]
</label>

[submit "Submit"]
```

## Usage

### For Site Visitors

1. **Fill out the form**: Enter a 17-character VIN in the designated field
2. **Real-time feedback**: See validation status and vehicle preview
3. **Submit**: Vehicle information is automatically included in the email

### For Administrators

1. **Dashboard Overview**
   - Visit WordPress Admin > VIN Decoder
   - View statistics and recent activity

2. **VIN Database**
   - Go to WordPress Admin > VIN Decoder > VIN Database
   - Search and filter decoded VINs
   - View detailed vehicle information
   - Delete entries as needed

## API Information

### Primary API: NHTSA
- **Source**: National Highway Traffic Safety Administration
- **Cost**: Free
- **Data**: Comprehensive vehicle specifications
- **Rate limits**: None specified

### Secondary API: VinDecoder.eu
- **Source**: Third-party VIN decoding service
- **Cost**: Freemium with paid plans
- **Data**: Additional market data (MSRP, category)
- **Setup**: Requires API key from VinDecoder.eu

## Database Structure

The plugin creates two custom tables:

### vin_decoder_decodes
Stores decoded VIN information:
- `vin` - Vehicle Identification Number (primary key)
- `make`, `model`, `year` - Basic vehicle info
- `engine_*` - Engine specifications
- `safety_*` - Safety features
- `raw_data` - Complete API response (JSON)
- `api_source` - API used for decoding
- `decoded_at` - Timestamp

### vin_decoder_submissions
Links form submissions to VINs:
- `vin_id` - Foreign key to decodes table
- `form_id` - Contact Form 7 form identifier
- `submission_data` - Form data (JSON)
- `user_ip`, `user_agent` - Tracking info
- `submitted_at` - Timestamp

## Security Features

- **Input sanitization**: All user inputs are sanitized
- **Nonces**: CSRF protection on all AJAX requests
- **Validation**: VIN format validation client and server-side
- **SQL injection prevention**: Prepared statements throughout
- **XSS protection**: Output escaping and content filtering

## Performance Optimization

- **Caching**: Decoded VINs are cached to reduce API calls
- **Debouncing**: VIN decoding waits for user to stop typing
- **Async processing**: Non-blocking AJAX requests
- **Database indexing**: Optimized queries with proper indexes

## Troubleshooting

### Common Issues

1. **VIN not decoding**
   - Check VIN format (17 characters, no I/O/Q)
   - Verify API connectivity
   - Check browser console for errors

2. **Form not submitting**
   - Ensure Contact Form 7 is active
   - Check for JavaScript errors
   - Verify field names contain "vin"

3. **Admin dashboard empty**
   - Check user permissions
   - Verify database tables exist
   - Check for PHP errors

### Debug Mode

Enable WordPress debug mode to see detailed error logs:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Development

### File Structure
```
wordpress_vin_decoder/
├── vin-decoder.php              # Main plugin file
├── uninstall.php                # Uninstall cleanup
├── includes/
│   ├── class-vin-decoder-db.php     # Database operations
│   ├── class-vin-decoder-admin.php  # Admin interface
│   ├── class-vin-decoder-frontend.php # Frontend integration
│   └── class-vin-decoder-api.php    # API integrations
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── languages/                   # Translation files
├── contact7form_integration.php # Legacy compatibility
├── cf7_vin_decoder.js          # Legacy JavaScript
└── README.md
```

### Hooks and Filters

#### Actions
- `vin_decoder_before_decode` - Before VIN decoding
- `vin_decoder_after_decode` - After successful decoding
- `vin_decoder_decode_failed` - When decoding fails

#### Filters
- `vin_decoder_api_timeout` - Modify API timeout
- `vin_decoder_enable_secondary_api` - Control secondary API usage
- `vin_decoder_email_format` - Customize email formatting

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## Changelog

### Version 1.0.0
- Initial release with full plugin functionality
- NHTSA API integration
- Contact Form 7 integration
- Admin dashboard and VIN database
- Settings page and configuration options
- Security hardening and performance optimization
- Tested with WordPress 6.8.3

## License

This plugin is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Create an issue on GitHub
- Check the WordPress.org support forums
- Review the documentation above

## Credits

- **NHTSA**: For providing free VIN decoding API
- **Contact Form 7**: For the excellent form plugin
- **WordPress Community**: For the robust plugin framework
