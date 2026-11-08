<?php
/**
 * index.php
 * -----------------------------------------------------------
 * Responsibilities of this file:
 *   - Decide what to show based on session state (logged in?
 *     which role?) and the ?view= / ?tab= query string.
 *   - Query the database for whatever that page needs.
 *   - Render the page as plain server-side HTML.
 * Every form on every page below POSTs to actions.php, which
 * does the actual work and redirects back here.
 * -----------------------------------------------------------
 */

require_once __DIR__ . "/config.php";

$user = current_user();
$flash = flash_get();
$view = $_GET["view"] ?? "home";
$tab = $_GET["tab"] ?? null;

/* =========================================================
   SMALL SHARED COMPONENTS
========================================================= */

function icon(string $name, int $size = 16, string $color = "currentColor"): string
{
    $c = "width=\"$size\" height=\"$size\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"$color\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"";
    $paths = [
        "truck" => '<rect x="1" y="8" width="12" height="8"/><path d="M13 11h4l3 3v2h-7z"/><circle cx="5" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/>',
        "package" => '<path d="M21 7l-9-4-9 4 9 4 9-4z"/><path d="M3 7v9l9 4 9-4V7"/><path d="M12 11v9"/>',
        "pin" => '<path d="M12 22s7-7.2 7-12a7 7 0 10-14 0c0 4.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.3"/>',
        "message" => '<path d="M21 14a2 2 0 01-2 2H8l-5 4V6a2 2 0 012-2h14a2 2 0 012 2z"/>',
        "gear" => '<circle cx="12" cy="12" r="3.2"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/>',
        "info" => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><circle cx="12" cy="8" r="0.6" fill="' . $color . '"/>',
        "logout" => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        "camera" => '<rect x="2" y="6" width="20" height="14" rx="2.5"/><circle cx="12" cy="13" r="3.5"/><path d="M8 6l1.5-2h5L16 6"/>',
        "user" => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.2-4.4 5-6.5 8-6.5s6.8 2.1 8 6.5"/>',
        "phone" => '<rect x="7" y="2" width="10" height="20" rx="2"/><line x1="10" y1="18" x2="14" y2="18"/>',
        "car" => '<path d="M3 13l1.5-5A2 2 0 016.4 6.5h11.2A2 2 0 0119.5 8L21 13"/><rect x="2" y="13" width="20" height="6" rx="1.5"/><circle cx="7" cy="19" r="1.4"/><circle cx="17" cy="19" r="1.4"/>',
        "history" => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2"/><path d="M9 3h6"/>',
        "chart" => '<line x1="5" y1="20" x2="5" y2="12"/><line x1="12" y1="20" x2="12" y2="6"/><line x1="19" y1="20" x2="19" y2="15"/>',
        "plus" => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        "send" => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>',
        "moon" => '<path d="M20 14.5A8.5 8.5 0 119.5 4a7 7 0 0010.5 10.5z"/>',
        "sun" => '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2.5M12 19.5V22M4.2 4.2l1.8 1.8M18 18l1.8 1.8M2 12h2.5M19.5 12H22M4.2 19.8L6 18M18 6l1.8-1.8"/>',
        "check" => '<path d="M20 6L9 17l-5-5"/>',
        "nav" => '<path d="M3 11l18-8-8 18-2-8-8-2z"/>',
        "flag" => '<path d="M5 21V4"/><path d="M5 4h13l-3 4 3 4H5"/>',
        "dollar" => '<line x1="12" y1="2" x2="12" y2="22"/><path d="M17 6.5c0-1.9-2.2-3.5-5-3.5s-5 1.5-5 3.5 2.2 3 5 3.5 5 1.6 5 3.5-2.2 3.5-5 3.5-5-1.6-5-3.5"/>',
        "shield" => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/>',
        "ban" => '<circle cx="12" cy="12" r="9"/><line x1="6" y1="6" x2="18" y2="18"/>',
        "key" => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9"/><path d="M16 7l3 3"/><path d="M14 9l2.5 2.5"/>',
        "file" => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>',
    ];
    return "<svg $c>" . ($paths[$name] ?? "") . "</svg>";
}

function route_mark(int $size = 28): string
{
    $h = $size * 0.5;
    return "<svg width=\"$size\" height=\"$h\" viewBox=\"0 0 56 28\" fill=\"none\">
        <circle cx=\"6\" cy=\"14\" r=\"5\" fill=\"var(--accent)\"/>
        <line x1=\"12\" y1=\"14\" x2=\"38\" y2=\"14\" stroke=\"var(--route)\" stroke-width=\"2.5\" stroke-dasharray=\"4 4\" stroke-linecap=\"round\"/>
        <path d=\"M42 6c-5 0-8 3.5-8 8 0 6 8 12 8 12s8-6 8-12c0-4.5-3-8-8-8z\" fill=\"var(--route)\"/>
        <circle cx=\"50\" cy=\"14\" r=\"2.2\" fill=\"var(--bg)\"/>
    </svg>";
}

function route_divider(): string
{
    return '<div class="divider"><div class="dot"></div><div class="line"></div><div class="tri"></div></div>';
}

/** A "Flag driver" / "Flag client" button. Pressing it opens a small box where a REASON must be typed before it can be sent. */
function flag_form_html(int $requestId, string $who, string $back, bool $alreadyFlagged = false): string
{
    $boxId = "flagbox" . $requestId;
    $badge = $alreadyFlagged ? '<span class="badge" style="background:var(--alert);color:#fff">Flagged</span>' : "";
    return '<div class="mt3">
        <div class="row gap2 flexwrap">
            <button type="button" class="btn btn-danger" onclick="var b=document.getElementById(\'' . $boxId . '\');b.style.display=(b.style.display===\'none\'?\'flex\':\'none\');">' . icon("flag", 14) . ' Flag ' . $who . '</button>' . $badge . '
        </div>
        <form method="post" action="actions.php" id="' . $boxId . '" class="row gap2 mt2 flexwrap" style="display:none">' . csrf_field() . '
            <input type="hidden" name="action" value="report_request">
            <input type="hidden" name="request_id" value="' . $requestId . '">
            <input type="hidden" name="back" value="' . h($back) . '">
            <input class="input" name="reason" placeholder="Reason for flagging the ' . $who . ' (required)" maxlength="255" required style="flex:1;min-width:200px">
            <button class="btn btn-danger" type="submit">Submit flag</button>
        </form>
    </div>';
}

/** A small tile for an uploaded document: a "PDF" tile for PDF files, a photo thumbnail for old image uploads. */
function doc_tile(?string $path, string $label, int $size = 64): string
{
    if (!$path) {
        return "";
    }
    $isPdf = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === "pdf";
    $box = "width:{$size}px;height:{$size}px;border-radius:10px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden";
    $inner = $isPdf
        ? '<span style="font-family:monospace;font-weight:700;font-size:13px;color:var(--alert)">PDF</span>'
        : '<img src="' . h($path) . '" style="width:100%;height:100%;object-fit:cover">';
    return '<div class="col" style="align-items:center"><a href="' . h($path) . '" target="_blank" style="' . $box . '">' . $inner . '</a>'
         . '<span style="font-size:10.5px;color:var(--ink-soft);margin-top:4px">' . h($label) . '</span></div>';
}

function status_badge(string $status): string
{
    $map = [
        "pending" => ["var(--accent)", "var(--accent-ink)", "Awaiting driver"],
        "accepted" => ["var(--route)", "#fff", "In transit"],
        "delivered" => ["var(--ink-soft)", "var(--bg)", "Delivered"],
        "cancelled" => ["var(--alert)", "#fff", "Cancelled"],
    ];
    [$bg, $fg, $label] = $map[$status] ?? $map["pending"];
    return "<span class=\"badge\" style=\"background:$bg;color:$fg\">$label</span>";
}

function avatar_html(?string $pic, string $name, int $size = 56, bool $editable = false): string
{
    $initials = strtoupper(substr(implode("", array_map(fn($w) => $w[0] ?? "", explode(" ", $name))), 0, 2));
    $inner = $pic
        ? '<img src="' . h($pic) . '" alt="' . h($name) . '" style="width:100%;height:100%;object-fit:cover">'
        : '<span class="disp" style="color:#fff;font-size:' . ($size * 0.32) . 'px">' . h($initials) . '</span>';
    $editBtn = "";
    if ($editable) {
        $editBtn = '<form method="post" action="actions.php" enctype="multipart/form-data" style="position:absolute;bottom:-2px;right:-2px">
            ' . csrf_field() . '
            <input type="hidden" name="action" value="upload_profile_pic">
            <label style="width:26px;height:26px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;border:2px solid var(--bg);cursor:pointer">
                ' . icon("camera", 13, "var(--accent-ink)") . '
                <input type="file" name="profile_pic" accept="image/*" style="display:none" onchange="this.form.submit()">
            </label>
        </form>';
    }
    return '<div style="position:relative;width:' . $size . 'px;height:' . $size . 'px;flex-shrink:0">
        <div style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;overflow:hidden;background:' . ($pic ? "transparent" : "var(--route)") . ';display:flex;align-items:center;justify-content:center;border:2px solid var(--border)">' . $inner . '</div>
        ' . $editBtn . '
    </div>';
}

function map_picker_html(string $which, array $pin, string $color, string $label, bool $locate = false): string
{
    $lat = $pin["lat"] ?? "";
    $lng = $pin["lng"] ?? "";
    $addr = $pin["addr"] ?? "";
    $locateBtn = $locate
        ? '<button type="button" id="locate_' . $which . '" class="btn btn-ghost mb2" style="width:100%;justify-content:center">'
            . icon("nav", 15, $color) . ' <span id="locate_text_' . $which . '">Use my current location</span></button>'
        : '';
    return '<div>
        <div class="row between mb2"><span style="font-size:12px;font-weight:600;color:var(--ink-soft)">' . h($label) . '</span></div>
        ' . $locateBtn . '
        <div class="row gap2 mb2">
            <input id="search_' . $which . '" class="input" placeholder="Search an address or place" autocomplete="off">
            <button type="button" id="search_btn_' . $which . '" class="btn btn-ghost" style="padding:10px 14px">Search</button>
        </div>
        <div id="results_' . $which . '" class="geo-results" style="display:none"></div>
        <div id="leaflet-' . $which . '" style="height:300px;border-radius:12px;overflow:hidden;border:1px solid var(--border)"></div>
        <input id="' . $which . '_addr" name="' . $which . '_addr" class="input mt2" placeholder="Tap the map or search to set this location" value="' . h($addr) . '">
        <input type="hidden" id="' . $which . '_lat" name="' . $which . '_lat" value="' . h((string)$lat) . '">
        <input type="hidden" id="' . $which . '_lng" name="' . $which . '_lng" value="' . h((string)$lng) . '">
    </div>';
}

/* =========================================================
   AUTH PAGE
========================================================= */

function page_auth(?array $flash): string
{
    $mode = $_GET["authmode"] ?? "signin";
    $role = $_GET["role"] ?? "client";
    $flashHtml = $flash ? '<div class="flash flash-' . h($flash["type"]) . '">' . h($flash["message"]) . '</div>' : "";

    $signinForm = '<form method="post" action="actions.php" class="col gap3">
        ' . csrf_field() . '
        <input type="hidden" name="action" value="login">
        <input class="input" name="name" placeholder="Full name" required>
        <input class="input" name="password" type="password" placeholder="Password" required>
        <button class="btn btn-primary" style="justify-content:center" type="submit">' . icon("check", 15) . ' Sign in</button>
        <a href="?authmode=reset" style="font-size:12.5px;color:var(--ink-soft);text-align:center;text-decoration:underline">Forgot your password?</a>
    </form>';

    $registerForm = '<form method="post" action="actions.php" class="col gap3">
        ' . csrf_field() . '
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="role" value="client">
        <div class="row gap2">
            <input class="input" name="name" placeholder="Name" required>
            <input class="input" name="surname" placeholder="Surname" required>
        </div>
        <input class="input" name="phone" placeholder="Phone number (e.g. 0821234567)" pattern="0[0-9]{9}" maxlength="10" title="South African number: 10 digits, starting with 0" required>
        <input class="input" name="email" type="email" placeholder="Email address (for password-reset codes)" maxlength="190" required>
        <div>
            <input class="input" name="password" type="password" placeholder="Password" required>
            <p style="font-size:11px;color:var(--ink-soft);margin-top:4px">' . h(PASSWORD_HINT) . '</p>
        </div>
        <button class="btn btn-primary" style="justify-content:center" type="submit">' . icon("plus", 15) . ' Create account</button>
        <p style="font-size:11.5px;color:var(--ink-soft);text-align:center">Registration is for clients only. Driver accounts are created by an admin.</p>
    </form>';

    $resetForm = '<form method="post" action="actions.php" class="col gap3">
        ' . csrf_field() . '
        <input type="hidden" name="action" value="request_otp">
        <p style="font-size:12.5px;color:var(--ink-soft)">Enter the email address on your account and we\'ll email you a one-time code to reset your password.</p>
        <input class="input" name="email" type="email" placeholder="Email address" maxlength="190" required autofocus value="' . h($_GET["email"] ?? "") . '">
        <button class="btn btn-primary" style="justify-content:center" type="submit">' . icon("send", 15) . ' Send code</button>
        <a href="?authmode=signin" style="font-size:12.5px;color:var(--ink-soft);text-align:center;text-decoration:underline">Back to sign in</a>
    </form>';

    $otpEmail = $_GET["email"] ?? "";
    $otpForm = '<form method="post" action="actions.php" class="col gap3">
        ' . csrf_field() . '
        <input type="hidden" name="action" value="verify_otp_reset">
        <p style="font-size:12.5px;color:var(--ink-soft)">Enter the 6-digit code we emailed you — it expires 60 seconds after it was sent. Check your spam folder if it hasn\'t arrived.</p>
        <input type="hidden" name="email" value="' . h($otpEmail) . '">
        <input class="input mono" name="otp" placeholder="6-digit code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus style="text-align:center;letter-spacing:4px;font-size:18px">
        <div>
            <input class="input" name="new_password" type="password" placeholder="New password" required>
            <p style="font-size:11px;color:var(--ink-soft);margin-top:4px">' . h(PASSWORD_HINT) . '</p>
        </div>
        <button class="btn btn-primary" style="justify-content:center" type="submit">' . icon("check", 15) . ' Reset password</button>
        <a href="?authmode=reset&email=' . h(urlencode($otpEmail)) . '" style="font-size:12.5px;color:var(--ink-soft);text-align:center;text-decoration:underline">Didn\'t get it in time? Request a new code</a>
    </form>';

    $modeLabel = ["signin" => "Sign in", "register" => "Register", "reset" => "Reset password", "otp_verify" => "Enter your code"][$mode] ?? "Sign in";
    $formHtml = $resetForm;
    if ($mode === "signin") { $formHtml = $signinForm; }
    if ($mode === "register") { $formHtml = $registerForm; }
    if ($mode === "otp_verify") { $formHtml = $otpForm; }

    return '<div class="row center" style="min-height:100vh;padding:16px">
        <div class="card" style="width:100%;max-width:420px">
            <div class="row gap2 mb1">' . route_mark() . '<span class="disp" style="font-size:20px">Phoenix</span></div>
            <p style="font-size:13px;color:var(--ink-soft);margin-bottom:18px">Local delivery, matched fast.</p>
            ' . (($mode === "signin" || $mode === "register") ? '<div class="row gap2 mb4">
                <a class="tab ' . ($mode === "signin" ? "active" : "") . '" style="flex:1;justify-content:center" href="?authmode=signin">' . icon("check", 14) . ' Sign in</a>
                <a class="tab ' . ($mode === "register" ? "active" : "") . '" style="flex:1;justify-content:center" href="?authmode=register">' . icon("plus", 14) . ' Register</a>
            </div>' : '<div class="disp mb4" style="font-size:15px">' . h($modeLabel) . '</div>') . '
            ' . $flashHtml . '
            ' . $formHtml . '
        </div>
    </div>';
}

