# Phodio — Soul Print Photography Service Management

Phodio is the web-based photography service management system for Soul Print Multimedia Production. The client and studio workflows are organized around the study objectives: appointment requests, event/service progress monitoring, and preference-based package recommendations.

## Manuscript-aligned workflows

1. **Centralized booking requests:** clients can compare packages, request a morning or afternoon appointment, view occupied periods, edit pending requests, and cancel eligible requests. New client requests are marked **Pending** until the studio confirms them. The database's unique date/period key prevents two active requests from taking the same slot.
2. **Service progress monitoring:** studio staff can post client-visible statuses—Pending, Confirmed, In Progress, Editing, Ready for Pickup, Completed, or Cancelled—with notes. The portal shows the status history and refreshes the selected booking/calendar every 30 seconds.
3. **Package recommendations:** the package finder ranks studio packages against the client's session type, group size, budget, style, and backdrop preference. It is an explainable, local weighted-matching/expert-rule engine: it does not send client preferences to an external AI API and does not claim to use a trained machine-learning model. The catalog is shared by recommendations and booking so suggested packages can be selected directly.

## Database setup

The application uses PHP with `mysqli` and MySQL/MariaDB.

- **New installation:** create the `studio_mgmt` database and import `Phodio/studio_mgmt.sql`.
- **Existing installation:** back up the database, then apply the one-time migration `Phodio/db/migrations/20261002_manuscript_alignment.sql` to `studio_mgmt`. Do not run this migration against a database already created from the updated SQL dump.
- Configure the connection in `Phodio/db/db.php` for the local MySQL user/database.

Open the client portal through `Phodio/index.php`; studio administration is under `Phodio/admin/`. The client must be logged in to submit requests or view private booking details. Studio booking and progress endpoints require an admin session.
