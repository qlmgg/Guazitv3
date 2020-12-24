<?php
namespace pc\controllers;

use api\helpers\ErrorCode;
use common\models\BookChapter;
use common\models\AdvertPosition;
use common\models\UserAuth;
use common\helpers\Tool;
use common\models\WxSetting;
use Yii;
use yii\helpers\Url;
use yii\web\Cookie;
use common\models\Activity;
use common\models\ActivityLog;


class VideoController extends BaseController
{
    /**
     * 视频首页
     */
    public function actionIndex()
    {
        //获取影片系列、剧集、源信息
        $channel_id = Yii::$app->request->get('channel_id', '');
        $keyword = Yii::$app->request->get('keyword', '');

        //请求频道、搜索信息
        $channels = Yii::$app->api->get('/video/channels');

        //请求影片筛选信息
        $info = Yii::$app->api->get('/video/filter', ['channel_id' => $channel_id, 'type' => 1, 'page_size' => 24]);
        //请求热门搜索信息
        $hot = Yii::$app->api->get('/search/hot-word');

        return $this->render('index', [
            'info'      => $info,
            'hot'       => $hot,
            'channel_id'=> $channel_id,
            'keyword'   => $keyword,
            'channels'  => $channels
        ]);
    }

    /**
     * 视频新首页
     */
    public function actionNewIndex()
    {
        //获取频道信息
        $channel_id = Yii::$app->request->get('channel_id', 0);

        //请求首页信息
        $data = Yii::$app->api->get('/video/index', ['channel_id' => $channel_id]);

        //请求频道、搜索信息
        $channels = Yii::$app->api->get('/video/channels');

        //获取热搜
        $hotword = Yii::$app->api->get('/search/hot-word');

        if(!$data) {
            return $this->redirect('/site/error');
        }

        return $this->render('newindex',[
            'data'          => $data,
            'channels'      => $channels,
            'channel_id'    => $channel_id,
            'hotword'       => $hotword
        ]);
    }


    /**
     * 视频类目页
     */
    public function actionChannel()
    {
        //获取频道信息
        $channel_id = Yii::$app->request->get('channel_id', 0);

        //请求首页信息
        $data = Yii::$app->api->get('/video/index', ['channel_id' => $channel_id]);

        //请求频道、搜索信息
        $channels = Yii::$app->api->get('/video/channels');

        //获取热搜
        $hotword = Yii::$app->api->get('/search/hot-word');

        //请求影片筛选信息
        $info = Yii::$app->api->get('/video/filter', ['channel_id' => $channel_id, 'type' => 1, 'page_size' => 12]);

        if(!$data) {
            return $this->redirect('/site/error');
        }

        return $this->render('channel',[
            'data'          => $data,
            'channels'      => $channels,
            'channel_id'    => $channel_id,
            'hotword'       => $hotword,
            'info'          => $info,
        ]);
    }

    /**
     * 视频详情播放页
     */
    public function actionDetail()
    {
        //获取影片系列、剧集、源信息
        $video_id = Yii::$app->request->get('video_id', 0);
        $chapter_id = Yii::$app->request->get('chapter_id', '');
        $source_id = Yii::$app->request->get('source_id', '');

        //请求频道、搜索信息
        $channels = Yii::$app->api->get('/video/channels');

        //请求视频信息
        $data = Yii::$app->api->get('/video/info', ['video_id' => $video_id, 'chapter_id' => $chapter_id, 'source_id' => $source_id]);


        if(!$data) {
            return $this->redirect('/site/error');
        }

        //请求热门搜索信息
        $hot = Yii::$app->api->get('/search/hot-word');

        return $this->render('detail', [
            'data'      => $data,
            'channels'  => $channels,
            'hot'       => $hot,
            'source_id' => $source_id,
        ]);
    }

    /**
     * 视频详情播放页
     */
    public function actionNewDetail()
    {
        //获取影片系列、剧集、源信息
        $video_id = Yii::$app->request->get('video_id', 0);
        $chapter_id = Yii::$app->request->get('chapter_id', '');
        $source_id = Yii::$app->request->get('source_id', '');

        //请求频道、搜索信息
        $channels = Yii::$app->api->get('/video/channels');

        //请求视频信息
        $data = Yii::$app->api->get('/video/info', ['video_id' => $video_id, 'chapter_id' => $chapter_id, 'source_id' => $source_id]);


        if(!$data) {
            return $this->redirect('/site/error');
        }

        //请求热门搜索信息
        $hot = Yii::$app->api->get('/search/hot-word');

        return $this->render('newdetail', [
            'data'      => $data,
            'channels'  => $channels,
            'hot'       => $hot,
            'source_id' => $source_id,
        ]);
    }

    /**
     * 视频详情列表页
     */
    public function actionList()
    {
        //获取影片系列、剧集、源信息
        $channel_id = Yii::$app->request->get('channel_id', '');
        $keyword = Yii::$app->request->get('keyword', '');

        //请求频道、搜索信息
        $channels = Yii::$app->api->get('/video/channels');

        //请求影片筛选信息
        $info = Yii::$app->api->get('/video/filter', ['channel_id' => $channel_id, 'type' => 1, 'page_size' => 24]);
        //请求热门搜索信息
        $hot = Yii::$app->api->get('/search/hot-word');

        return $this->render('list', [
            'info'      => $info,
            'hot'       => $hot,
            'channel_id'=> $channel_id,
            'keyword'   => $keyword,
            'channels'  => $channels
        ]);
    }

    /**
     * 视频筛选接口
     */
    public function actionRefreshCate()
    {
        //获取影片系列、剧集、源信息
        $channel_id = Yii::$app->request->get('channel_id', 0);
        $sort = Yii::$app->request->get('sort', '');
        $tag = Yii::$app->request->get('tag', '');
        $area = Yii::$app->request->get('area', '');
        $year = Yii::$app->request->get('year', '');
        $play_limit = Yii::$app->request->get('play_limit', '');
        $page_num = Yii::$app->request->get('page_num', 1);

        //请求影片筛选信息
        $data = Yii::$app->api->get('/video/filter', ['channel_id' => $channel_id, 'tag' => $tag, 'sort' => $sort, 'area' => $area,
            'play_limit' => $play_limit, 'year' => $year, 'page_num' => $page_num, 'page_size' =>24 ,'type' => 1]);

        return Tool::responseJson(0, '操作成功', $data);
    }

    /**
     * 获取搜索影片接口
     */
    public function actionSearchVideo()
    {
        //获取搜索关键字、频道
        $keyword = Yii::$app->request->get('keyword');
        $page_num = Yii::$app->request->get('page_num', 1);

        //搜索首页信息
        $data = Yii::$app->api->get('/search/result', ['keyword' => $keyword, 'page_num' => $page_num, 'page_size' => 24]);

        return Tool::responseJson(0, '操作成功', $data);
    }


    /**
     * 切换下一集
     */
    public function actionSwitchVideo()
    {
        //获取影片系列、剧集、源信息
        $video_id = Yii::$app->request->get('video_id', 0);
        $chapter_id = Yii::$app->request->get('chapter_id', '');
        $source_id = Yii::$app->request->get('source_id', '');

        //请求视频信息
        $data = Yii::$app->api->get('/video/info', ['video_id' => $video_id, 'chapter_id' => $chapter_id, 'source_id' => $source_id]);

        return Tool::responseJson(0, '操作成功', $data );
    }

}