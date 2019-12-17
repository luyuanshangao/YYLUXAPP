<?php
namespace app\common\services;
use think\Exception;
use think\exception\HttpException;
/**
 * Redis集群使用基础类
 * Class RedisClusterBase
 * @author tinghu.liu
 * @date 2018-05-08
 */
class RedisClusterBase
{
    /**
     * 对象句柄
     * @var null|\RedisCluster
     */
    protected $handler = null;

    protected $options;

    private $timeout = 5;

    private $readTimeout = 5;

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
            try{
                $this->handler = new \RedisCluster(NULL, $this->options['host']);
            }catch(\Exception $e){
                $this->handler = false;
                //修改一分钟发一次

                //增加错误邮件提醒 add by zhongning,邮件会爆炸，要做时间发送
//                (new CommonService())->sendEmailForOrderBug([
//                    'title'=>'DX Redis Connect',
//                    'content'=>'Oh my God ！ redis 挂了 ！请赶紧查看！ '.date('Y-m-d H:i:s')
//                ]);
            }
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
        if (is_null($this->handler) || false === $this->handler) {
            return false;
        }
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
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
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
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        if (is_null($this->handler) || false === $this->handler) {
            return false;
        }
        return $this->handler->del($name);
    }

    /**
     * 模糊获取key名称
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function getKey($name)
    {
        if (is_null($this->handler) || false === $this->handler) {
            return false;
        }
        return $this->handler->keys($name);
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
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
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
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
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
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
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
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
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
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->rPop($key);
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
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->rPush($key, $value1);
    }

    /**
     * BRPOP is a blocking list pop primitive.
     * It is the blocking version of RPOP because it blocks the connection when
     * there are no elements to pop from any of the given lists.
     * An element is popped from the tail of the first list that is non-empty,
     * with the given keys being checked in the order that they are given.
     * See the BLPOP documentation(http://redis.io/commands/blpop) for the exact semantics,
     * since BRPOP is identical to BLPOP with the only difference being that
     * it pops elements from the tail of a list instead of popping from the head.
     *
     * @param array $keys    Array containing the keys of the lists
     *                       Or STRING Key1 STRING Key2 STRING Key3 ... STRING Keyn
     * @param int   $timeout Timeout
     *
     * @return  array array('listName', 'element')
     * @link    http://redis.io/commands/brpop
     * @example
     * <pre>
     * // Non blocking feature
     * $redisCluster->lPush('key1', 'A');
     * $redisCluster->del('key2');
     *
     * $redisCluster->blPop('key1', 'key2', 10); // array('key1', 'A')
     * // OR
     * $redisCluster->blPop(array('key1', 'key2'), 10); // array('key1', 'A')
     *
     * $redisCluster->brPop('key1', 'key2', 10); // array('key1', 'A')
     * // OR
     * $redisCluster->brPop(array('key1', 'key2'), 10); // array('key1', 'A')
     *
     * // Blocking feature
     *
     * // process 1
     * $redisCluster->del('key1');
     * $redisCluster->blPop('key1', 10);
     * // blocking for 10 seconds
     *
     * // process 2
     * $redisCluster->lPush('key1', 'A');
     *
     * // process 1
     * // array('key1', 'A') is returned
     * </pre>
     */
    public function brPop(array $keys,$timeout=0) {
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->brPop($keys,$timeout);
    }

    /**
     * Returns the length of a hash, in number of items
     *
     * @param   string $key
     *
     * @return  int     the number of items in a hash, FALSE if the key doesn't exist or isn't a hash.
     * @link    http://redis.io/commands/hlen
     * @example
     * <pre>
     * $redisCluster->del('h');
     * $redisCluster->hSet('h', 'key1', 'hello');
     * $redisCluster->hSet('h', 'key2', 'plop');
     * $redisCluster->hLen('h'); // returns 2
     * </pre>
     */
    public function hLen($key) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->hLen($key);
    }

    /**
     * Returns the keys in a hash, as an array of strings.
     *
     * @param   string $key
     *
     * @return  array   An array of elements, the keys of the hash. This works like PHP's array_keys().
     * @link    http://redis.io/commands/hkeys
     * @example
     * <pre>
     * $redisCluster->del('h');
     * $redisCluster->hSet('h', 'a', 'x');
     * $redisCluster->hSet('h', 'b', 'y');
     * $redisCluster->hSet('h', 'c', 'z');
     * $redisCluster->hSet('h', 'd', 't');
     * var_dump($redisCluster->hKeys('h'));
     *
     * //// Output:
     * //
     * // array(4) {
     * // [0]=>
     * // string(1) "a"
     * // [1]=>
     * // string(1) "b"
     * // [2]=>
     * // string(1) "c"
     * // [3]=>
     * // string(1) "d"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hKeys($key) {
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->hKeys($key);
    }


    /**
     * Returns the values in a hash, as an array of strings.
     *
     * @param   string $key
     *
     * @return  array   An array of elements, the values of the hash. This works like PHP's array_values().
     * @link    http://redis.io/commands/hvals
     * @example
     * <pre>
     * $redisCluster->del('h');
     * $redisCluster->hSet('h', 'a', 'x');
     * $redisCluster->hSet('h', 'b', 'y');
     * $redisCluster->hSet('h', 'c', 'z');
     * $redisCluster->hSet('h', 'd', 't');
     * var_dump($redisCluster->hVals('h'));
     *
     * //// Output:
     * //
     * // array(4) {
     * //   [0]=>
     * //   string(1) "x"
     * //   [1]=>
     * //   string(1) "y"
     * //   [2]=>
     * //   string(1) "z"
     * //   [3]=>
     * //   string(1) "t"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hVals($key) {
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->hVals($key);
    }

    /**
     * Gets a value from the hash stored at key.
     * If the hash table doesn't exist, or the key doesn't exist, FALSE is returned.
     *
     * @param   string $key
     * @param   string $hashKey
     *
     * @return  string  The value, if the command executed successfully BOOL FALSE in case of failure
     * @link    http://redis.io/commands/hget
     * @example
     * <pre>
     * $redisCluster->del('h');
     * $redisCluster->hSet('h', 'a', 'x');
     * $redisCluster->hGet('h', 'a'); // 'X'
     * </pre>
     */
    public function hGet($key, $hashKey) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->hGet($key, $hashKey);
    }


    /**
     * Returns the whole hash, as an array of strings indexed by strings.
     *
     * @param   string $key
     *
     * @return  array   An array of elements, the contents of the hash.
     * @link    http://redis.io/commands/hgetall
     * @example
     * <pre>
     * $redisCluster->del('h');
     * $redisCluster->hSet('h', 'a', 'x');
     * $redisCluster->hSet('h', 'b', 'y');
     * $redisCluster->hSet('h', 'c', 'z');
     * $redisCluster->hSet('h', 'd', 't');
     * var_dump($redisCluster->hGetAll('h'));
     *
     * //// Output:
     * //
     * // array(4) {
     * //   ["a"]=>
     * //   string(1) "x"
     * //   ["b"]=>
     * //   string(1) "y"
     * //   ["c"]=>
     * //   string(1) "z"
     * //   ["d"]=>
     * //   string(1) "t"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hGetAll($key) {
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->hGetAll($key);
    }


    /**
     * Verify if the specified member exists in a key.
     *
     * @param   string $key
     * @param   string $hashKey
     *
     * @return  bool:   If the member exists in the hash table, return TRUE, otherwise return FALSE.
     * @link    http://redis.io/commands/hexists
     * @example
     * <pre>
     * $redisCluster->hSet('h', 'a', 'x');
     * $redisCluster->hExists('h', 'a');               //  TRUE
     * $redisCluster->hExists('h', 'NonExistingKey');  // FALSE
     * </pre>
     */
    public function hExists($key, $hashKey) {
        if (is_null($this->handler) || false === $this->handler) {
            return false;
        }
        return $this->handler->hExists($key,$hashKey);
    }


    /**
     * Increments the value of a member from a hash by a given amount.
     *
     * @param   string $key
     * @param   string $hashKey
     * @param   int    $value (integer) value that will be added to the member's value
     *
     * @return  int     the new value
     * @link    http://redis.io/commands/hincrby
     * @example
     * <pre>
     * $redisCluster->del('h');
     * $redisCluster->hIncrBy('h', 'x', 2); // returns 2: h[x] = 2 now.
     * $redisCluster->hIncrBy('h', 'x', 1); // h[x] ← 2 + 1. Returns 3
     * </pre>
     */
    public function hIncrBy($key, $hashKey, $value) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->hIncrBy($key, $hashKey, $value);
    }

    /**
     * Adds a value to the hash stored at key. If this value is already in the hash, FALSE is returned.
     *
     * @param string $key
     * @param string $hashKey
     * @param string $value
     *
     * @return int
     * 1 if value didn't exist and was added successfully,
     * 0 if the value was already present and was replaced, FALSE if there was an error.
     * @link    http://redis.io/commands/hset
     * @example
     * <pre>
     * $redisCluster->del('h')
     * $redisCluster->hSet('h', 'key1', 'hello');  // 1, 'key1' => 'hello' in the hash at "h"
     * $redisCluster->hGet('h', 'key1');           // returns "hello"
     *
     * $redisCluster->hSet('h', 'key1', 'plop');   // 0, value was replaced.
     * $redisCluster->hGet('h', 'key1');           // returns "plop"
     * </pre>
     */
    public function hSet($key, $hashKey, $value) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->hSet($key, $hashKey, $value);
    }

    /**
     * Adds a value to the hash stored at key only if this field isn't already in the hash.
     *
     * @param   string $key
     * @param   string $hashKey
     * @param   string $value
     *
     * @return  bool    TRUE if the field was set, FALSE if it was already present.
     * @link    http://redis.io/commands/hsetnx
     * @example
     * <pre>
     * $redisCluster->del('h')
     * $redisCluster->hSetNx('h', 'key1', 'hello'); // TRUE, 'key1' => 'hello' in the hash at "h"
     * $redisCluster->hSetNx('h', 'key1', 'world'); // FALSE, 'key1' => 'hello' in the hash at "h". No change since the
     * field wasn't replaced.
     * </pre>
     */
    public function hSetNx($key, $hashKey, $value) {
        if (is_null($this->handler) || false === $this->handler) {
            return false;
        }
        return $this->handler->hSetNx($key, $hashKey, $value);
    }

    /**
     * Retirieve the values associated to the specified fields in the hash.
     *
     * @param   string $key
     * @param   array  $hashKeys
     *
     * @return  array   Array An array of elements, the values of the specified fields in the hash,
     * with the hash keys as array keys.
     * @link    http://redis.io/commands/hmget
     * @example
     * <pre>
     * $redisCluster->del('h');
     * $redisCluster->hSet('h', 'field1', 'value1');
     * $redisCluster->hSet('h', 'field2', 'value2');
     * $redisCluster->hMGet('h', array('field1', 'field2')); // returns array('field1' => 'value1', 'field2' =>
     * 'value2')
     * </pre>
     */
    public function hMGet($key, $hashKeys) {
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->hMGet($key, $hashKeys);
    }

    /**
     * Fills in a whole hash. Non-string values are converted to string, using the standard (string) cast.
     * NULL values are stored as empty strings
     *
     * @param   string $key
     * @param   array  $hashKeys key → value array
     *
     * @return  bool
     * @link    http://redis.io/commands/hmset
     * @example
     * <pre>
     * $redisCluster->del('user:1');
     * $redisCluster->hMSet('user:1', array('name' => 'Joe', 'salary' => 2000));
     * $redisCluster->hIncrBy('user:1', 'salary', 100); // Joe earns 100 more now.
     * </pre>
     */
    public function hMSet($key, $hashKeys) {
        if (is_null($this->handler) || false === $this->handler) {
            return false;
        }
        return $this->handler->hMSet($key, $hashKeys);
    }

    /**
     * Removes a values from the hash stored at key.
     * If the hash table doesn't exist, or the key doesn't exist, FALSE is returned.
     *
     * @param   string $key
     * @param   string $hashKey1
     * @param   string $hashKey2
     * @param   string $hashKeyN
     *
     * @return  int     Number of deleted fields
     * @link    http://redis.io/commands/hdel
     * @example
     * <pre>
     * $redisCluster->hMSet('h',
     *               array(
     *                    'f1' => 'v1',
     *                    'f2' => 'v2',
     *                    'f3' => 'v3',
     *                    'f4' => 'v4',
     *               ));
     *
     * var_dump( $redisCluster->hDel('h', 'f1') );        // int(1)
     * var_dump( $redisCluster->hDel('h', 'f2', 'f3') );  // int(2)
     *
     * var_dump( $redisCluster->hGetAll('h') );
     *
     * //// Output:
     * //
     * //  array(1) {
     * //    ["f4"]=> string(2) "v4"
     * //  }
     * </pre>
     */
    public function hDel($key, $hashKey1, $hashKey2 = null, $hashKeyN = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->hDel($key, $hashKey1,$hashKey2 , $hashKeyN);
    }


    /**
     * Increment the number stored at key by one.
     *
     * @param   string $key
     * @return  int    the new value
     * @link    http://redis.io/commands/incr
     * @example
     * <pre>
     * $redis->incr('key1'); // key1 didn't exists, set to 0 before the increment and now has the value 1
     * $redis->incr('key1'); // 2
     * $redis->incr('key1'); // 3
     * $redis->incr('key1'); // 4
     * </pre>
     */
    public function incr($key){
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->incr($key);
    }

    /**
     * 判断缓存
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public function has($name)
    {
        if (is_null($this->handler) || false === $this->handler) {
            return false;
        }
        return $this->handler->get($name) ? true : false;
    }

    /**
     * 清除缓存
     * @return bool
     */
    public function clear()
    {
        if (is_null($this->handler) || false === $this->handler) {
            return false;
        }
        return $this->handler->flushDB();
    }

    public function __destruct()
    {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        $this->handler->close();
    }

    /**
     * Returns the remaining time to live of a key that has a timeout.
     * This introspection capability allows a Redis client to check how many seconds a given key will continue to be
     * part of the dataset. In Redis 2.6 or older the command returns -1 if the key does not exist or if the key exist
     * but has no associated expire. Starting with Redis 2.8 the return value in case of error changed: Returns -2 if
     * the key does not exist. Returns -1 if the key exists but has no associated expire.
     *
     * @param   string $key
     *
     * @return  int,    the time left to live in seconds.
     * @link    http://redis.io/commands/ttl
     * @example $redisCluster->ttl('key');
     */
    public function ttl($key) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->ttl($key);
    }

    /**
     * Scan the keyspace for keys.
     *
     * @param  int    $iterator Iterator, initialized to NULL.
     * @param  string $pattern  Pattern to match.
     * @param  int    $count    Count of keys per iteration (only a suggestion to Redis).
     *
     * @return array            This function will return an array of keys or FALSE if there are no more keys.
     * @link   http://redis.io/commands/scan
     * @example
     * <pre>
     * $iterator = null;
     * while($keys = $redisCluster->scan($iterator)) {
     *     foreach($keys as $key) {
     *         echo $key . PHP_EOL;
     *     }
     * }
     * </pre>
     */
    public function scan(&$iterator, $pattern = null, $count = 0) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->scan($iterator,$pattern,$count);
    }

    /**
     * Set client option.
     *
     * @param   string $name  parameter name
     * @param   string $value parameter value
     *
     * @return  bool:   TRUE on success, FALSE on error.
     * @example
     * <pre>
     * $redisCluster->setOption(RedisCluster::OPT_SERIALIZER, RedisCluster::SERIALIZER_NONE);        // don't serialize data
     * $redisCluster->setOption(RedisCluster::OPT_SERIALIZER, RedisCluster::SERIALIZER_PHP);         // use built-in serialize/unserialize
     * $redisCluster->setOption(RedisCluster::OPT_SERIALIZER, RedisCluster::SERIALIZER_IGBINARY);    // use igBinary serialize/unserialize
     * $redisCluster->setOption(RedisCluster::OPT_PREFIX, 'myAppName:');                             // use custom prefix on all keys
     * </pre>
     */
    public function setOption($name, $value) {
        return $this->handler->setOption($name,$value);
    }

    /**
     * Return all redis master nodes
     *
     * @return array
     * @example
     * <pre>
     * $redisCluster->_masters(); // Will return [[0=>'127.0.0.1','6379'],[0=>'127.0.0.1','6380']]
     * </pre>
     */
    public function _masters() {
        return $this->handler->_masters();
    }
}
