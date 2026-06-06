# LeadPilot

AI-assisted Laravel CRM for finding Pakistani businesses that have public Google Business data but no listed website. The application uses the official Google Places API and generates outreach drafts for manual review; it does not scrape Google Maps pages or send automated messages.

## Included

- Laravel 12 authentication, password reset, Spatie roles, and Super Admin usage logs
- Services and queued Google Places campaigns with progress, filters, duplicate detection, blacklists, and deterministic scoring
- Lead table, Excel export, CRM Kanban board with persisted drag/drop status and order
- Lead details, notes, follow-ups, activity timeline, Claude JSON analysis, English and Roman Urdu outreach drafts
- Dashboard metrics, campaign history, settings, saved-search data model, API/AI/export logs
- Responsive Bootstrap UI with no frontend build required

## Setup

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`, then add:

```dotenv
GOOGLE_PLACES_API_KEY=your_google_places_api_key
ANTHROPIC_API_KEY=your_anthropic_api_key
QUEUE_CONNECTION=database
CAMPAIGN_QUEUE_CONNECTION=background
```

Run:

```powershell
php artisan migrate --seed
php artisan serve
```

Campaigns use Laravel's `background` queue connection by default, so clicking
**Run campaign** starts a separate PHP process automatically. No terminal queue
worker is required. On a production server managed by Supervisor, set
`CAMPAIGN_QUEUE_CONNECTION=database` and run the normal queue worker.

Demo login after seeding: `admin@leadpilot.pk` / `password`. Change this password immediately outside local development.

For a quick local demo, the default SQLite configuration also works. Email reset links are written to `storage/logs/laravel.log` while `MAIL_MAILER=log`.

## Compliance

- Enable the Google Places API (New) for the configured project and follow Google's storage/display terms.
- The integration requests only public Place fields and records request metadata in `api_logs`.
- Do not use collected phone numbers for unsolicited bulk messaging. Outreach text is generated for a human to review, copy, and send where lawful and appropriate.
- API keys stay in environment configuration and are never displayed or saved through the web settings form.

## Verification

```powershell
php artisan test
php artisan route:list
php artisan view:cache
```

