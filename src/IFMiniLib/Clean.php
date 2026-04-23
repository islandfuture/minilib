<?php

namespace IFMiniLib;

class Clean
{
    public static function string($string, $symbols = '', $stripTags = true)
    {
        if ($stripTags) {
            $string = strip_tags($string);
        }
        $string = preg_replace('/[^a-zA-Zёа-яЁА-ЯЁёäöüÄÖÜßèéûşç0-9\p{L}\p{N} +\-_\:' . $symbols . ']/ui', '', $string);
        return $string;
    }

    public static function stringMb4($str, $stripTags = true)
    {
        if ($stripTags) {
            $str = strip_tags($str);
        }
        $str = preg_replace("/[\x{10000}-\x{10FFFF}]/u", "\xEF\xBF\xBD", $str);
        return $str;
    }

    public static function letters($str)
    {
        $str = strip_tags($str);
        $str = preg_replace("/[^A-Za-z-_.\/]+/", "", $str);
        return $str;
    }

    public static function lettersAndNumbers($str)
    {
        $str = strip_tags($str);
        $str = preg_replace("/[^A-Za-z0-9-_.\/]+/", "", $str);
        return $str;
    }

    public static function html($html, $allowedTags = '')
    {
        if ($allowedTags > '') {
            $html = strip_tags($html, $allowedTags);
        }

        // composer require symfony/html-sanitizer
        $sanitizer = new \Symfony\Component\HtmlSanitizer\HtmlSanitizer(
            (new \Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig())->allowSafeElements()
        );
        $clean = $sanitizer->sanitize($html);
        return $clean;
    }

    public static function json($json, $sanityze = false): ?string
    {
        if (!is_string($json)) {
            return null;
        }

        $json = trim($json);

        try {
            // Просто проверяем что строка валидный JSON и нормализуем её
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            // Рекурсивно санируем строковые значения
            if ($sanityze) {
                array_walk_recursive($decoded, function (&$value) {
                    if (is_string($value)) {
                        $value = strip_tags($value);
                        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
                    }
                });
            }

            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
    }

    public static function htmlentitiesValuesInArray($array)
    {
        if (!is_array($array)) {
            return [];
        }

        $result = [];
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $result[$key] = htmlentities($value);
            } elseif (is_array($value)) {
                $result[$key] = self::htmlentitiesValuesInArray($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public static function sanitizeDomain($str, $onlyascii = false)
    {
        $str = mb_strtolower($str, 'utf8');
        $str = str_replace(";//", "://", $str);
        $str = str_replace("http://", "", $str);
        $str = str_replace("https://", "", $str);
        $str = str_replace(" ", "", $str);
        $str = str_replace("/", "", $str);
        if (substr($str, 0, 4) == 'www.') {
            $str = str_replace("www.", "", $str);
        }

        $str = preg_replace("/[^a-z0-9_\\x80-\\xff\-.]+/i", "", $str);

        if ($onlyascii) {
            $str2 = preg_replace("/[^a-z0-9-.]+/i", "", $str);
            if ($str2 > '') {
                $str = idn_to_ascii($str, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            }
        }
        return $str;
    }

    public static function isSafeLink($url): bool
    {
        $url = trim($url);
        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            return false;
        }
        $original = $url;
        $url = strtolower($url);

        $host = $parsed['host'];
        $host = strtolower($host);

        // Проверка на localhost
        if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
            return false;
        }

        $url = self::findXssInUrl($url);

        if ($url === false) {
            App::one()->log("[Bad URL]: XSS: " . $original, ['params' => [$url]], 'error');
            return false;
        }

        // Декодируем все возможные варианты кодирования
        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = rawurldecode($decoded);
        $decoded = strtolower($decoded);

        // Убираем пробелы и управляющие символы (в т.ч. %00, %09 и т.д.)
        $decoded = preg_replace('/[\x00-\x20\x7f]+/', '', $decoded);

        // Проверяем схему после полного декодирования
        $scheme = parse_url($decoded, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            App::one()->log("[Bad URL]: invalid scheme: " . $original, ['url' => $url, 'decoded' => $decoded, 'scheme' => $scheme, 'original' => $original], 'error');
            return false;
        }

        // Проверяем на javascript: с любым кодированием
        if (preg_match('/j[\s\x00]*a[\s\x00]*v[\s\x00]*a[\s\x00]*s[\s\x00]*c[\s\x00]*r[\s\x00]*i[\s\x00]*p[\s\x00]*t[\s\x00]*:/i', $decoded)) {
            App::one()->log("[Bad URL]: javascript scheme: " . $original, ['url' => $url, 'decoded' => $decoded, 'scheme' => $scheme, 'original' => $original], 'error');
            return false;
        }

        if (substr($host, 0, 1) == '[') {
            // Убираем квадратные скобки у IPv6 адреса
            $host = substr($host, 1, -1);
        }
        // Определяем, является ли host IP-адресом
        $isIP = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_IPV4) !== false;

        if ($isIP) {
            // Если это IP - проверяем что он не приватный и не зарезервированный
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        } else {
            // Если это доменное имя - валидация домена

            // Проверка валидности домена (базовая проверка формата)
            if (!self::isValidDomain($host)) {
                return false;
            }

            // Проверка черного списка доменов
            if (is_array(Core::$app->blackListDomains) && in_array($host, Core::$app->blackListDomains)) {
                return false;
            }

            // Резолвим домен в IP и проверяем, что IP не приватный
            $ip = gethostbyname($host);

            // Если резолв не удался, gethostbyname вернет исходный hostname
            if ($ip === $host) {
                return false;
            }

            // Проверяем что полученный IP не приватный и не зарезервированный
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }

            return true;
        }
    }

