<?php
namespace Core\Framework;

class Response extends ResponseHeader
{
    static $instance = null;
    private $end_response = 0;
    private $swoole_http_response = null;

    static function getInstance(\swoole_http_response $swoole_http_response = null)
    {
        if($swoole_http_response !== null) {
            self::$instance = new Response($swoole_http_response);
        }
        return self::$instance;
    }

    function __construct(\swoole_http_response $swoole_http_response)
    {
        $this->swoole_http_response = $swoole_http_response;
    }

    function end()
    {
        if(!$this->end_response) {
            $this->end_response = 1;
            return true;
        }else{
            return false;
        }
    }

    function isEndResponse()
    {
        return $this->end_response;
    }

    public function redirect($url)
    {
        if(!$this->isEndResponse()) {
            $this->setStatus(Status::CODE_MOVED_PERMANENTLY);
            $this->setHeader('Location', $url);
        }else{
            trigger_error('response has end');
        }
    }

    public function getSwooleResponse()
    {
        return $this->swoole_http_response;
    }

    public function setCookie($name, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httponly = null)
    {
        if(!$this->isEndResponse()) {
            $temp = " {$name}={$value};";
            if($expire != null) {
                $temp .= " Expires=".date('D, d M Y H:i:s', $expire).' GMT;';
                $maxAge = $expire - time();
                $temp .= " Max-age={$maxAge};";
            }
            if($path != null) {
                $temp .= " Path={$path};";
            }
            if($domain != null) {
                $temp .= " Domain={$domain};";
            }
            if($secure != null) {
                $temp .= " Secure;";
            }
            if($httponly != null) {
                $temp .= " HttpOnly;";
            }
            $this->setCookie('Set-Cookie', $temp);
            return true;
        }else{
            trigger_error('response has end');
            return false;
        }
    }

    /**
     * 写数据到内存中
     *
     * @param $view
     * @param $params
     * @return bool
     */
    function view($view, $params)
    {
        if(!$this->isEndResponse()) {
            ob_start();
            $data = ob_get_contents();
            ob_end_clean();
            $this->getBody()->write($data);
            return true;
        }else{
            trigger_error('response has end');
            return false;
        }
    }

    /**
     * 写入json到内存中
     *
     * @param int $statusCode
     * @param null $result
     * @param null $msg
     * @return bool
     */
    function writeJson($statusCode = 200, $result = null, $msg = null)
    {
        if(!$this->isEndResponse()) {
            $this->getBody()->rewind();
            $data = [
                'code' => $statusCode,
                'result' => $result,
                'msg' => $msg
            ];
            $this->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $this->setHeader('Content-Type', 'application/json;charset=utf-8');
            $this->setStatus($statusCode);
            return true;
        }else{
            trigger_error('response has end');
            return false;
        }
    }
}