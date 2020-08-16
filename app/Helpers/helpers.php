<?php

function make_random_hash(string $salt = '')
{
    try {
        $string = bin2hex(random_bytes(32)) . $salt;
    } catch (Exception $e) {
        $string = mt_rand() . $salt;
    }
    return sha1($string);
}

function add_query_param_to_url($url, $parameter)
{
    $urlParts = parse_url($url);
    if (!array_key_exists('query', $urlParts)) {
        $urlParts['query'] = '';
    }
    $params = explode('&', $urlParts['query']);
    $params[] = $parameter;
    $urlParts['query'] = implode('&', $params);
    $url = array_key_exists('scheme', $urlParts) ? $urlParts['scheme'] . '://' : 'https://';
    $url .= array_key_exists('host', $urlParts) ? $urlParts['host'] : '';
    $url .= array_key_exists('path', $urlParts) ? $urlParts['path'] : '';
    $url .= '?' . $urlParts['query'];
    return $url;
}