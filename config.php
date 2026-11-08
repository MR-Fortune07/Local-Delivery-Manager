<?php
/**
 * config.php
 * -----------------------------------------------------------
 * Responsibilities of this file (and only this file):
 *   1. Start the PHP session.
 *   2. Open the database connection (PDO).
 *   3. Provide small helper functions shared by index.php and
 *      actions.php (auth checks, escaping, fare maths, etc).
 *   4. Bootstrap the database on first run — creates the default
 *      super-admin account and the platform_config row if they
 *      don't exist yet, so you don't have to hand-write a seed
 *      insert with a pre-hashed password.
 * -----------------------------------------------------------
 * Change the four constants below to match your own database.
 */

session_start();

// All dates and times in the app use South African time.
date_default_timezone_set("Africa/Johannesburg");

const DB_HOST = "sql110.infinityfree.com";
const DB_NAME = "if0_43330832_phoenix";
const DB_USER = "if0_43330832";
const DB_PASS = "pHoeniX1de1iv3r";
/**
 * Returns a single shared PDO connection for this request.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

/* ---------------------------------------------------------------
   AUTH HELPERS
--------------------------------------------------------------- */

/** Returns the logged-in user's row, or null if nobody is logged in. */
function current_user(): ?array
{
    static $cached = null;
    static $checked = false;
    if ($checked) {
        return $cached;
    }
    $checked = true;
    if (empty($_SESSION["user_id"])) {
        return null;
    }
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
    $user = $stmt->fetch();
    $cached = $user ?: null;
    return $cached;
}

/** Sends the user to the login page if they are not signed in. */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header("Location: index.php");
        exit;
    }
    return $user;
}

/** Strips internal-only fields before a user row is used for display. */
function public_user(array $user): array
{
    unset($user["password_hash"]);
    return $user;
}

/** Like require_login(), but also checks the account's role. */
function require_role(array $allowedRoles): array
{
    $user = require_login();
    if (!in_array($user["role"], $allowedRoles, true)) {
        http_response_code(403);
        die("Forbidden — your account doesn't have access to this page.");
    }
    return $user;
}

function is_super_admin(array $user): bool
{
    return $user["role"] === "admin" && $user["admin_tier"] === "super";
}

/* ---------------------------------------------------------------
   SECURITY HELPERS
--------------------------------------------------------------- */

/** Escapes text for safe HTML output. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}

/** Generates (or reuses) a CSRF token for the current session. */
function csrf_token(): string
{
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}

/** Renders a hidden CSRF input for a form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

/** Halts execution if the submitted CSRF token doesn't match. */
function check_csrf(): void
{
    $submitted = $_POST["csrf"] ?? "";
    if (!hash_equals($_SESSION["csrf"] ?? "", $submitted)) {
        die("Your session expired or the form was submitted incorrectly. Please go back and try again.");
    }
}

/* ---------------------------------------------------------------
   FLASH MESSAGES (one-time success/error banners across a redirect)
--------------------------------------------------------------- */

function flash_set(string $type, string $message): void
{
    $_SESSION["flash"] = ["type" => $type, "message" => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION["flash"])) {
        return null;
    }
    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);
    return $flash;
}

/* ---------------------------------------------------------------
   APP HELPERS
--------------------------------------------------------------- */

function fmt_r($amount): string
{
    return "R" . number_format((float)$amount, 2);
}

function fmt_date(?string $datetime): string
{
    if (!$datetime) {
        return "";
    }
    return date("d M, H:i", strtotime($datetime));
}

/** Great-circle distance between two GPS points, in kilometres. */
function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadiusKm = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/* ---------------------------------------------------------------
   INPUT VALIDATION
--------------------------------------------------------------- */

/** South African mobile/landline numbers: exactly 10 digits, starting with 0 (e.g. 0821234567). */
function valid_sa_phone(string $phone): bool
{
    return (bool)preg_match('/^0\d{9}$/', $phone);
}

