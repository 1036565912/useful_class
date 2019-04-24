<?php
namespace Helper;

/** 单例性状 */
/**
 *  
 * ＠author chenlin
 * @date 2019/4/24
 */

trait SingleTon{
    private static $instance;
    public static function getInstance(...$args){
        self::$instance = new static(...$args);
    }
}