<?php
/**
 * actions.php
 * -----------------------------------------------------------
 * Every form in the app POSTs here. This file never renders a
 * page — it validates input, talks to the database, sets a
 * flash message, and redirects back to index.php. Nothing in
 * here trusts the browser: role checks happen server-side even
 * though the UI already hides buttons the user shouldn't see.
 * -----------------------------------------------------------
 */

require_once __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

// If the uploaded files were bigger than the server allows, PHP throws the whole form away.
// Catch that here so the driver sees a clear message instead of "session expired".
if (empty($_POST) && empty($_FILES) && (int)($_SERVER["CONTENT_LENGTH"] ?? 0) > 0) {
    flash_set("error", "Your files were too large to upload. Each PDF must be under 10MB and the photo under 5MB.");
    header("Location: index.php?tab=documents");
    exit;
}

check_csrf();

$action = $_POST["action"] ?? "";

switch ($action) {

    /* =========================================================
       AUTH
    ========================================================= */

    case "register": {
        $name = trim($_POST["name"] ?? "");
        $surname = trim($_POST["surname"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $email = strtolower(trim($_POST["email"] ?? ""));
        $password = $_POST["password"] ?? "";

        if ($name === "" || $surname === "" || $phone === "" || $email === "" || $password === "") {
            flash_set("error", "Please fill in every field.");
            header("Location: index.php?authmode=register");
            exit;
        }
        if (!valid_email($email)) {
            flash_set("error", "Please enter a valid email address — we send your password-reset codes there.");
            header("Location: index.php?authmode=register");
            exit;
        }
        if (!valid_sa_phone($phone)) {
            flash_set("error", "Please enter a valid South African phone number — 10 digits, starting with 0 (e.g. 0821234567).");
            header("Location: index.php?authmode=register");
            exit;
        }
        $pwErrors = password_validation_errors($password);
        if ($pwErrors) {
            flash_set("error", "Password needs " . implode(", ", $pwErrors) . ".");
            header("Location: index.php?authmode=register");
            exit;
        }

        $existing = db()->prepare("SELECT id FROM users WHERE phone = ?");
        $existing->execute([$phone]);
        if ($existing->fetch()) {
            flash_set("error", "An account with that phone number already exists.");
            header("Location: index.php?authmode=register");
            exit;
        }
        $existing = db()->prepare("SELECT id FROM users WHERE email = ?");
        $existing->execute([$email]);
        if ($existing->fetch()) {
            flash_set("error", "An account with that email address already exists.");
            header("Location: index.php?authmode=register");
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            "INSERT INTO users (role, name, surname, phone, email, password_hash, suspended, verified, created_at)
             VALUES ('client', ?, ?, ?, ?, ?, 0, 1, NOW())"
        );
        $stmt->execute([$name, $surname, $phone, $email, $hash]);

        $_SESSION["user_id"] = (int)db()->lastInsertId();
        header("Location: index.php");
        exit;
    }

    case "login": {
        $fullName = trim($_POST["name"] ?? "");
        $password = $_POST["password"] ?? "";

        $stmt = db()->prepare(
            "SELECT * FROM users WHERE LOWER(CONCAT(name, ' ', surname)) = LOWER(?)"
        );
        $stmt->execute([$fullName]);
        $candidates = $stmt->fetchAll();

        $matched = null;
        foreach ($candidates as $candidate) {
            if (password_verify($password, $candidate["password_hash"])) {
                $matched = $candidate;
                break;
            }
        }

        if (!$matched) {
            flash_set("error", "Name or password doesn't match any account.");
            header("Location: index.php?authmode=signin");
            exit;
        }
        if ((int)$matched["suspended"] === 1) {
            flash_set("error", "This account has been suspended. Contact platform support.");
            header("Location: index.php?authmode=signin");
            exit;
        }

        $_SESSION["user_id"] = (int)$matched["id"];
        header("Location: index.php");
        exit;
    }

    case "logout": {
        $_SESSION = [];
        session_destroy();
        header("Location: index.php");
        exit;
    }

    case "request_otp": {
        $email = strtolower(trim($_POST["email"] ?? ""));
        if (!valid_email($email)) {
            flash_set("error", "Please enter the email address on your account.");
            header("Location: index.php?authmode=reset");
            exit;
        }

        $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        // Same message whether or not the email is registered, so nobody can use this
        // form to find out which email addresses have Phoenix accounts.
        $generic = "If that email is registered, a 6-digit code is on its way. It expires in 60 seconds — check your spam folder too.";

        if ($account && (int)$account["suspended"] === 0 && !otp_recently_requested((int)$account["id"])) {
            $code = issue_otp((int)$account["id"]);
            log_audit($account["name"] . " " . $account["surname"], "Requested a password-reset code");

            if (MAIL_ENABLED) {
                if (!send_otp_email($email, $account["name"], $code)) {
                    flash_set("error", "We couldn't send the email right now. Please try again in a moment.");
                    header("Location: index.php?authmode=reset");
                    exit;
                }
                flash_set("success", $generic);
            } else {
                // Email sending isn't set up yet (see mail_settings.php), so show the code here instead.
                flash_set("success", "Demo mode (email sending is switched off) — your code is $code. It expires in 60 seconds.");
            }
        } else {
            flash_set("success", $generic);
        }

        header("Location: index.php?authmode=otp_verify&email=" . urlencode($email));
        exit;
    }

    case "verify_otp_reset": {
        $email = strtolower(trim($_POST["email"] ?? ""));
        $code = trim($_POST["otp"] ?? "");
        $newPassword = $_POST["new_password"] ?? "";

        if ($email === "" || $code === "" || $newPassword === "") {
            flash_set("error", "Please fill in every field.");
            header("Location: index.php?authmode=otp_verify&email=" . urlencode($email));
            exit;
        }
        $pwErrors = password_validation_errors($newPassword);
        if ($pwErrors) {
            flash_set("error", "Password needs " . implode(", ", $pwErrors) . ".");
            header("Location: index.php?authmode=otp_verify&email=" . urlencode($email));
            exit;
        }

        $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if (!$account || !verify_otp((int)$account["id"], $code)) {
            flash_set("error", "That code is invalid or has expired — request a new one.");
            header("Location: index.php?authmode=reset");
            exit;
        }

        $stmt = db()->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $account["id"]]);
        log_audit($account["name"] . " " . $account["surname"], "Reset password via OTP");

        flash_set("success", "Password reset. You can sign in with your new password now.");
        header("Location: index.php?authmode=signin");
        exit;
    }

    /* =========================================================
       CLIENT — post a delivery request
    ========================================================= */

    case "post_request": {
        $user = require_role(["client"]);
        $config = get_config();
        if ((int)$config["maintenance_mode"] === 1) {
            flash_set("error", "The platform is temporarily under maintenance. Please try again later.");
            header("Location: index.php?tab=post");
            exit;
        }
        if (!client_has_card((int)$user["id"])) {
            // No card yet: keep what the client typed, then send them to add a card.
            $_SESSION["draft_request"] = [
                "description"  => trim($_POST["description"] ?? ""),
                "pickup_lat"   => $_POST["pickup_lat"] ?? "",
                "pickup_lng"   => $_POST["pickup_lng"] ?? "",
                "pickup_addr"  => trim($_POST["pickup_addr"] ?? ""),
                "dropoff_lat"  => $_POST["dropoff_lat"] ?? "",
                "dropoff_lng"  => $_POST["dropoff_lng"] ?? "",
                "dropoff_addr" => trim($_POST["dropoff_addr"] ?? ""),
            ];
            flash_set("error", "Add a card to pay for this delivery. Your request details are saved - once the card is added you can continue.");
            header("Location: index.php?tab=card");
            exit;
        }

        $description = trim($_POST["description"] ?? "");
        $pickupLat = $_POST["pickup_lat"] ?? "";
        $pickupLng = $_POST["pickup_lng"] ?? "";
        $dropoffLat = $_POST["dropoff_lat"] ?? "";
        $dropoffLng = $_POST["dropoff_lng"] ?? "";

        if ($description === "" || $pickupLat === "" || $pickupLng === "" || $dropoffLat === "" || $dropoffLng === "") {
            flash_set("error", "Please describe the package and drop a pin for both pickup and drop-off.");
            header("Location: index.php?tab=post");
            exit;
        }

        // Track how far the delivery is and price it right away — this is what the client is charged.
        $km = haversine_km((float)$pickupLat, (float)$pickupLng, (float)$dropoffLat, (float)$dropoffLng);
        $fare = round((float)$config["base_fare"] + $km * (float)$config["rate_per_km"], 2);
        $driverPayout = round($fare * ((float)$config["commission_pct"] / 100), 2); // driver's 10% cut
        $companyShare = round($fare - $driverPayout, 2);                            // rest goes to the company account

        $stmt = db()->prepare(
            "INSERT INTO requests
                (client_id, description, pickup_lat, pickup_lng, pickup_addr, dropoff_lat, dropoff_lng, dropoff_addr,
                 status, distance_km, fare, commission, payout, paid, payment_method, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, 1, 'simulated_card', NOW())"
        );
        $stmt->execute([
            $user["id"], $description,
            $pickupLat, $pickupLng, trim($_POST["pickup_addr"] ?? ""),
            $dropoffLat, $dropoffLng, trim($_POST["dropoff_addr"] ?? ""),
            round($km, 2), $fare, $companyShare, $driverPayout,
        ]);

        unset($_SESSION["draft_request"]);   // the saved draft is no longer needed
        flash_set("success", "Payment received — " . fmt_r($fare) . " for " . round($km, 2) . " km. An admin will assign a driver shortly.");
        header("Location: index.php?tab=history");
        exit;
    }

    /* =========================================================
       DRIVER — deliver a request assigned by an admin
    ========================================================= */

    case "deliver_request": {
        $user = require_role(["driver"]);
        $requestId = (int)($_POST["request_id"] ?? 0);

        $stmt = db()->prepare("SELECT delivery_photo FROM requests WHERE id = ? AND driver_id = ? AND status = 'accepted'");
        $stmt->execute([$requestId, $user["id"]]);
        $request = $stmt->fetch();

        if (!$request) {
            flash_set("error", "That delivery isn't assigned to you or is no longer active.");
            header("Location: index.php?tab=active");
            exit;
        }
        if (!$request["delivery_photo"]) {
            flash_set("error", "Capture a delivery photo before marking this complete.");
            header("Location: index.php?tab=active");
            exit;
        }

        $stmt = db()->prepare(
            "UPDATE requests SET status = 'delivered', delivered_at = NOW()
             WHERE id = ? AND driver_id = ? AND status = 'accepted'"
        );
        $stmt->execute([$requestId, $user["id"]]);

        flash_set("success", "Delivery marked as complete.");
        header("Location: index.php?tab=history");
        exit;
    }

    /* =========================================================
       CHAT
    ========================================================= */

    case "send_message": {
        $user = require_login();
        $requestId = (int)($_POST["request_id"] ?? 0);
        $text = trim($_POST["message"] ?? "");

        if ($text !== "") {
            $stmt = db()->prepare(
                "INSERT INTO messages (request_id, sender_id, message, created_at) VALUES (?, ?, ?, NOW())"
            );
            $stmt->execute([$requestId, $user["id"], $text]);
        }

        header("Location: index.php?view=chat&request=" . $requestId);
        exit;
    }

    case "report_request": {
        // The CLIENT flags the DRIVER, or the DRIVER flags the CLIENT, on one delivery.
        // A reason is required, and only the two people on that delivery may flag it.
        $user = require_login();
        if ($user["role"] === "admin") {
            header("Location: index.php");
            exit;
        }
        $requestId = (int)($_POST["request_id"] ?? 0);
        $reason = substr(trim($_POST["reason"] ?? ""), 0, 255);
        $back = $_POST["back"] ?? "chat";           // where to return: chat, history (client) or active (driver)

        $stmt = db()->prepare("SELECT * FROM requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        $isClient = $request && $user["role"] === "client" && (int)$request["client_id"] === (int)$user["id"];
        $isDriver = $request && $user["role"] === "driver" && (int)$request["driver_id"] === (int)$user["id"];

        // where to send the person back to
        $backUrl = "index.php?view=chat&request=" . $requestId;
        if ($back === "history" && $isClient) {
            $backUrl = "index.php?tab=history";
        } elseif ($back === "active" && $isDriver) {
            $backUrl = "index.php?tab=active";
        }

        if (!$isClient && !$isDriver) {
            flash_set("error", "You can only flag a delivery that you are part of.");
            header("Location: index.php");
            exit;
        }
        if ($isClient && !$request["driver_id"]) {
            flash_set("error", "No driver has been assigned to this delivery yet, so there is nobody to flag.");
            header("Location: " . $backUrl);
            exit;
        }
        if ($reason === "") {
            flash_set("error", "Please give a reason for the flag.");
            header("Location: " . $backUrl);
            exit;
        }

        $target = $isClient ? "driver" : "client";   // who is being flagged
        $stmt = db()->prepare(
            "UPDATE requests SET flagged = 1, report_reason = ?, reported_by = ?, flag_target = ? WHERE id = ?"
        );
        $stmt->execute([$reason, $user["name"] . " " . $user["surname"], $target, $requestId]);
        log_audit($user["name"] . " " . $user["surname"], "Flagged the " . $target, "Request #" . $requestId . ": " . $reason);

        flash_set("success", "Thanks - the " . $target . " has been flagged and an admin will review it.");
        header("Location: " . $backUrl);
        exit;
    }

    /* =========================================================
       PROFILE
    ========================================================= */

    case "add_card": {
        $user = require_role(["client"]);
        $holder = trim($_POST["card_holder"] ?? "");
        $numberRaw = $_POST["card_number"] ?? "";
        $digitsOnly = preg_replace('/\D/', '', $numberRaw);
        $expMonth = (int)($_POST["expiry_month"] ?? 0);
        $expYear = (int)($_POST["expiry_year"] ?? 0);
        $cvv = $_POST["cvv"] ?? "";

        $errors = [];
        if ($holder === "") $errors[] = "cardholder name";
        if (strlen($digitsOnly) < 13 || strlen($digitsOnly) > 19) $errors[] = "a valid card number";
        if ($expMonth < 1 || $expMonth > 12) $errors[] = "a valid expiry month";
        if ($expYear < (int)date("Y") || $expYear > (int)date("Y") + 15) $errors[] = "a valid expiry year";
        if (!preg_match('/^\d{3,4}$/', $cvv)) $errors[] = "a valid CVV";

        if ($errors) {
            flash_set("error", "Please check: " . implode(", ", $errors) . ".");
            header("Location: index.php?tab=card");
            exit;
        }
        if ($expYear === (int)date("Y") && $expMonth < (int)date("n")) {
            flash_set("error", "That card has already expired.");
            header("Location: index.php?tab=card");
            exit;
        }

        // Simulated payment method — we only ever keep the last 4 digits and expiry.
        // The full number and CVV are used to validate the form and then discarded,
        // never written to the database.
        $last4 = substr($digitsOnly, -4);
        $brand = guess_card_brand($digitsOnly);

        db()->prepare("DELETE FROM client_cards WHERE client_id = ?")->execute([$user["id"]]);
        $stmt = db()->prepare(
            "INSERT INTO client_cards (client_id, card_holder, brand, last4, expiry_month, expiry_year, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$user["id"], $holder, $brand, $last4, $expMonth, $expYear]);

        if (!empty($_SESSION["draft_request"])) {
            // They were in the middle of a request: take them back to it.
            flash_set("success", "$brand card ending in $last4 saved. Your request is ready - review it and press Pay & post.");
            header("Location: index.php?tab=post");
            exit;
        }
        flash_set("success", "$brand card ending in $last4 saved. You can now post delivery requests.");
        header("Location: index.php?tab=card");
        exit;
    }

    case "remove_card": {
        $user = require_role(["client"]);
        db()->prepare("DELETE FROM client_cards WHERE client_id = ?")->execute([$user["id"]]);
        flash_set("success", "Card removed. Add a new one before your next delivery request.");
        header("Location: index.php?tab=card");
        exit;
    }

    case "upload_profile_pic": {
        $user = require_login();
        if (empty($_FILES["profile_pic"]) || $_FILES["profile_pic"]["error"] !== UPLOAD_ERR_OK) {
            flash_set("error", "Please choose an image to upload.");
            header("Location: index.php?tab=profile");
            exit;
        }
        $file = $_FILES["profile_pic"];
        $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
        $mime = mime_content_type($file["tmp_name"]);
        if (!isset($allowed[$mime]) || $file["size"] > 1_500_000) {
            flash_set("error", "Please upload a JPG, PNG, or WEBP image under 1.5MB.");
            header("Location: index.php?tab=profile");
            exit;
        }

        $filename = "user_" . $user["id"] . "_" . time() . "." . $allowed[$mime];
        $destination = __DIR__ . "/uploads/profile_pics/" . $filename;
        move_uploaded_file($file["tmp_name"], $destination);

        $stmt = db()->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
        $stmt->execute(["uploads/profile_pics/" . $filename, $user["id"]]);

        header("Location: index.php?tab=profile");
        exit;
    }

    /* =========================================================
       DRIVER VERIFICATION DOCUMENTS
    ========================================================= */

    case "upload_driver_documents": {
        $user = require_role(["driver"]);
        // field name => [name shown to the driver, type of file allowed]
        // (car_reg_document is the old column name; it now stores the ID photo)
        $fields = [
            "id_document"               => ["ID copy", "pdf"],
            "license_document"          => ["driving license", "pdf"],
            "car_reg_document"          => ["ID photo", "image"],
            "police_clearance_document" => ["police clearance certificate", "pdf"],
            "bank_proof_document"       => ["proof of bank account", "pdf"],
        ];
        $saved = [];
        $errors = [];

        foreach ($fields as $field => [$label, $type]) {
            $file = $_FILES[$field] ?? null;
            $ext = null;
            if ($file) {
                $ext = $type === "pdf" ? validate_pdf_upload($file) : validate_image_upload($file, 5_000_000);
            }
            if (!$ext) {
                // tell the driver when the SERVER refused a big file, and show the server's real limit
                $tooBig = $file && in_array($file["error"] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
                $errors[] = $tooBig ? $label . " (too large for the server, its limit is " . ini_get("upload_max_filesize") . ")" : $label;
                continue;
            }
            $filename = "driver_" . $user["id"] . "_" . $field . "_" . time() . "." . $ext;
            move_uploaded_file($file["tmp_name"], __DIR__ . "/uploads/driver_docs/" . $filename);
            $saved[$field] = "uploads/driver_docs/" . $filename;
        }

        if ($errors) {
            flash_set("error", "Please attach a valid file for: " . implode(", ", $errors) . ". (PDFs under 10MB, the ID photo as an image under 5MB.)");
            header("Location: index.php?tab=documents");
            exit;
        }

        // A fresh submission replaces any previous one and always starts pending review again.
        db()->prepare("DELETE FROM driver_documents WHERE driver_id = ?")->execute([$user["id"]]);
        $stmt = db()->prepare(
            "INSERT INTO driver_documents (driver_id, id_document, license_document, car_reg_document, police_clearance_document, bank_proof_document, status, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([$user["id"], $saved["id_document"], $saved["license_document"], $saved["car_reg_document"], $saved["police_clearance_document"], $saved["bank_proof_document"]]);
        db()->prepare("UPDATE users SET verified = 0 WHERE id = ?")->execute([$user["id"]]);

        log_audit($user["name"] . " " . $user["surname"], "Submitted verification documents");
        flash_set("success", "Documents submitted — an admin will review them before you can be assigned deliveries.");
        header("Location: index.php?tab=documents");
        exit;
    }

    case "review_driver_documents": {
        $admin = require_role(["admin"]);
        $driverId = (int)($_POST["driver_id"] ?? 0);
        $decision = $_POST["decision"] ?? "";

        if (!in_array($decision, ["verified", "rejected"], true)) {
            header("Location: index.php?tab=verification");
            exit;
        }

        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'driver'");
        $stmt->execute([$driverId]);
        $driver = $stmt->fetch();

        if ($driver) {
            db()->prepare("UPDATE driver_documents SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE driver_id = ?")
                ->execute([$decision, $admin["id"], $driverId]);
            db()->prepare("UPDATE users SET verified = ? WHERE id = ?")
                ->execute([$decision === "verified" ? 1 : 0, $driverId]);

            log_audit($admin["name"] . " " . $admin["surname"],
                $decision === "verified" ? "Verified driver documents" : "Rejected driver documents",
                $driver["name"] . " " . $driver["surname"]);
            flash_set("success", $driver["name"] . " " . $driver["surname"] . "'s documents were marked as $decision.");
        }

        header("Location: index.php?tab=verification");
        exit;
    }

    /* =========================================================
       PROOF-OF-DELIVERY PHOTOS
    ========================================================= */

    case "upload_pickup_photo":
    case "upload_delivery_photo": {
        $user = require_role(["driver"]);
        $requestId = (int)($_POST["request_id"] ?? 0);
        $column = $action === "upload_pickup_photo" ? "pickup_photo" : "delivery_photo";
        $label = $action === "upload_pickup_photo" ? "pickup" : "delivery";

        $file = $_FILES["photo"] ?? null;
        $ext = $file ? validate_image_upload($file) : null;
        if (!$ext) {
            flash_set("error", "Please attach a valid photo (JPG, PNG, or WEBP, under 3MB).");
            header("Location: index.php?tab=active");
            exit;
        }

        $stmt = db()->prepare("SELECT * FROM requests WHERE id = ? AND driver_id = ? AND status = 'accepted'");
        $stmt->execute([$requestId, $user["id"]]);
        $request = $stmt->fetch();
        if (!$request) {
            flash_set("error", "That delivery isn't assigned to you or is no longer active.");
            header("Location: index.php?tab=active");
            exit;
        }

        $filename = "request_" . $requestId . "_" . $label . "_" . time() . "." . $ext;
        move_uploaded_file($file["tmp_name"], __DIR__ . "/uploads/delivery_photos/" . $filename);

        db()->prepare("UPDATE requests SET $column = ? WHERE id = ?")
            ->execute(["uploads/delivery_photos/" . $filename, $requestId]);

        flash_set("success", ucfirst($label) . " photo saved.");
        header("Location: index.php?tab=active");
        exit;
    }

    case "update_email": {
        $user = require_login();
        $email = strtolower(trim($_POST["email"] ?? ""));
        $back = $user["role"] === "admin" ? "index.php?tab=config" : "index.php?tab=profile";

        if (!valid_email($email)) {
            flash_set("error", "Please enter a valid email address.");
            header("Location: $back");
            exit;
        }
        $stmt = db()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user["id"]]);
        if ($stmt->fetch()) {
            flash_set("error", "That email address is already used by another account.");
            header("Location: $back");
            exit;
        }
        db()->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $user["id"]]);
        log_audit($user["name"] . " " . $user["surname"], "Updated own email address");

        flash_set("success", "Email saved. Password-reset codes will be sent to " . mask_email($email) . ".");
        header("Location: $back");
        exit;
    }

    case "change_password": {
        $user = require_login();
        $wasForced = (int)$user["must_change_password"] === 1;
        $newPassword = $_POST["new_password"] ?? "";
        $backTab = $user["role"] === "admin" ? "index.php?tab=config" : "index.php";

        $pwErrors = password_validation_errors($newPassword);
        if ($pwErrors) {
            flash_set("error", "Password needs " . implode(", ", $pwErrors) . ".");
            header("Location: " . ($wasForced ? "index.php" : $backTab));
            exit;
        }
        $stmt = db()->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user["id"]]);
        log_audit($user["name"] . " " . $user["surname"], $wasForced ? "Set password after admin-issued temporary password" : "Changed own password");

        flash_set("success", "Password updated.");
        header("Location: " . $backTab);
        exit;
    }

    /* =========================================================
       ADMIN
    ========================================================= */

    case "assign_driver":
    case "reassign_request": {
        $admin = require_role(["admin"]);
        $requestId = (int)($_POST["request_id"] ?? 0);
        $driverId = (int)($_POST["driver_id"] ?? 0);

        $stmt = db()->prepare("SELECT name, surname FROM users WHERE id = ? AND role = 'driver' AND suspended = 0 AND verified = 1");
        $stmt->execute([$driverId]);
        $driver = $stmt->fetch();

        if ($driver) {
            $update = db()->prepare(
                "UPDATE requests SET status = 'accepted', driver_id = ?, accepted_at = NOW() WHERE id = ? AND status != 'delivered' AND status != 'cancelled'"
            );
            $update->execute([$driverId, $requestId]);
            log_audit($admin["name"] . " " . $admin["surname"], "Assigned delivery",
                "Request #" . $requestId . " → " . $driver["name"] . " " . $driver["surname"]);
            flash_set("success", "Assigned to " . $driver["name"] . " " . $driver["surname"] . ".");
        } else {
            flash_set("error", "Couldn't find that driver — they may not have verified documents on file yet.");
        }

        $back = ($_POST["back"] ?? "deliveries") === "assign" ? "assign" : "deliveries";
        header("Location: index.php?tab=" . $back);
        exit;
    }

    case "cancel_request": {
        // A client can cancel their own request until the driver has collected the parcel.
        // An admin can cancel any open request.
        // A PAID request that is cancelled needs a refund (the admin does it, see refund_request below).
        $user = require_login();
        $requestId = (int)($_POST["request_id"] ?? 0);

        $stmt = db()->prepare("SELECT * FROM requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        $isAdmin = $user["role"] === "admin";
        $isOwner = $request && $user["role"] === "client" && (int)$request["client_id"] === (int)$user["id"];
        $backUrl = $isAdmin ? "index.php?tab=deliveries" : "index.php?tab=history";

        if (!$request || (!$isAdmin && !$isOwner)) {
            flash_set("error", "You cannot cancel this request.");
            header("Location: index.php");
            exit;
        }
        if (in_array($request["status"], ["delivered", "cancelled"], true)) {
            flash_set("error", "This request is already finished.");
            header("Location: " . $backUrl);
            exit;
        }
        if ($isOwner && $request["pickup_photo"]) {
            flash_set("error", "The driver has already collected your package, so it can no longer be cancelled. Please contact the admin.");
            header("Location: " . $backUrl);
            exit;
        }

        db()->prepare("UPDATE requests SET status = 'cancelled' WHERE id = ?")->execute([$requestId]);
        $refundDue = $request["paid"] && !$request["refunded"];
        log_audit($user["name"] . " " . $user["surname"], $isAdmin ? "Cancelled delivery" : "Client cancelled request",
            "Request #" . $requestId . ($refundDue ? " - refund due" : ""));

        if ($isAdmin) {
            flash_set("success", "Request cancelled." . ($refundDue ? " Remember to refund the client." : ""));
        } else {
            flash_set("success", "Your request was cancelled." . ($refundDue ? " The admin will refund your payment." : ""));
        }
        header("Location: " . $backUrl);
        exit;
    }

    case "refund_request": {
        // The admin marks a cancelled (or delivered) PAID request as refunded.
        $admin = require_role(["admin"]);
        $requestId = (int)($_POST["request_id"] ?? 0);

        $stmt = db()->prepare("UPDATE requests SET refunded = 1 WHERE id = ? AND paid = 1 AND refunded = 0 AND status IN ('cancelled','delivered')");
        $stmt->execute([$requestId]);
        if ($stmt->rowCount() > 0) {
            log_audit($admin["name"] . " " . $admin["surname"], "Refunded client", "Request #" . $requestId);
            flash_set("success", "Marked as refunded. Remember to send the money back to the client.");
        }
        header("Location: index.php?tab=" . (($_POST["back"] ?? "") === "deliveries" ? "deliveries" : "finance"));
        exit;
    }

    case "toggle_flag": {
        $admin = require_role(["admin"]);
        $requestId = (int)($_POST["request_id"] ?? 0);
        db()->prepare("UPDATE requests SET flagged = 0 WHERE id = ?")->execute([$requestId]);
        log_audit($admin["name"] . " " . $admin["surname"], "Cleared report flag", "Request #" . $requestId);
        header("Location: index.php?tab=moderation");
        exit;
    }

    case "toggle_suspend": {
        $admin = require_role(["admin"]);
        $targetId = (int)($_POST["user_id"] ?? 0);

        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if ($target) {
            $newValue = (int)$target["suspended"] === 1 ? 0 : 1;
            db()->prepare("UPDATE users SET suspended = ? WHERE id = ?")->execute([$newValue, $targetId]);
            log_audit(
                $admin["name"] . " " . $admin["surname"],
                $newValue === 1 ? "Suspended account" : "Reactivated account",
                $target["name"] . " " . $target["surname"]
            );
        }

        $back = $_POST["back"] ?? "users";
        header("Location: index.php?tab=" . $back);
        exit;
    }

    case "add_driver": {
        $admin = require_role(["admin"]);
        $config = get_config();

        $name = trim($_POST["name"] ?? "");
        $surname = trim($_POST["surname"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $email = strtolower(trim($_POST["email"] ?? ""));
        $idNumber = preg_replace('/\s+/', "", $_POST["id_number"] ?? "");          // ID number, spaces removed
        $accountNumber = preg_replace('/\s+/', "", $_POST["account_number"] ?? ""); // bank account number
        $address = trim($_POST["address"] ?? "");          // residential address
        $bankName = trim($_POST["bank_name"] ?? "");       // name of the bank
        $password = $_POST["password"] ?? "";
        $carModel = trim($_POST["car_model"] ?? "");
        $carReg = trim($_POST["car_reg"] ?? "");
        $baseSalary = $_POST["base_salary"] !== "" ? (float)$_POST["base_salary"] : (float)$config["driver_base_salary"];

        if ($name === "" || $surname === "" || $phone === "" || $email === "" || $password === "") {
            flash_set("error", "Please fill in the driver's name, phone, email, and a password.");
            header("Location: index.php?tab=adddriver");
            exit;
        }
        if (!valid_email($email)) {
            flash_set("error", "Please enter a valid email address for the driver.");
            header("Location: index.php?tab=adddriver");
            exit;
        }
        if (!valid_sa_phone($phone)) {
            flash_set("error", "Please enter a valid South African phone number — 10 digits, starting with 0 (e.g. 0821234567).");
            header("Location: index.php?tab=adddriver");
            exit;
        }
        if (!preg_match('/^[0-9]{13}$/', $idNumber)) {
            flash_set("error", "The ID number must be exactly 13 digits.");
            header("Location: index.php?tab=adddriver");
            exit;
        }
        if (!preg_match('/^[0-9]{6,16}$/', $accountNumber)) {
            flash_set("error", "The account number must be 6 to 16 digits (numbers only).");
            header("Location: index.php?tab=adddriver");
            exit;
        }
        if (strlen($address) < 8 || strlen($address) > 255) {
            flash_set("error", "Please enter the driver's full residential address (street, suburb and city).");
            header("Location: index.php?tab=adddriver");
            exit;
        }
        if ($bankName === "" || strlen($bankName) > 60) {
            flash_set("error", "Please enter the name of the driver's bank.");
            header("Location: index.php?tab=adddriver");
            exit;
        }
        $pwErrors = password_validation_errors($password);
        if ($pwErrors) {
            flash_set("error", "Password needs " . implode(", ", $pwErrors) . ".");
            header("Location: index.php?tab=adddriver");
            exit;
        }

        $existing = db()->prepare("SELECT id FROM users WHERE phone = ?");
        $existing->execute([$phone]);
        if ($existing->fetch()) {
            flash_set("error", "An account with that phone number already exists.");
            header("Location: index.php?tab=adddriver");
            exit;
        }
        $existing = db()->prepare("SELECT id FROM users WHERE email = ?");
        $existing->execute([$email]);
        if ($existing->fetch()) {
            flash_set("error", "An account with that email address already exists.");
            header("Location: index.php?tab=adddriver");
            exit;
        }
        $existing = db()->prepare("SELECT id FROM users WHERE id_number = ?");
        $existing->execute([$idNumber]);
        if ($existing->fetch()) {
            flash_set("error", "A user with that ID number already exists.");
            header("Location: index.php?tab=adddriver");
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            "INSERT INTO users (role, name, surname, phone, email, id_number, account_number, password_hash, car_model, car_reg, base_salary, must_change_password, suspended, verified, created_at)
             VALUES ('driver', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0, NOW())"
        );
        $stmt->execute([$name, $surname, $phone, $email, $idNumber, $accountNumber, $hash, $carModel ?: null, $carReg ?: null, $baseSalary]);

        log_audit($admin["name"] . " " . $admin["surname"], "Added driver", "$name $surname · " . fmt_r($baseSalary) . "/mo base salary");
        flash_set("success", "Driver account created for $name $surname. They'll be asked to set their own password on first sign-in, and must submit verification documents before they can be assigned deliveries.");
        header("Location: index.php?tab=users&role=driver");
        exit;
    }

    case "pay_driver_salary": {
        $admin = require_role(["admin"]);
        $config = get_config();
        $driverId = (int)($_POST["driver_id"] ?? 0);
        $periodStart = $_POST["period_start"] ?? "";
        $periodEnd = $_POST["period_end"] ?? "";

        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'driver'");
        $stmt->execute([$driverId]);
        $driver = $stmt->fetch();

        if (!$driver || !$periodStart || !$periodEnd) {
            flash_set("error", "Couldn't find that driver or pay period.");
            header("Location: index.php?tab=payroll");
            exit;
        }
        if (driver_already_paid_for($driverId, $periodStart, $periodEnd)) {
            flash_set("error", "This driver has already been paid for that period.");
            header("Location: index.php?tab=payroll&period=" . date("Y-m", strtotime($periodStart)));
            exit;
        }

        $stats = driver_period_stats($driverId, $periodStart, $periodEnd);
        $baseSalary = (float)($driver["base_salary"] ?? $config["driver_base_salary"]);
        $total = round($stats["commission"] + $baseSalary, 2);

        $insert = db()->prepare(
            "INSERT INTO salary_payments (driver_id, period_start, period_end, deliveries_count, commission_total, base_salary, total_amount, paid_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $insert->execute([$driverId, $periodStart, $periodEnd, $stats["jobs"], $stats["commission"], $baseSalary, $total, $admin["id"]]);

        log_audit($admin["name"] . " " . $admin["surname"], "Paid driver salary",
            $driver["name"] . " " . $driver["surname"] . " · " . fmt_r($total) . " for " . date("F Y", strtotime($periodStart)));
        flash_set("success", "Paid " . $driver["name"] . " " . $driver["surname"] . " " . fmt_r($total) . " for " . date("F Y", strtotime($periodStart)) . ".");
        header("Location: index.php?tab=payroll&period=" . date("Y-m", strtotime($periodStart)));
        exit;
    }

    case "delete_account": {
        $admin = require_role(["admin"]);
        if (!is_super_admin($admin)) {
            flash_set("error", "Only a super admin can delete accounts.");
            header("Location: index.php?tab=users");
            exit;
        }
        $targetId = (int)($_POST["user_id"] ?? 0);

        $stmt = db()->prepare("SELECT name, surname FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if ($target) {
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
            log_audit($admin["name"] . " " . $admin["surname"], "Deleted account",
                $target["name"] . " " . $target["surname"]);
        }

        header("Location: index.php?tab=users");
        exit;
    }

    case "save_config": {
        $admin = require_role(["admin"]);
        if (!is_super_admin($admin)) {
            flash_set("error", "Only a super admin can change platform configuration.");
            header("Location: index.php?tab=config");
            exit;
        }

        $stmt = db()->prepare(
            "UPDATE platform_config SET base_fare = ?, rate_per_km = ?, commission_pct = ?, driver_base_salary = ?, maintenance_mode = ?, announcement = ? WHERE id = 1"
        );
        $stmt->execute([
            (float)($_POST["base_fare"] ?? 25),
            (float)($_POST["rate_per_km"] ?? 12),
            (float)($_POST["commission_pct"] ?? 10),
            (float)($_POST["driver_base_salary"] ?? 7000),
            isset($_POST["maintenance_mode"]) ? 1 : 0,
            trim($_POST["announcement"] ?? ""),
        ]);
        log_audit($admin["name"] . " " . $admin["surname"], "Updated platform config");

        flash_set("success", "Platform configuration saved.");
        header("Location: index.php?tab=config");
        exit;
    }

    /* =========================================================
       THEME (personal display preference — cookie, not DB)
    ========================================================= */

    case "set_theme": {
        $value = ($_POST["theme"] ?? "light") === "dark" ? "dark" : "light";
        setcookie("ta_theme", $value, time() + 60 * 60 * 24 * 365, "/");
        $back = (string)($_POST["back"] ?? "");
        if (!preg_match('/^index\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $back)) {
            $back = "index.php?view=settings";
        }
        header("Location: " . $back);
        exit;
    }

    default: {
        header("Location: index.php");
        exit;
    }
}