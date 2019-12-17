<?php
namespace app\index\dxcommon;

/**
 * Redis集群使用基础类
 * Class RedisClusterBase
 * @author tinghu.liu
 * @date 2018-05-08
 * @package app\index\dxcommon
 */
class RedisClusterBase
{
    /**
     * 对象句柄
     * @var null|\RedisCluster
     */
    protected $handler = null;

    /**
     * redis集群配置格式：
     * [
            'host'=>[
                'redis.dxinterns.com:7000',
                'redis.dxinterns.com:7001',
                'redis.dxinterns.com:7002',
            ],
            // 缓存有效期(秒) 0表示永久缓存
            'expire'=>0
        ]
     * @var array
     */
    protected $options;

    /**
     * 构造函数
     * @param array $options 缓存参数
     * @access public
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
        if (!empty($options)){
            $this->options = $options;
        }else{
            $this->options = config('redis_cluster_config');
        }
        //单例限制
        if (null === $this->handler){
            $this->handler = new \RedisCluster(NULL, $this->options['host']);
        }
    }

    /**
     * 返回RedisCluster对象句柄
     */
    public function handler(){
        return $this->handler;
    }

    /**
     * 写入缓存
     * @access public
     * @param string            $name 缓存变量名
     * @param mixed             $value  存储数据
     * @param integer|\DateTime $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        if ($expire instanceof \DateTime) {
            $expire = $expire->getTimestamp() - time();
        }
        $value = is_scalar($value) ? $value : 'redis_serialize:' . serialize($value);
        if ($expire) {
            $result = $this->handler->setex($name, $expire, $value);
        } else {
            $result = $this->handler->set($name, $value);
        }
        return $result;
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $value = $this->handler->get($name);
        if (is_null($value) || false === $value) {
            return $default;
        }
        try {
            $result = 0 === strpos($value, 'redis_serialize:') ? unserialize(substr($value, 16)) : $value;
        } catch (\Exception $e) {
            $result = $default;
        }
        return $result;
    }

    /**
     * 删除缓存
     * @param string $key 缓存变量名
     * @return boolean
     */
    public function del($key)
    {
        return $this->handler->del($key);
    }

    /**
     * Returns the specified elements of the list stored at the specified key in
     * the range [start, end]. start and stop are interpretated as indices: 0 the first element,
     * 1 the second ... -1 the last element, -2 the penultimate ...
     *
     * @param   string $key
     * @param   int    $start
     * @param   int    $end
     *
     * @return  array containing the values in specified range.
     * @link    http://redis.io/commands/lrange
     * @example
     * <pre>
     * $redisCluster->rPush('key1', 'A');
     * $redisCluster->rPush('key1', 'B');
     * $redisCluster->rPush('key1', 'C');
     * $redisCluster->lRange('key1', 0, -1); // array('A', 'B', 'C')
     * </pre>
     */
    public function lRange($key, $start, $end){
        return $this->handler->lRange($key,$start,$end);
    }

    /**
     * Adds the string values to the head (left) of the list. Creates the list if the key didn't exist.
     * If the key exists and is not a list, FALSE is returned.
     *
     * @param   string $key
     * @param   string $value1 String, value to push in key
     * @param   string $value2 Optional
     * @param   string $valueN Optional
     *
     * @return  int    The new length of the list in case of success, FALSE in case of Failure.
     * @link    http://redis.io/commands/lpush
     * @example
     * <pre>
     * $redisCluster->lPush('l', 'v1', 'v2', 'v3', 'v4')   // int(4)
     * var_dump( $redisCluster->lRange('l', 0, -1) );
     * //// Output:
     * // array(4) {
     * //   [0]=> string(2) "v4"
     * //   [1]=> string(2) "v3"
     * //   [2]=> string(2) "v2"
     * //   [3]=> string(2) "v1"
     * // }
     * </pre>
     */
    public function lPush($key, $value1){
        return $this->handler->lPush($key, $value1);
    }

