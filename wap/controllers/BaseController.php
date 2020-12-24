<?php
namespace wap\controllers;

use common\helpers\Tool;
use Yii;
use yii\web\Controller;

class BaseController extends Controller
{
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $hostInfo = Yii::$app->request->hostInfo;  //域名 https://www.bilibili.com
        $currentRoute = Yii::$app->requestedRoute; //路由  site/index
        $getUlr = Yii::$app->request->getUrl(); //地址/site/update?id=1

        if (!defined('IS_WECHAT_AGENT')) {
            define('IS_WECHAT_AGENT', false);
        }

        if (!Tool::isMobileClient()) {
            $this->redirect(PC_HOST_PATH);
        }

        return true;
    }

    public function afterAction($action, $result)
    {
        //判断IP地区
        $data = Yii::$app->api->get('/service/check-ip');
        if($data['status']== 1) {
            return $this->render('/site/refuse-visit', [
                'data' => $data
            ]);
        }

        return parent::afterAction($action, $result); // TODO: Change the autogenerated stub
    }
}