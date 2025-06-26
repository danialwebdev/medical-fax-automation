# Medical Billing Fax Automation System

Automates the processing of patient data and faxing of medical forms to providers using RingCentral's API.

## Features

- Import patient data from Google Sheets
- Generate PDF medical forms automatically
- Queue and send faxes via RingCentral
- Track fax status (pending/sent/failed)
- Automatic retry for failed faxes
- Dashboard for monitoring operations

## Requirements

- PHP 7.4+
- MySQL/MariaDB
- RingCentral developer account
- Google Sheets API access

## Installation

1. Clone this repository
2. Run `composer install`
3. Configure database settings in `includes/config.php`
4. Set up RingCentral credentials
5. Set up cron job for fax queue processing

## Usage

1. Upload patient data via Google Sheet URL
2. System processes data and generates PDFs
3. Faxes are queued and sent automatically
4. Monitor status through the dashboard

## License

[MIT License](LICENSE)
