<?php
namespace Document\Controller;
use Helper\Helper;
use Model\PagesModel;

class IndexController extends BaseController {
     //设置redis缓存的生命周期
     private $_life_time = 86400;
     private $_type_arr = [0,1,2];

    /**
     *  搜索页面展示
     */
    public function index(){
        return $this->display('index');
    }

    /**
     * 结果展示页面
     */
    public function show(){
        return $this->display('show');
    }

    /**
     *  搜索结果接口
     *  @params $key int category
     *  @params  $keyword string 搜索关键字
     *  @author chenlin
     *  @date 2018/8/22
     */
    public function search(){
        $key = I('post.key',0,'intval');
        $keyword = I('post.keyword','');
        if(empty($keyword)){
            return Helper::jsonError([],'搜索关键字为空！');
        }
        $true_key = $keyword.$key;
        try{
            //检查缓存是否存在 存在则就直接获取
            if($this->_predis->exists($true_key)){
                $data = $this->_predis->get($true_key);
                return Helper::jsonSuccess(json_decode($data,true),'查询成功!');
            }
        }catch(\Exception $e){
            return Helper::jsonError([],'获取失败!');
        }
        if($key){
            $params = [
                'index' => 'test',
                'type'  => 'my_type',
                'body'  => [
                    'size' => 100,
                    'query' => [
                        'bool' => [//还可以使用filter来进行过滤  如果不需要进行相关度计算
                            'must' => [
                                [
                                    'term' => [
                                        'category' => $key,
                                    ]
                                ],
                                [
                                    'match_phrase' => [
                                        'pcontent' => $keyword
                                    ]
                                ]

                            ]
                        ]
                    ],
                    'highlight' => [
                        'pre_tags' => ['<strong>'],
                        'post_tags' => ['</strong>'],
                        'fields' => [
                            'pcontent' => new \stdClass(),
                        ]
                    ]
                ]

            ];
        }else{
            $params = [
                'index' => 'test',
                'type' => 'my_type',
                'body' => [
                    'size' => 100,
                    'query' => [
                        'match_phrase' => [
                            'pcontent' => $keyword,
                        ]
                    ],
                    'highlight' => [
                        'pre_tags' => ['<strong>'],
                        'post_tags' => ['</strong>'],
                        'fields' => [
                            'pcontent' => new \stdClass()
                        ]
                    ]
                ]
            ];
        }
        try{
            $res = $this->_elastic->search($params);
        }catch (\Exception $e){
            return Helper::jsonError([],'查询失败!');
        }
//        $data = $res['hits']['hits'][0]['highlight']['pcontent'][0]; //高亮部分
        $data1 = $res['hits']['hits'];  //二维
        $tmp = array();
        $i = 0;
        foreach ($data1 as $row){
            $highlight = str_replace(' ','',$row['highlight']['pcontent'][0]);
            $tmp[$i]['id'] = $row['_source']['id'];
            $tmp[$i]['title'] = $row['_source']['title'];
            $tmp[$i]['content'] = mb_substr($highlight,0,100,'UTF-8'); //需要调整
            $tmp[$i]['updatetime'] = date('Y-m-d H:i:s',$row['_source']['updatetime']);
            $tmp[$i]['riqi'] = C('riqi')[date('N',$row['_source']['updatetime'])];
            $i++;
        }
        try{
            //添加redis缓存
            $this->_predis->setex($true_key,$this->_life_time,json_encode($tmp));
        }catch (\Exception $e){
            return Helper::jsonError([],'存储失败!');
        }
        return  Helper::jsonSuccess($tmp,'查询成功!');
    }

    /**
     * 档案详情
     * @params $id 档案id
     */
    public function documentInfo(){
        $id = I('get.id',0,'intval');
        if(!empty($id)){
            $model = new PagesModel();
            $data = $model->find($id);
            if(empty($data)){
                return $this->error('当前ID不存在!',U('index'),2);
            }
            $this->assign('data',$data);
            return $this->display('info');
        }else{
            return $this->error('当前ID不存在!',U('index'),2);
        }
    }

    /**
     *  生成档案文档
     *  ajax访问接口
     * @param $goal string
     * @return json
     * @author chenlin
     * @date 2018/8/21
     */
    public function generateDoc(){
        $id = I('post.id',0,'intval');
        $json = Helper::phantopdf($id);
        return  $json;
    }

    /**
     * 下载文档
     * @params $type  文件类型 1 doc  2 pdf
     * @params $file_name 文件名称
     * @author chenlin
     * @date  2018/8/21
     */
    public function downloadFile(){
          $type = I('get.type');
          $type = C('file_type')[$type];
          $file_name = I('get.fileName');
          $download_file = PDF_PATH.date('Y-m-d').DIRECTORY_SEPARATOR.$file_name.$type;
          if(is_file($download_file)){
              //设置输出头
              header("Cache-Control: no-cache, must-revalidate");
              header("Pragma: no-cache");
              header("Content-Type: application/octet-stream");  //传递给客户端返回的数据是文件流
              header("Content-Disposition: attachment; filename=$file_name.$type");  //这里代表返回的数据作为附件
              $file = file_get_contents($download_file);
              echo $file;
          }
    }