/* =========================================================
   FORCED PASSWORD CHANGE
   Shown instead of the normal app to any driver who hasn't yet
   replaced the temporary password an admin issued them — they can't
   reach any other screen until they do.
========================================================= */

function page_force_password_change(array $user, ?array $flash): string
{
    $flashHtml = $flash ? '<div class="flash flash-' . h($flash["type"]) . '">' . h($flash["message"]) . '</div>' : "";
    return '<div class="row center" style="min-height:100vh;padding:16px">
        <div class="card" style="width:100%;max-width:420px">
            <div class="row gap2 mb1">' . route_mark() . '<span class="disp" style="font-size:20px">Phoenix</span></div>
            <p style="font-size:13px;color:var(--ink-soft);margin-bottom:18px">Welcome, ' . h($user["name"]) . '. For security, set your own password before continuing — the temporary one your admin gave you can\'t be used again.</p>
            ' . $flashHtml . '
            <form method="post" action="actions.php" class="col gap3">
                ' . csrf_field() . '
                <input type="hidden" name="action" value="change_password">
                <div>
                    <input class="input" name="new_password" type="password" placeholder="New password" required autofocus>
                    <p style="font-size:11px;color:var(--ink-soft);margin-top:4px">' . h(PASSWORD_HINT) . '</p>
                </div>
                <button class="btn btn-primary" style="justify-content:center" type="submit">' . icon("check", 15) . ' Set password and continue</button>
            </form>
            <form method="post" action="actions.php" style="margin-top:12px">' . csrf_field() . '<input type="hidden" name="action" value="logout">
                <button class="btn btn-ghost" style="width:100%;justify-content:center" type="submit">' . icon("logout", 14) . ' Sign out instead</button>
            </form>
        </div>
    </div>';
}


/* =========================================================
   EMAIL ON FILE — shown on profile pages so existing accounts
   (created before email codes existed) can add one too.
========================================================= */

function email_form_html(array $user): string
{
    $status = !empty($user["email"])
        ? 'Password-reset codes are sent to <b>' . h(mask_email($user["email"])) . '</b>.'
        : '<span style="color:var(--alert)">No email on file</span> — add one so you can reset your password if you forget it.';
    return '<div class="card card-sm" style="margin-top:14px">
        <div class="disp mb2" style="font-size:14px">' . icon("send", 14) . ' Email for password resets</div>
        <p style="font-size:12.5px;color:var(--ink-soft);margin-bottom:8px">' . $status . '</p>
        <form method="post" action="actions.php" class="row gap2">' . csrf_field() . '<input type="hidden" name="action" value="update_email">
            <input class="input" name="email" type="email" placeholder="you@example.com" maxlength="190" required value="' . h($user["email"] ?? "") . '">
            <button class="btn btn-ghost" type="submit">Save email</button>
        </form>
    </div>';
}

/* =========================================================
   NAV
========================================================= */

/** Menu items for each role: [tab id, label, icon]. */
function role_tabs(string $role): array
{
    if ($role === "driver") {
        return [
            ["active", "My deliveries", "nav"], ["history", "History", "history"],
            ["earnings", "Earnings", "dollar"], ["performance", "Performance", "chart"],
            ["documents", "Verification", "shield"], ["profile", "Profile", "user"],
        ];
    }
    if ($role === "admin") {
        return [
            ["dashboard", "Dashboard", "chart"], ["verification", "Verification", "shield"], ["assign", "Assign drivers", "truck"],
            ["users", "Users", "user"], ["adddriver", "Add driver staff", "plus"], ["deliveries", "Deliveries", "nav"], ["payroll", "Payroll", "dollar"],
            ["finance", "Finance", "chart"], ["moderation", "Moderation", "flag"], ["config", "Platform config", "gear"],
            ["audit", "Audit log", "history"],
        ];
    }
    return [["post", "Post a request", "plus"], ["history", "My requests", "history"], ["card", "Payment card", "dollar"], ["profile", "Profile", "user"]];
}

function page_nav(array $user, string $view, ?string $tab = null): string
{
    global $currentTheme;

    $roleLabel = "Client";
    if ($user["role"] === "driver") $roleLabel = "Driver";
    if ($user["role"] === "admin") $roleLabel = $user["admin_tier"] === "super" ? "Super Admin" : "Admin";

    $tabs = role_tabs($user["role"]);
    $current = $tab ?? $tabs[0][0];

    /* ---- desktop: the sidebar ---- */
    $links = "";
    foreach ($tabs as [$id, $label, $ic]) {
        $active = ($view === "home" && $current === $id) ? "active" : "";
        $links .= '<a class="sb-link ' . $active . '" href="?tab=' . h($id) . '" title="' . h($label) . '">'
               . icon($ic, 20) . '<span class="sb-text">' . h($label) . '</span></a>';
    }
    $links .= '<div class="sb-sep"></div>';
    foreach ([["settings", "Settings", "gear"], ["about", "About", "info"]] as [$id, $label, $ic]) {
        $active = $view === $id ? "active" : "";
        $links .= '<a class="sb-link ' . $active . '" href="?view=' . $id . '" title="' . $label . '">'
               . icon($ic, 20) . '<span class="sb-text">' . $label . '</span></a>';
    }

    $isDark = $currentTheme === "dark";
    $back = "index.php" . (!empty($_SERVER["QUERY_STRING"]) ? "?" . $_SERVER["QUERY_STRING"] : "");
    $fullName = trim($user["name"] . " " . $user["surname"]);

    /* ---- phone: a bottom bar with the first 4 items, and a "More" sheet with the rest ---- */
    $short = ["post" => "Request", "card" => "Card", "active" => "Deliveries", "performance" => "Stats",
              "verification" => "Verify", "assign" => "Assign", "adddriver" => "Add driver"];
    $bottom = "";
    foreach (array_slice($tabs, 0, 4) as [$id, $label, $ic]) {
        $active = ($view === "home" && $current === $id) ? " active" : "";
        $bottom .= '<a class="bb-item' . $active . '" href="?tab=' . h($id) . '">' . icon($ic, 22) . '<span>' . h($short[$id] ?? $label) . '</span></a>';
    }
    $moreActive = in_array($view, ["settings", "about"], true);
    $sheet = "";
    foreach (array_slice($tabs, 4) as [$id, $label, $ic]) {
        $isCurrent = ($view === "home" && $current === $id);
        if ($isCurrent) $moreActive = true;
        $sheet .= '<a class="ms-link' . ($isCurrent ? " active" : "") . '" href="?tab=' . h($id) . '">' . icon($ic, 20) . ' ' . h($label) . '</a>';
    }
    foreach ([["settings", "Settings", "gear"], ["about", "About", "info"]] as [$id, $label, $ic]) {
        $sheet .= '<a class="ms-link' . ($view === $id ? " active" : "") . '" href="?view=' . $id . '">' . icon($ic, 20) . ' ' . $label . '</a>';
    }
    $moreIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>';

    return '<aside class="sidebar" id="sidebar">
        <div class="sb-head">
            <div class="sb-mark">' . route_mark(30) . '</div>
            <div class="sb-text sb-title"><div class="disp" style="font-size:16px;font-weight:700">Phoenix</div>
                <div style="font-size:11px;color:var(--ink-soft)">' . h($roleLabel) . '</div></div>
            <button type="button" class="sb-toggle" id="sbToggle" title="Collapse / expand menu" aria-label="Toggle menu">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
            </button>
        </div>
        <div class="sb-user sb-text" title="' . h($fullName) . '">' . h($fullName) . '</div>
        <nav class="sb-nav">' . $links . '</nav>
        <div class="sb-foot">
            <form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="logout">
                <button class="sb-link sb-btn" type="submit" title="Logout">' . icon("logout", 20) . '<span class="sb-text">Logout</span></button>
            </form>
            <form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="set_theme">
                <input type="hidden" name="theme" value="' . ($isDark ? "light" : "dark") . '">
                <input type="hidden" name="back" value="' . h($back) . '">
                <button class="sb-link sb-btn sb-darkrow" type="submit" title="Dark mode">' . icon("moon", 20) . '<span class="sb-text">Dark mode</span>
                    <span class="sb-switch ' . ($isDark ? "on" : "") . '"><span class="knob"></span></span></button>
            </form>
        </div>
    </aside>
    <header class="m-top">
        <span class="m-mark">' . route_mark(26) . '</span>
        <span class="disp" style="font-size:17px;font-weight:700">Phoenix</span>
        <span class="m-role">' . h($roleLabel) . ' · ' . h($user["name"]) . '</span>
    </header>
    <nav class="bottombar">' . $bottom . '
        <button type="button" class="bb-item' . ($moreActive ? " active" : "") . '" id="moreBtn" aria-label="More menu">' . $moreIcon . '<span>More</span></button>
    </nav>
    <div class="more-sheet" id="moreSheet">
        <div class="more-panel">
            <div class="ms-head"><div style="font-weight:700">' . h($fullName) . '</div><div style="font-size:12px;color:var(--ink-soft)">' . h($roleLabel) . '</div></div>
            ' . $sheet . '
            <div class="sb-sep"></div>
            <form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="set_theme">
                <input type="hidden" name="theme" value="' . ($isDark ? "light" : "dark") . '">
                <input type="hidden" name="back" value="' . h($back) . '">
                <button class="ms-link" type="submit">' . icon("moon", 20) . ' ' . ($isDark ? "Light mode" : "Dark mode") . '</button>
            </form>
            <form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="logout">
                <button class="ms-link" type="submit" style="color:var(--alert)">' . icon("logout", 20) . ' Logout</button>
            </form>
        </div>
    </div>
    <script>
    (function () {
        var b = document.getElementById("moreBtn"), s = document.getElementById("moreSheet");
        if (!b || !s) { return; }
        b.addEventListener("click", function () { s.classList.toggle("open"); });
        s.addEventListener("click", function (e) { if (e.target === s) { s.classList.remove("open"); } });
    })();
    </script>';
}

/* =========================================================
   SETTINGS + ABOUT
========================================================= */

function page_settings(string $theme): string
{
    return '<div class="wrap"><div class="card">
        <div class="disp mb1" style="font-size:16px">Appearance</div>
        <p style="font-size:13px;color:var(--ink-soft);margin-bottom:16px">Switch between light and dark mode. Saved as a cookie on this browser.</p>
        <div class="row gap3">
            <form method="post" action="actions.php" style="flex:1">' . csrf_field() . '<input type="hidden" name="action" value="set_theme"><input type="hidden" name="theme" value="light">
                <button type="submit" class="card card-sm col center" style="width:100%;align-items:center;gap:8px;border-width:' . ($theme === "light" ? 2 : 1) . 'px;border-color:' . ($theme === "light" ? "var(--route)" : "var(--border)") . '">
                    ' . icon("sun", 20, $theme === "light" ? "var(--route)" : "var(--ink-soft)") . '<span style="font-size:13px;font-weight:600">White mode</span>
                </button>
            </form>
            <form method="post" action="actions.php" style="flex:1">' . csrf_field() . '<input type="hidden" name="action" value="set_theme"><input type="hidden" name="theme" value="dark">
                <button type="submit" class="card card-sm col center" style="width:100%;align-items:center;gap:8px;border-width:' . ($theme === "dark" ? 2 : 1) . 'px;border-color:' . ($theme === "dark" ? "var(--route)" : "var(--border)") . '">
                    ' . icon("moon", 20, $theme === "dark" ? "var(--route)" : "var(--ink-soft)") . '<span style="font-size:13px;font-weight:600">Black mode</span>
                </button>
            </form>
        </div>
    </div>
    <div class="card" id="installCard" style="margin-top:14px">
        <div class="disp mb1" style="font-size:16px">Install the app</div>
        <p style="font-size:13px;color:var(--ink-soft);margin-bottom:12px">Add Phoenix to your phone menu so it opens full screen like a normal app.</p>
        <button type="button" class="btn btn-primary" id="installBtn" style="display:none;margin-bottom:12px">Install Phoenix</button>
        <p style="font-size:12.5px;color:var(--ink-soft);margin:0 0 6px"><b>Android (Chrome):</b> tap the menu (three dots), then Install app or Add to Home screen.</p>
        <p style="font-size:12.5px;color:var(--ink-soft);margin:0"><b>iPhone (Safari):</b> tap Share, then Add to Home Screen.</p>
    </div></div>';
}

