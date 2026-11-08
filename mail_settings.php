<?php
/* ---------------------------------------------------------------
   GMAIL SETTINGS — used to email password-reset codes (OTP).

   HOW TO SET UP (about 10 minutes, free):
   1. Create a Gmail account just for the app, e.g. phoenix.app.yourname@gmail.com
   2. In that account: Google Account -> Security -> turn ON 2-Step Verification
   3. Google Account -> Security -> App passwords -> create one (name it "Phoenix").
      Google shows a 16-character password ONCE — copy it.
   4. Fill in MAIL_USERNAME and MAIL_PASSWORD below and set MAIL_ENABLED to true.

   While MAIL_ENABLED is false the app stays in demo mode: the code is shown
   on screen instead of being emailed, so nothing breaks before you set this up.

   KEEP THIS FILE PRIVATE: don't upload it to GitHub or include your real
   app password in a zip you submit or share.
--------------------------------------------------------------- */

const MAIL_ENABLED   = true;                      // true = really send emails
const MAIL_USERNAME  = "myphoenix676@gmail.com";        // the Gmail address you created
const MAIL_PASSWORD  = "xexw agvq rsht bpkg";      // the 16-character App Password (NOT your normal password)
const MAIL_FROM_NAME = "Phoenix";                  // sender name users will see

// Gmail's servers. Only change these if you use a different provider.
const MAIL_HOST   = "smtp.gmail.com";
const MAIL_PORT   = 587;
const MAIL_SECURE = "tls";                         // "tls" = STARTTLS on port 587; "ssl" for port 465
