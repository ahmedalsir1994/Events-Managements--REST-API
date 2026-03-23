# Event Management API

A RESTful API built with **Laravel 12** for managing events and attendees. It supports token-based authentication, authorization policies, relationship eager-loading, email notifications, and a scheduled reminder command.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| Authentication | Laravel Sanctum (token-based) |
| Authorization | Laravel Policies |
| Database | MySQL |
| Mail / Preview | SMTP → Mailpit (local dev) |
| Notifications | Laravel Notifications (Mail channel) |
| Scheduler | Laravel Artisan Command |
| API Responses | Laravel API Resources |
| Dev tooling | Laravel Pint, Pail, Sail, PHPUnit |

---

## Requirements

- PHP 8.2+
- Composer
- MySQL
- [Mailpit](https://mailpit.axllent.org/) (for local email preview)

---

## Installation

```bash
git clone <repo-url>
cd API_event-management

composer install

cp .env.example .env
php artisan key:generate

# Configure your database in .env, then:
php artisan migrate --seed
```

---

## Environment Configuration (`.env`)

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=api_event_management
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025          # Mailpit SMTP port
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

QUEUE_CONNECTION=sync
```

> View emails locally at **http://localhost:8025** (Mailpit web UI).

---

## Running the Application

```bash
php artisan serve
```

Or run everything together (server + queue + logs + Vite):

```bash
composer run dev
```

---

## Database Schema

### `users`
| Column | Type |
|---|---|
| id | bigint (PK) |
| name | string |
| email | string (unique) |
| password | string (hashed) |
| timestamps | |

### `events`
| Column | Type |
|---|---|
| id | bigint (PK) |
| user_id | FK → users |
| name | string |
| description | text (nullable) |
| start_time | datetime |
| end_time | datetime |
| timestamps | |

### `attendees`
| Column | Type |
|---|---|
| id | bigint (PK) |
| user_id | FK → users |
| event_id | FK → events |
| timestamps | |

---

## Authentication

Authentication uses **Laravel Sanctum** (token-based).

### Login
```
POST /api/login
```
**Body:**
```json
{
  "email": "user@example.com",
  "password": "password"
}
```
**Response:**
```json
{
  "token": "1|abc123..."
}
```

Pass the token in subsequent requests:
```
Authorization: Bearer <token>
```

### Logout
```
POST /api/logout
```
Requires authentication. Deletes the current access token.

---

## API Endpoints

### Events

| Method | Endpoint | Auth Required | Description |
|---|---|---|---|
| GET | `/api/events` | No | List all events (paginated) |
| GET | `/api/events/{id}` | No | Get a single event |
| POST | `/api/events` | Yes | Create an event |
| PUT/PATCH | `/api/events/{id}` | Yes (owner only) | Update an event |
| DELETE | `/api/events/{id}` | Yes (owner only) | Delete an event |

#### Create / Update Event — Request Body

```json
{
  "name": "Laravel Conference",
  "description": "Annual Laravel developer meetup",
  "start_time": "2026-06-01 09:00:00",
  "end_time": "2026-06-01 18:00:00"
}
```

#### Event Resource Response

```json
{
  "id": 1,
  "name": "Laravel Conference",
  "description": "Annual Laravel developer meetup",
  "start_time": "2026-06-01T09:00:00.000000Z",
  "end_time": "2026-06-01T18:00:00.000000Z",
  "user_id": 1,
  "user": { ... },
  "attendees": [ ... ]
}
```

---

### Attendees

Attendees are scoped to a parent event: `/api/events/{event}/attendees`

| Method | Endpoint | Auth Required | Description |
|---|---|---|---|
| GET | `/api/events/{event}/attendees` | No | List attendees for an event |
| GET | `/api/events/{event}/attendees/{id}` | No | Get a single attendee |
| POST | `/api/events/{event}/attendees` | Yes | Register current user as attendee |
| DELETE | `/api/events/{event}/attendees/{id}` | Yes (owner or event owner) | Remove an attendee |

#### Attendee Resource Response

```json
{
  "id": 1,
  "user_id": 2,
  "event_id": 1,
  "user": { ... },
  "created_at": "2026-03-23T10:00:00.000000Z"
}
```

---

## Eager-Loading Relationships

Any endpoint that returns events or attendees supports an `include` query parameter to load relationships on demand:

```
GET /api/events?include=user,attendees
GET /api/events?include=user,attendees,attendees.user
GET /api/events/{event}/attendees?include=user
```

This is handled by the `CanLoadRelationships` trait, which parses the `include` parameter and calls Eloquent `with()` only for requested relations.

---

## Authorization Policies

### `EventPolicy`
| Action | Rule |
|---|---|
| `viewAny` | Public (guests allowed) |
| `view` | Public (guests allowed) |
| `create` | Any authenticated user |
| `update` | Event owner only |
| `delete` | Event owner only |

### `AttendeePolicy`
| Action | Rule |
|---|---|
| `create` | Any authenticated user |
| `delete` | Attendee themselves **or** the event owner |

---

## Rate Limiting

Write operations (`store`, `update`, `destroy`) on both events and attendees are throttled to **60 requests per minute** per user via the `throttle:60,1` middleware.

---

## Email Notifications & Scheduler

### Reminder Notification

The `EventReminderNotification` sends a mail notification to attendees of upcoming events. It uses Laravel's `MailMessage` and is dispatched via the `mail` channel.

### Artisan Command

```bash
php artisan app:send-event-reminders
```

- Queries all events starting within the **next 24 hours**.
- Notifies every attendee of each matching event via email.
- Intended to be scheduled (e.g. run daily via `php artisan schedule:run`).

---

## API Resources

Responses are transformed using dedicated API Resource classes to ensure a consistent JSON structure:

- `EventResource` — formats event data, conditionally includes `user` and `attendees`
- `AttendeeResource` — formats attendee data, conditionally includes `user`
- `UserResource` — formats basic user data embedded in other resources

---

## Project Structure

```
app/
├── Console/Commands/       # SendEventReminders artisan command
├── Http/
│   ├── Controllers/Api/    # AuthController, EventController, AttendeeController
│   ├── Resources/          # EventResource, AttendeeResource, UserResource
│   └── Traits/             # CanLoadRelationships trait
├── Models/                 # User, Event, Attendee
├── Notifications/          # EventReminderNotification
├── Policies/               # EventPolicy, AttendeePolicy
└── Providers/              # AuthServiceProvider (policy registration)
database/
├── factories/              # UserFactory, EventFactory, AttendeeFactory
├── migrations/             # Schema definitions
└── seeders/                # DatabaseSeeder, EventSeeder, AttendeeSeeder
routes/
└── api.php                 # All API route definitions
```

---

## Running Tests

```bash
php artisan test
```

---

## Seeding the Database

```bash
php artisan db:seed
```

This seeds users, events, and attendees with realistic fake data using Faker factories.
