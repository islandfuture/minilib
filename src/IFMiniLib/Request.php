<?php

namespace IFMiniLib;

use IFMiniLib\Only;

class Request extends Only
{
    public $post = [];
    public $get = [];
    public $cookie = [];
    public $files = [];
    public $server = [];
    public $headers = [];
    public $args = [];

    public $isProxyRequest = false;

    public function afterConstruct($arParams = null)
    {
        $this->post = $_POST ?? [];
        $this->get = $_GET ?? [];
        $this->cookie = $_COOKIE ?? [];
        $this->files = $_FILES ?? [];
        $this->server = $_SERVER ?? [];
        if (! empty($_SERVER['argv'][1])) {
            $this->args = array_slice($_SERVER['argv'], 1);
            $this->__prepareArgs();
        }

        if (function_exists('getallheaders')) {
            $this->headers = getallheaders();
        }

        // определяем, что запрос пришел через прокси
        if (!empty($this->server['HTTP_X_TILDA_PHP_PROXY'])) {
            $this->isProxyRequest = true;
        }

        if (isset($_POST['gzcontent'])) {
            $params = json_decode(gzinflate(base64_decode($_POST['gzcontent'])), true);
            if (isset($params['post'])) {
                $this->post = $params['post'];
            }
            if (isset($params['get'])) {
                $this->get = $params['get'];
            }
            if (isset($params['cookie'])) {
                $this->cookie = $params['cookie'];
            }
            if (isset($params['headers'])) {
                $this->headers = $params['headers'];
            }
            unset($params);
        }


        if (
            isset($this->server['HTTP_CONTENT_TYPE'])
            && empty($this->post)
            && strpos($this->server['HTTP_CONTENT_TYPE'], 'x-www-form-urlencoded') === false
        ) {
            $str = $this->getRawInput(true);
            if (substr($str, 0, 1) == '{') {
                $this->post = json_decode($str, true);
            }
        }
    }

    public function __prepareArgs()
    {
        $args = [];
        foreach ($this->args as $arg) {
            $parts = explode('=', $arg, 2);
            if (count($parts) == 2) {
                $args[$parts[0]] = $parts[1];
            } else {
                $args[] = $parts[0];
            }
        }
        $this->args = $args;
    }

    public function __get($name)
    {
        $pos = strpos($name, '.');
        if ($pos === false) {
            if (isset($this->post[$name])) {
                return $this->post[$name];
            } elseif (isset($this->get[$name])) {
                return $this->get[$name];
            } elseif (isset($this->server[$name])) {
                return $this->server[$name];
            } elseif (isset($this->cookie[$name])) {
                return $this->cookie[$name];
            }
        } else {
            $type = substr($name, 0, $pos);
            $key = substr($name, $pos + 1);

            if ($type == 'post' && isset($this->post[$key])) {
                return $this->post[$key];
            } elseif ($type == 'get' && isset($this->get[$key])) {
                return $this->get[$key];
            } elseif ($type == 'server' && isset($this->server[$key])) {
                return $this->server[$key];
            } elseif ($type == 'cookie' && isset($this->cookie[$key])) {
                return $this->cookie[$key];
            } elseif ($type == 'args' && isset($this->args[$key])) {
                return $this->args[$key];
            }
        }
        return null;
    }

    /**
     * Обертка для получения сырого POST запроса
     *
     * @return false|mixed|string
     */
    public function getRawInput($unset = false)
    {
        $raw = file_get_contents('php://input');

        return $raw;
    }
}
