# MediMind: AI-Powered Medication Reminder and Management System with Predictive Analytics

Capstone prototype, Bachelor of Science in Information Technology, School of Information Technology and Engineering, St. Paul University Philippines, Tuguegarao City, Cagayan.

**Group members**

- John Nino C. Miranda
- Christian Gem S. Raganit

## About the system

MediMind helps patients take their medicines correctly and on time. It sends reminders, records each intake, and uses the intake history to estimate who is at risk of poor adherence, so that doctors and pharmacies can follow up early. It connects patients, doctors and pharmacies in one platform. It supports, but does not replace, the advice of a doctor or pharmacist.

## User roles

| Role | What they can do in the prototype |
|---|---|
| **Patient** | Register medicines and set dosage schedules. Get reminders, confirm or snooze each dose, and review intake history. Read medicine information with safety notes and interaction warnings. Get over-the-counter suggestions and compare pharmacy prices. Invite doctors, add trusted contacts for emergency alerts, choose which pharmacies see refill dates, and ask a pharmacist a question. |
| **Doctor** | Accept link requests from patients. See each linked patient's adherence, risk score (with the reasons behind it), intake history, medicines and interactions. Correct a patient's medicine entries and schedule. Receive and resolve alerts. Doctors never prescribe through the system. |
| **Pharmacy** | Keep a price and stock list used for price comparison. See a refill-due list of patients who agreed to share, and send them follow-up messages. Answer patients' safe-use questions. A pharmacy sees only the medicine name and supply date, never doses, history or contact details. |
| **Admin** | Approve doctor and pharmacy accounts before they can use the system. |

## Core feature

**Smart reminders with predictive adherence-risk alerts.** A patient registers a medicine and a schedule. MediMind sends a reminder at each dose time (in the app and by email), and the patient confirms the dose. Every dose is recorded as taken or missed. From that history, a transparent rule-based score from 0 to 100 estimates the risk of poor adherence and explains its reasons. When a patient misses several doses in a row, MediMind alerts their trusted contacts and linked doctors. When one time of day is often missed, it offers to move that reminder earlier.

The risk score is rule-based (written in PHP) and uses hand-set weights in `config/medimind.php`. It is not a trained machine-learning model yet. The medicine information and over-the-counter suggestions are sample guidance for the prototype and should be reviewed by a pharmacist before real use.

## Demo accounts

Created by the seeder. All use the password `password`.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@medimind.test` | `password` |
| Doctor | `doctor@medimind.test` | `password` |
| Patient | `patient@medimind.test` | `password` |
| Pharmacy | `pharmacy@medimind.test` | `password` |
| Pharmacy (second, for price comparison) | `pharmacy2@medimind.test` | `password` |

The demo patient is already linked to the demo doctor and shares refill dates with the first pharmacy. Prices in the demo data are made-up sample figures.

## Setup (from a fresh clone)

Requirements: PHP 8.3 or newer, Composer, and Node.js with npm. The prototype uses SQLite, so no database server is needed.

```bash
git clone https://github.com/liec14/medimind_prototype.git
cd medimind_prototype

# 1. Install dependencies
composer install
npm install

# 2. Create the environment file and app key
cp .env.example .env          # on Windows PowerShell: copy .env.example .env
php artisan key:generate

# 3. Create the SQLite database file
touch database/database.sqlite   # on Windows PowerShell: New-Item database/database.sqlite

# 4. Create the tables and load the demo data
php artisan migrate:fresh --seed

# 5. Build the front-end assets
npm run build
```

Run the app:

```bash
composer run dev
```

Then open http://localhost:8000 and log in with one of the demo accounts above.

For reminders, missed-dose detection and email to work while you demo, also run these in two more terminals:

```bash
php artisan schedule:work     # marks missed doses and sends reminders every minute
php artisan queue:listen      # delivers the notifications
```

Emails are written to `storage/logs/laravel.log` (the default mail driver is `log`), so no mail server is needed.

Run the automated tests:

```bash
php artisan test
```

Reset the demo data at any time with `php artisan migrate:fresh --seed`. This erases everything in the local database.
