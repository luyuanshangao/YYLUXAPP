<?php
namespace app\common\helpers;

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
            return null;
        }
        return $this->handler->rPush($key, $value1);
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


    public function brPop($key,$timeout=0) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->brPop($key,$timeout);
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
    //尾部输出
    public function LPOP($value){
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->LPOP($value);
    }



    /**
     * Returns the set cardinality (number of elements) of the set stored at key.
     *
     * @param   string $key
     *
     * @return  int   the cardinality (number of elements) of the set, or 0 if key does not exist.
     * @link    http://redis.io/commands/scard
     * @example
     * <pre>
     * $redisCluster->sAdd('key1' , 'set1');
     * $redisCluster->sAdd('key1' , 'set2');
     * $redisCluster->sAdd('key1' , 'set3');   // 'key1' => {'set1', 'set2', 'set3'}
     * $redisCluster->sCard('key1');           // 3
     * $redisCluster->sCard('keyX');           // 0
     * </pre>
     */
    public function sCard($key) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->sCard($key);
    }

    /**
     * Returns all the members of the set value stored at key.
     * This has the same effect as running SINTER with one argument key.
     *
     * @param   string $key
     *
     * @return  array   All elements of the set.
     * @link    http://redis.io/commands/smembers
     * @example
     * <pre>
     * $redisCluster->del('s');
     * $redisCluster->sAdd('s', 'a');
     * $redisCluster->sAdd('s', 'b');
     * $redisCluster->sAdd('s', 'a');
     * $redisCluster->sAdd('s', 'c');
     * var_dump($redisCluster->sMembers('s'));
     *
     * ////Output:
     * //
     * //array(3) {
     * //  [0]=>
     * //  string(1) "b"
     * //  [1]=>
     * //  string(1) "c"
     * //  [2]=>
     * //  string(1) "a"
     * //}
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function sMembers($key) {
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->sMembers($key);
    }

    /**
     * Returns if member is a member of the set stored at key.
     *
     * @param   string $key
     * @param   string $value
     *
     * @return  bool    TRUE if value is a member of the set at key key, FALSE otherwise.
     * @link    http://redis.io/commands/sismember
     * @example
     * <pre>
     * $redisCluster->sAdd('key1' , 'set1');
     * $redisCluster->sAdd('key1' , 'set2');
     * $redisCluster->sAdd('key1' , 'set3'); // 'key1' => {'set1', 'set2', 'set3'}
     *
     * $redisCluster->sIsMember('key1', 'set1'); // TRUE
     * $redisCluster->sIsMember('key1', 'setX'); // FALSE
     * </pre>
     */
    public function sIsMember($key, $value) {
        if (is_null($this->handler) || false === $this->handler) {
            return false;
        }
        return $this->handler->sIsMember($key, $value);
    }

    /**
     * Adds a values to the set value stored at key.
     * If this value is already in the set, FALSE is returned.
     *
     * @param   string $key    Required key
     * @param   string $value1 Required value
     * @param   string $value2 Optional value
     * @param   string $valueN Optional value
     *
     * @return  int     The number of elements added to the set
     * @link    http://redis.io/commands/sadd
     * @example
     * <pre>
     * $redisCluster->sAdd('k', 'v1');                // int(1)
     * $redisCluster->sAdd('k', 'v1', 'v2', 'v3');    // int(2)
     * </pre>
     */
    public function sAdd($key, $value1, $value2 = null, $valueN = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->sAdd($key, $value1, $value2, $valueN);
    }

    /**
     * Adds a values to the set value stored at key.
     * If this value is already in the set, FALSE is returned.
     *
     * @param   string $key Required key
     * @param   array  $valueArray
     *
     * @return  int     The number of elements added to the set
     * @example
     * <pre>
     * $redisCluster->sAddArray('k', ['v1', 'v2', 'v3']);
     * //This is a feature in php only. Same as $redisCluster->sAdd('k', 'v1', 'v2', 'v3');
     * </pre>
     */
    public function sAddArray($key, array $valueArray) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->sAddArray($key, $valueArray);
    }

    /**
     * Removes the specified members from the set value stored at key.
     *
     * @param   string $key
     * @param   string $member1
     * @param   string $member2
     * @param   string $memberN
     *
     * @return  int     The number of elements removed from the set.
     * @link    http://redis.io/commands/srem
     * @example
     * <pre>
     * var_dump( $redisCluster->sAdd('k', 'v1', 'v2', 'v3') );    // int(3)
     * var_dump( $redisCluster->sRem('k', 'v2', 'v3') );          // int(2)
     * var_dump( $redisCluster->sMembers('k') );
     * //// Output:
     * // array(1) {
     * //   [0]=> string(2) "v1"
     * // }
     * </pre>
     */
    public function sRem($key, $member1, $member2 = null, $memberN = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->sRem($key, $member1, $member2, $memberN);
    }

    /**
     * Performs the union between N sets and returns it.
     *
     * @param   string $key1 Any number of keys corresponding to sets in redis.
     * @param   string $key2 ...
     * @param   string $keyN ...
     *
     * @return  array   of strings: The union of all these sets.
     * @link    http://redis.io/commands/sunionstore
     * @example
     * <pre>
     * $redisCluster->del('s0', 's1', 's2');
     *
     * $redisCluster->sAdd('s0', '1');
     * $redisCluster->sAdd('s0', '2');
     * $redisCluster->sAdd('s1', '3');
     * $redisCluster->sAdd('s1', '1');
     * $redisCluster->sAdd('s2', '3');
     * $redisCluster->sAdd('s2', '4');
     *
     * var_dump($redisCluster->sUnion('s0', 's1', 's2'));
     *
     * //// Output:
     * //
     * //array(4) {
     * //  [0]=>
     * //  string(1) "3"
     * //  [1]=>
     * //  string(1) "4"
     * //  [2]=>
     * //  string(1) "1"
     * //  [3]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sUnion($key1, $key2, $keyN = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->sUnion($key1, $key2, $keyN);
    }

    /**
     * Performs the same action as sUnion, but stores the result in the first key
     *
     * @param   string $dstKey the key to store the diff into.
     * @param   string $key1   Any number of keys corresponding to sets in redis.
     * @param   string $key2   ...
     * @param   string $keyN   ...
     *
     * @return  int     Any number of keys corresponding to sets in redis.
     * @link    http://redis.io/commands/sunionstore
     * @example
     * <pre>
     * $redisCluster->del('s0', 's1', 's2');
     *
     * $redisCluster->sAdd('s0', '1');
     * $redisCluster->sAdd('s0', '2');
     * $redisCluster->sAdd('s1', '3');
     * $redisCluster->sAdd('s1', '1');
     * $redisCluster->sAdd('s2', '3');
     * $redisCluster->sAdd('s2', '4');
     *
     * var_dump($redisCluster->sUnionStore('dst', 's0', 's1', 's2'));
     * var_dump($redisCluster->sMembers('dst'));
     *
     * //// Output:
     * //
     * //int(4)
     * //array(4) {
     * //  [0]=>
     * //  string(1) "3"
     * //  [1]=>
     * //  string(1) "4"
     * //  [2]=>
     * //  string(1) "1"
     * //  [3]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sUnionStore($dstKey, $key1, $key2, $keyN = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->sUnionStore($dstKey, $key1, $key2, $keyN);
    }

    /**
     * Returns the members of a set resulting from the intersection of all the sets
     * held at the specified keys. If just a single key is specified, then this command
     * produces the members of this set. If one of the keys is missing, FALSE is returned.
     *
     * @param   string $key1 keys identifying the different sets on which we will apply the intersection.
     * @param   string $key2 ...
     * @param   string $keyN ...
     *
     * @return  array, contain the result of the intersection between those keys.
     * If the intersection between the different sets is empty, the return value will be empty array.
     * @link    http://redis.io/commands/sinterstore
     * @example
     * <pre>
     * $redisCluster->sAdd('key1', 'val1');
     * $redisCluster->sAdd('key1', 'val2');
     * $redisCluster->sAdd('key1', 'val3');
     * $redisCluster->sAdd('key1', 'val4');
     *
     * $redisCluster->sAdd('key2', 'val3');
     * $redisCluster->sAdd('key2', 'val4');
     *
     * $redisCluster->sAdd('key3', 'val3');
     * $redisCluster->sAdd('key3', 'val4');
     *
     * var_dump($redisCluster->sInter('key1', 'key2', 'key3'));
     *
     * // Output:
     * //
     * //array(2) {
     * //  [0]=>
     * //  string(4) "val4"
     * //  [1]=>
     * //  string(4) "val3"
     * //}
     * </pre>
     */
    public function sInter($key1, $key2, $keyN = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->sInter($key1, $key2, $keyN);
    }

    /**
     * Performs a sInter command and stores the result in a new set.
     *
     * @param   string $dstKey the key to store the diff into.
     * @param   string $key1   are intersected as in sInter.
     * @param   string $key2   ...
     * @param   string $keyN   ...
     *
     * @return  int:    The cardinality of the resulting set, or FALSE in case of a missing key.
     * @link    http://redis.io/commands/sinterstore
     * @example
     * <pre>
     * $redisCluster->sAdd('key1', 'val1');
     * $redisCluster->sAdd('key1', 'val2');
     * $redisCluster->sAdd('key1', 'val3');
     * $redisCluster->sAdd('key1', 'val4');
     *
     * $redisCluster->sAdd('key2', 'val3');
     * $redisCluster->sAdd('key2', 'val4');
     *
     * $redisCluster->sAdd('key3', 'val3');
     * $redisCluster->sAdd('key3', 'val4');
     *
     * var_dump($redisCluster->sInterStore('output', 'key1', 'key2', 'key3'));
     * var_dump($redisCluster->sMembers('output'));
     *
     * //// Output:
     * //
     * //int(2)
     * //array(2) {
     * //  [0]=>
     * //  string(4) "val4"
     * //  [1]=>
     * //  string(4) "val3"
     * //}
     * </pre>
     */
    public function sInterStore($dstKey, $key1, $key2, $keyN = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->sInterStore($dstKey, $key1, $key2, $keyN);
    }

    /**
     * Performs the difference between N sets and returns it.
     *
     * @param   string $key1 Any number of keys corresponding to sets in redis.
     * @param   string $key2 ...
     * @param   string $keyN ...
     *
     * @return  array   of strings: The difference of the first set will all the others.
     * @link    http://redis.io/commands/sdiff
     * @example
     * <pre>
     * $redisCluster->del('s0', 's1', 's2');
     *
     * $redisCluster->sAdd('s0', '1');
     * $redisCluster->sAdd('s0', '2');
     * $redisCluster->sAdd('s0', '3');
     * $redisCluster->sAdd('s0', '4');
     *
     * $redisCluster->sAdd('s1', '1');
     * $redisCluster->sAdd('s2', '3');
     *
     * var_dump($redisCluster->sDiff('s0', 's1', 's2'));
     *
     * //// Output:
     * //
     * //array(2) {
     * //  [0]=>
     * //  string(1) "4"
     * //  [1]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sDiff($key1, $key2, $keyN = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->sDiff($key1, $key2, $keyN);
    }

    /**
     * Performs the same action as sDiff, but stores the result in the first key
     *
     * @param   string $dstKey the key to store the diff into.
     * @param   string $key1   Any number of keys corresponding to sets in redis
     * @param   string $key2   ...
     * @param   string $keyN   ...
     *
     * @return  int:    The cardinality of the resulting set, or FALSE in case of a missing key.
     * @link    http://redis.io/commands/sdiffstore
     * @example
     * <pre>
     * $redisCluster->del('s0', 's1', 's2');
     *
     * $redisCluster->sAdd('s0', '1');
     * $redisCluster->sAdd('s0', '2');
     * $redisCluster->sAdd('s0', '3');
     * $redisCluster->sAdd('s0', '4');
     *
     * $redisCluster->sAdd('s1', '1');
     * $redisCluster->sAdd('s2', '3');
     *
     * var_dump($redisCluster->sDiffStore('dst', 's0', 's1', 's2'));
     * var_dump($redisCluster->sMembers('dst'));
     *
     * //// Output:
     * //
     * //int(2)
     * //array(2) {
     * //  [0]=>
     * //  string(1) "4"
     * //  [1]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sDiffStore($dstKey, $key1, $key2, $keyN = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->sDiffStore($dstKey, $key1, $key2, $keyN);
    }

    /**
     * Returns a random element(s) from the set value at Key, without removing it.
     *
     * @param   string $key
     * @param   int    $count [optional]
     *
     * @return  string|array  value(s) from the set
     * bool FALSE if set identified by key is empty or doesn't exist and count argument isn't passed.
     * @link    http://redis.io/commands/srandmember
     * @example
     * <pre>
     * $redisCluster->sAdd('key1' , 'one');
     * $redisCluster->sAdd('key1' , 'two');
     * $redisCluster->sAdd('key1' , 'three');              // 'key1' => {'one', 'two', 'three'}
     *
     * var_dump( $redisCluster->sRandMember('key1') );     // 'key1' => {'one', 'two', 'three'}
     *
     * // string(5) "three"
     *
     * var_dump( $redisCluster->sRandMember('key1', 2) );  // 'key1' => {'one', 'two', 'three'}
     *
     * // array(2) {
     * //   [0]=> string(2) "one"
     * //   [1]=> string(2) "three"
     * // }
     * </pre>
     */
    public function sRandMember($key, $count = null) {
        if (is_null($this->handler) || false === $this->handler) {
            return array();
        }
        return $this->handler->sRandMember($key, $count);
    }

    /**
     * Get the length of a string value.
     *
     * @param   string $key
     *
     * @return  int
     * @link    http://redis.io/commands/strlen
     * @example
     * <pre>
     * $redisCluster->set('key', 'value');
     * $redisCluster->strlen('key'); // 5
     * </pre>
     */
    public function strlen($key) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->strlen($key);
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
     * 删除key
     * [del description]
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public function del($key) {
        if (is_null($this->handler) || false === $this->handler) {
            return null;
        }
        return $this->handler->del($key);
    }


}
