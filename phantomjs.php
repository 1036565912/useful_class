<?php
/**
 * Created by PhpStorm.
 * User: 10365
 * Date: 2018/8/17
 * Time: 11:23
 */
namespace  Helper;
use mPDF;
use JonnyW\PhantomJs\Client as PhanClient;
/**
 * Class Helper
 * @package Helper
 * 助手类
 * @author chenlin
 * @date 2018/8/17
 */
class Helper {
    /**
     * @return string 获取域名
     */
    public static function domain(){
        return 'http://shud-t.com';
    }


    /**
     *  生成doc文档 让客户端下载
     * @params  $content  正文内容
     * @params  $fileName 文件名称
     * @author  chenlin
     * @date 2018/8/20
     */
    public static function generateDoc($content){
        if(empty($content)){
            return self::jsonError([],'当前需要转化的文档内容为空,无法转化!');
        }
        $html = '<html xmlns:v="urn:schemas-microsoft-com:vml"
         xmlns:o="urn:schemas-microsoft-com:office:office"
         xmlns:w="urn:schemas-microsoft-com:office:word" 
         xmlns:m="http://schemas.microsoft.com/office/2004/12/omml" 
         xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="UTF-8" />';
        //这里添加css样式
        $html .= '<style>'.file_get_contents(CSS_PATH.'test.css').'</style></head>';
        $html .= '<body>'.$content .'</body>';
        $html .= '</html>';
        //创建文件夹  防止文件重复
        $now_doc_path = DOC_PATH.date('Y-m-d').DIRECTORY_SEPARATOR;
        if(!is_dir($now_doc_path)){
            if(!mkdir($now_doc_path,0700)){
                return self::jsonError([],'没有权限来创建文件夹!');
            }
        }
        $doc_name = uniqid(md5(time()));
        $doc_true_name = $doc_name.'.doc';
        $handle = fopen($now_doc_path.$doc_true_name,'w+');
        $res = fwrite($handle,$html);
        fclose($handle);
        if(is_file($now_doc_path.$doc_true_name) && $res){
            return self::jsonSuccess(['file_name'=>$doc_name],'生成成功,请点击确定下载文档！');
        }else{
            return self::jsonError([],'生成失败!');
        }
    }


    /**
     *  生成pdf文件
     *  return array
     */
    public static function GeneratePdf($content){
        if(empty($content)){
            return self::jsonError([],'当前需要转化的文档内容为空,无法转化!');
        }
        $mpdf= new mPDF('zh-cn','A4','0','宋体',0,0);
        //添加css文件
        $css_file = file_get_contents(CSS_PATH.'test.css');
        $mpdf->writeHTML($css_file,1);   //直接生成了style格式的  优先级大于 外联
        $mpdf->writeHTML($content);
        //创建文件夹  防止文件重复
        $now_pdf_path = PDF_PATH.date('Y-m-d').DIRECTORY_SEPARATOR;
        if(!is_dir($now_pdf_path)){
            if(!mkdir($now_pdf_path,0700)){
                return self::jsonError([],'没有权限来创建文件夹!');
            }
        }
        $pdf_name = uniqid(md5(time()));
        $pdf_true_name = $pdf_name.'.pdf';
        //$mpdf->Output($pdf_name,'I');  //I在线展示  D是下载
        $mpdf->Output($now_pdf_path.$pdf_true_name);  //什么参数都不加  代表直接存储服务器端了
        //判断文件是否生成
        if(is_file($now_pdf_path.$pdf_true_name)){
            return self::jsonSuccess(['file_name'=>$pdf_name],'生成成功,请点击确定下载文档！');
        }else{
            return self::jsonError([],'生成失败!');
        }

    }

    /**
     *  通过phantomjs生成pdf文件
     */
    public static function phantopdf($id){
        $client = PhanClient::getInstance();
        $delay = 2;
        //设置phantomjs的执行文件的路径
        $client->getEngine()->setPath(PHANTOMJS_PATH);
        $client->isLazy(); //让客户端等待所有资源加载完毕
        $request = $client->getMessageFactory()->createPdfRequest("http://120.79.214.20:9022/index.html",'GET');
        $now_pdf_path = PDF_PATH.date('Y-m-d').DIRECTORY_SEPARATOR;
        if(!is_dir($now_pdf_path)){
            if(!mkdir($now_pdf_path,0755)){
                return self::jsonError([],'没有权限来创建文件夹!');
            }
        }
        $pdf_name = uniqid(md5(time()));
        $pdf_true_name = $pdf_name.'.pdf';
        $file_name = str_replace('\\','/',$now_pdf_path.$pdf_true_name);
        try {
            $request->setOutputFile($file_name);
            $request->setFormat('A4');
            $request->setOrientation('landscape');
            $request->setMargin('1cm');
            $request->setDelay($delay);//设置delay是因为有一些特效会在页面加载完成后加载，没有等待就会漏掉
            $response = $client->getMessageFactory()->createResponse();
            $client->send($request, $response);
        }catch(\Exception $e){
            return self::jsonError([],$e->getMessage());
        }
        if(is_file($now_pdf_path.$pdf_true_name)){
            return self::jsonSuccess(['file_name'=>$pdf_name],'生成成功,请点击确定下载文档！');
        }else{
            return self::jsonError([],'生成失败!');
        }
    }

    /**
     * json返回失败数据
     * @param $data array
     * @param $msg  string
     */
    public static function jsonError($data,$msg){
        $tmp = array();
        if(!empty($data)){
            $tmp['data'] = $data;
        }
        $tmp['msg'] = $msg;
        $tmp['code'] = 500;
        echo  json_encode($tmp);
    }

    /**
     *  json返回成功数据
     * @param $data  需要返回的数据 array
     * @param $msg   返回的信息   string
     * @author chenlin
     */
    public static function jsonSuccess($data,$msg){
        $tmp = array();
        if(!empty($data)){
            $tmp['data'] = $data;
        }
        $tmp['msg'] = $msg;
        $tmp['code'] = 200;
        echo  json_encode($tmp);
    }

}