<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/7/24
 * Time: 13:43
 */
namespace app\admin\dxcommon;

use think\Controller;
use think\Db;
use think\Config;
use think\Session;
use think\Request;
use think\Loader;

class Auth  extends Controller
{
    protected $requestUri = '';
    protected $breadcrumb = [];
    protected $user = [];
    protected $config = [
        'auth_on'           => 1, // 权限开关
        'auth_strict'           => 0, // 是否严格模式,非严格模式的意思是不在规则表中的url不验证
        'auth_type'         => 1, // 认证方式，1为实时认证；2为登录认证。
        'auth_group'        => 'navigation_role', // 用户组数据表名
        'auth_group_access' => 'company_auth_group_access', // 用户-用户组关系表(暂时用不上)
        'auth_rule'         => 'navigation_bar', // 权限规则表
        'auth_user'         => 'user', // 用户信息表
    ];

    /**
     * 类架构函数
     * Auth constructor.
     */
    public function __construct()
    {
        if ($auth = Config::get('auth'))
        {
            $this->config = array_merge($this->config, $auth);
        }
        // 初始化request
        $this->request = Request::instance();
    }

    /**
     * 检测当前控制器和方法是否匹配传递的数组
     *
     * @param array $arr 需要验证权限的数组
     */
    public function match($arr = [])
    {
        $request = Request::instance();
        //判断是否是IT超级管理员,零时方案(自己都没权限使用了)
        $username = Session::get('username');
        if($username&&($username=='usadmin')){
            return true;
        }
        // 是否存在
        if (in_array(strtolower($request->action()), $arr) || in_array('*', $arr))
        {
            return TRUE;
        }
        // 没找到匹配
        return FALSE;
    }

    /**
     * 检查权限
     * @param       $name   string|array    需要验证的规则列表,支持逗号分隔的权限规则或索引数组
     * @param       $uid    int             认证用户的id
     * @param       string  $relation       如果为 'or' 表示满足任一条规则即通过验证;如果为 'and'则表示需满足所有规则才能通过验证
     * @param       string  $mode           执行验证的模式,可分为url,normal
     * @return bool               通过验证返回true;失败返回false
     */
    public function check($name, $uid, $relation = 'or', $mode = 'url')
    {
        $name = strtolower($name);
        if (!$this->config['auth_on'])
        {
            return true;
        }

        if (!$this->config['auth_strict'])
        {
            //如果不在规则表则返回 true,如果在,则继续验证
            $map['url']=$name;
            $count= Db::name($this->config['auth_rule'])->where($map)->count();
            if($count==0){
                return true;
            }
        }

        // 获取用户需要验证的所有有效规则列表
        $rulelist = $this->getRuleList($uid);
        if (in_array('*', $rulelist)){
            return true;
        }

        if (is_string($name))
        {
            if (strpos($name, ',') !== false)
            {
                $name = explode(',', $name);
            }
            else
            {
                $name = [$name];
            }
        }
        $list = []; //保存验证通过的规则名

        foreach ($rulelist as $rule)
        {

                if (in_array($rule, $name))
                {

                    $list[] = $rule;
                }
        }

        if ('or' == $relation && !empty($list))
        {
            return true;
        }
        $diff = array_diff($name, $list);
        if ('and' == $relation && empty($diff))
        {
            return true;
        }

        return false;
    }

    /**
     * 获得权限规则列表
     * @param integer $uid 用户id
     * @return array
     */
    public function getRuleList($uid)
    {
        static $_rulelist = []; //保存用户验证通过的权限列表
        if (isset($_rulelist[$uid]))
        {
            return $_rulelist[$uid];
        }
        if (2 == $this->config['auth_type'] && Session::has('_rule_list_' . $uid))
        {
            return Session::get('_rule_list_' . $uid);
        }
        // 读取用户规则节点
        $ids = $this->getRuleIds($uid);
        if (empty($ids))
        {
            $_rulelist[$uid] = [];
            return [];
        }

        // 筛选条件
        /*
        $where = [
            'status' => 1
        ];*/
        if (!in_array('*', $ids))
        {
            $where['id'] = ['in', $ids];
        }
        //读取用户组所有权限规则
        $this->rules = Db::name($this->config['auth_rule'])->where($where)->field('id, url')->select();
        // var_dump($this->rules);die;
        //循环规则，判断结果。
        $rulelist = []; //
        foreach ($this->rules as $rule)
        {
            //只要存在就记录
            $rulelist[$rule['id']] = strtolower($rule['url']);
        }

        $_rulelist[$uid] = $rulelist;

        //登录验证则需要保存规则列表
        if (2 == $this->config['auth_type'])
        {
            //规则列表结果保存到session
            Session::set('_rule_list_' . $uid, $rulelist);
        }
        $data=array_unique($rulelist);
        return $data;
    }

    public function getRuleIds($uid)
    {
        //读取用户所属用户组
        $groups = $this->getGroups($uid);
        $ids = []; //保存用户所属用户组设置的所有权限规则id
        foreach ($groups as $g)
        {
            $ids = array_merge($ids, explode(',', trim($g['rules'], ',')));
        }
        return $ids;
    }

    /**
     * 根据用户id获取用户组,返回值为int
     * @param  $uid int     用户id
     * @return array
     */
    public function getGroups($uid)
    {
        $where['a.id']=$uid;
        $where['ac.status']=['<>',0];//0表示禁用 svn
        // 执行查询
        $user_groups = Db::name($this->config['auth_user'])
            ->alias("a")
            ->join("navigation_role ac","a.group_id = ac.id")
            ->where($where)
            ->field('ac.power as rules')
            ->select();
        return $user_groups;
    }
}