const PASSWORD_HINT = "At least 8 characters, with a letter, a number, and a special character (e.g. !@#$%).";

/** Returns a list of unmet password requirements — empty array means the password is valid. */
function password_validation_errors(string $password): array
{
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "at least 8 characters";
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        $errors[] = "a letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "a number";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "a special character (e.g. ! @ # $ % & *)";
    }
    return $errors;
}

/** True if this client has at least one saved card on file. */
function client_has_card(int $clientId): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM client_cards WHERE client_id = ?");
    $stmt->execute([$clientId]);
    return (int)$stmt->fetchColumn() > 0;
}

/** Best-effort card brand guess from the first digit — cosmetic only, not used for processing. */
function guess_card_brand(string $digitsOnly): string
{
    if ($digitsOnly === "") return "Card";
    $first = $digitsOnly[0];
    if ($first === "4") return "Visa";
    if ($first === "5") return "Mastercard";
    if ($first === "3") return "Amex";
    return "Card";
}


/* ---------------------------------------------------------------
   DRIVER VERIFICATION DOCUMENTS
   A driver uploads an ID copy, driving license, and car registration;
   an admin reviews them and marks the submission verified or rejected.
   Only a verified driver (users.verified = 1) appears in the admin's
   driver-assignment lists — see the "assign" and "deliveries" tabs.
--------------------------------------------------------------- */
/** Validates an uploaded image (jpg/png/webp) and returns its extension, or null if invalid. */
function validate_image_upload(array $file, int $maxBytes = 3_000_000): ?string
{
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    $mime = mime_content_type($file["tmp_name"]);
    if (!isset($allowed[$mime]) || $file["size"] > $maxBytes) {
        return null;
    }
    return $allowed[$mime];
}
/** Validates an uploaded PDF and returns "pdf", or null if it is not a real PDF (max 50 MB). */
function validate_pdf_upload(array $file, int $maxBytes = 50_000_000): ?string
{
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    // The real content is checked, not the file name.
    if ($file["size"] > $maxBytes || mime_content_type($file["tmp_name"]) !== "application/pdf") {
        return null;
    }
    return "pdf";
}


/** The driver's current (only) document submission, or null if they haven't uploaded one yet. */
function driver_document_status(int $driverId): ?array
{
    $stmt = db()->prepare("SELECT * FROM driver_documents WHERE driver_id = ?");
    $stmt->execute([$driverId]);
    return $stmt->fetch() ?: null;
}

/* ---------------------------------------------------------------
   DRIVER ONBOARDING HELPERS
   A new driver signs in with the temporary password, fills in a
   verification form (personal details + documents), and an admin
   approves it before the driver can use the rest of the app.
--------------------------------------------------------------- */

/** Nationalities a driver can choose: South Africa and its neighbouring countries. */
function driver_nationalities(): array
{
    return ["South Africa", "Botswana", "Eswatini", "Lesotho", "Mozambique", "Namibia", "Zimbabwe"];
}

/** Returns an error message if the date of birth is invalid or the person is under 18, otherwise null. */
function dob_error(string $dob): ?string
{
    $date = DateTime::createFromFormat("Y-m-d", $dob);
    if (!$date || $date->format("Y-m-d") !== $dob) {
        return "Please enter a valid date of birth.";
    }
    $today = new DateTime("today");
    if ($date > $today) {
        return "Your date of birth cannot be in the future.";
    }
    $age = $date->diff($today)->y;
    if ($age < 18) {
        return "Drivers must be at least 18 years old.";
    }
    if ($age > 90) {
        return "Please check the date of birth you entered.";
    }
    return null;
}

/** Age in whole years for a YYYY-MM-DD date, or null if it is missing or unreadable. */
function age_from_dob(?string $dob): ?int
{
    if (!$dob) {
        return null;
    }
    try {
        return (new DateTime($dob))->diff(new DateTime("today"))->y;
    } catch (Throwable $e) {
        return null;
    }
}