function page_about(): string
{
    // ===== EDIT THESE: our contact details and offices (these are placeholders, replace with the real ones) =====
    $contactEmail = "info@example.co.za";
    $contactPhone = "015 000 0000";
    $offices = [
        ["Polokwane Central (head office)", "Landdros Mare Street, Polokwane Central, 0699"],
        ["Bendor branch",                   "Bendor Drive, Bendor, Polokwane, 0699"],
        ["Flora Park branch",               "Flora Park, Polokwane, 0699"],
        ["Seshego branch",                  "Zone 1, Seshego, 0742"],
    ];

    $contactHtml = '<div class="disp mt4 mb2" style="font-size:14px">Contact us</div>
        <div class="col gap2" style="font-size:13.5px">
            <div class="row gap2">' . icon("send", 14, "var(--route)") . ' <a href="mailto:' . h($contactEmail) . '">' . h($contactEmail) . '</a></div>
            <div class="row gap2">' . icon("phone", 14, "var(--route)") . ' <a href="tel:' . h(preg_replace('/\s+/', "", $contactPhone)) . '">' . h($contactPhone) . '</a></div>
        </div>';

    $officeHtml = '<div class="disp mt4 mb2" style="font-size:14px">Our offices around Polokwane</div><div class="col gap2">';
    foreach ($offices as [$place, $address]) {
        $officeHtml .= '<div class="card card-sm"><div style="font-weight:600;font-size:13.5px">' . icon("pin", 13, "var(--route)") . ' ' . h($place) . '</div>
            <div style="font-size:12.5px;color:var(--ink-soft);margin-top:2px">' . h($address) . '</div></div>';
    }
    $officeHtml .= '</div>';

    return '<div class="wrap"><div class="card">
        <div class="row gap2 mb3">' . route_mark() . '<span class="disp" style="font-size:18px">About Phoenix</span></div>
        <p style="font-size:14px;line-height:1.7">Phoenix exists to close the gap between someone who needs a package moved and a driver who\'s already on the road. We believe local delivery should be simple, fast, and personal — no call centres, no waiting on hold, just a direct line between client and driver from pickup to drop-off.</p>
        ' . route_divider() . '
        <div class="disp mb2" style="font-size:14px">Our goals</div>
        <ul style="font-size:13.5px;line-height:1.9;padding-left:18px">
            <li>Connect clients and independent drivers directly, with no unnecessary middlemen.</li>
            <li>Keep every delivery transparent — real-time status, direct messaging, and a clear price.</li>
            <li>Give drivers fair, visible earnings and a simple way to track their performance over time.</li>
            <li>Make requesting a delivery as easy as dropping a pin and writing a short description.</li>
        </ul>
        <div class="disp mt4 mb2" style="font-size:14px">Our vision</div>
        <p style="font-size:13.5px;line-height:1.8">We want Phoenix to become the trusted first call for everyday deliveries in every neighbourhood we operate in — reliable enough for a business, and simple enough for a next-door favour.</p>
        ' . $contactHtml . $officeHtml . '
    </div></div>';
}

/* =========================================================
   CLIENT HOME
========================================================= */

function page_client_home(array $user, string $tab): string
{
    $tabBar = "";

    $body = "";

    if ($tab === "post") {
        $config = get_config();
        $hasCard = client_has_card((int)$user["id"]);   // true when a payment card is saved
        $draft = $_SESSION["draft_request"] ?? [];      // a request kept earlier (see actions.php)

        // A friendly reminder instead of blocking the page
        $cardNotice = $hasCard ? "" : '<div class="card card-sm row between gap3 flexwrap" style="border-color:var(--accent);margin-bottom:12px">
                <span style="font-size:13px">' . icon("info", 14, "var(--accent)") . ' You have no payment card yet. Fill in your request now - you will be asked to add a card when you press the button.</span>
                <a class="btn btn-primary" style="padding:6px 12px;font-size:12px" href="?tab=card">' . icon("plus", 13) . ' Add a card</a>
            </div>';

        {   // (a plain block, so the closing brace further down still matches)
        $body = $cardNotice . '<div class="card col gap4">
            <form method="post" action="actions.php" class="col gap4" id="postForm">
                ' . csrf_field() . '
                <input type="hidden" name="action" value="post_request">
                <div>
                    <div style="font-size:12px;font-weight:600;color:var(--ink-soft);margin-bottom:8px">Package description</div>
                    <textarea class="input" name="description" rows="3" placeholder="What are we delivering? e.g. Sealed envelope, 2kg box, fragile item…" required>' . h($draft["description"] ?? "") . '</textarea>
                </div>
                <div id="stepPickup">
                    <div class="row gap2 mb3"><span class="badge" style="background:var(--accent);color:var(--accent-ink)">Step 1 of 2</span><span style="font-size:13px;color:var(--ink-soft)">Where should the driver collect the package?</span></div>
                    ' . map_picker_html("pickup", ["lat" => $draft["pickup_lat"] ?? "", "lng" => $draft["pickup_lng"] ?? "", "addr" => $draft["pickup_addr"] ?? ""], "var(--accent)", "Pickup location", true) . '
                    <button type="button" class="btn btn-route mt3" id="confirmPickup" style="width:100%;justify-content:center" disabled>Confirm pickup and choose drop-off</button>
                </div>
                <div id="pickupSummary" class="card card-sm row between gap3" style="display:none;background:var(--surface-alt)">
                    <span class="row gap2" style="min-width:0">' . icon("pin", 14, "var(--accent)") . ' <span id="pickupSummaryText" style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span></span>
                    <button type="button" class="btn btn-ghost" id="changePickup" style="padding:6px 12px;font-size:12px;flex-shrink:0">Change</button>
                </div>
                <div id="stepDropoff" style="display:none">
                    <div class="row gap2 mb3"><span class="badge" style="background:var(--route);color:#fff">Step 2 of 2</span><span style="font-size:13px;color:var(--ink-soft)">Where should it be delivered?</span></div>
                    ' . map_picker_html("dropoff", ["lat" => $draft["dropoff_lat"] ?? "", "lng" => $draft["dropoff_lng"] ?? "", "addr" => $draft["dropoff_addr"] ?? ""], "var(--route)", "Drop-off location") . '
                </div>
                <div class="card card-sm" id="fareEstimate" style="display:none;background:var(--surface-alt)">
                    <div class="row between">
                        <span style="font-size:12px;color:var(--ink-soft)">Distance</span>
                        <span class="mono" id="estDistance" style="font-weight:700">Set both pins</span>
                    </div>
                    <div class="row between mt2">
                        <span style="font-size:12px;color:var(--ink-soft)">Estimated fare (charged to your card on posting)</span>
                        <span class="mono" id="estFare" style="font-weight:700;color:var(--route)">—</span>
                    </div>
                </div>
                <button class="btn btn-primary" id="submitBtn" style="justify-content:center" type="submit" disabled>' . icon("send", 15) . ' ' . ($hasCard ? "Pay & post delivery request" : "Add card & continue") . '</button>
            </form>
        </div>
        <script>
        (function () {
            var BASE_FARE = ' . (float)$config["base_fare"] . ';
            var RATE_PER_KM = ' . (float)$config["rate_per_km"] . ';
            function km(lat1, lng1, lat2, lng2) {
                var R = 6371, dLat = (lat2 - lat1) * Math.PI / 180, dLng = (lng2 - lng1) * Math.PI / 180;
                var a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
                return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            }
            var confirmed = false;
            function val(id) { return parseFloat(document.getElementById(id).value); }
            function show(id, on) { document.getElementById(id).style.display = on ? "" : "none"; }
            function pickupSet() { return !isNaN(val("pickup_lat")) && !isNaN(val("pickup_lng")); }
            function dropSet() { return !isNaN(val("dropoff_lat")) && !isNaN(val("dropoff_lng")); }
            function resize(which) {
                setTimeout(function () {
                    if (window.phoenixMaps && window.phoenixMaps[which]) { window.phoenixMaps[which].invalidateSize(); }
                }, 80);
            }
            function refresh() {
                var confirmBtn = document.getElementById("confirmPickup");
                confirmBtn.disabled = !pickupSet();
                var ready = confirmed && pickupSet() && dropSet();
                show("fareEstimate", ready);
                document.getElementById("submitBtn").disabled = !ready;
                if (!ready) { return; }
                var d = km(val("pickup_lat"), val("pickup_lng"), val("dropoff_lat"), val("dropoff_lng"));
                var fare = BASE_FARE + d * RATE_PER_KM;
                document.getElementById("estDistance").textContent = d.toFixed(2) + " km";
                document.getElementById("estFare").textContent = "R" + fare.toFixed(2);
            }
            document.addEventListener("phoenix:pin", refresh);
            document.getElementById("confirmPickup").addEventListener("click", function () {
                if (!pickupSet()) { return; }
                confirmed = true;
                document.getElementById("pickupSummaryText").textContent = document.getElementById("pickup_addr").value || "Pinned location";
                show("stepPickup", false); show("pickupSummary", true); show("stepDropoff", true);
                var m = window.phoenixMaps && window.phoenixMaps.dropoff;
                if (m && !dropSet()) { m.setView([val("pickup_lat"), val("pickup_lng")], 13); }
                resize("dropoff");
                refresh();
            });
            document.getElementById("changePickup").addEventListener("click", function () {
                confirmed = false;
                show("pickupSummary", false); show("stepDropoff", false); show("stepPickup", true);
                resize("pickup");
                refresh();
            });
            refresh();
            setInterval(refresh, 700);
        })();
        </script>';
        }
    } elseif ($tab === "history") {
        $stmt = db()->prepare(
            "SELECT r.*, d.name AS driver_name, d.surname AS driver_surname
             FROM requests r LEFT JOIN users d ON d.id = r.driver_id
             WHERE r.client_id = ? ORDER BY r.created_at DESC"
        );
        $stmt->execute([$user["id"]]);
        $requests = $stmt->fetchAll();

        if (!$requests) {
            $body = '<div class="card" style="text-align:center;color:var(--ink-soft);font-size:13.5px">Nothing posted yet — your requests will show up here.</div>';
        } else {
            $body = '<div class="col gap3">';
            foreach ($requests as $r) {
                $driverName = $r["driver_name"] ? h($r["driver_name"] . " " . $r["driver_surname"]) : null;
                $chatBtn = ($r["driver_id"] && $r["status"] !== "delivered")
                    ? '<a class="btn btn-ghost mt3" href="?view=chat&request=' . $r["id"] . '">' . icon("message", 14) . ' Message driver</a>' : "";
                $fareHtml = $r["fare"] !== null ? '<span class="mono" style="color:var(--route);font-weight:700">' . fmt_r($r["fare"]) . '</span>' : "";
                $distHtml = $r["distance_km"] !== null ? '<span class="row gap2">' . icon("nav", 13, "var(--ink-soft)") . ' ' . h(number_format((float)$r["distance_km"], 2)) . ' km</span>' : "";
                $paidBadge = $r["paid"] ? '<span class="badge" style="background:var(--route);color:#fff">Paid</span>' : "";
                 // Cancel button: only before the driver has collected the parcel
                $cancelBtn = (in_array($r["status"], ["pending", "accepted"], true) && !$r["pickup_photo"])
                    ? '<form method="post" action="actions.php" style="display:inline-block;margin-left:8px" onsubmit="return confirm(\'Cancel this request? The admin will refund your payment.\')">' . csrf_field() . '<input type="hidden" name="action" value="cancel_request"><input type="hidden" name="request_id" value="' . $r["id"] . '"><button class="btn btn-danger" type="submit">' . icon("ban", 14) . ' Cancel request</button></form>' : "";

                // After cancelling: is the money back yet?
                $refundBadge = "";
                if ($r["status"] === "cancelled" && $r["paid"]) {
                    $refundBadge = $r["refunded"]
                        ? '<span class="badge" style="background:var(--route);color:#fff">Refunded</span>'
                        : '<span class="badge" style="background:var(--accent);color:var(--accent-ink)">Refund pending</span>';
                }
                $photosHtml = "";
                if ($r["pickup_photo"] || $r["delivery_photo"]) {
                    $photosHtml = '<div class="row gap2 mt2">';
                    if ($r["pickup_photo"]) $photosHtml .= '<a href="' . h($r["pickup_photo"]) . '" target="_blank"><img src="' . h($r["pickup_photo"]) . '" title="Pickup photo" style="width:36px;height:36px;border-radius:8px;object-fit:cover"></a>';
                    if ($r["delivery_photo"]) $photosHtml .= '<a href="' . h($r["delivery_photo"]) . '" target="_blank"><img src="' . h($r["delivery_photo"]) . '" title="Delivery photo" style="width:36px;height:36px;border-radius:8px;object-fit:cover"></a>';
                    $photosHtml .= '</div>';
                }
                $body .= '<div class="card card-sm">
                    <div class="row between start">
                        <div><div class="disp" style="font-size:15px">' . h($r["description"]) . '</div>
                        <div style="font-size:12px;color:var(--ink-soft)">' . fmt_date($r["created_at"]) . ($driverName ? " · Driver: $driverName" : " · Awaiting driver assignment") . '</div></div>
                        <div class="col gap2" style="align-items:flex-end">' . status_badge($r["status"]) . $paidBadge . '</div>
                    </div>
                    <div class="row between mt3 route-row">
                        <div class="row gap4 route-addrs" style="font-size:12px;color:var(--ink-soft)">
                            <span class="row gap2">' . icon("pin", 13, "var(--accent)") . ' ' . h($r["pickup_addr"] ?: "Pinned") . '</span>
                            <span class="row gap2">' . icon("pin", 13, "var(--route)") . ' ' . h($r["dropoff_addr"] ?: "Pinned") . '</span>
                            ' . $distHtml . '
                        </div>
                        ' . $fareHtml . '
                    </div>
                    ' . $photosHtml . '
                    ' . $chatBtn . $cancelBtn . ($r["driver_id"] ? flag_form_html((int)$r["id"], "driver", "history", (bool)$r["flagged"]) : "") . '
                </div>';
            }
            $body .= '</div>';
        }
    } elseif ($tab === "card") {
        $stmt = db()->prepare("SELECT * FROM client_cards WHERE client_id = ?");
        $stmt->execute([$user["id"]]);
        $card = $stmt->fetch();

        if ($card) {
            $body = '<div class="card col gap4">
                <div class="card card-sm row between" style="background:var(--surface-alt)">
                    <div>
                        <div class="disp" style="font-size:16px">' . h($card["brand"]) . ' •••• •••• •••• ' . h($card["last4"]) . '</div>
                        <div style="font-size:12px;color:var(--ink-soft)">' . h($card["card_holder"]) . ' · Expires ' . str_pad((string)$card["expiry_month"], 2, "0", STR_PAD_LEFT) . '/' . substr((string)$card["expiry_year"], -2) . '</div>
                    </div>
                    ' . icon("check", 20, "var(--route)") . '
                </div>
                <p style="font-size:12px;color:var(--ink-soft)">This is a simulated card on file — no real payment gateway is connected. Only the last 4 digits and expiry are stored; the full number and CVV are never saved.</p>
                <form method="post" action="actions.php" onsubmit="return confirm(\'Remove this card? You will need to add one again before your next delivery request.\')">' . csrf_field() . '<input type="hidden" name="action" value="remove_card">
                    <button class="btn btn-danger" type="submit">' . icon("ban", 14) . ' Remove card</button>
                </form>
            </div>';
        } else {
            $body = '<div class="card col gap4">
                <p style="font-size:13px;color:var(--ink-soft)">Add a card to pay for delivery requests. This is a simulated payment method for demo purposes — no real gateway is connected, and we only store the last 4 digits and expiry, never the full number or CVV.</p>
                <form method="post" action="actions.php" class="col gap3">' . csrf_field() . '<input type="hidden" name="action" value="add_card">
                    <input class="input" name="card_holder" placeholder="Name on card" required>
                    <input class="input" name="card_number" placeholder="Card number" inputmode="numeric" maxlength="19" required>
                    <div class="row gap2">
                        <select class="input" name="expiry_month" required>
                            <option value="">Month</option>' . implode("", array_map(fn($m) => '<option value="' . $m . '">' . str_pad((string)$m, 2, "0", STR_PAD_LEFT) . '</option>', range(1, 12))) . '
                        </select>
                        <select class="input" name="expiry_year" required>
                            <option value="">Year</option>' . implode("", array_map(fn($y) => '<option value="' . $y . '">' . $y . '</option>', range((int)date("Y"), (int)date("Y") + 10))) . '
                        </select>
                        <input class="input" name="cvv" placeholder="CVV" inputmode="numeric" maxlength="4" required>
                    </div>
                    <button class="btn btn-primary" style="justify-content:center" type="submit">' . icon("plus", 15) . ' Save card</button>
                </form>
            </div>';
        }
    } elseif ($tab === "profile") {
        $body = '<div class="card">
            <div class="row gap4 mb3">' . avatar_html($user["profile_pic"], $user["name"] . " " . $user["surname"], 64, true) . '
                <div><div class="disp" style="font-size:17px">' . h($user["name"] . " " . $user["surname"]) . '</div><div style="font-size:12px;color:var(--ink-soft)">Client</div></div>
            </div>
            ' . route_divider() . '
            <div class="col gap3" style="font-size:14px">
                <div class="row gap2">' . icon("phone", 14, "var(--ink-soft)") . ' ' . h($user["phone"]) . '</div>
                <div class="row gap2">' . icon("key", 14, "var(--ink-soft)") . ' Member since ' . date("d M Y", strtotime($user["created_at"])) . '</div>
            </div>
        </div>' . email_form_html($user);
    }

    return '<div class="wrap">' . $tabBar . '<div id="liveArea" data-live="' . ($tab === "history" ? "1" : "0") . '">' . $body . '</div></div>';
}