    /**
     * Проверка валидности доменного имени
     * @param string $domain
     * @return bool
     */
    public static function isValidDomain(string $domain): bool
    {
        // Базовая проверка формата домена
        // Домен должен содержать только разрешенные символы: a-z, 0-9, дефис, точка
        // Не должен начинаться или заканчиваться дефисом
        // Должен содержать хотя бы одну точку (для TLD)

        if (strlen($domain) > 253) {
            return false;
        }

        // Проверяем что домен содержит хотя бы одну точку (есть TLD)
        if (strpos($domain, '.') === false) {
            return false;
        }

        $pattern = '/^(?!-)[a-z0-9\\x80-\\xff-]{1,63}(?<!-)(\\.(?!-)[a-z0-9\\x80-\\xff-]{1,63}(?<!-))*$/i';

        if (!preg_match($pattern, $domain)) {
            return false;
        }

        return true;
    }

    public static function link($str)
    {
        if (static::isSafeLink($str) === false) {
            return "";
        }

        $str = substr($str, 0, 500);
        return $str;
    }

    public static function findXssInUrl($url)
    {
        $isAttack = 0;
        if (strpos($url, "eval") !== false) {
            $url = str_replace("eval", 'еvаl', $url);
            $isAttack++;
        }
        if (strpos($url, "atob") !== false) {
            $url = str_replace("atob", 'аtоb', $url);
            $isAttack++;
        }
        if (strpos($url, "onerror") !== false) {
            $url = str_replace("onerror", '', $url);
            $isAttack++;
        }
        if (strpos($url, "onfocus") !== false) {
            $url = str_replace("onfocus", '', $url);
            $isAttack++;
        }
        if (strpos($url, "onclick") !== false) {
            $url = str_replace("onclick", '', $url);
            $isAttack++;
        }
        if (strpos($url, "><") !== false) {
            $url = str_replace("><", '', $url);
            $isAttack++;
        }
        if (strpos($url, "javascript:") !== false) {
            $url = str_replace("javascript:", '', $url);
            $isAttack++;
        }

        if ($isAttack > 1) {
            return false;
        }

        return $url;
    }

    public static function url($url, $shemes = 'http,https')
    {
        if (is_null($url)) {
            $url = '';
        }
        $url = trim($url);
        $url = strip_tags($url);

        $url = self::findXssInUrl($url);
        if ($url === false) {
            return false;
        }

        if ($p = parse_url($url)) {
            if (isset($p['host'])) {
                $p['host'] = idn_to_ascii($p['host']) ?: $p['host'];
            }
            if (isset($p['path'])) {
                $p['path'] = implode('/', array_map(
                    fn($seg) => rawurlencode(rawurldecode($seg)),
                    explode('/', $p['path'])
                ));
            }
            if (isset($p['query'])) {
                parse_str($p['query'], $q);
                $p['query'] = http_build_query($q);
            }
            $url =
                ($p['scheme'] ?? 'https') . '://' .
                ($p['host'] ?? '') .
                ($p['path'] ?? '') .
                (isset($p['query']) ? '?' . $p['query'] : '') .
                (isset($p['fragment']) ? '#' . $p['fragment'] : '');
        } else {
            $url = '';
        }

        $url = filter_var($url, FILTER_VALIDATE_URL);

        if ($url === false) {
            $url = '';
        }

        return $url;
    }

