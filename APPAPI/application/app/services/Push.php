<?php
namespace app\app\services;
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/8/14
 * Time: 14:05
 */
class Push
{
    private $title;
    private $message;
    private $image;
    private $data;
    private $is_background;

    function __construct()
    {
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function setImage($imageUrl)
    {
        $this->image = $imageUrl;
    }

    public function setPayload($data)
    {
        $this->data = $data;
    }

    public function setIsBackground($is_background)
    {
        $this->is_background = $is_background;
    }

    public function getPush()
    {
        $res = array();
        $res['data']['title'] = $this->title;
        $res['data']['is_background'] = $this->is_background;
        $res['data']['message'] = $this->message;
        $res['data']['image'] = $this->image;
        $res['data']['payload'] = $this->data;
        $res['data']['timestamp'] = date('Y-m-d G:i:s');
        return $res;
    }

    public function getData()
    {
        $res = array();
        $res['notification']['title'] = $this->title;
        $res['notification']['body'] = $this->message;
        return $res;
    }
}