/* =========================================================
   DRIVER HOME
========================================================= */

function page_driver_home(array $user, string $tab): string
{
    $tabBar = "";

    $body = "";

    if ($tab === "active") {
        $stmt = db()->prepare(
            "SELECT r.*, c.name AS client_name, c.surname AS client_surname FROM requests r
             JOIN users c ON c.id = r.client_id WHERE r.status = 'accepted' AND r.driver_id = ? ORDER BY r.accepted_at DESC"
        );
        $stmt->execute([$user["id"]]);
        $active = $stmt->fetchAll();

        if (!$active) {
            $body = '<div class="card" style="text-align:center;color:var(--ink-soft);font-size:13.5px">You have no deliveries in progress.</div>';
        } else {
            $body = '<div class="col gap3">';
            foreach ($active as $r) {
                $distHtml = $r["distance_km"] !== null ? number_format((float)$r["distance_km"], 2) . " km · " : "";
                $payoutHtml = $r["payout"] !== null ? '<span class="mono" style="color:var(--route);font-weight:700">' . fmt_r($r["payout"]) . ' your cut</span>' : "";

                $pickupPhotoHtml = $r["pickup_photo"]
                    ? '<div class="row gap2"><img src="' . h($r["pickup_photo"]) . '" style="width:40px;height:40px;border-radius:8px;object-fit:cover"><span style="font-size:11.5px;color:var(--route)">' . icon("check", 12, "var(--route)") . ' Pickup photo saved</span></div>'
                    : '<form method="post" action="actions.php" enctype="multipart/form-data" class="row gap2">' . csrf_field() . '<input type="hidden" name="action" value="upload_pickup_photo"><input type="hidden" name="request_id" value="' . $r["id"] . '">
                        <label class="btn btn-ghost" style="font-size:11.5px;padding:6px 10px;cursor:pointer">' . icon("camera", 13) . ' Add pickup photo
                            <input type="file" name="photo" accept="image/*" capture="environment" style="display:none" onchange="this.form.submit()">
                        </label>
                       </form>';

                $deliveryPhotoHtml = $r["delivery_photo"]
                    ? '<div class="row gap2"><img src="' . h($r["delivery_photo"]) . '" style="width:40px;height:40px;border-radius:8px;object-fit:cover"><span style="font-size:11.5px;color:var(--route)">' . icon("check", 12, "var(--route)") . ' Delivery photo saved</span></div>'
                    : '<form method="post" action="actions.php" enctype="multipart/form-data" class="row gap2">' . csrf_field() . '<input type="hidden" name="action" value="upload_delivery_photo"><input type="hidden" name="request_id" value="' . $r["id"] . '">
                        <label class="btn btn-ghost" style="font-size:11.5px;padding:6px 10px;cursor:pointer">' . icon("camera", 13) . ' Add delivery photo
                            <input type="file" name="photo" accept="image/*" capture="environment" style="display:none" onchange="this.form.submit()">
                        </label>
                       </form>';

                $deliverBtn = $r["delivery_photo"]
                    ? '<form method="post" action="actions.php">' . csrf_field() . '
                        <input type="hidden" name="action" value="deliver_request">
                        <input type="hidden" name="request_id" value="' . $r["id"] . '">
                        <button class="btn btn-route" type="submit">' . icon("check", 14) . ' Mark as delivered</button>
                      </form>'
                    : '<span class="btn btn-ghost" style="opacity:.5;cursor:not-allowed" title="Add a delivery photo first">' . icon("check", 14) . ' Mark as delivered</span>';

                $body .= '<div class="card card-sm">
                    <div class="row between start">
                        <div><div class="disp" style="font-size:15px">' . h($r["description"]) . '</div>
                        <div style="font-size:12px;color:var(--ink-soft)">Client: ' . h($r["client_name"] . " " . $r["client_surname"]) . ' · ' . $distHtml . fmt_date($r["accepted_at"]) . '</div></div>
                        ' . status_badge($r["status"]) . '
                    </div>
                    <div class="row between mt2 route-row" style="font-size:12px;color:var(--ink-soft)">
                        <div class="row gap4 route-addrs">
                            <span class="row gap2">' . icon("pin", 13, "var(--accent)") . ' ' . h($r["pickup_addr"] ?: "Pinned") . '</span>
                            <span class="row gap2">' . icon("pin", 13, "var(--route)") . ' ' . h($r["dropoff_addr"] ?: "Pinned") . '</span>
                        </div>
                        ' . $payoutHtml . '
                    </div>
                    <div class="row gap3 mt3 flexwrap">' . $pickupPhotoHtml . $deliveryPhotoHtml . '</div>
                    <div class="row gap2 mt3">
                        <a class="btn btn-ghost" href="?view=chat&request=' . $r["id"] . '">' . icon("message", 14) . ' Message client</a>
                        ' . $deliverBtn . '
                    </div>
                    ' . flag_form_html((int)$r["id"], "client", "active", (bool)$r["flagged"]) . '
                </div>';
            }
            $body .= '</div>';
        }
    } elseif ($tab === "history") {
        $stmt = db()->prepare(
            "SELECT r.*, c.name AS client_name, c.surname AS client_surname FROM requests r
             JOIN users c ON c.id = r.client_id WHERE r.status = 'delivered' AND r.driver_id = ? ORDER BY r.delivered_at DESC"
        );
        $stmt->execute([$user["id"]]);
        $history = $stmt->fetchAll();

        if (!$history) {
            $body = '<div class="card" style="text-align:center;color:var(--ink-soft);font-size:13.5px">Completed deliveries will be listed here.</div>';
        } else {
            $body = '<div class="col gap3">';
            foreach ($history as $r) {
                $distHtml = $r["distance_km"] !== null ? number_format((float)$r["distance_km"], 2) . " km · " : "";
                $photosHtml = '<div class="row gap2 mt2">';
                if ($r["pickup_photo"]) $photosHtml .= '<img src="' . h($r["pickup_photo"]) . '" title="Pickup photo" style="width:32px;height:32px;border-radius:7px;object-fit:cover">';
                if ($r["delivery_photo"]) $photosHtml .= '<img src="' . h($r["delivery_photo"]) . '" title="Delivery photo" style="width:32px;height:32px;border-radius:7px;object-fit:cover">';
                $photosHtml .= '</div>';
                $body .= '<div class="card card-sm">
                    <div class="row between">
                        <div><div style="font-weight:600;font-size:14px">' . h($r["description"]) . '</div>
                        <div style="font-size:12px;color:var(--ink-soft)">' . h($r["client_name"] . " " . $r["client_surname"]) . ' · ' . $distHtml . fmt_date($r["delivered_at"]) . '</div></div>
                        <div class="mono" style="color:var(--route);font-weight:700">' . fmt_r($r["payout"]) . '</div>
                    </div>
                    ' . $photosHtml . '
                </div>';
            }
            $body .= '</div>';
        }
    } elseif ($tab === "earnings") {
        $config = get_config();
        $baseSalary = (float)($user["base_salary"] ?? $config["driver_base_salary"]);
        [$mStart, $mEnd] = month_bounds();
        $thisMonth = driver_period_stats((int)$user["id"], $mStart, $mEnd);

        $lifetimeStmt = db()->prepare("SELECT COUNT(*) AS jobs, COALESCE(SUM(payout),0) AS commission FROM requests WHERE status='delivered' AND driver_id = ?");
        $lifetimeStmt->execute([$user["id"]]);
        $lifetime = $lifetimeStmt->fetch();

        $payHistStmt = db()->prepare("SELECT * FROM salary_payments WHERE driver_id = ? ORDER BY period_start DESC LIMIT 24");
        $payHistStmt->execute([$user["id"]]);
        $payHist = $payHistStmt->fetchAll();

        $payHtml = '<div style="color:var(--ink-soft);font-size:13px">No salary payments recorded yet.</div>';
        if ($payHist) {
            $payHtml = '<div class="col gap2">';
            foreach ($payHist as $p) {
                $payHtml .= '<div class="row between" style="padding:8px 0;border-bottom:1px solid var(--border)">
                    <div style="font-size:13px">' . date("M Y", strtotime($p["period_start"])) . ' <span style="color:var(--ink-soft)">· ' . $p["deliveries_count"] . ' deliveries</span></div>
                    <div class="mono" style="font-weight:700;color:var(--route)">' . fmt_r($p["total_amount"]) . '</div>
                </div>';
            }
            $payHtml .= '</div>';
        }

        $body = '<div class="col gap4">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--route);display:flex;align-items:center;justify-content:center">' . icon("dollar", 19, "#fff") . '</div>
                    <div><div class="disp" style="font-size:20px">' . fmt_r($baseSalary) . '</div><div style="font-size:12px;color:var(--ink-soft)">Monthly base salary</div></div></div>
                <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center">' . icon("chart", 19, "#fff") . '</div>
                    <div><div class="disp" style="font-size:20px">' . $thisMonth["jobs"] . '</div><div style="font-size:12px;color:var(--ink-soft)">Deliveries this month</div></div></div>
            </div>
            <div class="card card-sm">
                <div class="disp mb2" style="font-size:14px">This month so far</div>
                <div class="row between" style="font-size:13.5px"><span>Commission earned (10% per delivery)</span><span class="mono" style="font-weight:700;color:var(--route)">' . fmt_r($thisMonth["commission"]) . '</span></div>
                <div class="row between mt2" style="font-size:13.5px"><span>Base salary</span><span class="mono">' . fmt_r($baseSalary) . '</span></div>
                <div class="row between mt2" style="font-size:14px;font-weight:700;border-top:1px solid var(--border);padding-top:8px"><span>Estimated total due</span><span class="mono" style="color:var(--route)">' . fmt_r($thisMonth["commission"] + $baseSalary) . '</span></div>
                <p style="font-size:11.5px;color:var(--ink-soft);margin-top:8px">Paid out by an admin at month end — see payment history below.</p>
            </div>
            <div class="card card-sm">
                <div class="disp mb2" style="font-size:14px">Lifetime</div>
                <div class="row between" style="font-size:13.5px"><span>Deliveries completed</span><span class="mono">' . (int)$lifetime["jobs"] . '</span></div>
                <div class="row between mt2" style="font-size:13.5px"><span>Total commission earned</span><span class="mono" style="font-weight:700;color:var(--route)">' . fmt_r($lifetime["commission"]) . '</span></div>
            </div>
            <div class="card card-sm"><div class="disp mb3" style="font-size:14px">Salary payment history</div>' . $payHtml . '</div>
        </div>';
    } elseif ($tab === "performance") {
        $countStmt = db()->prepare("SELECT COUNT(*) FROM requests WHERE status = 'delivered' AND driver_id = ?");
        $countStmt->execute([$user["id"]]);
        $count = (int)$countStmt->fetchColumn();

        $earnStmt = db()->prepare("SELECT COALESCE(SUM(payout),0), COALESCE(SUM(distance_km),0) FROM requests WHERE status = 'delivered' AND driver_id = ?");
        $earnStmt->execute([$user["id"]]);
        [$total, $totalKm] = $earnStmt->fetch(PDO::FETCH_NUM);
        $total = (float)$total; $totalKm = (float)$totalKm;

        $body = '<div class="col gap4">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--route);display:flex;align-items:center;justify-content:center">' . icon("package", 19, "#fff") . '</div>
                    <div><div class="disp" style="font-size:20px">' . $count . '</div><div style="font-size:12px;color:var(--ink-soft)">Deliveries completed</div></div></div>
                <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center">' . icon("chart", 19, "#fff") . '</div>
                    <div><div class="disp" style="font-size:20px">' . fmt_r($total) . '</div><div style="font-size:12px;color:var(--ink-soft)">Total commission earned</div></div></div>
            </div>
            <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--ink-soft);display:flex;align-items:center;justify-content:center">' . icon("nav", 19, "#fff") . '</div>
                <div><div class="disp" style="font-size:20px">' . number_format($totalKm, 1) . ' km</div><div style="font-size:12px;color:var(--ink-soft)">Total distance driven</div></div></div>
        </div>';
    } elseif ($tab === "documents") {
        $doc = driver_document_status((int)$user["id"]);

        $statusBadge = '<span class="badge" style="background:var(--surface-alt);color:var(--ink-soft)">Not submitted</span>';
        if ($doc) {
            $map = [
                "pending" => ["var(--accent)", "var(--accent-ink)", "Pending review"],
                "verified" => ["var(--route)", "#fff", "Verified"],
                "rejected" => ["var(--alert)", "#fff", "Rejected — please resubmit"],
            ];
            [$bg, $fg, $label] = $map[$doc["status"]];
            $statusBadge = '<span class="badge" style="background:' . $bg . ';color:' . $fg . '">' . $label . '</span>';
        }

        $thumbs = $doc
            ? '<div class="row gap3 mt3 mb3">'
                . doc_tile($doc["id_document"], "ID copy")
                . doc_tile($doc["license_document"], "License")
                . doc_tile($doc["car_reg_document"], "ID photo")
                . doc_tile($doc["police_clearance_document"] ?? null, "Police clearance")
                . doc_tile($doc["bank_proof_document"] ?? null, "Bank proof")
                . '</div>'
            : "";

        $intro = !$doc
            ? "Upload PDF files of your ID copy, driving license, police clearance certificate and proof of bank account, plus a photo of your ID. An admin needs to verify these before you can be assigned any deliveries."
            : ($doc["status"] === "rejected"
                ? "Your last submission was rejected. Please upload clear, valid files again."
                : "You can resubmit at any time — a new upload replaces the one below and goes back to pending review.");

        $body = '<div class="card">
            <div class="row between mb2"><span class="disp" style="font-size:15px">Verification documents</span>' . $statusBadge . '</div>
            <p style="font-size:13px;color:var(--ink-soft)">' . h($intro) . '</p>
            ' . $thumbs . '
            <form method="post" action="actions.php" enctype="multipart/form-data" class="col gap3 mt2">
                ' . csrf_field() . '<input type="hidden" name="action" value="upload_driver_documents">
                <div>
                    <div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">ID copy</div>
                    <input class="input" type="file" name="id_document" accept="application/pdf,.pdf" required>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">Driving license</div>
                    <input class="input" type="file" name="license_document" accept="application/pdf,.pdf" required>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">ID photo (image)</div>
                    <input class="input" type="file" name="car_reg_document" accept="image/*" required>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">Police clearance certificate</div>
                    <input class="input" type="file" name="police_clearance_document" accept="application/pdf,.pdf" required>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">Proof of bank account (PDF)</div>
                    <input class="input" type="file" name="bank_proof_document" accept="application/pdf,.pdf" required>
                </div>
                <button class="btn btn-primary" style="justify-content:center" type="submit">' . icon("shield", 15) . ' Submit for review</button>
            </form>
        </div>';
    } elseif ($tab === "profile") {
        $body = '<div class="card">
            <div class="row gap4 mb3">' . avatar_html($user["profile_pic"], $user["name"] . " " . $user["surname"], 64, true) . '
                <div><div class="disp" style="font-size:17px">' . h($user["name"] . " " . $user["surname"]) . '</div><div style="font-size:12px;color:var(--ink-soft)">Driver</div></div>
            </div>
            ' . route_divider() . '
            <div class="col gap3" style="font-size:14px">
                <div class="row gap2">' . icon("phone", 14, "var(--ink-soft)") . ' ' . h($user["phone"]) . '</div>
                <div class="row gap2">' . icon("car", 14, "var(--ink-soft)") . ' ' . h($user["car_model"]) . ' · <span class="mono">' . h($user["car_reg"]) . '</span></div>
                <div class="row gap2">' . icon("dollar", 14, "var(--ink-soft)") . ' Base salary: ' . fmt_r($user["base_salary"] ?? get_config()["driver_base_salary"]) . '/month</div>
            </div>
        </div>' . email_form_html($user);
    }

    return '<div class="wrap">' . $tabBar . '<div id="liveArea" data-live="' . ($tab === "active" ? "1" : "0") . '">' . $body . '</div></div>';
}

