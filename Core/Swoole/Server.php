<?php

namespace Core\Swoole;

use Core\Framework\Dispatch;
use Core\Framework\Request;
use Core\Framework\Response;

class Server
{
    protected static $instance;

    protected $server;

    public $server_config = [
        'host' => '127.0.0.1',
        'port' => 9519,
        'setting' => [
            'task_worker_num' => 2, //异步任务进程
            "task_max_request" => 10,
            'max_request' => 1000,//强烈建议设置此配置项
            'worker_num' => 2,
            "log_file" => __DIR__ . "/swoole.log",
            'pid_file' => __DIR__ . "/pid.pid",
        ]
    ];

    function __construct()
    {
        $this->server = new \swoole_http_server($this->server_config['host'], $this->server_config['port']);
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function startServer()
    {
        $this->server->set($this->server_config['setting']);
        $this->serverStart();
        $this->workerStart();
        $this->onRequest();
        $this->onTask();
        $this->onTaskFinish();
        $this->workerStop();
        $this->workerError();
        //server start
        $this->getServer()->start();
    }

    /**
     * 重点
     * 之前的fpm的模式下，request，response，header都是通过获取全局函数$_XXX获取
     * 现在使用swoole的时候，我们就不能根据之前的想法写代码，需要转换思路
     *
     * 1。request的数据都是通过获取swoole来获取
     * 2。response的status，cookie，html等数据都是先通过框架发送给swoole，再由swoole发送到用户浏览器界面
     * 3。修改代码需要重启swoole后生效
     * 4。html的数据可以先存到数据流中，最后swoole直接读取数据流发送到用户浏览器中
     * 5。swoole的类是会保存到内存中，所以单列模式的优越性会很好的体现。
     * 6。不要使用静态属性（保存单列属性除外）
     */
    private function onRequest()
    {
        $this->getServer()->on('request',
            function (\swoole_http_request $swoole_request, \swoole_http_response $swoole_response) {
                //框架请求的类
                $userRequest = new Request($swoole_request);
                //框架响应的类
                $userResponse = new Response($swoole_response);
                //框架路由
                Dispatch::getInstance()->dispatch($userRequest, $userResponse);
                //获取当前请求框架的状态码
                $status = $userResponse->getStatusCode();
                //设置swoole的状态码
                $swoole_response->status($status);
                //获取框架的header
                $headers = $userResponse->getHeaders();
                //设置swoole的header
                foreach ($headers as $header => $val) {
                    foreach ($val as $sub) {
                        $swoole_response->header($header, $sub);
                    }
                }
                //获取正文（Core\Framework\response中write/writeJson的数据）
                $write = $userResponse->getBody()->__toString();
                if (!empty($write)) {
                    $swoole_response->write($write);
                }
                //关闭文件资源（释放内存）
                $userResponse->getBody()->close();
                //结束Http响应，发送HTML内容
                $swoole_response->end();
                //设置这次请求结束
                $userResponse->end();
            });
    }

    public function getServer()
    {
        return $this->server;
    }

    private function serverStart()
    {
        $this->getServer()->on("start", function (\swoole_http_server $server) {
            echo 'server start';
        });
    }

    private function workerStart()
    {
        $this->getServer()->on("WorkerStart", function (\swoole_server $server, $workerId) {
            echo 'Worker start';
        });
    }

    private function workerStop()
    {
        $this->getServer()->on("WorkerStop", function (\swoole_server $server, $workerId) {
            echo 'Worker stop';
        });
    }

    private function onTask()
    {
        $this->getServer()->on("task", function (\swoole_http_server $server, $taskId, $workerId, $data) {
            echo 'start task';
        });
    }

    private function onTaskFinish()
    {
        $this->getServer()->on("finish", function (\swoole_http_server $server, $taskId, $taskObj) {
            echo 'task finish';
        });
    }

    private function workerError()
    {
        $this->getServer()->on("workerError", function (\swoole_http_server $server, $worker_id, $worker_pid, $exit_code) {
            echo 'worker Error';
        });
    }
}

$res = new Server();
$res->startServer();