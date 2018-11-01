<?php
/**
 * Created by PhpStorm.
 * User: chen
 * Date: 18-10-16
 * Time: 上午11:48
 */

namespace App\Common;
use Config;
class Sftp{
    private $sftp_host = null;
    private $sftp_port = null;
    private $sftp_user = null;
    private $sftp_pass = null;
    private $sftp_connection = null;
    private $sftp_resSFTP = null;

    public function __construct(){
        $this->sftp_host = env('SFTP_HOST');
        $this->sftp_port = env('SFTP_PORT');
        $this->sftp_user = env('SFTP_USER');
        $this->sftp_pass = env('SFTP_PWD');

        //对sftp服务进行初始化
        $this->sftp_connection = ssh2_connect($this->sftp_host,$this->sftp_port);
        if(!ssh2_auth_password($this->sftp_connection,$this->sftp_user, $this->sftp_pass)){
            return [
                'code' => 500,
                'msg'  => '无法在文件服务器进行验证',
            ];
        }
        //初始化
        $this->initialize();
    }
    /**
     * 初始化
     */
    public function initialize(){
        $this->sftp_resSFTP = ssh2_sftp($this->sftp_connection);
        if(!$this->sftp_resSFTP){
            return [
                'code' => 500,
                'msg'  => '初始化失败',
            ];
        }
    }


    /**
     * @param $directory 文件夹
     * @return bool
     * @author chenlin
     * @date 2018/10/16
     */
    public function createDirectory($directory){
        $path = env('SFTP_ROOT_PATH').$directory.'/';
        $result = ssh2_sftp_mkdir($this->sftp_resSFTP, $path, 0755); //新建文件夹
        if(!$result){
            return [
                'code' => 500,
                'msg'  => '创建文件夹失败',
            ];

        }
        return [
            'code' => 200,
            'msg' => '创建成功!',
        ];
    }
    /**
     * sftp上传文件 不包含创建文件夹
     * @param $directory   存放的文件夹
     * @param $current_file 当前需要上传的文件
     * @param $new_file    上传到文件服务器的文件的名称
     * tip 公司离职员工的图像文件夹不会删除，当前工号也不会使用
     */
    public function upload($directory,$current_file,$new_file){
        //上传开始
        //设置一个文件
        $path = env('SFTP_ROOT_PATH').$directory.'/';
        //sftp 删除文件夹  先要删除文件夹下的文件
        //ssh2_sftp_unlink($this->sftp_resSFTP, $path.$new_file);
        //ssh2_sftp_rmdir($this->sftp_resSFTP, $path);  //确保文件夹不存在
        if(ssh2_scp_send($this->sftp_connection,$current_file,$path.$new_file,0755)){
            return [
                'code' => 200,
                'msg'  => '上传成功',
            ];
        }else{
            return [
                'code' => 500,
                'msg'  => '上传失败,请联系管理员!',
            ];
        }
    }


    /**
     * 后台审批注册审批通过后,需要进行数据的转移,生成新的数据文件夹并返回新的数据文件图片
     * @param $employee_code 员工工号
     * @param $directory 临时用户图片地址
     * @return mixed
     * @author chenlin
     * @date 2018/10/29
     */
    public function dealRegister($employee_code,$directory){
        //创建工号文件夹 并且把临时文件夹信息复制到新文件夹  临时文件夹移到register文件夹下
        $path = env('SFTP_ROOT_PATH').$employee_code.'/';
        $tmp_dir = env('SFTP_ROOT_PATH').$directory.'/';
        $register_dir = env('SFTP_ROOT_PATH').'register/'.$directory.'/';
        $cmd_one = "cp -r $tmp_dir $path";
        $result_one = ssh2_exec($this->sftp_connection,$cmd_one);
        //获取当前执行的linux命令是否发生错误 获取错误流信息 只能获取一次 之后获取就为空了 如果需要利用之前的错误信息来进行逻辑判断 则需要存入变量中
        $errorStream_one = ssh2_fetch_stream($result_one, SSH2_STREAM_STDERR);
        stream_set_blocking($errorStream_one, true);
        $error_one = stream_get_contents($errorStream_one); //这里只能回去一次 切记
        if($error_one){
            return false;
        }
        $cmd_two = "mv $tmp_dir $register_dir";
        $result_two = ssh2_exec($this->sftp_connection,$cmd_two);
        //获取当前执行的linux命令是否发生错误
        $errorStream_two = ssh2_fetch_stream($result_two, SSH2_STREAM_STDERR);
        stream_set_blocking($errorStream_two, true);
        $error_two = stream_get_contents($errorStream_two);
        if($error_two){
            return false;
        }else{
            //复制成功  生成的新的用户图片路径并且返回
            $avatar = env('IMG_HTTP_URL').$employee_code.'/0.jpeg';
            return $avatar;
        }
    }

    /**
     *修改员工的肖像照 第一张图片作为肖像照(0.jpeg)
     * @param $employee_code 员工工号
     * @param $current_file 需要上传的文件路径
     * @param $file_name 创建的文件的名称
     * @return bool
     * @author chenlin
     * @date 2018/10/31
     */
    public function changeUserImg($employee_code,$current_file,$file_name){
        //首先找到当前用户的文件夹
        $current_user_directory = env('SFTP_ROOT_PATH').$employee_code.'/';
        //删除已经存在的肖像图片
        $flag = ssh2_sftp_unlink($this->sftp_resSFTP,$current_user_directory.$file_name);
        if(!$flag){
            //删除失败 则返回
            return false;
        }
        //创建新文件
        $flag = ssh2_scp_send($this->sftp_connection,$current_file,$current_user_directory.$file_name,0755);
        if($flag){
            return true;
        }else{
            return false;
        }
    }
}