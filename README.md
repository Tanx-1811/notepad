# Rio Notes

A simple, shareable online notepad. Visit any URL and start typing — the note
autosaves, can be optionally password-protected, and can be renamed to a
custom, easy-to-share identifier.

Live at [rionotes.com](https://rionotes.com/).

## Features

- No sign-up: visiting the site generates a random note and URL
- Autosaves on blur (click/tab away from the textarea)
- Rename a note's URL to a custom identifier
- Optional password protection per note
- Character/word count and last-saved timestamp
- Light/dark theme, follows system preference by default with a manual toggle
- Copy the note's share URL to the clipboard in one click

## Stack

PHP + MySQL (mysqli, prepared statements) on the backend, no framework;
vanilla JS + Bootstrap 5 on the frontend.

## Setup

1. Create a MySQL database with a `notes` table with (at least) the columns
   the code reads and writes: `identifier`, `content`, `passwords`,
   `created_at`, `time_create`. A minimal schema:
   ```sql
   CREATE TABLE notes (
       id INT AUTO_INCREMENT PRIMARY KEY,
       identifier VARCHAR(64) NOT NULL UNIQUE,
       content MEDIUMTEXT,
       passwords VARCHAR(255) DEFAULT NULL,
       created_at DATETIME,
       time_create INT
   );
   ```
2. Copy `.env.example` to `.env` and fill in your database credentials:
   ```
   DB_HOST=localhost
   DB_USER=your_db_user
   DB_PASSWORD=your_db_password
   DB_NAME=your_db_name
   ```
3. Point your web server's document root at this directory (PHP 7.4+ with the
   `mysqli` extension). `.env` is gitignored and never committed.

## Project structure

- `index.php` — main note editor page
- `login.php` — password prompt for protected notes
- `connect.php` — JSON API used by the frontend (load/save/rename/password actions)
- `config.php` — loads `.env` and opens the database connection
- `styles.css` — stylesheet
