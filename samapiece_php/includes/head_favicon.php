<?php
if (function_exists('app_logo_available') && app_logo_available()) {
    echo '<link rel="icon" href="' . htmlspecialchars(APP_LOGO_URL, ENT_QUOTES, 'UTF-8') . '" type="image/png">' . "\n    ";
}
