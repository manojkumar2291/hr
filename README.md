# HR Application

A simple HR portal application.

## Setup Instructions

### Prerequisites

*   PHP
*   MySQL Server

### Database Setup

1.  **Create the database:**
    You need to create a MySQL database named `hrapp`. You can use the following SQL command:
    ```sql
    CREATE DATABASE hrapp;
    ```

2.  **Database Credentials:**
    The application connects to the database using the credentials specified in `db.php`. By default, these are:
    *   **Username:** `root`
    *   **Password:** `1234`

    If your MySQL setup uses different credentials, please update them in the `db.php` file.

3.  **Create Tables and Sample User:**
    The database tables (`users` and `user_details`) and a sample user are created by the `setup_database.php` script, which is not part of the repository. The login logic in `login.php` expects a `users` table with `username`, `password` and `role` columns. The password should be hashed.

### Running the Application

To run the application, you need a web server with PHP support (like Apache or Nginx). Place the project files in the web server's document root and access `login.php` from your browser.

### Login Credentials

You can log in with the following sample user:

*   **Username:** `testuser`
*   **Password:** `password123`