/* =========================================================
   ADMIN HOME
========================================================= */

function page_admin_home(array $user, string $tab): string
{
    $tabBar = "";

    $isSuper = is_super_admin($user);
    $body = "";

    if ($tab === "dashboard") {
        $drivers = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='driver'")->fetchColumn();
        $clients = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
        $unassigned = (int)db()->query("SELECT COUNT(*) FROM requests WHERE status='pending'")->fetchColumn();
        $pendingDocs = (int)db()->query("SELECT COUNT(*) FROM driver_documents WHERE status='pending'")->fetchColumn();
        $active = (int)db()->query("SELECT COUNT(*) FROM requests WHERE status='accepted'")->fetchColumn();
        $todayCount = (int)db()->query("SELECT COUNT(*) FROM requests WHERE status='delivered' AND DATE(delivered_at)=CURDATE()")->fetchColumn();
        $weekCount = (int)db()->query("SELECT COUNT(*) FROM requests WHERE status='delivered' AND delivered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $revenue = (float)db()->query("SELECT COALESCE(SUM(fare),0) FROM requests WHERE paid=1 AND refunded=0")->fetchColumn();
        $refundsDue = (int)db()->query("SELECT COUNT(*) FROM requests WHERE status='cancelled' AND paid=1 AND refunded=0")->fetchColumn();
        $companyEarnings = (float)db()->query("SELECT COALESCE(SUM(commission),0) FROM requests WHERE status='delivered'")->fetchColumn();
        $config = get_config();

        $cards = [
            ["truck", "Drivers", $drivers, "var(--route)"],
            ["package", "Clients", $clients, "var(--accent)"],
            ["nav", "Deliveries in progress", $active, "var(--route)"],
            ["check", "Delivered — today / week", "$todayCount / $weekCount", "var(--accent)"],
            ["dollar", "Money paid into company account", fmt_r($revenue), "var(--route)"],
            ["shield", "Company earnings (after 10% driver cut)", fmt_r($companyEarnings), "var(--accent)"],
        ];
        $body = '<div class="col gap4">';
        if ($unassigned > 0) {
            $body .= '<div class="card card-sm" style="border-color:var(--accent)"><b style="color:var(--accent-ink);background:var(--accent);padding:2px 8px;border-radius:6px">' . $unassigned . '</b>
                <span style="margin-left:8px">paid ' . ($unassigned === 1 ? "delivery is" : "deliveries are") . ' waiting for a driver to be assigned.</span>
                <a class="btn btn-primary" style="margin-left:12px;padding:6px 12px;font-size:12px" href="?tab=assign">' . icon("truck", 13) . ' Assign now</a></div>';
        }
		if ($refundsDue > 0) {
            $body .= '<div class="card card-sm" style="border-color:var(--alert)"><b style="color:#fff;background:var(--alert);padding:2px 8px;border-radius:6px">' . $refundsDue . '</b>
                <span style="margin-left:8px">cancelled ' . ($refundsDue === 1 ? "request needs" : "requests need") . ' a refund to the client.</span>
                <a class="btn btn-danger" style="margin-left:12px;padding:6px 12px;font-size:12px" href="?tab=deliveries">' . icon("dollar", 13) . ' Refund now</a></div>';
        }
        if ($pendingDocs > 0) {
            $body .= '<div class="card card-sm" style="border-color:var(--route)"><b style="color:#fff;background:var(--route);padding:2px 8px;border-radius:6px">' . $pendingDocs . '</b>
                <span style="margin-left:8px">driver ' . ($pendingDocs === 1 ? "submission is" : "submissions are") . ' waiting for document verification.</span>
                <a class="btn btn-route" style="margin-left:12px;padding:6px 12px;font-size:12px" href="?tab=verification">' . icon("shield", 13) . ' Review now</a></div>';
        }
        $body .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
        foreach ($cards as [$ic, $label, $value, $accent]) {
            $body .= '<div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:' . $accent . ';display:flex;align-items:center;justify-content:center">' . icon($ic, 19, "#fff") . '</div>
                <div><div class="disp" style="font-size:20px">' . h((string)$value) . '</div><div style="font-size:12px;color:var(--ink-soft)">' . h($label) . '</div></div></div>';
        }
        $body .= '</div>';
        if ((int)$config["maintenance_mode"] === 1) {
            $body .= '<div class="card card-sm" style="border-color:var(--alert)"><b style="color:var(--alert)">Maintenance mode is ON</b> — clients can\'t post requests.</div>';
        }
        $body .= '</div>';

    } elseif ($tab === "assign") {
        $stmt = db()->query(
            "SELECT r.*, c.name AS client_name, c.surname AS client_surname FROM requests r
             JOIN users c ON c.id = r.client_id WHERE r.status = 'pending' ORDER BY r.created_at ASC"
        );
        $pending = $stmt->fetchAll();
        $drivers = db()->query("SELECT id, name, surname, car_model, car_reg FROM users WHERE role='driver' AND suspended=0 AND verified=1 ORDER BY name")->fetchAll();

        if (!$pending) {
            $body = '<div class="card" style="text-align:center;color:var(--ink-soft);font-size:13.5px">Nothing to assign — every paid delivery currently has a driver.</div>';
        } else {
            $body = '<div class="col gap3">';
            foreach ($pending as $r) {
                $distHtml = $r["distance_km"] !== null ? number_format((float)$r["distance_km"], 2) . ' km · ' : '';
                $fareHtml = $r["fare"] !== null ? '<span class="mono" style="color:var(--route);font-weight:700">' . fmt_r($r["fare"]) . '</span>' : '';
                $options = '<option value="">Choose a driver…</option>';
                foreach ($drivers as $d) {
                    $options .= '<option value="' . $d["id"] . '">' . h($d["name"] . " " . $d["surname"]) . ' — ' . h($d["car_model"] ?: "no vehicle on file") . '</option>';
                }
                $body .= '<div class="card card-sm">
                    <div class="row between start">
                        <div><div class="disp" style="font-size:15px">' . h($r["description"]) . '</div>
                        <div style="font-size:12px;color:var(--ink-soft)">' . h($r["client_name"] . " " . $r["client_surname"]) . ' · ' . $distHtml . fmt_date($r["created_at"]) . '</div></div>
                        ' . status_badge($r["status"]) . '
                    </div>
                    <div class="row between mt2 route-row" style="font-size:12px;color:var(--ink-soft)">
                        <div class="row gap4 route-addrs">
                            <span class="row gap2">' . icon("pin", 13, "var(--accent)") . ' ' . h($r["pickup_addr"] ?: "Pinned") . '</span>
                            <span class="row gap2">' . icon("pin", 13, "var(--route)") . ' ' . h($r["dropoff_addr"] ?: "Pinned") . '</span>
                        </div>
                        ' . $fareHtml . '
                    </div>
                    <form method="post" action="actions.php" class="row gap2 mt3">' . csrf_field() . '<input type="hidden" name="action" value="assign_driver"><input type="hidden" name="request_id" value="' . $r["id"] . '"><input type="hidden" name="back" value="assign">
                        <select name="driver_id" class="input" required>' . $options . '</select>
                        <button class="btn btn-primary" type="submit">' . icon("truck", 14) . ' Assign</button>
                    </form>
                </div>';
            }
            $body .= '</div>';
        }
        if (!$drivers) {
            $body = '<div class="card" style="border-color:var(--accent)">No verified drivers yet. Add a driver from the <a href="?tab=users">Users</a> tab, or review pending submissions under <a href="?tab=verification">Verification</a>, before you can assign deliveries.</div>' . $body;
        }

    } elseif ($tab === "verification") {
        $submissions = db()->query(
            "SELECT dd.*, u.name, u.surname, u.phone FROM driver_documents dd
             JOIN users u ON u.id = dd.driver_id ORDER BY FIELD(dd.status,'pending','rejected','verified'), dd.uploaded_at DESC"
        )->fetchAll();

        if (!$submissions) {
            $body = '<div class="card" style="text-align:center;color:var(--ink-soft);font-size:13.5px">No drivers have submitted verification documents yet.</div>';
        } else {
            $body = '<div class="col gap3">';
            foreach ($submissions as $d) {
                $map = [
                    "pending" => ["var(--accent)", "var(--accent-ink)", "Pending review"],
                    "verified" => ["var(--route)", "#fff", "Verified"],
                    "rejected" => ["var(--alert)", "#fff", "Rejected"],
                ];
                [$bg, $fg, $label] = $map[$d["status"]];
                $badge = '<span class="badge" style="background:' . $bg . ';color:' . $fg . '">' . $label . '</span>';

                $actions = $d["status"] !== "verified"
                    ? '<form method="post" action="actions.php" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="review_driver_documents"><input type="hidden" name="driver_id" value="' . $d["driver_id"] . '"><input type="hidden" name="decision" value="verified">
                        <button class="btn btn-route" style="padding:6px 10px;font-size:12px" type="submit">' . icon("check", 12) . ' Verify</button></form>' : '';
                $rejectBtn = $d["status"] !== "rejected"
                    ? '<form method="post" action="actions.php" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="review_driver_documents"><input type="hidden" name="driver_id" value="' . $d["driver_id"] . '"><input type="hidden" name="decision" value="rejected">
                        <button class="btn btn-danger" style="padding:6px 10px;font-size:12px" type="submit">Reject</button></form>' : '';

                $body .= '<div class="card card-sm">
                    <div class="row between start">
                        <div><div class="disp" style="font-size:15px">' . h($d["name"] . " " . $d["surname"]) . '</div>
                        <div style="font-size:12px;color:var(--ink-soft)">' . h($d["phone"]) . ' · Submitted ' . fmt_date($d["uploaded_at"]) . '</div></div>
                        ' . $badge . '
                    </div>
                    <div class="row gap3 mt3">' . doc_tile($d["id_document"], "ID copy", 72) . doc_tile($d["license_document"], "License", 72) . doc_tile($d["car_reg_document"], "ID photo", 72) . doc_tile($d["police_clearance_document"] ?? null, "Police clearance", 72) . doc_tile($d["bank_proof_document"] ?? null, "Bank proof", 72) . '</div>
                    <div class="row gap2 mt3">' . $actions . $rejectBtn . '</div>
                </div>';
            }
            $body .= '</div>';
        }

    } elseif ($tab === "adddriver") {
        $config = get_config();
        $addDriverForm = '<div class="card card-sm">
            <div class="disp mb3" style="font-size:14px">' . icon("plus", 15) . ' Add driver staff</div>
            <form method="post" action="actions.php" class="col gap2">' . csrf_field() . '<input type="hidden" name="action" value="add_driver">
                                <div class="row gap2">
                    <input class="input" name="name" autocomplete="off" placeholder="Name" required>
                    <input class="input" name="surname" placeholder="Surname" required>
                </div>
                <div class="row gap2">
                    <input class="input" name="email" type="email" autocomplete="off" placeholder="Email address of the driver" maxlength="190" required>
                </div>
                <div class="row gap2">
                    <input class="input" name="id_number" placeholder="ID number (13 digits)" inputmode="numeric" pattern="[0-9]{13}" maxlength="13" title="The 13-digit ID number" required>
                    <input class="input" name="phone" placeholder="Phone number (e.g. 0821234567)" pattern="0[0-9]{9}" maxlength="10" title="South African number: 10 digits, starting with 0" required>
                </div>
                <input class="input" name="address" placeholder="Residential address (street, suburb, city)" maxlength="255" required>
                <div class="row gap2">
                    <input class="input" name="password" type="password" autocomplete="new-password" placeholder="Temporary password" required>
                </div>
                <p style="font-size:11px;color:var(--ink-soft);margin-top:-6px">' . h(PASSWORD_HINT) . '</p>

                <div style="font-size:12px;font-weight:700;color:var(--ink-soft);margin-top:6px">Finances</div>
                <div class="row gap2">
                    <input class="input" name="bank_name" placeholder="Name of bank (e.g. FNB)" maxlength="60" required>
                    <input class="input" name="account_number" placeholder="Account number" inputmode="numeric" pattern="[0-9]{6,16}" maxlength="16" title="Bank account number: 6 to 16 digits, numbers only" required>
                </div>
                <div class="row gap2">
                    <div class="grow"><div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">Monthly base salary (R)</div>
                        <input class="input" name="base_salary" type="number" step="0.01" value="' . h((string)$config["driver_base_salary"]) . '"></div>
                </div>

                <div style="font-size:12px;font-weight:700;color:var(--ink-soft);margin-top:6px">Assign Vehicle</div>
                <div class="row gap2">
                    <input class="input" name="car_model" placeholder="Car model">
                    <input class="input" name="car_reg" placeholder="Car registration no.">
                </div>
                <button class="btn btn-primary" style="justify-content:center" type="submit">' . icon("truck", 15) . ' Create driver account</button>
            </form>
        </div>';

        $body = '<div class="col gap3">' . $addDriverForm . '</div>';

    } elseif ($tab === "users") {
        $q = trim($_GET["q"] ?? "");
        $roleFilter = $_GET["role"] ?? "all";

        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];
        if ($roleFilter === "suspended") {
            $sql .= " AND suspended = 1";
        } elseif ($roleFilter !== "all") {
            $sql .= " AND role = ?";
            $params[] = $roleFilter;
        }
        if ($q !== "") {
            $sql .= " AND (name LIKE ? OR surname LIKE ? OR phone LIKE ?)";
            $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $accounts = $stmt->fetchAll();

        $filters = [["all", "All"], ["driver", "Drivers"], ["client", "Clients"], ["suspended", "Suspended"]];
        $filterBar = '<div class="row gap2">';
        foreach ($filters as [$id, $label]) {
            $active = $roleFilter === $id ? "active" : "";
            $filterBar .= "<a class=\"tab $active\" href=\"?tab=users&role=$id" . ($q !== "" ? "&q=" . urlencode($q) : "") . "\">$label</a>";
        }
        $filterBar .= '</div>';

        $config = get_config();
        

        $body = '<div class="col gap3">
            <form method="get"><input type="hidden" name="tab" value="users"><input type="hidden" name="role" value="' . h($roleFilter) . '">
                <input class="input" name="q" placeholder="Search by name or phone…" value="' . h($q) . '">
            </form>
            ' . $filterBar . '
            ' . render_admin_user_rows($accounts) . '
        </div>';

    } elseif ($tab === "deliveries") {
        $stmt = db()->query(
            "SELECT r.*, c.name AS client_name, c.surname AS client_surname, d.name AS driver_name, d.surname AS driver_surname
             FROM requests r JOIN users c ON c.id = r.client_id LEFT JOIN users d ON d.id = r.driver_id
             ORDER BY r.created_at DESC"
        );
        $requests = $stmt->fetchAll();
        $drivers = db()->query("SELECT id, name, surname FROM users WHERE role='driver' AND suspended=0 AND verified=1")->fetchAll();

        if (!$requests) {
            $body = '<div class="card" style="text-align:center;color:var(--ink-soft);font-size:13.5px">No delivery requests on the platform yet.</div>';
        } else {
            $body = '<div class="col gap3">';
            foreach ($requests as $r) {
                $driverName = $r["driver_name"] ? h($r["driver_name"] . " " . $r["driver_surname"]) : "unassigned";
                $flagIcon = $r["flagged"] ? icon("flag", 13, "var(--alert)") : "";
                $fareHtml = $r["fare"] !== null ? '<span class="mono" style="color:var(--route);font-weight:700">' . fmt_r($r["fare"]) . '</span>' : "";
                $distHtml = $r["distance_km"] !== null ? '<span class="row gap2">' . icon("nav", 13, "var(--ink-soft)") . ' ' . h(number_format((float)$r["distance_km"], 2)) . ' km</span>' : "";
                $chatBtn = $r["driver_id"] ? '<a class="btn btn-ghost" style="padding:6px 10px;font-size:12px" href="?view=chat&request=' . $r["id"] . '">' . icon("message", 12) . ' View chat</a>' : "";
                $cancelBtn = "";
                if (!in_array($r["status"], ["delivered", "cancelled"], true)) {
                    $cancelBtn = '<form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="cancel_request"><input type="hidden" name="request_id" value="' . $r["id"] . '">
                        <button class="btn btn-danger" style="padding:6px 10px;font-size:12px" type="submit">Cancel</button></form>';
                }
                $refundBtn = "";
                $refundDue = ($r["paid"] && !$r["refunded"] && in_array($r["status"], ["cancelled", "delivered"], true));
                if ($refundDue) {
                    $refundBtn = '<form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="refund_request"><input type="hidden" name="request_id" value="' . $r["id"] . '"><input type="hidden" name="back" value="deliveries">
                        <button class="btn btn-primary" style="padding:6px 10px;font-size:12px" type="submit">' . icon("dollar", 12) . ' ' . ($r["status"] === "cancelled" ? "Refund client " . fmt_r($r["fare"]) : "Mark refunded") . '</button></form>';
                }
                $refundedBadge = $r["refunded"]
                    ? '<span class="badge" style="background:var(--alert);color:#fff">Refunded</span>'
                    : (($r["status"] === "cancelled" && $r["paid"]) ? '<span class="badge" style="background:var(--accent);color:var(--accent-ink)">Refund due</span>' : "");
                $reassignForm = "";
                if (!in_array($r["status"], ["delivered", "cancelled"], true) && $drivers) {
                    $options = '<option value="">' . ($r["driver_id"] ? "Reassign to…" : "Assign to…") . '</option>';
                    foreach ($drivers as $d) {
                        $options .= '<option value="' . $d["id"] . '">' . h($d["name"] . " " . $d["surname"]) . '</option>';
                    }
                    $reassignForm = '<form method="post" action="actions.php" class="row gap2">' . csrf_field() . '<input type="hidden" name="action" value="reassign_request"><input type="hidden" name="request_id" value="' . $r["id"] . '">
                        <select name="driver_id" class="input" style="width:auto;padding:6px 8px;font-size:12px" required>' . $options . '</select>
                        <button class="btn btn-route" style="padding:6px 10px;font-size:12px" type="submit">' . ($r["driver_id"] ? "Reassign" : "Assign") . '</button></form>';
                }

                $photosHtml = "";
                if ($r["pickup_photo"] || $r["delivery_photo"]) {
                    $photosHtml = '<div class="row gap2 mt2">';
                    if ($r["pickup_photo"]) $photosHtml .= '<a href="' . h($r["pickup_photo"]) . '" target="_blank"><img src="' . h($r["pickup_photo"]) . '" title="Pickup photo" style="width:32px;height:32px;border-radius:7px;object-fit:cover"></a>';
                    if ($r["delivery_photo"]) $photosHtml .= '<a href="' . h($r["delivery_photo"]) . '" target="_blank"><img src="' . h($r["delivery_photo"]) . '" title="Delivery photo" style="width:32px;height:32px;border-radius:7px;object-fit:cover"></a>';
                    $photosHtml .= '</div>';
                }
                $body .= '<div class="card card-sm">
                    <div class="row between start">
                        <div><div class="disp" style="font-size:15px">' . h($r["description"]) . " $flagIcon" . '</div>
                        <div style="font-size:12px;color:var(--ink-soft)">' . h($r["client_name"] . " " . $r["client_surname"]) . " → $driverName · " . fmt_date($r["created_at"]) . '</div></div>
                        ' . status_badge($r["status"]) . $paidBadge . $refundBadge . '</div>
                    <div class="row gap4 mt2" style="font-size:12px;color:var(--ink-soft)">
                        <span class="row gap2">' . icon("pin", 13, "var(--accent)") . ' ' . h($r["pickup_addr"] ?: "Pinned") . '</span>
                        <span class="row gap2">' . icon("pin", 13, "var(--route)") . ' ' . h($r["dropoff_addr"] ?: "Pinned") . '</span>
                        ' . $distHtml . $fareHtml . '
                    </div>
                    ' . $photosHtml . '
                    <div class="row gap2 mt3" style="flex-wrap:wrap">' . $chatBtn . $cancelBtn . $refundBtn . $refundedBadge . $reassignForm . '</div>
                </div>';
            }
            $body .= '</div>';
        }

    } elseif ($tab === "finance") {
        $config = get_config();
        $totalPaidIn = (float)db()->query("SELECT COALESCE(SUM(fare),0) FROM requests WHERE paid=1 AND refunded=0")->fetchColumn();
        $driverPayoutsDelivered = (float)db()->query("SELECT COALESCE(SUM(payout),0) FROM requests WHERE status='delivered'")->fetchColumn();
        $companyShare = (float)db()->query("SELECT COALESCE(SUM(commission),0) FROM requests WHERE status='delivered'")->fetchColumn();
        $salariesPaidOut = (float)db()->query("SELECT COALESCE(SUM(total_amount),0) FROM salary_payments")->fetchColumn();
        $totalDeliveries = (int)db()->query("SELECT COUNT(*) FROM requests WHERE status='delivered'")->fetchColumn();
        $totalKm = (float)db()->query("SELECT COALESCE(SUM(distance_km),0) FROM requests WHERE status='delivered'")->fetchColumn();

        $driverRows = db()->query(
            "SELECT u.id, u.name, u.surname, COUNT(r.id) AS jobs, COALESCE(SUM(r.fare),0) AS gross, COALESCE(SUM(r.payout),0) AS commission, COALESCE(SUM(r.distance_km),0) AS km
             FROM users u LEFT JOIN requests r ON r.driver_id = u.id AND r.status = 'delivered'
             WHERE u.role = 'driver' GROUP BY u.id ORDER BY commission DESC"
        )->fetchAll();

        $rowsHtml = "";
        foreach ($driverRows as $d) {
            $rowsHtml .= '<div class="row between" style="padding:8px 0;border-bottom:1px solid var(--border)">
                <div style="font-size:13.5px;font-weight:600">' . h($d["name"] . " " . $d["surname"]) . '</div>
                <div class="row gap3 mono" style="font-size:12px;color:var(--ink-soft)">
                    <span>' . $d["jobs"] . ' jobs</span><span>' . number_format((float)$d["km"], 1) . ' km</span><span>' . fmt_r($d["gross"]) . ' gross</span>
                    <span style="color:var(--route);font-weight:700">' . fmt_r($d["commission"]) . ' commission</span>
                </div></div>';
        }
        if (!$driverRows) {
            $rowsHtml = '<div style="color:var(--ink-soft);font-size:13px">No drivers yet.</div>';
        }

        $body = '<div class="col gap4">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--route);display:flex;align-items:center;justify-content:center">' . icon("dollar", 19, "#fff") . '</div>
                    <div><div class="disp" style="font-size:20px">' . fmt_r($totalPaidIn) . '</div><div style="font-size:12px;color:var(--ink-soft)">Paid into company account by clients</div></div></div>
                <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center">' . icon("shield", 19, "#fff") . '</div>
                    <div><div class="disp" style="font-size:20px">' . fmt_r($companyShare) . '</div><div style="font-size:12px;color:var(--ink-soft)">Company share (fare minus driver\'s ' . h((string)$config["commission_pct"]) . '%)</div></div></div>
                <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--route);display:flex;align-items:center;justify-content:center">' . icon("truck", 19, "#fff") . '</div>
                    <div><div class="disp" style="font-size:20px">' . fmt_r($driverPayoutsDelivered) . '</div><div style="font-size:12px;color:var(--ink-soft)">Driver commission owed/paid (10% per delivery)</div></div></div>
                <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center">' . icon("check", 19, "#fff") . '</div>
                    <div><div class="disp" style="font-size:20px">' . fmt_r($salariesPaidOut) . '</div><div style="font-size:12px;color:var(--ink-soft)">Total salary runs paid out</div></div></div>
            </div>
            <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--ink-soft);display:flex;align-items:center;justify-content:center">' . icon("nav", 19, "#fff") . '</div>
                <div><div class="disp" style="font-size:20px">' . $totalDeliveries . ' deliveries · ' . number_format($totalKm, 1) . ' km</div><div style="font-size:12px;color:var(--ink-soft)">Overall delivery volume and distance covered</div></div></div>
            <div class="card card-sm"><div class="disp mb3" style="font-size:14px">Performance & earnings by driver</div>' . $rowsHtml . '</div>
        </div>';

    } elseif ($tab === "payroll") {
        $config = get_config();
        $period = $_GET["period"] ?? date("Y-m");
        $periodTs = strtotime($period . "-01");
        if ($periodTs === false) { $periodTs = time(); }
        [$pStart, $pEnd] = month_bounds(date("Y-m-d", $periodTs));

        $drivers = db()->query("SELECT * FROM users WHERE role='driver' ORDER BY name")->fetchAll();

        $prevPeriod = date("Y-m", strtotime($pStart . " -1 month"));
        $nextPeriod = date("Y-m", strtotime($pStart . " +1 month"));
        $periodNav = '<div class="row between mb3">
            <a class="btn btn-ghost" href="?tab=payroll&period=' . $prevPeriod . '">← ' . date("M Y", strtotime($prevPeriod . "-01")) . '</a>
            <span class="disp" style="font-size:15px">' . date("F Y", $periodTs) . ' payroll</span>
            <a class="btn btn-ghost" href="?tab=payroll&period=' . $nextPeriod . '">' . date("M Y", strtotime($nextPeriod . "-01")) . ' →</a>
        </div>';

        $rows = "";
        $periodTotal = 0.0;
        foreach ($drivers as $d) {
            $stats = driver_period_stats((int)$d["id"], $pStart, $pEnd);
            $baseSalary = (float)($d["base_salary"] ?? $config["driver_base_salary"]);
            $total = $stats["commission"] + $baseSalary;
            $periodTotal += $total;
            $alreadyPaid = driver_already_paid_for((int)$d["id"], $pStart, $pEnd);

            $payBtn = $alreadyPaid
                ? '<span class="badge" style="background:var(--route);color:#fff">' . icon("check", 12) . ' Paid this period</span>'
                : '<form method="post" action="actions.php" onsubmit="return confirm(\'Pay ' . h(addslashes($d["name"] . " " . $d["surname"])) . ' ' . fmt_r($total) . ' for ' . date("F Y", $periodTs) . '?\')">' . csrf_field() . '
                    <input type="hidden" name="action" value="pay_driver_salary">
                    <input type="hidden" name="driver_id" value="' . $d["id"] . '">
                    <input type="hidden" name="period_start" value="' . $pStart . '">
                    <input type="hidden" name="period_end" value="' . $pEnd . '">
                    <button class="btn btn-primary" style="padding:6px 12px;font-size:12px" type="submit">' . icon("dollar", 12) . ' Pay ' . fmt_r($total) . '</button>
                  </form>';

            $rows .= '<div class="card card-sm">
                <div class="row between start">
                    <div><div style="font-weight:600;font-size:14px">' . h($d["name"] . " " . $d["surname"]) . '</div>
                    <div style="font-size:12px;color:var(--ink-soft)">' . $stats["jobs"] . ' deliveries · ' . number_format($stats["km"], 1) . ' km this period</div>
                    <div style="font-size:12px;color:var(--ink-soft)">Account number: <b class="mono" style="color:var(--ink)">' . h($d["account_number"] ?: "not entered") . '</b></div></div>
                    ' . $payBtn . '
                </div>
                <div class="row gap4 mt2" style="font-size:12.5px;color:var(--ink-soft)">
                    <span>Base salary: <b class="mono" style="color:var(--ink)">' . fmt_r($baseSalary) . '</b></span>
                    <span>Commission (10%): <b class="mono" style="color:var(--route)">' . fmt_r($stats["commission"]) . '</b></span>
                    <span>Total due: <b class="mono" style="color:var(--ink)">' . fmt_r($total) . '</b></span>
                </div>
            </div>';
        }
        if (!$drivers) {
            $rows = '<div class="card" style="text-align:center;color:var(--ink-soft);font-size:13.5px">No drivers on staff yet — add one from the Users tab.</div>';
        }

        $histRows = db()->query(
            "SELECT sp.*, u.name, u.surname FROM salary_payments sp JOIN users u ON u.id = sp.driver_id ORDER BY sp.created_at DESC LIMIT 40"
        )->fetchAll();
        $histHtml = '<div style="color:var(--ink-soft);font-size:13px">No salary runs recorded yet.</div>';
        if ($histRows) {
            $histHtml = '<div class="col gap2">';
            foreach ($histRows as $p) {
                $histHtml .= '<div class="row between" style="padding:8px 0;border-bottom:1px solid var(--border)">
                    <div style="font-size:13px">' . h($p["name"] . " " . $p["surname"]) . ' <span style="color:var(--ink-soft)">· ' . date("M Y", strtotime($p["period_start"])) . '</span></div>
                    <div class="mono" style="font-weight:700;color:var(--route)">' . fmt_r($p["total_amount"]) . '</div>
                </div>';
            }
            $histHtml .= '</div>';
        }

        $body = '<div class="col gap4">
            ' . $periodNav . '
            <div class="card card-sm row gap3"><div style="width:40px;height:40px;border-radius:10px;background:var(--route);display:flex;align-items:center;justify-content:center">' . icon("dollar", 19, "#fff") . '</div>
                <div><div class="disp" style="font-size:20px">' . fmt_r($periodTotal) . '</div><div style="font-size:12px;color:var(--ink-soft)">Total payroll liability for ' . date("F Y", $periodTs) . '</div></div></div>
            <div class="col gap3">' . $rows . '</div>
            <div class="card card-sm"><div class="disp mb3" style="font-size:14px">Recent salary payments</div>' . $histHtml . '</div>
        </div>';

    } elseif ($tab === "moderation") {
        $flagged = db()->query(
            "SELECT r.*, c.id AS client_uid, d.id AS driver_uid, c.suspended AS client_suspended, d.suspended AS driver_suspended,
                    c.name AS cname, c.surname AS csurname, d.name AS dname, d.surname AS dsurname
             FROM requests r JOIN users c ON c.id = r.client_id LEFT JOIN users d ON d.id = r.driver_id
             WHERE r.flagged = 1"
        )->fetchAll();
        $suspended = db()->query("SELECT * FROM users WHERE suspended = 1")->fetchAll();

        $flaggedHtml = '<div style="color:var(--ink-soft);font-size:13px">No reports right now.</div>';
        if ($flagged) {
            $flaggedHtml = '<div class="col gap3">';
            foreach ($flagged as $r) {
                $clientBtn = '<form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="toggle_suspend"><input type="hidden" name="user_id" value="' . $r["client_uid"] . '"><input type="hidden" name="back" value="moderation">
                    <button class="btn btn-danger" style="padding:6px 10px;font-size:12px" type="submit">' . icon("ban", 12) . ' ' . ($r["client_suspended"] ? "Reactivate" : "Suspend") . ' client</button></form>';
                $driverBtn = $r["driver_uid"] ? '<form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="toggle_suspend"><input type="hidden" name="user_id" value="' . $r["driver_uid"] . '"><input type="hidden" name="back" value="moderation">
                    <button class="btn btn-danger" style="padding:6px 10px;font-size:12px" type="submit">' . icon("ban", 12) . ' ' . ($r["driver_suspended"] ? "Reactivate" : "Suspend") . ' driver</button></form>' : "";
                $flaggedHtml .= '<div class="card card-sm" style="border-color:var(--alert)">
                    <div class="row between start">
                        <div><div style="font-weight:600;font-size:14px">' . h($r["description"]) . '</div>
                        <div style="font-size:12px;color:var(--ink-soft)">Reported by ' . h($r["reported_by"] ?: "unknown") . ($r["flag_target"] ? " (flagged the " . h($r["flag_target"]) . ")" : "") . ': "' . h($r["report_reason"] ?: "") . '"</div></div>
                        ' . status_badge($r["status"]) . '
                    </div>
                    <div class="row gap2 mt2" style="flex-wrap:wrap">
                        <a class="btn btn-ghost" style="padding:6px 10px;font-size:12px" href="?view=chat&request=' . $r["id"] . '">' . icon("message", 12) . ' View chat</a>
                        <form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="toggle_flag"><input type="hidden" name="request_id" value="' . $r["id"] . '">
                            <button class="btn btn-ghost" style="padding:6px 10px;font-size:12px" type="submit">Clear flag</button></form>
                        ' . $clientBtn . $driverBtn . '
                    </div>
                </div>';
            }
            $flaggedHtml .= '</div>';
        }

        $body = '<div class="col gap4">
            <div class="card card-sm"><div class="disp mb3" style="font-size:14px">Flagged deliveries</div>' . $flaggedHtml . '</div>
            <div class="card card-sm"><div class="disp mb3" style="font-size:14px">Suspended accounts (ban list)</div>' . ($suspended ? render_admin_user_rows($suspended) : '<div style="color:var(--ink-soft);font-size:13px">Nobody is currently suspended.</div>') . '</div>
        </div>';

    } elseif ($tab === "config") {
        $config = get_config();
        $locked = !$isSuper;
        $lockedNote = $locked ? '<span class="badge" style="background:var(--surface-alt);color:var(--ink-soft)">Super admin only</span>' : "";
        $disabled = $locked ? "disabled" : "";
        $checked = (int)$config["maintenance_mode"] === 1 ? "checked" : "";

        $body = '<div class="col gap4">
            <form method="post" action="actions.php" class="col gap4">
                ' . csrf_field() . '
                <input type="hidden" name="action" value="save_config">
                <div class="card card-sm">
                    <div class="row between mb2"><span class="disp" style="font-size:14px">Fare formula</span>' . $lockedNote . '</div>
                    <div class="row gap3">
                        <div class="grow"><div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">Base fare (R)</div><input class="input" name="base_fare" type="number" step="0.01" value="' . h((string)$config["base_fare"]) . '" ' . $disabled . '></div>
                        <div class="grow"><div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">Rate per km (R)</div><input class="input" name="rate_per_km" type="number" step="0.01" value="' . h((string)$config["rate_per_km"]) . '" ' . $disabled . '></div>
                        <div class="grow"><div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">Driver commission (%)</div><input class="input" name="commission_pct" type="number" step="0.01" value="' . h((string)$config["commission_pct"]) . '" ' . $disabled . '></div>
                    </div>
                    <p style="font-size:11.5px;color:var(--ink-soft);margin-top:8px">Fare = base fare + (distance in km × rate per km). The driver keeps the commission % of each fare; the rest goes to the company account.</p>
                </div>
                <div class="card card-sm">
                    <div class="row between mb2"><span class="disp" style="font-size:14px">Payroll</span>' . $lockedNote . '</div>
                    <div class="grow"><div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">Default monthly base salary for new drivers (R)</div><input class="input" name="driver_base_salary" type="number" step="0.01" value="' . h((string)$config["driver_base_salary"]) . '" ' . $disabled . '></div>
                </div>
                <div class="card card-sm">
                    <div class="disp mb2" style="font-size:14px">Platform status</div>
                    <label class="row gap2 mb3"><input type="checkbox" name="maintenance_mode" ' . $checked . ' ' . $disabled . '>
                        <span style="font-size:13.5px">Maintenance mode — blocks new requests and accepting deliveries</span></label>
                    <div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px">Announcement banner (leave blank to hide)</div>
                    <input class="input" name="announcement" value="' . h($config["announcement"]) . '" ' . $disabled . '>
                </div>
                <button class="btn btn-primary" style="justify-content:center" type="submit" ' . $disabled . '>' . icon("check", 15) . ' Save platform config</button>
            </form>
            <div class="card card-sm">
                <div class="disp mb2" style="font-size:14px">My admin account</div>
                <form method="post" action="actions.php" class="row gap2">' . csrf_field() . '<input type="hidden" name="action" value="change_password">
                    <input class="input" name="new_password" type="password" placeholder="New password">
                    <button class="btn btn-ghost" type="submit">Update password</button>
                </form>
                <p style="font-size:11px;color:var(--ink-soft);margin-top:6px">' . h(PASSWORD_HINT) . '</p>
            </div>
            ' . email_form_html($user) . '
        </div>';

    } elseif ($tab === "audit") {
        $log = db()->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 300")->fetchAll();
        if (!$log) {
            $body = '<div class="card" style="text-align:center;color:var(--ink-soft);font-size:13.5px">No admin actions logged yet.</div>';
        } else {
            $body = '<div class="col gap2">';
            foreach ($log as $e) {
                $body .= '<div class="card card-sm row between">
                    <div><div style="font-size:13.5px;font-weight:600">' . h($e["action"]) . '</div><div style="font-size:12px;color:var(--ink-soft)">' . h($e["detail"] ?: "") . '</div></div>
                    <div class="col" style="align-items:flex-end"><div class="mono" style="font-size:11px;color:var(--ink-soft)">' . fmt_date($e["created_at"]) . '</div><div style="font-size:11px;color:var(--ink-soft)">' . h($e["actor"]) . '</div></div>
                </div>';
            }
            $body .= '</div>';
        }
    }

    return '<div class="wrap">' . $tabBar . '<div id="liveArea" data-live="' . (in_array($tab, ["dashboard", "assign", "deliveries"], true) ? "1" : "0") . '">' . $body . '</div></div>';
}