    public static function email($email)
    {
        $ar = explode('@', $email);
        if (!$ar || sizeof($ar) != 2) {
            return '';
        }

        $emailaccount = self::string($ar[0]);
        $domain = $ar[1];
        $domain = self::sanitizeDomain($domain, false);
        // если домен рускоязычный, то конвертнем его
        if (isset($domain) && preg_match("/[^a-zA-Z.\-_0-9]+/i", $domain)) {
            $domain = substr($domain, 0, 4) != 'xn--' ? idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : $domain;
        }

        // one more check
        $emailaccount = preg_replace("/[^a-zA-Z+.\-_0-9]+/iu", '', $emailaccount);

        // one more check
        $domain = preg_replace("/[^a-zA-Z._\-0-9]+/iu", '', $domain);

        $email = $emailaccount . '@' . $domain;

        // FILTER_VALIDATE_EMAIL - не валидирует email в доменах которых
        // идет два минуса подряд '--', а это все домены в punycode
        if (strpos($domain, 'xn---') === false && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $email;
    }

    public static function stringSC($str)
    {
        $str = strip_tags($str);
        $str = htmlspecialchars($str);
        return $str;
    }

    public static function imageUrl($str)
    {
        $str = preg_replace("/[^a-zA-Z0-9\-_.:\/]/", "", $str);
        $str = self::url($str);
        $str = substr($str, 0, 500);
        return $str;
    }

    public static function hash($hash, $length)
    {
        $hash = preg_replace('/[^a-zA-Z0-9]/iu', '', $hash);
        return substr($hash, 0, $length);
    }

    public static function price($price)
    {
        if (is_string($price)) {
            if (strpos($price, '(') > 0) {
                $price = explode('(', $price);
                $price = $price[0];
            }
            if (strpos($price, '/') > 0) {
                $price = explode('/', $price);
                $price = $price[0];
            }
            $price = preg_replace("/[^0-9,.]+/", "", $price);
            $pos1 = strpos($price, '.');
            $pos2 = strpos($price, ',');
            if ($pos1 > $pos2 && $pos2 > 0) {
                $price = str_replace(',', '', $price);
            } elseif ($pos2 > $pos1 && $pos1 > 0) {
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            } elseif ($pos1 === false && $pos2 > 0) {
                if (strlen($price) - $pos2 > 3) {
                    $price = str_replace(',', '', $price);
                } else {
                    $price = str_replace(',', '.', $price);
                }
            }
        } else {
            return $price;
        }
        return (float)$price;
    }

    public static function number($str)
    {
        if (is_null($str)) {
            $str = '';
        }
        $str = preg_replace('/[^0-9\.,]/iu', '', $str);

        if (is_null($str)) {
            $str = '';
        }

        if (strpos($str, ',') !== false) {
            if (strpos($str, '.') !== false) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        }
        if ($str > '' && strpos($str, '.') !== false) {
            $tmp = explode('.', $str);
            $tmp[0] = intval($tmp[0]);

            if (! empty($tmp[1])) {
                if (intval($tmp[1]) > 0) {
                    $tmp[1] = rtrim($tmp[1], '0');
                    $str = implode('.', $tmp);
                } else {
                    $str = $tmp[0];
                }
            } else {
                $str = $tmp[0];
            }
        }

        return $str;
    }

    public static function digitFloat($value)
    {
        if ($value <> "") {
            if (1 === preg_match('~[0-9]~', $value)) {
                $value = (float)$value;
            } else {
                $value = '';
            }
        }
        return ($value);
    }

    public static function filenamePart($part)
    {
        return preg_replace("/[^a-zA-Z0-9_]/", "", $part);
    }

    public static function digitPx($value)
    {
        if ($value <> "") {
            $units = substr($value, -2, 2);
            $v = $value;
            settype($v, "integer");
            if ($units == "px" or $units == "vh" or $units == "vw") {
                $value = $v . $units;
            } else {
                if ($v != 0 or $value == "0") {
                    $value = $v . "px";
                }
                if ($v == 0) {
                    $value = "";
                }
            }
        }
        return ($value);
    }

    public static function ip($ip)
    {
        if (!is_string($ip)) {
            return '';
        }

        $ip = preg_replace('/[^0-9a-fA-F:.]/', '', $ip);
        if (strpos($ip, ':') !== false) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ip = '';
            }
        } elseif (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = '';
        }

