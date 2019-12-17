<?php
namespace app\cic\controller;

use app\common\controller\AppBase;
use think\Exception;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;
use app\common\model\Address as AddressModel;

class Address extends AppBase
{
    /*
     * 获取地址
    */
    public function getRegion()
    {
        $ParentID = input("ParentID", 0);
        $res = model("region")->getRegion($ParentID);
        if (empty($res)) {
            return apiReturn(['code' => 1006]);
        }
        return apiReturn(['code' => 200, 'data' => $res]);
    }

    /*
     * 保存用户收货地址
     * @param int $ID
     * @param string
     * @param string
     * @Return: array
     * */
    public function saveAddress()
    {
        $rule = [
            ['AddressID', 'number', "地址id无效"],
            ['ContactName', 'require|length:1,20', '姓名必须是1-20的长度'],
            ['CityCode', 'length:1,50', 'CityCode无效的长度'],
            ['City', 'require|length:1,80', '请填写正确的城市名称'],
            ['ProvinceCode', 'length:1,50', 'ProvinceCode无效的长度'],
            ['Province', 'require|length:1,50', '请填写正确的省名称'],
            ['AreaCode', 'length:1,50', 'AreaCode无效的长度'],
            ['Area', 'require|length:1,50', '请填写正确的区名称'],
            ['Mobile', 'require|length:5,11|/^1\d{10}/', '无效的电话位数|无效的电话'],
            ['PostalCode', 'length:6', "邮政编码必须是6位"],
            ['Detail', 'require|length:1,50', "详细地址必须是1-50位"],
            ['IsDefault', 'require|in:0,1', "IsDefault必须是0或者1"],
        ];
        $request = request();
        $params = $request->param();
        $param = $this->getAddressParams($params);
        $param['CustomerID'] = $this->uid;
        $validate = $this->validate($params, $rule);
        if (true !== $validate) {
            return $this->result([], 100, $validate);
        }

        $AddressModel = new AddressModel();
        $where = [];
        $oldDefault = null;
        if (isset($param['IsDefault']) && $param['IsDefault'] == 1) {
            $oldWhere['AddressID'] = 1;//假数据$this->uid
            $oldData['IsDefault'] = 0;
            $oldDefault = $AddressModel->update($oldData, $oldWhere);
        }
        $address = null;
        if (!empty($param['AddressID'])) {
            //更新地址
            $where['CustomerID'] = $param['CustomerID'];
            $where['AddressID'] = $param['AddressID'];
            $address = $AddressModel->get($where);
            if (empty($address)) {
                return $this->result([], 1001, '地址不存在');
            }
        } else {
            $address = new AddressModel();
            $count = $address->where('AddressID', $param['CustomerID'])->count();
            if ($count >= 10) {
                return $this->result([], 1002, '最多只允许新建10个地址');
            }
        }
        $res = $address->save($param);
        if (!empty($address->AddressID)) {
            $data['AddressID'] = $address->AddressID;
            return $this->result($data);
        } else {
            return $this->result((object)[], 1003, $validate);
        }
    }

    /*
    * 获取用户收货地址列表
    * @param int CustomerID
    * @Return: array
    * */
    public function getAddressList()
    {
        $addressModel = new AddressModel();
        $where['CustomerID'] = $this->uid;
        $addressData = $addressModel->where($where)->order('IsDefault desc,UpdateTime desc')->select();
        return $this->result($addressData);
    }

    /*
    * 获取用户收货地址
    * @param int $ID
    * @param int CustomerID
    * @Return: array
    * */
    public function getAddress()
    {
        $rule = [
            ['AddressID', 'require|number', "地址id无效"],
        ];
        $request = request();
        $params = $request->param();
        $validate = $this->validate($params, $rule);
        if (true !== $validate) {
            return $this->result([], 1004, $validate);
        }
        //获取收获地址
        $addressModel = new AddressModel();
        $where['CustomerID'] = $this->uid;
        $where['AddressID'] = $params['AddressID'];
        $addressData = $addressModel->get($where);
        $addressData = $addressData ?: (object)[];
        return $this->result($addressData);
    }

    /*
    * 删除用户收货地址
    * @param int $AddressID
    * @Return: array
    * */
    function delAddress()
    {
        $rule = [
            ['AddressID', 'number', "地址id无效"],
        ];
        $request = request();
        $param = $request->param();
        $validate = $this->validate($param, $rule);
        if (true !== $validate) {
            return $this->result([], 1004, $validate);
        }
        $where = [];
        $where['CustomerID'] = $this->uid;
        $where['AddressID'] = $param['AddressID'];
        $addressModel = new AddressModel();

        $res = $addressModel->where($where)->delete();
        if ($res !== false) {
            return $this->result($res);
        } else {
            return $this->result(0, 1005, '删除失败');
        }
    }

    private function getAddressParams($data)
    {
        $params = [];
        $this->getParam($params, 'AddressID', $data, 'AddressID');
        $this->getParam($params, 'CustomerID', $data, 'CustomerID');
        $this->getParam($params, 'ContactName', $data, 'ContactName');
        $this->getParam($params, 'Detail', $data, 'Detail');
        $this->getParam($params, 'CityCode', $data, 'CityCode');
        $this->getParam($params, 'City', $data, 'City');
        $this->getParam($params, 'ProvinceCode', $data, 'ProvinceCode');
        $this->getParam($params, 'Province', $data, 'Province');
        $this->getParam($params, 'Area', $data, 'Area');
        $this->getParam($params, 'AreaCode', $data, 'AreaCode');
        $this->getParam($params, 'PostalCode', $data, 'PostalCode');
        $this->getParam($params, 'Mobile', $data, 'Mobile');
        $this->getParam($params, 'IsDefault', $data, 'IsDefault', 0);
        return $params;
    }

}