function render_admin_user_rows(array $accounts): string
{
    if (!$accounts) {
        return '<div class="card" style="text-align:center;color:var(--ink-soft);font-size:13.5px">No accounts match this search.</div>';
    }
    $html = '<div class="col gap3">';
    foreach ($accounts as $a) {
        $adminBadge = $a["role"] === "admin" ? '<span class="badge" style="background:var(--ink);color:var(--bg)">' . h($a["admin_tier"] ?: "admin") . '</span>' : "";
       $suspendedBadge = $a["suspended"] ? '<span class="badge" style="background:var(--alert);color:#fff">Suspended</span>' : "";
        $salaryBadge = "";
           
        $verifyBadge = "";
        if ($a["role"] === "driver") {
            $doc = driver_document_status((int)$a["id"]);
            $vMap = [
                null => ["var(--surface-alt)", "var(--ink-soft)", "No documents"],
                "pending" => ["var(--accent)", "var(--accent-ink)", "Pending review"],
                "verified" => ["var(--route)", "#fff", "Verified"],
                "rejected" => ["var(--alert)", "#fff", "Rejected"],
            ];
            [$vbg, $vfg, $vlabel] = $vMap[$doc["status"] ?? null];
            $verifyBadge = '<span class="badge" style="background:' . $vbg . ';color:' . $vfg . '">' . $vlabel . '</span>';
        }
        $suspendBtn = $a["role"] !== "admin"
            ? '<form method="post" action="actions.php">' . csrf_field() . '<input type="hidden" name="action" value="toggle_suspend"><input type="hidden" name="user_id" value="' . $a["id"] . '"><input type="hidden" name="back" value="users">
                <button class="btn btn-ghost" style="padding:6px 10px;font-size:12px" type="submit">' . icon("ban", 12) . ' ' . ($a["suspended"] ? "Reactivate" : "Suspend") . '</button></form>' : "";
        $deleteBtn = $a["role"] !== "admin"
            ? '<form method="post" action="actions.php" onsubmit="return confirm(\'Permanently delete this account?\')">' . csrf_field() . '<input type="hidden" name="action" value="delete_account"><input type="hidden" name="user_id" value="' . $a["id"] . '">
                <button class="btn btn-danger" style="padding:6px 10px;font-size:12px" type="submit">Delete</button></form>' : "";

        $html .= '<div class="card card-sm row between">
            <div class="row gap3">' . avatar_html($a["profile_pic"], $a["name"] . " " . $a["surname"], 42, false) . '
                <div>
                    <div style="font-weight:600;font-size:14px">' . h($a["name"] . " " . $a["surname"]) . " $adminBadge" . '</div>
                    <div style="font-size:12px;color:var(--ink-soft)">' . h($a["phone"]) . ' · ' . h($a["role"]) . ($a["role"] === "driver" ? ' · ' . h($a["car_model"]) . ' (' . h($a["car_reg"]) . ')' : '') . '</div>
                    <div style="font-size:12px;color:var(--ink-soft)">' . h($a["email"] ?: "No email on file") . '</div>
                    <div class="row gap2 mt2">' . $suspendedBadge . $salaryBadge . $verifyBadge . '</div>
                </div>
            </div>
            <div class="col gap2" style="align-items:flex-end">' . $suspendBtn . $deleteBtn . '</div>
        </div>';
    }
    $html .= '</div>';
    return $html;
}

