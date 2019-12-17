<?php
namespace app\admin\dxcommon;

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

    /**
     * 构造函数
     * Mongo constructor.
     * @param string $collection_name 要操作的集合名称
     * @param string $config_name 要读取的配置名
     */
    public function __construct($collection_name, $config_name = '')
    {
        if (empty($config_name)){
            $this->config_name = 'db_mongo';
        }
        $config = config($this->config_name);

        $this->host_name = $config['hostname'];
        $this->host_port = $config['hostport'];
        $this->user_name = $config['username'];
        $this->pass_word = $config['password'];
        $this->database = $config['database'];
        $this->collection_name = $collection_name;

        $url = 'mongodb://'.$this->user_name.':'.$this->pass_word.'@'.$this->host_name.':'.$this->host_port.'/'.$this->database;
        $this->manager_handle = new Manager($url);
        $this->write_concern_handle = new WriteConcern(1, 2000, true);
        $this->bulk_write_handle = new BulkWrite();
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

}