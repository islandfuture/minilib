<?php

namespace IFMiniLib;

use IFMiniLib\Only;
use IFMiniLib\Clean;

class Response extends Only
{
    public const HTTP_OK = 200;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;

    public $content;
    public $fileName;
    public $statusCode = Response::HTTP_OK;
    public $headers = [];

    public function set($content, $code = null)
    {
        $this->content = $content;
        if ($code !== null) {
            $this->statusCode = $code;
        }
        return $this;
    }

    public function json($data, $code = Response::HTTP_OK)
    {
        $this->content = json_encode(['success' => true, 'result' => $data]);
        $this->statusCode = $code;
        $this->header('Content-Type', 'application/json');
        return $this;
    }

    public function jsonError($error, $code = Response::HTTP_BAD_REQUEST)
    {
        $this->content = json_encode(['success' => false, 'error' => $error]);
        $this->statusCode = $code;
        $this->header('Content-Type', 'application/json');
        return $this;
    }


    public function setStatusCode($code)
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }


    public function setFile($fileName)
    {
        $this->statusCode = Response::HTTP_OK;
        $this->fileName = $fileName;
        return $this;
    }

    public function sendFileAndExit()
    {
        http_response_code($this->statusCode);

        header('Content-Type: ' . mime_content_type($this->fileName));
        readfile($this->fileName);
        exit;
    }

    public function sendAndExit()
    {
        if (Core::$app->_mode !== 'cli') {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header("$name: $value", true);
            }

            if (!empty($this->content)) {
                echo $this->content;
            }
        } else {
            if (is_array($this->content) || is_object($this->content)) {
                print_r($this->content);
                echo PHP_EOL;
                exit;
            }
            echo $this->content . PHP_EOL;
        }

        exit;
    }

    public function redirect($page = '', $code = 301, $sanitize = true)
    {
        if ($sanitize) {
            $page = Clean::url($page);
        }

        if (substr($page, 0, 1) == '/') {
            $page = substr($page, 1);
        }

        if (substr($page, -1, 1) == '/') {
            $page = substr($page, 0, -1);
        }

        $this
            ->header('Location', $page)
            ->setStatusCode($code)
            ->set('');
        return $this;
    }
}
