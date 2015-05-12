<?php

class WeixinController extends Yaf_Controller_Abstract {

    private $pdo = null;

    public function init() {
        $this->pdo = new PDO('sqlite:api.db');
    }

    public function accessTokenAction() {
        Yaf_DisPatcher::getInstance()->disableView();
        header('Content-type: application/json');
        $id = $this->getRequest()->get('id');
        $appId = $this->getRequest()->get('appId');
        $appSecret = $this->getRequest()->get('appSecret');
        if(intval($id) > 0){
            echo json_encode($this->accessTokenWithCacheById($id));
        } elseif ($appId && $appSecret) {
            echo json_encode($this->accessTokenWithCacheByAppId($appId, $appSecret));
        } else {
            echo json_encode(array('resultMsg' => 'params error'));
        }
    }

    public function jssdkSignPackageAction(){
        Yaf_DisPatcher::getInstance()->disableView();
        header('Content-type: application/json');
        $id = $this->getRequest()->get('id');
        $appId = $this->getRequest()->get('appId');
        $appSecret = $this->getRequest()->get('appSecret');
        $url = $this->getRequest()->get('url');
        if(empty($url)) {
            $url = urlencode($_SERVER['HTTP_REFERER']);
        }
        if(empty($url)) {
            echo json_encode(array('resultMsg' => 'no url'));
            return;
        }

        if(intval($id) > 0){
            $signPackage = $this->jssdkSignPackageById($id, $url);
            unset($signPackage['url']);
            unset($signPackage['rawString']);
            echo json_encode($signPackage);
            return;
        } elseif ($appId && $appSecret) {
            $signPackage = $this->jssdkSignPackageByAppId($appId, $appSecret, $url);
            unset($signPackage['url']);
            unset($signPackage['rawString']);
            echo json_encode($signPackage);
            return;
        } else {
            $rawUrl = urldecode($url);
            if(! preg_match('/^http[s]?:\/\/(\w+[\.\w+]+)\//', $rawUrl, $match)){
                echo json_encode(array('resultMsg' => 'invalid url'));
                return;
            }
            $domain = $match[1];
            $statement = $this->pdo->prepare("SELECT appId, appSecret FROM weixin AS w
                LEFT JOIN weixin_jsapi_security_domain AS d ON w.id = d.weixin_id
                WHERE d.jsapi_security_domain = ? LIMIT 0, 1");
            $statement->bindParam(1, $domain);
            $statement->execute();
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            if(count($result) == 0){
                echo json_encode(array('resultMsg' => 'no weixin can sign this url'));
                return;
            }
            $result = $result[0];
            $signPackage = $this->jssdkSignPackageByAppId($result['appId'], $result['appSecret'], $url);
            unset($signPackage['url']);
            unset($signPackage['rawString']);
            echo json_encode($signPackage);
            return;
        }
    }

    public function authorizeBridgeAction() {
        Yaf_DisPatcher::getInstance()->disableView();
        $callbackUrl = $this->getRequest()->get('callbackUrl');
        $callbackUrl = urldecode($callbackUrl);
        if(strpos($callbackUrl, '&') === false) {
            $callbackUrl .= '?';
        } else {
            $callbackUrl .= '&';
        }
        $callbackUrl .= 'code='.$this->getRequest()->get('code');
        $this->getResponse()->setRedirect($callbackUrl);
    }

    private function accessTokenWithCacheById($id){
        $statement = $this->pdo->prepare("SELECT * FROM weixin WHERE id = ? LIMIT 1");
        $statement->bindParam(1, $id);
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        if(count($result) == 0){
            return array('resultMsg' => "id not exists.");
        }
        $result = $result[0];
        return $this->accessTokenWithCacheByAppId($result['appId'], $result['appSecret']);
    }

    private function accessTokenWithCacheByAppId($appId, $appSecret){
        $statement = $this->pdo->prepare("SELECT * FROM weixin WHERE appId = ? AND appSecret = ? LIMIT 0, 1");
        $statement->bindParam(1, $appId);
        $statement->bindParam(2, $appSecret);
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        if(count($result) == 0){
            $json = $this->accessToken($appId, $appSecret);
            $statement = $this->pdo->prepare("INSERT INTO weixin(appId, appSecret, access_token, access_token_expires)
                VALUES (?, ?, ?, ?)");
            $statement->bindParam(1, $appId);
            $statement->bindParam(2, $appSecret);
            $statement->bindParam(3, $json['access_token']);
            $statement->bindParam(4, $json['access_token_expires']);
            $statement->execute();
            return array(
                'access_token' => $json['access_token'], 
                'access_token_expires' => $json['access_token_expires']
            );
        }
        $result = $result[0];
        if($result['access_token'] && $result['access_token_expires'] > time()) {
            return array(
                'access_token' => $result['access_token'], 
                'access_token_expires' => $result['access_token_expires']
            );
        } else {
            $json = $this->accessToken($appId, $appSecret);
            $statement = $this->pdo->prepare("UPDATE weixin SET access_token = ?, access_token_expires = ? 
                WHERE appId = ? AND appSecret = ?");
            $statement->bindParam(1, $json['access_token']);
            $statement->bindParam(2, $json['access_token_expires']);
            $statement->bindParam(3, $appId);
            $statement->bindParam(4, $appSecret);
            $statement->execute();
            return array(
                'access_token' => $json['access_token'], 
                'access_token_expires' => $json['access_token_expires']
            );
        }
    }

    private function accessToken($appId, $appSecret) {
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appId=%s&secret=%s';
        $url = sprintf($url, $appId, $appSecret);
        $json = json_decode($this->httpGet($url), true);
        $json['access_token_expires'] = time() + $json['expires_in'] - 60; // 60秒误差
        unset($json['token_expires']);
        return $json;
    }

    // private function jsapiTicketWithCacheById($id) {
    //     $statement = $this->pdo->prepare("SELECT * FROM weixin WHERE id = ? LIMIT 1");
    //     $statement->bindParam(1, $id);
    //     $statement->execute();
    //     $result = $statement->fetchAll(PDO::FETCH_ASSOC);
    //     if(count($result) == 0){
    //         return array('resultMsg' => "id not exists.");
    //     }
    //     $result = $result[0];
    //     return $this->accessTokenWithCacheByAppId($result['appId'], $result['appSecret']);
    // }

    private function jsapiTicketWithCacheByAppId($appId, $appSecret) {
        $statement = $this->pdo->prepare("SELECT * FROM weixin WHERE appId = ? AND appSecret = ? LIMIT 0, 1");
        $statement->bindParam(1, $appId);
        $statement->bindParam(2, $appSecret);
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        if(count($result) != 0 && $result[0]['jsapi_ticket'] && $result[0]['jsapi_ticket_expires'] > time()) {
            $result = $result[0];
            return array(
                'jsapi_ticket' => $result['jsapi_ticket'], 
                'jsapi_ticket_expires' => $result['jsapi_ticket_expires']
            );
        } else {
            $json = $this->jsapiTicket($appId, $appSecret);
            $statement = $this->pdo->prepare("UPDATE weixin SET jsapi_ticket = ?, jsapi_ticket_expires = ?
                WHERE appId = ? AND appSecret = ?");
            $statement->bindParam(1, $json['jsapi_ticket']);
            $statement->bindParam(2, $json['jsapi_ticket_expires']);
            $statement->bindParam(3, $appId);
            $statement->bindParam(4, $appSecret);
            $statement->execute();
            return array(
                'jsapi_ticket' => $json['jsapi_ticket'], 
                'jsapi_ticket_expires' => $json['jsapi_ticket_expires']
            );
        }
    }

    private function jsapiTicket($appId, $appSecret) {
        $access_token = $this->accessTokenWithCacheByAppId($appId, $appSecret);
        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=%s";
        $url = sprintf($url, $access_token['access_token']);
        $json = json_decode($this->httpGet($url), true);
        $json['jsapi_ticket'] = $json['ticket'];
        $json['jsapi_ticket_expires'] = time() + $json['expires_in'] - 60; // 60秒误差
        unset($json['ticket']);
        unset($json['token_expires']);
        return $json;
    }

    private function httpGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function jssdkSignPackageById($id, $url) {
        $statement = $this->pdo->prepare("SELECT * FROM weixin WHERE id = ? LIMIT 1");
        $statement->bindParam(1, $id);
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        if(count($result) == 0){
            return array('resultMsg' => "id not exists.");
        }
        $result = $result[0];
        return $this->jssdkSignPackageByAppId($result['appId'], $result['appSecret'], $url);
    }

    private function jssdkSignPackageByAppId($appId, $appSecret, $url) {
        $url = urldecode($url);
        $jsapiTicket = $this->jsapiTicketWithCacheByAppId($appId, $appSecret);
        $jsapiTicket = $jsapiTicket['jsapi_ticket'];
        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $signPackage = array(
            "appId"     => $appId,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        );
        return $signPackage; 
    }

}