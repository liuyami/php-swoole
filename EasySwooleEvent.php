<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:33.
 */

namespace EasySwoole\EasySwoole;

use App\Process\HotReload;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\FastCache\Cache;
use EasySwoole\FastCache\CacheProcess;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Utility\File;

class EasySwooleEvent implements Event
{
    public static function initialize()
    {
        // TODO: Implement initialize() method.
        date_default_timezone_set('Asia/Shanghai');
    }

    public static function mainServerCreate(EventRegister $register)
    {
        $swooleServer = ServerManager::getInstance()->getSwooleServer();

        //自适应热重启 虚拟机下可以传入 disableInotify => true 强制使用扫描式热重启 规避虚拟机无法监听事件刷新
        //$swooleServer->addProcess((new HotReload('HotReload', ['disableInotify' => false]))->getProcess());

        /*
         * FastCache 落地重启恢复
         * 官网文档没有及时更新：http://easyswoole.com/Manual/3.x/Cn/_book/SystemComponent/FastCache.html
         */
        Cache::getInstance()->setTickInterval(5 * 1000); //设置定时频率
        Cache::getInstance()->setOnTick(function (CacheProcess $cacheProcess) {
            $data = [
                'data' => $cacheProcess->getSplArray(),
                'queue' => $cacheProcess->getQueueArray(),
            ];
            $path = EASYSWOOLE_ROOT.'/Temp/'.$cacheProcess->getProcessName();
            File::createFile($path, serialize($data)); //每隔5秒将数据存回文件
        });

        Cache::getInstance()->setOnStart(function (CacheProcess $cacheProcess) {
            $path = EASYSWOOLE_ROOT.'/Temp/'.$cacheProcess->getProcessName();
            if (is_file($path)) {
                $data = unserialize(file_get_contents($path));
                $cacheProcess->setQueueArray($data['queue']);
                $cacheProcess->setSplArray($data['data']);
            }//启动时将存回的文件重新写入
        });

        Cache::getInstance()->setOnShutdown(function (CacheProcess $cacheProcess) {
            $data = [
                'data' => $cacheProcess->getSplArray(),
                'queue' => $cacheProcess->getQueueArray(),
            ];
            $path = EASYSWOOLE_ROOT.'/Temp/'.$cacheProcess->getProcessName();
            File::createFile($path, serialize($data)); //在守护进程时,php easyswoole stop 时会调用,落地数据
        });
    }

    public static function onRequest(Request $request, Response $response): bool
    {
        // TODO: Implement onRequest() method.
        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterAction() method.
    }

    public static function onReceive(\swoole_server $server, int $fd, int $reactor_id, string $data): void
    {
    }
}
