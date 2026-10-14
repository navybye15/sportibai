<?php
/**
 * PHPMailer SMTP Configuration Template
 * Based on the pattern from https://github.com/m3lfx/itim211-2b-shop
 *
 * Copy this file to config/mail_config.php and update with your credentials.
 *
 * OPTION 1: MAILTRAP (Rekomendado ng Prof para sa school/testing)
 * - Sign up free at https://mailtrap.io -> Inboxes -> SMTP Settings -> Integrations: PHPMailer
 * - Lahat ng email pupunta sa virtual Mailtrap inbox (hindi mag-eerror o ma-spam).
 *
 * OPTION 2: GMAIL SMTP (Kung gusto mong pumasok sa totoong Gmail app sa cellphone mo)
 * - Kailangan ng Google App Password (hindi regular gmail password).
 * - Host: 'smtp.gmail.com', Port: 587
 */

return array(
    // --- Sample para sa Mailtrap (Default ng Prof): ---
    'host'       => 'sandbox.smtp.mailtrap.io',
    'port'       => 2525,
    'user'       => '', // Ilagay ang Mailtrap Username dito
    'pass'       => '', // Ilagay ang Mailtrap Password dito
    'encryption' => '',
    'from_email' => 'no-reply@sportibai.ph',
    'from_name'  => 'Sportibai Athletes',

    /*
    // --- Sample para sa Totoong Gmail: ---
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'user'       => 'iyongemail@gmail.com',
    'pass'       => 'xxxx xxxx xxxx xxxx', // 16-character Google App Password
    'encryption' => 'tls',
    'from_email' => 'iyongemail@gmail.com',
    'from_name'  => 'Sportibai Athletes',
    */
);
