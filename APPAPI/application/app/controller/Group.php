<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\app\dxcommon\BaseApi;
use think\Log;
use app\app\services\BaseService;

class Group extends AppBase
{
    public $BaseApi = null;

    public function __construct()
    {
        parent::__construct();
        $this->BaseApi = new BaseApi();
    }

    /*
    * 新增分类
    */
    public function add()
    {
        $params = request()->post();
        $data = $this->BaseApi->addGroup($params);
        if (!empty($data['data'])) {
            $data['data'] = (int)$data['data'];
            $data['code'] = 200;
            $data['msg'] = '';
        } else {
            $data['data'] = 0;
            $data['code'] = 11000;
            $data['msg'] = !empty($data['msg']) ? $data['msg'] : '';

        }
        return $data;
    }

    public function index()
    {
        $params = input();
        $data = $this->BaseApi->indexGroup($params);
        if (empty($data['data'])) {
            $data['data'] = [];
            $data['code'] = 200;
            $data['msg'] = '';
        } else {
            foreach ($data['data'] as &$item) {
                $ProductLists['data'] = [];
                //收藏产品
                $img = ['', '', ''];
                if (!empty($item['spu'])) {
                    $spus = $item['spu'];
                    $Lang = !empty($params['lang']) ? $params['lang'] : '';
                    if (!empty($spus)) {
                        $ProductController = controller("mallextend/Product");
                        if (is_array($spus)) {
                            $spus = array_values(array_filter($spus));
                            $ProductLists = $ProductController->getWishProductLists(['ids' => $spus], $Lang);
                            if ($ProductLists['code'] == 200 && !empty($ProductLists['data']) && is_array($ProductLists['data'])) {
                                foreach ($ProductLists['data'] as $key => $v) {
                                    if ($key > 2) {
                                        break;
                                    }

                                    $img[$key] = $v['FirstProductImage'];

                                }
                            }
                        }
                    }
                }
                $item['count'] = count($ProductLists['data']);
                $item['img'] = $img;
            }
        }
        return $data;
    }

    public function getWishList()
    {
        $params = request()->post();
        $singleRule = [
            'currency' => 'require',
            'lang' => 'require',
            'customer_id' => 'require|number',
            'group_id' => 'require|number',
        ];
        $result = $this->validate($params, $singleRule);
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1003, $result);
        }
        $data = $this->BaseApi->getWishList($params);
        $Lang = $params['lang'];
        $Currency = $params['currency'];
        $PLists = [];
        if (!empty($data['data'])) {
            $spus = $data['data'];
            $ProductController = controller("mallextend/Product");
            if (is_array($spus)) {
                $spus = array_values(array_filter($spus));
                $ProductLists = $ProductController->getWishProductLists(['ids' => $spus], $Lang);
                if ($ProductLists['code'] == 200 && !empty($ProductLists['data']) && is_array($ProductLists['data'])) {
                    $BaseService = new BaseService();
                    $CurrencyArray = config("Currency");
                    foreach ($CurrencyArray as $key => $value) {
                        if ($Currency == $value['Name']) {
                            $CurrencyCode = $value['Code'];
                            break;
                        }
                    }
                    if ($Currency != "USD") {
                        $CurrentProductLists = $BaseService->changeCurrentRate($ProductLists['data'], $Currency);
                        $CurrentProductLists['data'] = is_array($CurrentProductLists) ? array_values($CurrentProductLists) : $CurrentProductLists;
                        $PLists = $CurrentProductLists['data'];
                    } else {
                        $PLists = is_array($ProductLists['data']) ? array_values($ProductLists['data']) : $ProductLists['data'];
                    }

                    foreach ($PLists as &$v) {
                        $v['currencyCodeSymbol'] = $CurrencyCode;
                        $LowPrice = !empty($v['LowPrice']) ? $v['LowPrice'] : '';
                        $HightPrice = !empty($v['HightPrice']) ? $v['HightPrice'] : '';
                        $DiscountLowPrice = !empty($v['DiscountLowPrice']) ? $v['DiscountLowPrice'] : '';
                        $DiscountHightPrice = !empty($v['DiscountHightPrice']) ? $v['DiscountHightPrice'] : '';
                        $v['id'] = (int)$v['_id'];
                        $v['LowPrice'] = (string)$LowPrice;
                        $v['HightPrice'] = (string)$HightPrice;
                        $v['OriginalLowPrice'] = (string)$DiscountLowPrice;
                        $v['OriginalHightPrice'] = (string)$DiscountHightPrice;
                    }
                }
            }
        }
        return $this->result($PLists);
    }

    public function save()
    {
        $params = request()->post();
        $data = $this->BaseApi->saveGroup($params);
        return $data;
    }

    public function del()
    {
        $params = request()->post();
        $data = $this->BaseApi->delGroup($params);
        return $data;
    }

}