    /**
     * 第三方查询接口
     * @params $type  中文 自己去转化
     * @params $desc 关键描述
     * @return json
     * @author  chenlin
     * @date 2018/8/31
     */
    public function thirdSearch(){
        $type = I('get.type','');
        $type = C('search_type')[$type];
        $desc = I('get.desc','');
        if( !in_array($type,$this->_type_arr) || empty($desc)){
            return Helper::jsonError([],'查询条件不合法!');
        }
        $type = C('search_type')[$type];
        $true_key = $desc.$type;
        //检查缓存是否存在缓存
        try{
            //检查缓存是否存在 存在则就直接获取
            if($this->_predis->exists($true_key)){
                $data = $this->_predis->get($true_key);
                return Helper::jsonSuccess(json_decode($data,true),'查询成功!');
            }
        }catch(\Exception $e){
            return Helper::jsonError([],'获取失败!');
        }
        //缓存没有数据 则在es进行查询
        if($type){
            $params = [
                'index' => 'test',
                'type'  => 'my_type',
                'body'  => [
                    'size' => 100,
                    'query' => [
                        'bool' => [//还可以使用filter来进行过滤  如果不需要进行相关度计算
                            'must' => [
                                [
                                    'term' => [
                                        'category' => $type,
                                    ]
                                ],
                                [
                                    'match_phrase' => [
                                        'pcontent' => $desc
                                    ]
                                ]

                            ]
                        ]
                    ],
                    'highlight' => [
                        'pre_tags' => ['<strong>'],
                        'post_tags' => ['</strong>'],
                        'fields' => [
                            'pcontent' => new \stdClass(),
                        ]
                    ]
                ]

            ];
        }else{
            $params = [
                'index' => 'test',
                'type' => 'my_type',
                'body' => [
                    'size' => 100,
                    'query' => [
                        'match_phrase' => [
                            'pcontent' => $desc,
                        ]
                    ],
                    'highlight' => [
                        'pre_tags' => ['<strong>'],
                        'post_tags' => ['</strong>'],
                        'fields' => [
                            'pcontent' => new \stdClass()
                        ]
                    ]
                ]
            ];
        }
        try{
            $res = $this->_elastic->search($params);
        }catch (\Exception $e){
            return Helper::jsonError([],'查询失败!');
        }

        $data1 = $res['hits']['hits'];  //二维
        $tmp = array();
        $i = 0;
        foreach ($data1 as $row){
            $highlight = str_replace(' ','',$row['highlight']['pcontent'][0]);
            $tmp[$i]['id'] = $row['_source']['id'];
            $tmp[$i]['title'] = $row['_source']['title'];
            $tmp[$i]['content'] = mb_substr($highlight,0,100,'UTF-8'); //需要调整
            $tmp[$i]['updatetime'] = date('Y-m-d H:i:s',$row['_source']['updatetime']);
            $tmp[$i]['riqi'] = C('riqi')[date('N',$row['_source']['updatetime'])];
            $i++;
        }
        try{
            //添加redis缓存
            $this->_predis->setex($true_key,$this->_life_time,json_encode($tmp));
        }catch (\Exception $e){
            return Helper::jsonError([],'存储失败!');
        }
        return  Helper::jsonSuccess($tmp,'查询成功!');

    }


    public function test(){
       //删除索引
        $params = [
            'index' => 'test'
        ];
        $res = $this->_elastic->indices()->delete($params);
        var_dump($res);
    }

    public function create(){
        $params = array(
            'index' => 'test',
            'body'  => array(
                'settings' => array(
                    'number_of_shards' => 5, //主分片数
                    'number_of_replicas' => 1, //主分片的副本数
                    'analysis' => [
                        'analyzer' => [
                            'ik' => [
                                'tokenizer' =>  'ik_max_word'
                            ]
                        ]
                    ]
                ),
                'mappings' => array(//映射
                    'my_type' => array(
                        '_all' => array(
                            //关闭所有字段检索
                            'enabled' => false,
                        ),
                        '_source' => array(
                            //存储原始文档
                            'enabled' => true
                        ),
                        'properties' => array(
                            'id' => array(
                                'type' => 'keyword', //keyword 是不分词的
                                //'fileddata' =>true,//需要使用集合或者排序的需要开启这个字段  6.x才生效
                            ),
                            'category' => [
                                'type' => 'keyword',
                            ],
                            'pcontent' => [
                                'type' => 'text',
                                'analyzer' => 'ik_max_word',
                                'search_analyzer' => 'ik_max_word',
                            ],
                            'title' => [
                                'type' => 'text',
                                'analyzer' => 'ik_max_word',
                                'search_analyzer' => 'ik_max_word'
                            ],
                            'updatetime' => [
                                'type' => 'long',  //integer  范围不够了
                                'index' => false
                            ]
                        )
                    )

                )
            )
        );
        $res = $this->_elastic->indices()->create($params);
        var_dump($res);
    }

    /**
     *  插入数据
     */
    public  function addData(){
        ini_set('memory_limit','1024M');
        set_time_limit(0);
        $model = new PagesModel();
        $data = $model->field(['id','title','category','pcontent','updatetime'])->limit(500)->select();
        $params = ['body' => []];
        foreach($data as $row ){
            $params['body'][] = [
                'index' => [
                    '_index' => 'test',
                    '_type' => 'my_type'
                ]
            ];
            $params['body'][] = [
                'id' => $row['id'],
                'pcontent' => $row['pcontent'],
                'category' => $row['category'],
                'title'   => $row['title'],
                'updatetime' => strtotime($row['updatetime'])
            ];
        }
        $res  = $this->_elastic->bulk($params);
        var_dump($res);
    }


    /**
     *  全部查询
     */
    public function query(){
        $params = [
            'index' => 'test',
            'type' => 'my_type',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ]
            ]
        ];
        $res = $this->_elastic->search($params);
        var_dump($res);
    }


}