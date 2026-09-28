<?php
/**
 * BethelDesk - Global Configuration & Constants
 */

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'BethelDesk');
}

if (!defined('SITE_TAGLINE')) {
    define('SITE_TAGLINE', 'Customer Support & Helpdesk Platform');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.0.0');
}

/**
 * Render the unified BethelDesk logo SVG mark
 * 
 * @param string $size 'sm', 'md', 'lg'
 * @param string $badge Optional badge text: 'Customer Portal', 'Admin', or empty
 * @param string $linkUrl Destination URL
 * @return string HTML
 */
if (!function_exists('renderBethelDeskLogo')) {
    function renderBethelDeskLogo($size = 'md', $badge = '', $linkUrl = 'index.php') {
        $iconSizes = [
            'sm' => ['box' => 28, 'font' => 16, 'badgeFont' => 10],
            'md' => ['box' => 34, 'font' => 18, 'badgeFont' => 11],
            'lg' => ['box' => 42, 'font' => 22, 'badgeFont' => 12]
        ];
        $s = $iconSizes[$size] ?? $iconSizes['md'];
        
        $badgeHtml = '';
        if (!empty($badge)) {
            $badgeClass = ($badge === 'Admin') ? 'logo-badge-admin' : 'logo-badge-portal';
            $badgeHtml = "<span class=\"logo-badge {$badgeClass}\">" . htmlspecialchars($badge) . "</span>";
        }
        
        $svg = '
        <svg class="brand-logo-icon" width="' . $s['box'] . '" height="' . $s['box'] . '" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="40" height="40" rx="8" fill="url(#bdesk_gradient)" />
            <path d="M12 12H22C25.3137 12 28 14.6863 28 18C28 20.3813 26.6083 22.438 24.5963 23.385C27.0504 24.3146 28.8 26.7027 28.8 29.5C28.8 33.0899 25.8899 36 22.3 36H12V12Z" fill="white" fill-opacity="0.15"/>
            <path d="M14 14H21.5C23.9853 14 26 16.0147 26 18.5C26 20.9853 23.9853 23 21.5 23H14V14Z" fill="white"/>
            <path d="M14 23H22.5C25.2614 23 27.5 25.2386 27.5 28C27.5 30.7614 25.2614 33 22.5 33H14V23Z" fill="white"/>
            <circle cx="28" cy="13" r="3.5" fill="#FAFAFA"/>
            <defs>
                <linearGradient id="bdesk_gradient" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#09090B"/>
                    <stop offset="1" stop-color="#27272A"/>
                </linearGradient>
            </defs>
        </svg>'; 

        return "
        <a href=\"{$linkUrl}\" class=\"brand-logo brand-logo-{$size}\">
            {$svg}
            <span class=\"brand-name\">" . SITE_NAME . "</span>
            {$badgeHtml}
        </a>";
    }
}