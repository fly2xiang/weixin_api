<?php
class Bootstrap extends Yaf_Bootstrap_Abstract {

    public function _initConfig() {
        date_default_timezone_set('Asia/ShangHai');
        header('Access-Control-Allow-Origin: *');
    }

    public function _initRoute() {
        $router = Yaf_Dispatcher::getInstance()->getRouter();
        // $route = new Yaf_Route_Simple("m", "c", "a");
        // $router->addRoute("name", $route);
        $route = new Yaf_Route_Rewrite('accessToken', array('controller' => 'weixin', 'action' => 'accessToken'));
        $router->addRoute('accessToken', $route);
        $route = new Yaf_Route_Rewrite('jssdkSignPackage', array('controller' => 'weixin', 'action' => 'jssdkSignPackage'));
        $router->addRoute('jssdkSignPackage', $route);
        $route = new Yaf_Route_Rewrite('authorizeBridge', array('controller' => 'weixin', 'action' => 'authorizeBridge'));
        $router->addRoute('authorizeBridge', $route);
        $route = new Yaf_Route_Rewrite('authorizeJSBridge', array('controller' => 'weixin', 'action' => 'authorizeJSBridge'));
        $router->addRoute('authorizeJSBridge', $route);
    }

    public function _initError() {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        // error_reporting(0);
    }
}