    /**
     * Removes the first count occurences of the value element from the list.
     * If count is zero, all the matching elements are removed. If count is negative,
     * elements are removed from tail to head.
     *
     * @param   string $key
     * @param   string $value
     * @param   int    $count
     *
     * @return  int     the number of elements to remove
     * bool FALSE if the value identified by key is not a list.
     * @link    http://redis.io/commands/lrem
     * @example
     * <pre>
     * $redisCluster->lPush('key1', 'A');
     * $redisCluster->lPush('key1', 'B');
     * $redisCluster->lPush('key1', 'C');
     * $redisCluster->lPush('key1', 'A');
     * $redisCluster->lPush('key1', 'A');
     *
     * $redisCluster->lRange('key1', 0, -1);   // array('A', 'A', 'C', 'B', 'A')
     * $redisCluster->lRem('key1', 'A', 2);    // 2
     * $redisCluster->lRange('key1', 0, -1);   // array('C', 'B', 'A')
     * </pre>
     */
    public function lRem($key, $value, $count) {
        return $this->handler->lRem($key, $value, $count);
    }

    /**
     * Returns the size of a list identified by Key. If the list didn't exist or is empty,
     * the command returns 0. If the data type identified by Key is not a list, the command return FALSE.
     *
     * @param   string $key
     *
     * @return  int     The size of the list identified by Key exists.
     * bool FALSE if the data type identified by Key is not list
     * @link    http://redis.io/commands/llen
     * @example
     * <pre>
     * $redisCluster->rPush('key1', 'A');
     * $redisCluster->rPush('key1', 'B');
     * $redisCluster->rPush('key1', 'C');  // key1 => [ 'A', 'B', 'C' ]
     * $redisCluster->lLen('key1');       // 3
     * $redisCluster->rPop('key1');
     * $redisCluster->lLen('key1');       // 2
     * </pre>
     */
    public function lLen($key) {
        return $this->handler->lLen($key);
    }

    /**
     * Returns and removes the last element of the list.
     *
     * @param   string $key
     *
     * @return  string if command executed successfully BOOL FALSE in case of failure (empty list)
     * @link    http://redis.io/commands/rpop
     * @example
     * <pre>
     * $redisCluster->rPush('key1', 'A');
     * $redisCluster->rPush('key1', 'B');
     * $redisCluster->rPush('key1', 'C');
     * var_dump( $redisCluster->lRange('key1', 0, -1) );
     * // Output:
     * // array(3) {
     * //   [0]=> string(1) "A"
     * //   [1]=> string(1) "B"
     * //   [2]=> string(1) "C"
     * // }
     * $redisCluster->rPop('key1');
     * var_dump( $redisCluster->lRange('key1', 0, -1) );
     * // Output:
     * // array(2) {
     * //   [0]=> string(1) "A"
     * //   [1]=> string(1) "B"
     * // }
     * </pre>
     */
    public function rPop($key) {
        return $this->handler->rPop($key);
    }

    public function lPop($key) {
        return $this->handler->lPop($key);
    }

    /**
     * Adds the string values to the tail (right) of the list. Creates the list if the key didn't exist.
     * If the key exists and is not a list, FALSE is returned.
     *
     * @param   string $key
     * @param   string $value1 String, value to push in key
     * @param   string $value2 Optional
     * @param   string $valueN Optional
     *
     * @return  int     The new length of the list in case of success, FALSE in case of Failure.
     * @link    http://redis.io/commands/rpush
     * @example
     * <pre>
     * $redisCluster->rPush('r', 'v1', 'v2', 'v3', 'v4');    // int(4)
     * var_dump( $redisCluster->lRange('r', 0, -1) );
     * //// Output:
     * // array(4) {
     * //   [0]=> string(2) "v1"
     * //   [1]=> string(2) "v2"
     * //   [2]=> string(2) "v3"
     * //   [3]=> string(2) "v4"
     * // }
     * </pre>
     */
    public function rPush($key, $value1) {
        return $this->handler->rPush($key, $value1);
    }

    /**
     * 清除缓存
     * @param $key
     * @return bool
     */
    public function flushDB()
    {
        return $this->handler->flushDB();
    }

    public function __destruct()
    {
        $this->handler->close();
    }
}
