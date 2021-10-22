<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\V1\SucaiRepository;
use App\Repositories\V1\UserRepository;
use Hyperf\Database\Model\Model;
use Hyperf\Di\Annotation\Inject;

/**
 * UserService
 * 用户中心相关逻辑.
 */
class PersonalHomePageService extends BaseService
{
    #[Inject]
  protected UserRepository $userRepository;

    #[Inject]
  protected SucaiRepository $sucaiRepository;

    /**
     * 个人主页.
     * @param mixed $uid
     */
    public function homePage($uid): Model
    {
        $field = [
            'u.id', 'u.nickname', 'u.imghead', 'u.content', 'u.wx',
            'u.money', 'u.qi', 'u.fans', 'u.guan', 'u.isview', 'd.shoucang', 'd.zhuanji', 'd.zuopin', 'd.sucainum', 'd.wenkunum',
        ];

        return $this->userRepository->getUserMerge($uid, $field);
    }

    /**
     * 获取某个用户的列表粉丝列表.
     * @param mixed $uid
     */
    public function fansListByUid($uid): array
    {
        $field = [
            'u.id', 'u.nickname', 'u.imghead', 'u.content', 'u.wx',
            'u.money', 'u.qi', 'u.fans', 'u.guan', 'u.isview', 'd.shoucang', 'd.zhuanji', 'd.zuopin', 'd.sucainum', 'd.wenkunum',
        ];
        //获取该用户关注的列表关联用户表，userdata表联合查询数据
        $fansList = $this->userRepository->getFansList($uid, $field);

        if (!empty($fansList) && isset($fansList['data']) && !empty($fansList['data'])) {
            foreach ($fansList['data'] as $key => &$val) {
                if (!isset($val['id'])) {
                    unset($fansList['data'][$key]);
                    continue;
                }
                $tmp                = $val;
                $tmp['sucai_list']  = [];

                if ($val['sucainum'] > 1) {
                    //循环从素材中获取图片
                    $where              = ['uid' => $val['id']];
                    $tmp['sucai_list']  = $this->sucaiRepository->searchImgList('', [], $where, '');
                }
                $fansList['data'][$key]  = $tmp;
            }
        }
        return $fansList;
    }
}
