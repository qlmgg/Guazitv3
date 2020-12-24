<?php
namespace admin\models\setting;

use common\helpers\RedisKey;
use common\helpers\Tool;

class SettingSystem extends \common\models\setting\SettingSystem
{

    public $areas;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['areas'], 'safe'],
            [['ad_switch', 'vip_play_all', 'third_pay', 'comment_switch'], 'integer'],
            [['site_name'], 'string', 'max' => 256],
            [['currency_unit', 'currency_coupon'], 'string', 'max' => 16],
            [['remove_ad_score', 'play_ad_time', 'coupon_expire_time'], 'required'],
            [['remove_ad_score', 'play_ad_time', 'coupon_expire_time',], 'integer', 'min' => NUMBER_INPUT_MIN, 'max' => NUMBER_INPUT_MAX],
            [['site_name', 'currency_unit', 'currency_coupon'], 'trim']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'site_name'          => '站点名称',
            'currency_unit'      => '货币',
            'currency_coupon'    => '付费货币名称',
            'ad_switch'          => '广告开关',
            'comment_switch'     => '评论审核开关',
            'remove_ad_score'    => '跳广告积分数',
            'play_ad_time'       => '广告时间',
            'coupon_expire_time' => '视频有效时间',
            'third_pay'          => '三方支付开关',
            'vip_play_all'       => 'VIP全站播放'
        ];
    }

    public function beforeSave($insert)
    {
        if (is_array($this->areas)) {
            $this->area_limit = implode(',', $this->areas);
        }
        return parent::beforeSave($insert); // TODO: Change the autogenerated stub
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        
        // 清理缓存
        Tool::clearCache(RedisKey::getSettingKey('system', ['id' => 1]));
    }
}