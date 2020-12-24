<?php
namespace api\controllers;

use api\dao\CommonDao;
use api\dao\RecommendDao;
use api\dao\VideoDao;
use api\exceptions\ApiException;
use api\helpers\ErrorCode;
use api\logic\ChannelLogic;
use api\logic\PayLogic;
use api\logic\VideoLogic;
use api\models\video\Recommend;
use api\models\video\UserWatchLog;
use common\helpers\RedisKey;
use common\helpers\RedisStore;
use Yii;
use yii\helpers\ArrayHelper;

class VideoController extends BaseController
{
    /**
     * 频道栏
     * @return mixed
     */
    public function actionChannels()
    {
        // 筛选字段
        $fields = ['channel_id', 'channel_name'];

        $commonDao = new CommonDao();
        $data['list'] = $commonDao->videoChannel($fields);

        $videoLogic = new VideoLogic();
        $data['hot_word'] =  $videoLogic->searchWord();;

        // 添加热门分类
        array_unshift($data['list'], ['channel_id' => 0, 'channel_name' => '首页']);

        return $data;
    }

    /**
     * 首页&频道首页
     * @return array
     * @throws \api\exceptions\InvalidParamException
     */
    public function actionIndex()
    {
        $channelId = $this->getParamOrFail('channel_id');

        $data = [];

        // 获取banner数据
        $bannerFields = ['title', 'action', 'content', 'image'];
        $videoDao= new VideoDao();
        $banner = $videoDao->banner($channelId, $bannerFields);
        $data['banner'] = $banner;

        // $channelId == 0 时返回首页数据
        $channelLogic = new ChannelLogic();
        if ($channelId == 0) {
            $channelData = $channelLogic->channelIndexData();
        } else {
            $channelData = $channelLogic->channelLabelData($channelId);
        }

        $data = array_merge($data, $channelData);
        return $data;
    }

    /**
     * 视频筛选
     * @return array
     */
    public function actionFilter()
    {
        $channelId = $this->getParam('channel_id', ''); // 频道
        $sort      = $this->getParam('sort', 'hot'); // 排序
        $tag       = $this->getParam('tag', ''); // 标签
        $area      = $this->getParam('area', ''); // 地区
        $year      = $this->getParam('year', ''); // 年代
        $page      = $this->getParam('page_num', DEFAULT_PAGE_NUM); // 页面 当传入1时，返回检索项
        $pageSize  = $this->getParam('page_size', 10);
        $type      = $this->getParam('type', 0); // 类型 当传入1时，位点击分类进入，服务端要返回所有频道筛选项
        $playLimit = $this->getParam('play_limit', '');

        $area      = !empty($area) ? $area : '';
        $year      = !empty($year) ? $year : '';
        $tag       = !empty($tag) ? $tag : '';
        $playLimit = !empty($playLimit) ? $playLimit : '';
        $channelId = !empty($channelId) ? $channelId : '';

        // 筛选项
        $data = [];
        // 当请求为第一页时，返回筛选页头部信息
        if ($page == 1) {
            $videoLogic = new VideoLogic();
            $data = $videoLogic->filterHeader($channelId, $sort, $tag, $area, $year, $type, $playLimit);
        }
        // 根据条件取视频信息
        $videoDao = new VideoDao();
        $video = $videoDao->filterVideoList($channelId, $sort, $tag, $area, $year, $type, $playLimit, $page, $pageSize);

        $data = array_merge($data, $video);
        return $data;
    }

    /**
     * 离线缓存视频
     */
    public function actionDown()
    {
        $videoId   = $this->getParamOrFail('video_id');  //视频id
        $chapterId = $this->getParamOrFail('chapter_id');  //视频id

        if (!$chapterId) {
            throw new ApiException(ErrorCode::EC_PARAM_INVALID);
        }
        $chapterId = explode(',', $chapterId);
        $videoLogic = new VideoLogic();
        return $videoLogic->down($videoId, $chapterId);
    }

    /**
     * 换一换
     * @return array
     * @throws \api\exceptions\InvalidParamException
     */
    public function actionRefresh()
    {
        $recommendId = $this->getParamOrFail('recommend_id');

        $recommendDao = new RecommendDao();
        $recommendInfo = $recommendDao->getRecommend($recommendId);
        $search = json_decode($recommendInfo['search'], true);

        // 检索
        $where = ['and', ['channel_id' => $recommendInfo['channel_id']]];

        foreach ($search as $item) {
            if ($item['field'] == 'tag') {
                $where[] = ['like', 'category_ids', $item['value']];
            } else {
                $where[] = [$item['field'] => $item['value']];
            }
        }
        
        // 获取缓存的影视
        $videoDao = new VideoDao();
        $fields = ['video_id', 'video_name', 'score', 'tag', 'flag', 'play_times', 'cover', 'horizontal_cover', 'intro'];

        return $videoDao->refreshVideo($where, $fields, Recommend::$selectLimit[$recommendInfo['style']]);
    }

