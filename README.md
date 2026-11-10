# Lab 05 - Event Registration

PHP 8 + MySQL/MariaDB form with server-side validation and secure insertion (PDO prepared statements).

## Files
| File | Purpose |
|---|---|
| `index.php` | Form, validation, database insert, extension feature |
| `style.css` | Layout and styling |
| `database.sql` | Creates database `lab05_event_registration` and its table(s) |
| `README.md` | This document |

## Setup
1. Install XAMPP, WAMP, MAMP or any PHP 8.0+ and MySQL 5.7+/MariaDB 10.2+ stack.
2. Import the schema: `mysql -u root -p < database.sql` (or paste it into phpMyAdmin > SQL).
3. Copy this folder into the web root (e.g. `htdocs/`). If your MySQL user or password differs from `root` / empty, edit the `DB_USER` and `DB_PASS` constants at the top of `index.php`.
4. Open `http://localhost/lab05_event_registration/index.php`.

## Database design
`registrations` (PK `registration_id`, UNIQUE (`email`,`workshop`), CHECK seats 1-3). Full definition in `database.sql`.

## Validation rules
- Participant name: required, 3-80 characters, letters plus space . ' - only.
- Email: required, valid, stored lower-case.
- Phone: optional; 9 digits starting with 6 if supplied.
- Workshop and attendance mode (In person / Online): required, from server lists.
- Seats: whole number 1-3.
- Special requirements: optional, max 300 characters. Terms must be ticked.
- Uniqueness is on **email + workshop** (pre-check and composite UNIQUE index), so one email can join different workshops but not the same one twice.

All checks run on the server; HTML5 attributes are only a convenience (the form uses `novalidate` so the server messages can be demonstrated). Invalid submissions keep the entered values (escaped with `htmlspecialchars`) and show a message beside each faulty field.

## Security measures
- Every query uses PDO prepared statements with real (non-emulated) preparation; user input is never concatenated into SQL.
- Success is shown only after `rowCount() === 1`; connection and SQL errors are logged with `error_log` and a generic message is shown to the user.
- CSRF token on every POST, post/redirect/get after success to prevent resubmission.
- All output is HTML-escaped.

## Extension challenge
Totals table: registrations and seats booked per workshop.

## Test cases
Run each case, then fill in the last column with the observed result and attach screenshots of the form and of the database query below.

| # | Case | Input | Expected result | Actual result |
|---|---|---|---|---|
| 1 | Valid submission | `Test User`, `test@example.com`, Web Security Basics, Online, 2 seats, terms ticked | Success with reference; 1 new row | _fill in_ |
| 2 | Missing required value | No attendance mode selected | Error under mode; no row | _fill in_ |
| 3 | Seats out of range | Seats `4` and `0` | Error "1 to 3"; no row | _fill in_ |
| 4 | Invalid email | `test@` | Error under email; no row | _fill in_ |
| 5 | Long requirements | 301 characters | Error under requirements; no row | _fill in_ |
| 6 | Same email, different workshop | Test 1 email with Introduction to Docker | Accepted; 2 rows for the email | _fill in_ |
| 7 | Duplicate email + workshop | Repeat test 1 exactly | Error "already registered for ..."; row count unchanged | _fill in_ |

### Database evidence query
```sql
SELECT * FROM registrations ORDER BY registration_id DESC;
SELECT workshop, COUNT(*) AS registrations, SUM(seats) AS seats FROM registrations GROUP BY workshop;
```

## Assumptions
- Use fictional data only; no real personal data.
- Phone numbers follow the Cameroon mobile format (9 digits starting with 6) because prices are in XAF; change the regular expression in `index.php` to adapt.
