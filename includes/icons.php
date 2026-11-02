<?php
/**
 * Sportibai Unified SVG Icon Library
 * 
 * Provides consistent, cohesive Lucide/Feather-style stroke vector icons.
 * Replaces mismatched OS emojis with a single unified 2px geometric stroke system.
 */

function sporti_icon($name, $class = '', $size = 18, $attrs = '') {
    $size_attr = $size ? "width=\"{$size}\" height=\"{$size}\"" : '';
    $class_attr = $class ? "class=\"sporti-icon {$class}\"" : 'class="sporti-icon"';
    
    // Base SVG container with uniform stroke parameters
    $open = "<svg xmlns=\"http://www.w3.org/2000/svg\" {$size_attr} viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" {$class_attr} {$attrs} style=\"vertical-align:middle; flex-shrink:0; display:inline-block;\">";
    $close = "</svg>";

    switch ($name) {
        // Orders & Inventory
        case 'package':
        case 'box':
            $body = '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>';
            break;

        case 'truck':
        case 'delivery':
            $body = '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14v10Z"/><circle cx="17" cy="18.5" r="2.5"/><circle cx="7" cy="18.5" r="2.5"/>';
            break;

        case 'store':
        case 'shop':
            $body = '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2"/>';
            break;

        case 'copy':
        case 'clipboard':
            $body = '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>';
            break;

        case 'user':
            $body = '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>';
            break;

        case 'users':
            $body = '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>';
            break;

        case 'phone':
            $body = '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>';
            break;

        case 'map-pin':
            $body = '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>';
            break;

        case 'message-square':
        case 'note':
            $body = '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>';
            break;

        case 'file-text':
        case 'receipt':
            $body = '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/>';
            break;

        case 'search':
            $body = '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>';
            break;

        case 'check':
            $body = '<polyline points="20 6 9 17 4 12"/>';
            break;

        case 'check-circle':
            $body = '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>';
            break;

        case 'clock':
            $body = '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>';
            break;

        case 'alert-triangle':
        case 'warning':
            $body = '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/>';
            break;

        case 'x':
        case 'close':
            $body = '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>';
            break;

        case 'x-circle':
            $body = '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>';
            break;

        case 'tag':
            $body = '<path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/>';
            break;

        case 'bar-chart':
        case 'chart':
            $body = '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>';
            break;

        case 'trending-up':
            $body = '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>';
            break;

        case 'banknote':
        case 'dollar-sign':
            $body = '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>';
            break;

        case 'printer':
            $body = '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>';
            break;

        case 'star':
            $body = '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>';
            break;

        case 'arrow-right':
            $body = '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>';
            break;

        case 'arrow-left':
            $body = '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>';
            break;

        case 'chevron-right':
            $body = '<path d="m9 18 6-6-6-6"/>';
            break;

        case 'shield':
            $body = '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>';
            break;

        case 'edit':
            $body = '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>';
            break;

        case 'eye':
            $body = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>';
            break;

        case 'download':
            $body = '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>';
            break;

        case 'shopping-bag':
            $body = '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>';
            break;

        case 'arrow-up-right':
            $body = '<path d="M7 7h10v10"/><path d="M7 17 17 7"/>';
            break;

        case 'zap':
            $body = '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>';
            break;

        case 'layers':
            $body = '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 12.5-8.58 3.91a2 2 0 0 1-1.66 0L2 12.5"/><path d="m22 17.5-8.58 3.91a2 2 0 0 1-1.66 0L2 17.5"/>';
            break;

        case 'award':
            $body = '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>';
            break;

        case 'lock':
            $body = '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>';
            break;

        case 'camera':
            $body = '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="4"/>';
            break;

        case 'save':
            $body = '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>';
            break;

        case 'plus':
            $body = '<line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>';
            break;

        case 'loader':
            $body = '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>';
            break;

        case 'mail':
            $body = '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>';
            break;

        case 'info':
            $body = '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/>';
            break;

        default:
            $body = '<circle cx="12" cy="12" r="10"/>';
            break;
    }

    return $open . $body . $close;
}

// Shorthand helper alias
if (!function_exists('icon')) {
    function icon($name, $class = '', $size = 18, $attrs = '') {
        return sporti_icon($name, $class, $size, $attrs);
    }
}