    /**
     * 视频详情
     * @return array
     * @throws ApiException
     * @throws \api\exceptions\InvalidParamException
     */
    public function actionInfo()
    {
        $videoId   = $this->getParamOrFail('video_id');
        $chapterId = $this->getParam('chapter_id');
        $sourceId  = $this->getParam('source_id');
        // 不传入id则设置为空
        $chapterId = $chapterId ? $chapterId : '';

        $videoLogic = new VideoLogic();
        return $videoLogic->playInfo($videoId, $chapterId, $sourceId);
    }

    /**
     * 我的观影记录
     */
    public function actionUserWatchLog()
    {
        $uid = Yii::$app->user->id;
        if (empty($uid)) {
            return [];
        }
        /** @var UserWatchLog $logInfo */
        $logInfo = UserWatchLog::find()->where(['uid' => $uid])->orderBy('updated_at desc')->one();
        if (empty($logInfo)) {
            return [];
        }
        $arrLogInfo = $logInfo->toArray();
        // 获取影视信息
        $videoDao = new VideoDao();
        $videoInfo = $videoDao->videoInfo($logInfo['video_id'], ['video_id', 'video_name']);
        if (empty($videoInfo)) {
            return [];
        }
        // 获取影视剧集信息
        $videoChapter = $videoDao->videoChapter($logInfo['video_id'], ['chapter_id','title'], true);
        $chapterInfo  = $videoChapter[$logInfo['chapter_id']];
        if (empty($chapterInfo)) {
            return [];
        }

        // 合并数据
        $data = array_merge($arrLogInfo, $videoInfo, $chapterInfo);

        $data['title'] = $videoInfo['video_name'] . ' ' . $chapterInfo['title'] . ' ' . $logInfo['time'];

        return $data;
    }

    /**
     * 购买选项
     * @return array
     * @throws ApiException
     * @throws \api\exceptions\InvalidParamException
     */
    public function actionBuyOption()
    {
        $videoId = $this->getParamOrFail('video_id');
        $videoLogic = new VideoLogic();
        return $videoLogic->buyOption($videoId);
    }

    /**
     * 确认购买
     * @return bool
     * @throws ApiException
     * @throws \api\exceptions\InvalidParamException
     */
    public function actionBuyConfirm()
    {
        $videoId = $this->getParamOrFail('video_id');
        $uid = Yii::$app->user->id;
        // 上锁
        $lockKey = RedisKey::getApiLockKey('video/buy-confirm', ['uid' => $uid, 'video_id' => $videoId]);
        $redis = new RedisStore();
        if ($redis->checkLock($lockKey)) {
            throw new ApiException(ErrorCode::EC_SYSTEM_OPERATING);
        }

        $videoDao = new VideoDao();
        $videoInfo = $videoDao->videoInfo($videoId);
        if (empty($videoInfo)) {
            $redis->releaseLock($lockKey);
            throw new ApiException(ErrorCode::EC_VIDEO_NOT_EXIST);
        }

        $payLogic = new PayLogic();
        $res = $payLogic->consumeCoupon($uid, $videoInfo['total_price'], $videoId);
        // 释放锁
        $redis->releaseLock($lockKey);

        return $res;
    }

    /**
     * 章节目录
     * @return array
     * @throws ApiException
     * @throws \api\exceptions\InvalidParamException
     */
    public function actionChapter()
    {
        $videoId = $this->getParamOrFail('video_id');
        $videoDao = new VideoDao();

        $videoInfo = $videoDao->videoInfo($videoId);

        // 获取影片剧集信息
        $videos = $videoDao->videoChapter($videoId, []);
        if (!$videos) { // 没有剧集抛出异常
            throw new ApiException(ErrorCode::EC_VIDEO_CHAPTER_NOT_EXIST);
        }
        // 格式化章节信息
        foreach ($videos as $key => &$video) {
            $video['cover']         = $videoInfo['cover'];
            //$video['download_name'] = md5($videoInfo['video_name'] . ' ' . $video['title']) . '.' . substr(strrchr($video['resource_url'][$sourceId], '.'), 1);
            $video['mime_type']     = substr(strrchr(reset($video['resource_url']), '.'), 1);
            $video['last_chapter']  = isset($videos[$key-1]) ? $videos[$key-1]['chapter_id'] : 0;
            $video['next_chapter']  = isset($videos[$key+1]) ? $videos[$key+1]['chapter_id'] : 0;
            unset($video['resource_url']); // 安全考虑，删除剧集播放连接，防止全部播放连接一次性全返回
        }

        return $videos;
    }

    /**
     * vip 列表
     * @return array
     */
    public function actionVip()
    {
        $channelId = $this->getParam('channel_id');
        $channelId = $channelId ? $channelId : '';

        $videoLogic = new VideoLogic();
        return $videoLogic->vipList($channelId);
    }
}