<?php
/**
 * Created by PhpStorm.
 * User: chen
 * Date: 18-10-30
 * Time: 下午1:44
 */

namespace App\Common;
use Mail;
/** 邮件助手类 author:chenlin date:2018/10/30 */
class Email{
    /**
     * 邮件发送方法
     * @param $email 发送的邮件账号
     * @param $code  发送的验证码
     * @return mixed 返回值
     * @author chenlin
     * @date 2018/10/30
     */
    public static function sendCode($to,$code,$subject){
        $flag = Mail::send('email.email',['code' => $code],function($message) use($to,$subject){
            $message->to($to)->subject($subject);
        });
        return $flag;
    }
}