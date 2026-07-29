<?php

function ccSafeInternalRedirect($value, $fallback = '../main/inicio.php')
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\r\n\\\\]/', $value)) {
        return $fallback;
    }

    $parts = parse_url($value);
    if ($parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
        return $fallback;
    }

    $path = $parts['path'] ?? '';
    if ($path === '' || $path[0] !== '/' || strncmp($path, '//', 2) === 0) {
        return $fallback;
    }

    $redirect = $path;
    if (isset($parts['query']) && $parts['query'] !== '') {
        $redirect .= '?' . $parts['query'];
    }

    return $redirect;
}