/** The full address of this app's sign-in page, used inside emails (e.g. https://example.com/phoenix/index.php). */
function app_url(): string
{
    $https = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', "", $_SERVER["HTTP_HOST"] ?? "localhost");
    $dir = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/");
    return ($https ? "https" : "http") . "://" . $host . $dir . "/index.php";
}

/**
 * Adds any database columns the driver onboarding feature needs, so an existing
 * database upgrades itself the first time the app runs. Checked once per session.
 * (The same changes are in sql/upgrade_driver_onboarding.sql if you prefer to run them by hand.)
 */
function ensure_schema(): void
{
    if (!empty($_SESSION["schema_ok"])) {
        return;
    }
    try {
        $pdo = db();
        $hasColumn = function (string $table, string $column) use ($pdo): bool {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if (!$hasColumn("users", "address")) {
            $pdo->exec("ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL AFTER email");
        }
        if (!$hasColumn("users", "date_of_birth")) {
            $pdo->exec("ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER address");
        }
        if (!$hasColumn("users", "nationality")) {
            $pdo->exec("ALTER TABLE users ADD COLUMN nationality VARCHAR(60) NULL AFTER date_of_birth");
        }
        if (!$hasColumn("driver_documents", "passport_document")) {
            $pdo->exec("ALTER TABLE driver_documents ADD COLUMN passport_document VARCHAR(255) NULL AFTER car_reg_document");
            $pdo->exec("ALTER TABLE driver_documents MODIFY id_document VARCHAR(255) NULL");
        }
        if (!$hasColumn("driver_documents", "reject_reason")) {
            $pdo->exec("ALTER TABLE driver_documents ADD COLUMN reject_reason VARCHAR(255) NULL AFTER status");
        }
        $_SESSION["schema_ok"] = true;
    } catch (Throwable $e) {
        error_log("Phoenix schema check failed: " . $e->getMessage());
    }
}

/* ---------------------------------------------------------------
   OTP HELPERS (forgot-password flow, clients and drivers)
   No SMS/email gateway is configured for this project, so the OTP is
   displayed directly on screen (clearly labelled as a demo) instead
   of actually being sent anywhere — but the verification mechanics
   are real: it's hashed, expires after 60 seconds, and can be used
   only once, the same way a production version would enforce it.
--------------------------------------------------------------- */

const OTP_TTL_SECONDS = 60;

/** Issues a fresh OTP for this user, invalidating any still-valid one. Returns the plaintext code. */
function issue_otp(int $userId): string
{
    db()->prepare("DELETE FROM password_reset_otps WHERE user_id = ? AND consumed = 0")->execute([$userId]);
    $code = str_pad((string)random_int(0, 999999), 6, "0", STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        "INSERT INTO password_reset_otps (user_id, otp_hash, expires_at, consumed, created_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), 0, NOW())"
    );
    $stmt->execute([$userId, $hash, OTP_TTL_SECONDS]);
    return $code;
}

/** True if this user requested a code in the last 10 seconds — a basic anti-spam cooldown. */
function otp_recently_requested(int $userId): bool
{
    // The comparison happens inside MySQL (not PHP) so the two clocks/timezones can never disagree.
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM password_reset_otps
         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)"
    );
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() > 0;
}

/** Verifies a submitted code for a user and consumes it on success. */
function verify_otp(int $userId, string $code): bool
{
    $stmt = db()->prepare(
        "SELECT * FROM password_reset_otps WHERE user_id = ? AND consumed = 0 AND expires_at >= NOW()
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($code, $row["otp_hash"])) {
        return false;
    }
    db()->prepare("UPDATE password_reset_otps SET consumed = 1 WHERE id = ?")->execute([$row["id"]]);
    return true;
}

/* ---------------------------------------------------------------
   EMAIL (Gmail via PHPMailer)
   Used to email the 6-digit password-reset code. Your Gmail details
   live in mail_settings.php. While MAIL_ENABLED is false, the app
   falls back to demo mode and shows the code on screen instead.
--------------------------------------------------------------- */

