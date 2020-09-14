<?php

namespace Library;
function ensy($data, $key) {
    $key = md5($key);
    $len = strlen($data);
    $code = '';
    for ($i = 0; $i < ceil($len / 32); $i++) {
        for ($j = 0; $j < 32; $j++) {
            $p = $i * 32 + $j;
            if ($p < $len) {
                $code.= $data{$p} ^ $key{$j};
            }
        }
    }
    $code = str_replace(array(
        '+',
        '/',
        '='
    ) , array(
        '_',
        '$',
        ''
    ) , base64_encode($code));
    return $code;
}
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data) , '+/', '-_') , '=');
}
class Pay {
    private $pid;
    private $key;
    private $api;
    public function __construct($pid, $key, $api) {
        $this->pid = $pid;
        $this->key = $key;
        $this->url = $api;
    }
    /**
     * @Note  支付发起
     * @param $type   支付方式
     * @param $out_trade_no     订单号
     * @param $notify_url     异步通知地址
     * @param $return_url     回调通知地址
     * @param $name     商品名称
     * @param $money     金额
     * @param $sitename     站点名称
     * @return string
     */
    public function submit($type, $out_trade_no, $notify_url, $return_url, $name, $money, $sitename, $qr) {
        $data = ['pid' => $this->pid, 'type' => $type, 'out_trade_no' => $out_trade_no, 'notify_url' => $notify_url, 'return_url' => $return_url, 'name' => $name, 'money' => $money, 'sitename' => $sitename, 'qrapi' => $qr];
        $string = http_build_query($data);
        $keys = ensy($string, $this->pid);
        $keyss = base64url_encode($this->pid . '-' . $keys);
        $sign = substr(ensy($keyss, $this->key) , 0, 15);
        if($qr=='yes'){
        $url =  $this->url . '/submit?skey=' . $keyss . '&sign=' . $sign . '&sign_type=MD5';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
        }else{
        return  $this->url . '/submit?skey=' . $keyss . '&sign=' . $sign . '&sign_type=MD5';
        }
        }
        /**
     * @Note  退款发起
     * @param $trade_no     订单号
     */
    public function refund($trade_no,$money) {
        $data = ['pid' => $this->pid, 'trade_no' => $trade_no, 'money' => $money];
        $string = http_build_query($data);
        $keys = ensy($string, $this->pid);
        $keyss = base64url_encode($this->pid . '-' . $keys);
        $sign = substr(ensy($keyss, $this->key) , 0, 15);
        return  $this->url . '/refund?skey=' . $keyss . '&sign=' . $sign . '&sign_type=MD5';
        }
    /**
     * @Note   验证签名
     * @param $data  待验证参数
     * @return bool
     */
    public function verify($data) {
        if (!isset($data['sign']) || !$data['sign']) {
            return false;
        }
        $sign = $data['sign'];
        unset($data['sign']);
        unset($data['sign_type']);
        $sign2 = $this->getSign($data, $this->key);
        if ($sign != $sign2) {
            return false;
        }
        return true;
    }
    /**
     * @Note  生成签名
     * @param $data   参与签名的参数
     * @return string
     */
    private function getSign($data) {
        $data = array_filter($data);
        ksort($data);
        $str1 = '';
        foreach ($data as $k => $v) {
            $str1.= '&' . $k . "=" . $v;
        }
        $str = $str1 . $this->key;
        $str = trim($str, '&');
        $sign = md5($str);
        return $sign;
    }
}
class Epay
{
    private $pid;
    private $key;
    private $url;

    public function __construct($url, $pid, $key)
    {
        $this->pid = $pid;
        $this->key = $key;
        $this->url = $url;
    }

    public function pay($params)
    {
        $pay = new Pay($this->pid, $this->key, $this->url);

//支付方式(all:全接口,alipay:支付宝,qqpay:QQ钱包,wxpay:微信支付)
$type = 'all';

//订单号
$out_trade_no = $params['out_trade_no'];

//异步通知地址
$notify_url = $params['notify_url'];

//回调通知地址
$return_url = $params['return_url'];

//商品名称
$name = 'V2B-'.$params['name'];

//支付金额（保留小数点后两位）
$money = $params['money'];

//站点名称
$sitename = $_SERVER['HTTP_HOST'];

//QRAPI
$qr='no';

//发起支付
$url = $pay->submit($type, $out_trade_no, $notify_url, $return_url, $name, $money, $sitename,$qr);
        return $url;
    }

    public function verify($params)
    {
        $pay = new Pay($this->pid, $this->key, $this->url);

//接收异步通知数据
$data = $params;

//商户订单号
$out_trade_no = $data['out_trade_no'];

//验证签名
if ($pay->verify($data)) {
    //验证支付状态
    if ($data['trade_status'] == 'TRADE_SUCCESS') {
        return true;
        //这里就可以放心的处理您的业务流程了
        //您可以通过上面的商户订单号进行业务流程处理
    }
} else {
    return false;
}
    }
}
