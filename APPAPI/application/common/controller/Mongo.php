<?php
namespace app\common\controller;

use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Command;
/**
 * MongoDB原生基本类
 * Class OrderLib
 * @author tinghu.liu 2018/06/23
 * @package app\common\controller
 * 配置示例如下：
 * Array
    (
        [type] => \think\mongo\Connection
        [hostname] => szmongodb01.dxqas.com
        [database] => PhoenixMall
        [username] => dev
        [password] => Dx+1234
        [hostport] => 37017
        [prefix] => dx_
        [debug] => 1
    )
 *
 */
class Mongo
{
    //主机名
    private $host_name;
    //主机名称端口号
    private $host_port;
    //用户名
    private $user_name;
    //用户名密码
    private $pass_word;
    //数据库
    private $database;
    //要操作的集合名称
    private $collection_name;

    //要读取的配置名称
    private $config_name;

    //Manager句柄
    private $manager_handle;
    //WriteConcern句柄
    private $write_concern_handle;
    //BulkWrite句柄
    private $bulk_write_handle;
    public $config = [];

    /**
     * 构造函数
     * Mongo constructor.
     * @param string $collection_name 要操作的集合名称
     * @param string $config_name 要读取的配置名
     */
    public function __construct($collection_name, $config_name = '')
    {
        $this->config = config('db_mongodb');

        $this->database = $this->config['database'];
        $this->collection_name = $collection_name;

        //$this->config['params']['replicaSet'] = $this->config['replica_name'];
        //$this->config['params']['readPreference'] = isset($this->config['read_preference']) ? $this->config['read_preference'] : 'primary';

        $this->manager_handle = new Manager($this->buildUrl());
        $this->write_concern_handle = new WriteConcern(1, 2000, true);
        $this->bulk_write_handle = new BulkWrite();
    }


    /**
     * 根据配置信息 生成适用于链接复制集的 URL
     * @return string
     */
    private function buildUrl()
    {
        $url      = 'mongodb://' . ($this->config['username'] ? "{$this->config['username']}" : '') . ($this->config['password'] ? ":{$this->config['password']}@" : '');
        $hostList = explode(',', $this->config['hostname']);
        $portList = explode(',', $this->config['hostport']);
        for ($i = 0; $i < count($hostList); $i++) {
            $url = $url . $hostList[0] . ':' . $portList[0] . ',';
        }
        return rtrim($url, ",") . '/'. ($this->config['database'] ? "{$this->config['database']}" : '');;
    }

    /**
     * 根据条件修改数据
     * @param array $filter 条件
     * 如：
     * $filter = [
            '_id'=>189
        ];
     * @param array $options 要修改的数据
     * 如：
     * $options = [
            '$set'=>[
                'Skus'=>json_decode($json)
            ]
        ];
     * @param boolean $all_match 是否全部匹配
     * @return int|null 返回修改的条数，失败返回0
     */
    public function update(array $filter, array $options, $all_match=false){
        $this->bulk_write_handle->update($filter, $options, ['multi' => $all_match, 'upsert' => false]);
        $namespace = $this->database.'.'.$this->collection_name;
        return $this->manager_handle->executeBulkWrite($namespace, $this->bulk_write_handle, $this->write_concern_handle)->getModifiedCount();
    }

    /**
     * @param string $group
     * @param array $where
     * @return mixed
     */
    public function group($group,$where){
        $document = [
            'aggregate' => $this->collection_name,
            'pipeline' => [
                [
                    '$match' => $where
                ],
                [
                    '$group' => [
                        '_id' => $group,
                        'count' => ['$sum'=>1]
                    ]
                ]
            ],
            'allowDiskUse' => false,
            'cursor' => new \stdClass(),
        ];
        $command = new Command($document);
        return $this->manager_handle->executeCommand($this->database, $command)->toArray();
    }



    /*
     * 关联查询加分页 （tp5目前不支持mongodb使用join等链式方式进行关联查询，包括框架中的关联模型好像也不支持mongodb，所以这里只能通过原生的方法实现表的关联查询）
     *@param $lookup array 关联表信息 如：'$lookup'=>[
                        'from'=>'info',    关联表
                        'localField'=>'uid',  主表关联字段
                        'foreignField'=>'uid',  副表关联字段
                        'as'=>'joinData', 数据集名称
                    ],
     *@param $where array 条件
     *@param $sort array 排序
     *@param $skip int 跳过条数
     *@param $limit int 限制条数
     * */
    public function joinSelect($lookup,$where,$sort,$skip,$limit)
    {
        $cmd = [
            'aggregate'=>$this->table,
            'pipeline'=>[
                [
                    '$lookup'=>$lookup,
                ],
                [
                    '$match'=>$where,
                ],
                [
                    '$sort'=>$sort,
                ],
                [
                    '$skip'=>$skip,
                ],
                [
                    '$limit'=>$limit,
                ],

            ],
            'explain'=>false,

        ];
        $cmdObj = new Command($cmd);
        $res = $this->command($cmdObj);
        return $res;
    }


//    public function __destruct(){
//        $this->manager_handle = null;
//    }
}