/* =========================================================
   CHAT PAGE
========================================================= */

function page_chat(array $user, int $requestId): string
{
    $stmt = db()->prepare(
        "SELECT r.*, c.name AS cname, c.surname AS csurname, d.name AS dname, d.surname AS dsurname
         FROM requests r JOIN users c ON c.id = r.client_id LEFT JOIN users d ON d.id = r.driver_id
         WHERE r.id = ?"
    );
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        return '<div class="wrap"><div class="card">Delivery not found.</div></div>';
    }
    $isParty = $user["id"] == $request["client_id"] || $user["id"] == $request["driver_id"];
    if ($user["role"] !== "admin" && !$isParty) {
        http_response_code(403);
        return '<div class="wrap"><div class="card">You don\'t have access to this conversation.</div></div>';
    }

    $msgStmt = db()->prepare(
        "SELECT m.*, u.name, u.surname FROM messages m JOIN users u ON u.id = m.sender_id
         WHERE m.request_id = ? ORDER BY m.created_at ASC"
    );
    $msgStmt->execute([$requestId]);
    $messages = $msgStmt->fetchAll();

    $title = $user["role"] === "admin"
        ? h($request["cname"] . " " . $request["csurname"]) . " & " . h($request["dname"] ?? "driver") . " — mediation"
        : ($user["role"] === "driver" ? h($request["cname"] . " " . $request["csurname"]) : h($request["dname"] . " " . $request["dsurname"]));

    $bubbles = "";
    foreach ($messages as $m) {
        $mine = $m["sender_id"] == $user["id"];
        $bubbles .= '<div style="align-self:' . ($mine ? "flex-end" : "flex-start") . ';max-width:78%;background:' . ($mine ? "var(--route)" : "var(--surface-alt)") . ';color:' . ($mine ? "#fff" : "var(--ink)") . ';padding:8px 12px;border-radius:14px;font-size:13.5px;margin-bottom:8px">
            ' . h($m["message"]) . '<div class="mono" style="font-size:9px;opacity:.7;margin-top:3px">' . fmt_date($m["created_at"]) . '</div>
        </div>';
    }
    if (!$messages) {
        $bubbles = '<div style="color:var(--ink-soft);font-size:13px;text-align:center;margin-top:20px">No messages yet. Say hello 👋</div>';
    }

    $flagWho = $user["role"] === "driver" ? "client" : "driver";   // who the flag button reports
    $reportBtn = $user["role"] !== "admin"
        ? '<a class="btn btn-ghost" style="padding:8px" href="#" onclick="document.getElementById(\'reportBox\').style.display=\'block\';return false;">' . icon("flag", 14, "var(--alert)") . ' Flag ' . $flagWho . '</a>' : "";

    return '<div class="wrap">
        <div class="card" style="padding:0">
            <div class="row between" style="padding:16px;border-bottom:1px solid var(--border)">
                <div><div class="disp" style="font-size:15px">' . $title . '</div>
                <div style="font-size:11px;color:var(--ink-soft)">Re: ' . h(mb_strimwidth($request["description"], 0, 40, "…")) . '</div></div>
                <div class="row gap2">' . $reportBtn . '<a class="btn btn-ghost" style="padding:8px" href="javascript:history.back()">' . icon("check", 16) . ' Back</a></div>
            </div>
            <div id="reportBox" class="card-sm" style="display:none;border-bottom:1px solid var(--border)">
                <form method="post" action="actions.php" class="row gap2">' . csrf_field() . '<input type="hidden" name="action" value="report_request"><input type="hidden" name="request_id" value="' . $requestId . '">
                    <input class="input" name="reason" placeholder="Reason for flagging the ' . $flagWho . ' (required)" maxlength="255" required>
                    <button class="btn btn-danger" type="submit">Submit flag</button>
                </form>
            </div>
            <div id="chatMessages" style="padding:16px;display:flex;flex-direction:column">' . $bubbles . '</div>
            <form method="post" action="actions.php" class="row gap2" style="padding:12px;border-top:1px solid var(--border)">
                ' . csrf_field() . '<input type="hidden" name="action" value="send_message"><input type="hidden" name="request_id" value="' . $requestId . '">
                <input class="input" name="message" placeholder="Type a message…" required autofocus>
                <button class="btn btn-primary" style="padding:10px 14px" type="submit">' . icon("send", 15) . '</button>
            </form>
        </div>
    </div>';
}

