<?php

namespace IFMiniLib;

use IFMiniLib\Request;

class CronAction
{
    public $maxExecutionTime = 0; // 0 - без ограничений
    public $maxRunningProcesses = 1; // 0 - без ограничений

    public function __construct()
    {
        // Установка максимального времени выполнения скрипта
        if ($this->maxExecutionTime > 0) {
            set_time_limit($this->maxExecutionTime);
        }
    }

    public static function init()
    {
        // $className = 'CliActions\\Cron\\' . ucfirst($action) . 'Action';
        // if (!class_exists($className)) {
        //     die("Class {$className} not found for cron action");

        // }

        $origCronAction = Request::I()->args[0] ?? '';
        $cronAction = explode('/', $origCronAction);
        $cronAction = array_pop($cronAction);
        $cronAction = preg_replace('/[^a-z\/\-]/', '', $cronAction);
        $cronAction = preg_replace_callback(
            '|([\-\\\])([a-z])|iu',
            function ($matches) {
                return ($matches[1] == '\\' ? $matches[1] : '') . mb_strtoupper($matches[2], 'UTF-8');
            },
            $cronAction,
            -1
        );

        $cronAction = mb_ucfirst($cronAction, 'UTF-8') ;
        $className = 'CliActions\\Cron\\' . ucfirst($cronAction);
        $cron = new $className();

        if ($cron->maxRunningProcesses > 0) {
            $count = AppUtils::I()->checkOneRun($origCronAction, false);
            if ($count >= $cron->maxRunningProcesses) {
                echo "Maximum running processes for {$origCronAction} reached. Current: {$count}. Exiting.";
                exit;
            }
        }

        return $cron;
    }

    public function run()
    {
        echo "[" . __CLASS__ . "][run]";
    }
}
