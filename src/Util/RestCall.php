<?php

namespace Util;

use Core\Logger;

/**
 * RestCall provides functions for HTTP request and etc.
 */
class RestCall
{
    public const LIMIT = 512;

    protected static $instance = null;

    protected $rest;
    protected $timeout;
    protected $info;

    public function __construct($timeout = 1)
    {
        $this->timeout = $timeout;
        $this->info = null;
    }

    public static function instance(): ?self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function setTimeout(int $timeout = 1)
    {
        $this->timeout = $timeout;
    }

    public function multiGet(array $urls, array $header = []): ?array
    {
        $results = [];

        do {
            $bucket = array_splice($urls, 0, self::LIMIT);
            $calls = [];
            $multi = curl_multi_init();

            foreach ($bucket as $url) {
                $c = curl_init();

                if (preg_match('/^'. preg_quote('https:', '/'). '/', $url)) {
                    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
                } elseif (!preg_match('/^'. preg_quote('http:', '/'). '/', $url)) {
                    $url = 'http://'. $url;
                }

                curl_setopt($c, CURLOPT_URL, $url);
                curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($c, CURLOPT_TIMEOUT, $this->timeout);

                if (count($header) > 0) {
                    curl_setopt($c, CURLOPT_HTTPHEADER, $header);
                }

                $calls[] = $c;
                curl_multi_add_handle($multi, $c);
            }

            do {
                $status = curl_multi_exec($multi, $active);
                if ($active) {
                    curl_multi_select($multi);
                }
            } while ($active && $status == CURLM_OK);

            foreach ($calls as $call) {
                $info = curl_getinfo($call);
                $results[] = [
                    'result' => curl_multi_getcontent($call),
                    'host' => preg_replace("/http:\/\/(.*?)\/.*/", '$1', $info['url']),
                    'exec_time' => $info['total_time'],
                ];
            }

            curl_multi_close($multi);
        } while (count($urls) > 0);

        return $results;
    }

    # TODO: remake
    public function multiPOST(array $items, array $header = []): ?array
    {
        $results = [];

        do {
            $bucket = array_splice($items, 0, self::LIMIT);
            $calls = [];
            $multi = curl_multi_init();

            foreach ($bucket as $item) {
                $c = curl_init();
                $url = $item['url'];
                $data = $item['data'];

                if (preg_match('/^' . preg_quote('https:', '/') . '/', $url)) {
                    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
                } else {
                    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
                }

                $url = preg_replace('/(^https?'. preg_quote('://', '/'). ')/', '', $url);

                curl_setopt($c, CURLOPT_URL, $url);
                curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($c, CURLOPT_TIMEOUT, $this->timeout);
                curl_setopt($c, CURLOPT_POST, true);

                if (is_array($data)) {
                    curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($data));
                } else {
                    curl_setopt($c, CURLOPT_POSTFIELDS, $data);
                }

                if (count($header) > 0) {
                    curl_setopt($c, CURLOPT_HTTPHEADER, $header);
                }

                $calls[] = $c;
                curl_multi_add_handle($multi, $c);
            }

            do {
                $status = curl_multi_exec($multi, $active);
                if ($active) {
                    curl_multi_select($multi);
                }
            } while ($active && $status == CURLM_OK);

            foreach ($calls as $call) {
                $info = curl_getinfo($call);
                $results[] = [
                    'result' => curl_multi_getcontent($call),
                    'host' => preg_replace("/http:\/\/(.*?)\/.*/", '$1', $info['url']),
                    'exec_time' => $info['total_time'],
                ];
            }

            curl_multi_close($multi);
        } while (count($items) > 0);

        return $results;
    }

    /**
     *  Requests an http response using the GET method with the given URL.
     *
     * @param string $url The URL address to send the request to.
     * @param bool $ssl If true, verifying the peer's certificate.
     * @param array $header The keys and values to include in the http header.
     *
     * @return bool|string true on success or false on failure.
     *                     However, if the CURLOPT_RETURNTRANSFER option is set,
     *                     it will return the result on success, false on failure.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.3.1
     * @see https://php.net/manual/en/function.curl-exec.php
     */
    public function get(string $url, array $header = [])
    {
        $this->rest = curl_init();

        if (preg_match('/^' . preg_quote('https:', '/') . '/', $url)) {
            curl_setopt($this->rest, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($this->rest, CURLOPT_SSL_VERIFYPEER, false);
        }

        $url = preg_replace('/(^https?'. preg_quote('://', '/'). ')/', '', $url);

        curl_setopt($this->rest, CURLOPT_URL, $url);
        curl_setopt($this->rest, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->rest, CURLOPT_TIMEOUT, $this->timeout);

        if (count($header) > 0) {
            curl_setopt($this->rest, CURLOPT_HTTPHEADER, $header);
        }

        $r = curl_exec($this->rest);
        $this->info = curl_getinfo($this->rest);
        curl_close($this->rest);

        return $r;
    }

    /**
     *  Requests an HTTP response using the POST method with the given URL
     *  and data.
     *
     * @param string $url The url address to send the request to.
     * @param array $data The data to attach to the request.
     * @param array $header The keys and values to include in the http header.
     *
     * @return bool|string true on success or false on failure.
     *                     However, if the CURLOPT_RETURNTRANSFER option is set,
     *                     it will return the result on success, false on failure.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.3.3
     * @see https://php.net/manual/en/function.curl-exec.php
     */
    public function post(string $url, $data = [], array $header = [])
    {
        $this->rest = curl_init();

        if (preg_match('/^' . preg_quote('https:', '/') . '/', $url)) {
            curl_setopt($this->rest, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($this->rest, CURLOPT_SSL_VERIFYPEER, false);
        }

        $url = preg_replace('/(^https?'. preg_quote('://', '/'). ')/', '', $url);

        curl_setopt($this->rest, CURLOPT_URL, $url);
        curl_setopt($this->rest, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->rest, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->rest, CURLOPT_POST, true);

        if (is_array($data)) {
            curl_setopt($this->rest, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($this->rest, CURLOPT_POSTFIELDS, $data);
        }

        if (count($header) > 0) {
            curl_setopt($this->rest, CURLOPT_HTTPHEADER, $header);
        }

        $r = curl_exec($this->rest);
        $this->info = curl_getinfo($this->rest);
        curl_close($this->rest);

        return $r;
    }
}