// Your private Gmail settings live in mail_settings.php. If that file is missing the
// example file is used instead (emails stay off and the app runs in demo mode).
require_once __DIR__ . (is_file(__DIR__ . "/mail_settings.php") ? "/mail_settings.php" : "/mail_settings.example.php");

function valid_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** jane.doe@gmail.com -> j*******@gmail.com (so the full address isn't shown on screen) */
function mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode("@", $email, 2), 2, "");
    return substr($local, 0, 1) . str_repeat("*", max(strlen($local) - 1, 3)) . "@" . $domain;
}

/** Sends one plain-text email through Gmail's SMTP server. Returns true on success. */
function send_email(string $to, string $toName, string $subject, string $body): bool
{
    if (!MAIL_ENABLED) {
        return false;
    }
    require_once __DIR__ . "/lib/PHPMailer/Exception.php";
    require_once __DIR__ . "/lib/PHPMailer/PHPMailer.php";
    require_once __DIR__ . "/lib/PHPMailer/SMTP.php";

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->Port = MAIL_PORT;
        if (MAIL_SECURE === "ssl") {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif (MAIL_SECURE === "tls") {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = "";
            $mail->SMTPAutoTLS = false;
        }
        $mail->SMTPAuth = MAIL_USERNAME !== "" && MAIL_PASSWORD !== "";
        $mail->Username = MAIL_USERNAME;
        $mail->Password = str_replace(" ", "", MAIL_PASSWORD); // Google shows app passwords in groups of 4
        $mail->Timeout = 15;
        $mail->CharSet = "UTF-8";
        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        // Logged for you (see Apache's error.log) — never shown to the user.
        error_log("Phoenix mail error: " . $e->getMessage());
        return false;
    }
}

/** Emails a password-reset code. The code itself is never stored in plain text. */
function send_otp_email(string $to, string $name, string $code): bool
{
    $body = "Hi $name,\n\n"
          . "Your Phoenix password-reset code is: $code\n\n"
          . "It expires in " . OTP_TTL_SECONDS . " seconds and can only be used once.\n"
          . "If you didn't ask to reset your password, you can ignore this email — your password hasn't changed.\n\n"
          . "— Phoenix";
    return send_email($to, $name, "Your Phoenix reset code: $code", $body);
}

/** Emails a new driver their login details and tells them what to do next. */
function send_driver_welcome_email(string $to, string $name, string $username, string $tempPassword): bool
{
    $body = "Hi $name,\n\n"
          . "An admin has created a driver account for you on Phoenix.\n\n"
          . "Sign in here: " . app_url() . "\n"
          . "Username (your full name): $username\n"
          . "Temporary password: $tempPassword\n\n"
          . "What happens next:\n"
          . "1. Sign in with the details above.\n"
          . "2. Fill in the verification form with your personal details and your documents (ID or passport, driving license and car registration).\n"
          . "3. An admin will review your form. We will email you as soon as you are verified.\n"
          . "4. Once you are verified, sign in again with the same temporary password. You will be asked to choose your own password, and then you can use your driver account.\n\n"
          . "Please keep this email private and do not share your temporary password.\n\n"
          . "- Phoenix";
    return send_email($to, $name, "Your Phoenix driver account", $body);
}

/** Emails a driver to say an admin has verified them. */
function send_driver_verified_email(string $to, string $name, string $username): bool
{
    $body = "Hi $name,\n\n"
          . "Good news: an admin has verified your details and documents.\n\n"
          . "Sign in here: " . app_url() . "\n"
          . "Username (your full name): $username\n"
          . "Password: the same temporary password from your first email\n\n"
          . "When you sign in you will be asked to choose your own password. After that you can use your driver account and be assigned deliveries.\n\n"
          . "- Phoenix";
    return send_email($to, $name, "You are verified on Phoenix", $body);
}