/* =========================================================
   ROUTING + OUTPUT
========================================================= */

$needsMap = $user && $user["role"] === "client" && $view === "home" && ($tab ?? "post") === "post";

$flashHtml = "";
if ($flash) {
    $flashHtml = '<div class="wrap" style="padding-top:12px"><div class="flash flash-' . h($flash["type"]) . '">' . h($flash["message"]) . '</div></div>';
}

$bodyContent = "";
if (!$user) {
    $bodyContent = page_auth($flash);
    $flashHtml = ""; // already placed inside page_auth()
} elseif ((int)$user["must_change_password"] === 1) {
    $bodyContent = page_force_password_change($user, $flash);
    $flashHtml = ""; // already placed inside page_force_password_change()
} else {
    $config = get_config();
    $announcementHtml = $config["announcement"]
        ? '<div class="wrap"><div class="card card-sm" style="border-color:var(--route);margin-bottom:12px">' . icon("info", 14, "var(--route)") . ' ' . h($config["announcement"]) . '</div></div>' : "";
    $maintenanceHtml = ((int)$config["maintenance_mode"] === 1 && $user["role"] !== "admin")
        ? '<div class="wrap"><div class="card card-sm" style="border-color:var(--alert);margin-bottom:12px;color:var(--alert)">' . icon("gear", 14, "var(--alert)") . ' The platform is temporarily under maintenance.</div></div>' : "";

    $page = "";
    if ($view === "chat") {
        $page = page_chat($user, (int)($_GET["request"] ?? 0));
    } elseif ($view === "settings") {
        $page = page_settings($currentTheme);
    } elseif ($view === "about") {
        $page = page_about();
    } else {
        if ($user["role"] === "driver") {
            $page = page_driver_home($user, $tab ?? "active");
        } elseif ($user["role"] === "client") {
            $page = page_client_home($user, $tab ?? "post");
        } elseif ($user["role"] === "admin") {
            $page = page_admin_home($user, $tab ?? "dashboard");
        }
    }

    $bodyContent = '<div class="app">' . page_nav($user, $view, $tab) . '<main class="main"><div style="padding-top:8px">' . $announcementHtml . $maintenanceHtml . $flashHtml . $page . '</div></main></div>';
    $flashHtml = ""; // already placed inside bodyContent for logged-in users
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Phoenix</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2F8F86">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Phoenix">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<link rel="icon" type="image/png" href="icons/icon-192.png">
<link rel="stylesheet" href="css/style.css?v=<?= (int)@filemtime(__DIR__ . "/css/style.css") ?>">
<?php if ($needsMap): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<?php endif; ?>
</head>
<body class="<?= $currentTheme === "dark" ? "dark" : "" ?>">
<script>try{if(localStorage.getItem("phoenix_sb")==="1")document.body.classList.add("sb-collapsed");}catch(e){}</script>
<?= $flashHtml ?>
<?= $bodyContent ?>
<?php if ($needsMap): ?>
<script src="js/map.js?v=<?= (int)@filemtime(__DIR__ . "/js/map.js") ?>"></script>
<?php endif; ?>
<script>
(function(){
  var b=document.getElementById("sbToggle");
  if(!b)return;
  b.addEventListener("click",function(){
    var c=document.body.classList.toggle("sb-collapsed");
    try{localStorage.setItem("phoenix_sb",c?"1":"0");}catch(e){}
  });
})();
</script>
<?php if ($user): ?>
<script>
(function () {
  // Auto-refresh: chat every 4 seconds, delivery lists every 10 seconds.
  // Only the changed block is swapped in, so whatever you are typing is never lost.
  function fetchPage(cb) {
    fetch(window.location.href, { credentials: "same-origin" })
      .then(function (r) { return r.text(); })
      .then(function (html) { cb(new DOMParser().parseFromString(html, "text/html")); })
      .catch(function () {});
  }
  function nearBottom() {
    return window.innerHeight + window.scrollY >= document.body.scrollHeight - 120;
  }
  function scrollBottom() { window.scrollTo(0, document.body.scrollHeight); }

  /* ----- chat ----- */
  var chat = document.getElementById("chatMessages");
  if (chat) {
    scrollBottom();
    setInterval(function () {
      if (document.hidden) return;
      fetchPage(function (doc) {
        var fresh = doc.getElementById("chatMessages");
        if (!fresh || fresh.innerHTML === chat.innerHTML) return;
        var stick = nearBottom();
        chat.innerHTML = fresh.innerHTML;
        if (stick) scrollBottom();
      });
    }, 4000);
  }

  /* ----- delivery lists ----- */
  var live = document.getElementById("liveArea");
  if (live && live.getAttribute("data-live") === "1") {
    function userIsBusy() {
      var fields = live.querySelectorAll("input, textarea, select");
      for (var i = 0; i < fields.length; i++) {
        var f = fields[i];
        if (f === document.activeElement) return true;
        if (f.type === "file" && f.files && f.files.length) return true;
        if (f.tagName === "SELECT") {
          for (var j = 0; j < f.options.length; j++) {
            if (f.options[j].selected !== f.options[j].defaultSelected) return true;
          }
        } else if ((f.type === "text" || f.tagName === "TEXTAREA") && f.value !== f.defaultValue) {
          return true;
        }
      }
      return false;
    }
    setInterval(function () {
      if (document.hidden || userIsBusy()) return;
      fetchPage(function (doc) {
        var fresh = doc.getElementById("liveArea");
        if (!fresh || fresh.innerHTML === live.innerHTML || userIsBusy()) return;
        live.innerHTML = fresh.innerHTML;
      });
    }, 10000);
  }
})();
</script>
<?php endif; ?>
<script>
(function () {
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("sw.js").catch(function () {});
    });
  }
  var deferred = null;
  var btn = document.getElementById("installBtn");
  window.addEventListener("beforeinstallprompt", function (e) {
    e.preventDefault();
    deferred = e;
    if (btn) { btn.style.display = ""; }
  });
  if (btn) {
    btn.addEventListener("click", function () {
      if (deferred) { deferred.prompt(); deferred = null; btn.style.display = "none"; }
    });
  }
  window.addEventListener("appinstalled", function () { if (btn) { btn.style.display = "none"; } });
})();
</script>
<script>
/* Show / hide password: adds an eye button to every password box on every page. */
(function () {
  var EYE = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.9 17.9A10.9 10.9 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.1-5.9M9.9 5.1A10.4 10.4 0 0 1 12 5c7 0 11 7 11 7a19.7 19.7 0 0 1-3.2 4.2M1 1l22 22"/></svg>';
  var boxes = document.querySelectorAll('input[type="password"]');
  for (var i = 0; i < boxes.length; i++) {
    (function (input) {
      var hadFocus = (document.activeElement === input);
      var wrap = document.createElement("div");
      wrap.className = "pw-wrap";
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "pw-eye";
      btn.setAttribute("aria-label", "Show password");
      btn.innerHTML = EYE;
      btn.addEventListener("click", function () {
        var show = (input.type === "password");
        input.type = show ? "text" : "password";
        btn.innerHTML = show ? EYE_OFF : EYE;
        btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
        input.focus();
      });
      wrap.appendChild(btn);
      if (hadFocus) { input.focus(); }
    })(boxes[i]);
  }
})();
</script>
</body>
</html>