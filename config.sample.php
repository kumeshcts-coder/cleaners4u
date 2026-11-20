<?php
/**
 * Cleaners4u quote form: the one configuration file.
 *
 * Copy this file to config.php (same folder) and fill in the values.
 * config.php is git-ignored and must never be committed: it holds passwords.
 * It is a PHP file, so the web server runs it instead of showing it, and the
 * guard below makes a direct request return nothing.
 */
defined('C4U_APP') || exit;

return [

    // Website details used in emails and links.
    'site' => [
        'name'       => 'Cleaners4u',
        'url'        => 'https://cleaners4u.com.au',
        'phone'      => '03 7042 9778',
        'phone_href' => '0370429778',
        'logo_url'   => 'https://cleaners4u.com.au/assets/email/logo.png',
        'timezone'   => 'Australia/Melbourne',
    ],

    // MySQL / MariaDB database (create it in CloudPanel > Databases, then import database/schema.sql).
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => '',
        'user'    => '',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // SMTP server used to send every email (PHPMailer).
    'smtp' => [
        'host'       => '',
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'encryption' => 'tls',          // 'tls' = STARTTLS (usually port 587), 'ssl' = SMTPS (port 465), 'none'
        'from_email' => '',             // must be an address your SMTP account may send from
        'from_name'  => 'Cleaners4u',
        'timeout'    => 15,             // seconds
    ],

    // Where new-lead and enquiry emails go. One address, or several separated by commas.
    'admin_email' => '',

    // Google Maps browser key for the address/suburb suggestions on step 2 (Maps JavaScript API + Places API (New)).
    // Restrict it in Google Cloud to the website https://cleaners4u.com.au/* and to those two APIs.
    // Leave empty to use the built-in Melbourne suburb list instead.
    'google' => [
        'maps_api_key' => '',
    ],

    // Spam, duplicate and abuse protection.
    'security' => [
        'allowed_hosts'             => ['cleaners4u.com.au', 'www.cleaners4u.com.au'],
        'trusted_proxies'           => ['127.0.0.1', '::1'],  // Varnish / nginx in front of PHP
        'min_fill_seconds'          => 2,     // faster step 1 submissions are treated as bots
        'max_leads_per_ip_per_hour' => 10,
        'duplicate_window_minutes'  => 30,    // a repeat step 1 with the same email + phone reuses the lead
        'session_idle_minutes'      => 120,
    ],

    // Log folder for database and SMTP errors. Keep it OUTSIDE the public web folder.
    // On the CloudPanel server this is /home/cleaners4u/logs/forms.
    'log_dir' => dirname(__DIR__, 2) . '/logs/forms',
];