/** Emails a driver to say their form was not approved, with the admin's reason. */
function send_driver_rejected_email(string $to, string $name, string $reason): bool
{
    $body = "Hi $name,\n\n"
          . "An admin could not approve your verification form yet.\n\n"
          . "Reason: $reason\n\n"
          . "Please sign in with your username and temporary password, correct the problem and submit the form again.\n"
          . "Sign in here: " . app_url() . "\n\n"
          . "- Phoenix";
    return send_email($to, $name, "Your Phoenix verification needs changes", $body);
}

function get_config(): array
{
    $row = db()->query("SELECT * FROM platform_config WHERE id = 1")->fetch();
    return $row ?: [
        "base_fare" => 25, "rate_per_km" => 12, "commission_pct" => 10,
        "driver_base_salary" => 7000, "maintenance_mode" => 0, "announcement" => "",
    ];
}

function log_audit(string $actor, string $action, string $detail = ""): void
{
    $stmt = db()->prepare(
        "INSERT INTO audit_log (actor, action, detail, created_at) VALUES (?, ?, ?, NOW())"
    );
    $stmt->execute([$actor, $action, $detail]);
}

/* ---------------------------------------------------------------
   PAYROLL HELPERS
   Drivers earn a percentage of every delivery they complete (their
   "commission" — set in platform config, default 10%) on top of a
   fixed monthly base salary. Admins run payroll once a period is
   over; each run is a row in salary_payments so a driver can never
   be paid twice for the same period.
--------------------------------------------------------------- */

/** Returns [start, end] (Y-m-d) for the calendar month containing $date. */
function month_bounds(?string $date = null): array
{
    $ts = $date ? strtotime($date) : time();
    return [date("Y-m-01", $ts), date("Y-m-t", $ts)];
}

/** Deliveries completed + total commission (driver's cut) for a driver within a date range (inclusive). */
function driver_period_stats(int $driverId, string $start, string $end): array
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS jobs, COALESCE(SUM(payout),0) AS commission, COALESCE(SUM(distance_km),0) AS km
         FROM requests
         WHERE driver_id = ? AND status = 'delivered' AND DATE(delivered_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$driverId, $start, $end]);
    $row = $stmt->fetch();
    return [
        "jobs" => (int)$row["jobs"],
        "commission" => (float)$row["commission"],
        "km" => (float)$row["km"],
    ];
}

/** True if this driver already has a salary_payments row overlapping this exact period. */
function driver_already_paid_for(int $driverId, string $start, string $end): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM salary_payments WHERE driver_id = ? AND period_start = ? AND period_end = ?"
    );
    $stmt->execute([$driverId, $start, $end]);
    return (int)$stmt->fetchColumn() > 0;
}

/* ---------------------------------------------------------------
   FIRST-RUN BOOTSTRAP
   Creates the default super-admin and the platform_config row the
   first time this file runs against an empty database.
--------------------------------------------------------------- */

ensure_schema();

$adminCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($adminCount === 0) {
    $defaultHash = password_hash("Admin@123", PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        "INSERT INTO users (role, admin_tier, name, surname, phone, password_hash, suspended, verified, created_at)
         VALUES ('admin', 'super', 'Admin', 'User', '0000000000', ?, 0, 1, NOW())"
    );
    $stmt->execute([$defaultHash]);
}

$configCount = (int)db()->query("SELECT COUNT(*) FROM platform_config WHERE id = 1")->fetchColumn();
if ($configCount === 0) {
    db()->exec(
        "INSERT INTO platform_config (id, base_fare, rate_per_km, commission_pct, driver_base_salary, maintenance_mode, announcement)
         VALUES (1, 25, 12, 10, 7000, 0, '')"
    );
}

/* Theme preference is stored in a plain cookie — it's a per-browser
   display setting, not something that needs a database round trip. */
$currentTheme = ($_COOKIE["ta_theme"] ?? "light") === "dark" ? "dark" : "light";