        return $ip;
    }

    public static function ipnet($ipnet)
    {
        list ($subnet, $bits) = strpos($ipnet, '/') !== false ? explode('/', $ipnet) : [$ipnet, 32];
        $subnet = self::ip($subnet);
        if ($subnet == '') {
            return '';
        }
        $bits = (int)$bits;
        if ($bits < 1 || $bits > 32) {
            return '';
        }

        return "$subnet/$bits";
    }

    /**
     * @param string $timezone
     * @param string $default
     * @return \DateTimeZone
     * @throws \Exception
     */
    public static function timezone($timezone, $default = 'GMT+3:00'): \DateTimeZone
    {
        try {
            return new \DateTimeZone((string) $timezone);
        } catch (\Exception $exception) {
        }
        return new \DateTimeZone($default);
    }

    /**
     * Очистка данных от паролей.
     * @param $data
     * @param string[] $params Список очищаемых полей по умолчанию. Можно передавать другой.
     * @param string[] $addHidePrefixes Список дополнительных префиксов. Если в строке встречается такое, то идет замена данных до конца строки (или символа & - пока так)
     * @return array|mixed|string|string[]|null
     */
    public static function hideLogData(
        $data,
        $params = [
            'password',
            'pass',
            'api_key'
        ],
        $addHidePrefixes = [
            'Authorization: OAuth ',
            'Authorization: Basic ',
            'Authorization: Bearer ',
            'PddToken: ',
            'Authorization: ',
            'X-Token: ',
            'X-API-Key: '
        ]
    ) {
        foreach ($params as $param) {
            $data = static::hideLogDataParam($data, $param, $addHidePrefixes);
        }
        return $data;
    }

    /**
     * Закрывает непустое значение параметра, если он присутствует в данных.
     * @param $data
     * @param $param
     * @param string[] $addHidePrefixes
     * @return array|mixed|string|string[]|null
     */
    public static function hideLogDataParam(
        $data,
        $param,
        $addHidePrefixes = [
            'Authorization: OAuth ',
            'Authorization: Basic ',
            'Authorization: Bearer ',
            'PddToken: ',
            'Authorization: ',
            'X-Token: ',
            'X-API-Key: '
            ]
    ) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (!empty($value)) {
                    if ($key === $param) {
                        $data[$param] = static::hideString($data[$param]);
                    } else {
                        $data[$key] = static::hideLogDataParam($value, $param);
                    }
                }
            }
        } elseif (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                if (!empty($value)) {
                    if ($key === $param) {
                        $data->$param = static::hideString($data->$param);
                    } else {
                        $data->$key = static::hideLogDataParam($value, $param);
                    }
                }
            }
        } else {
            foreach (array_merge($addHidePrefixes, [$param . '=']) as $prefix) {
                if (preg_match('/' . $prefix . '([^\s&]+)/i', $data ?? '')) {
                    $data = preg_replace_callback('/' . $prefix . '([^\s&]+)/i', function ($matches) {
                        return str_replace($matches[1], static::hideString($matches[1]), $matches[0]);
                    }, $data);
                }
            }
        }

        return $data;
    }

    /**
     * Скрыть часть строки звездочками.
     * @param $str
     * @return string
     */
    public static function hideString(string $str): string
    {
        if (!is_string($str)) {
            return '[***]';
        }
        $len = mb_strlen($str);
        $symbolsCount = min(intdiv($len, 7), 2); // Обычно обрезается в зависимости от длины строки, но - ограничение, оставляем не более 2 символов с каждого края
        return (
            $symbolsCount
            ? mb_substr($str, 0, $symbolsCount)
            : '*'
        ) . '***' . (
            $symbolsCount
            ? mb_substr($str, $len - $symbolsCount, $len)
            : '*'
        );
    }
}