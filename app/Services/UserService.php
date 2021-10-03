<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\ErrorCode;
use App\Constants\UserCenterStatus;
use App\Exception\BusinessException;
use App\Model\Noticelook;
use App\Model\Tixian;
use App\Model\User;
use App\Model\Userdata;
use App\Repositories\V1\AlbumRepository;
use App\Repositories\V1\UserRepository;
use Exception;
use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;

/**
 * UserService
 * 用户相关逻辑.
 */
class UserService extends BaseService
{
    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected AlbumRepository $albumRepository;

    public function __call($name, $arguments)
    {
        return $this->userRepository->{$name}(...$arguments);
    }

    public function login(string $username, string $password): User
    {
        /** @var User $user */
        $user = User::query()->where('username', $username)->first();

        if (empty($user)) {
            throw new BusinessException(ErrorCode::LOGIN_FAIL, '账号不存在');
        }

        if ($user['jinzhi'] == 2) {
            throw new BusinessException(ErrorCode::LOGIN_FAIL, '您的账号因涉嫌违规操作，被系统临时冻结，如有问题，请联系客服');
        }

        if (md5($password) != $user['password']) {
            throw new BusinessException(ErrorCode::LOGIN_FAIL, '密码错误');
        }
        $user->contentId = $user['id'];
        return $user;
    }

    /**
     * 获取动态
     * @param array|string[] $column
     */
    public function getMoving(int $userid, array $query, array $column = ['*']): array
    {
        $data = $this->userRepository->getMoving($userid, $query, $column);

        if (empty($data['list'])) {
            return $data;
        }

        foreach ($data['list'] as &$v) {
            $detail    = match ($v['type']) {
                1, 2 => $this->albumRepository->getAlbumDetail(['a.id' => $v['cid']], ['a.name', 'l.path']),
                4, 6, 10 => $this->albumRepository->getAlbumListDetail(['id' => $v['cid']], ['name', 'path']),
                5, 9 => $this->albumRepository->getLibraryDetail(['id' => $v['cid']], ['name', 'pdfimg as path']),
                7, 8 => $this->albumRepository->getMaterialDetail(['id' => $v['cid']], ['title as name', 'path']),
                default => null,
            };
            $v['name'] = $detail ? $detail->toArray()['name'] : '';
            $v['path'] = $detail ? $detail->toArray()['path'] : '';
        }
        unset($v);
        return $data;
    }

    /**
     * 获取私信
     * @param array|string[] $column
     */
    public function getPrivateMessage(int $userid, array $query, array $column = ['*']): array
    {
        $data = $this->userRepository->getPrivateMessage($userid, $query, $column);

        if (empty($data['list'])) {
            return $data;
        }
        return $this->checkRead($userid, $data);
    }

    /**
     * 获取系统公告.
     * @param array|string[] $column
     */
    public function getSystemMessage(int $userid, array $query, array $column = ['*']): array
    {
        $data = $this->userRepository->getSystemMessage($query, $column);

        if (empty($data['list'])) {
            return $data;
        }
        return $this->checkRead($userid, $data);
    }

    /**
     * 获取消息内容.
     */
    public function getMessageDetail(int $noticeId): array
    {
        return $this->userRepository->getMessageDetail($noticeId);
    }

    /**
     * 用户收益.
     */
    public function getUserIncome(int $userid): array
    {
        return [
            'day'        => $this->userRepository->todayIncome($userid) ?? 0,
            'yesterday'  => $this->userRepository->yesterdayIncome($userid) ?? 0,
            'this_week'  => $this->userRepository->thisWeekIncome($userid) ?? 0,
            'last_week'  => $this->userRepository->lastWeekIncome($userid) ?? 0,
            'this_month' => $this->userRepository->thisMonthIncome($userid) ?? 0,
            'last_month' => $this->userRepository->lastMonthIncome($userid) ?? 0,
        ];
    }

    public function getUserData(int $userid): Userdata
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    public function getUser(int $userid): User
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    public function getUserMerge(int $userid, $column = ['*']): Model
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    public function getMoneyLog(int $userid, array $query, array $column = ['*']): array
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    public function getScoreLog(int $userid, array $query, array $column = ['*']): array
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    public function getCashLog(int $userid, int $page, int $pageSize, array $column = ['*']): array
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    public function decrMoney(int $userid, string $money): int
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    public function cash(int $userid, string $money): bool
    {
        if (!is_numeric($money)) {
            throw new BusinessException(ErrorCode::ERROR, '请输入正确的金额');
        }

        if ($money < 100) {
            throw new BusinessException(ErrorCode::ERROR, '满100才能提现');
        }
        Db::beginTransaction();
        try {
            $userdata = $this->userRepository->getUserData($userid);

            if (empty($userdata['zhi'])) {
                throw new Exception('支付宝信息未填写');
            }

            if ($userdata['status'] != UserCenterStatus::USER_CERT_IS_PASS) {
                throw new Exception('用户认证未通过');
            }

            if (Tixian::query()->where(['uid' => $userid, 'status' => UserCenterStatus::USER_CASH_NO_VERIFY])->exists()) {
                throw new Exception('您有正在审核的提现申请,请勿重复提交');
            }
            $user = $this->userRepository->getUser($userid);

            if ($user->money < $money) {
                throw new Exception('可提现金额不足');
            }
            //减少金额
            $this->decrMoney($userid, $money);
            //增加提现记录
            $cashLog = [
                'uid'    => $userid,
                'money'  => $money,
                'status' => UserCenterStatus::USER_CASH_NO_VERIFY,
                'name'   => $userdata->name,
                'zhi'    => $userdata->zhi,
                'time'   => time(),
            ];
            Tixian::create($cashLog);
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollBack();
            throw new BusinessException(ErrorCode::ERROR, $e->getMessage());
        }
    }

    /**
     * 检查消息是否已读.
     */
    private function checkRead(int $userid, array $data): array
    {
        $ids = array_column($data['list'], 'id');
        $nid = Noticelook::query()->where('uid', $userid)->whereIn('nid', $ids)->pluck('nid')->toArray();

        foreach ($data['list'] as &$v) {
            $v['read'] = 0;

            if (in_array($v['id'], $nid)) {
                $v['read'] = 1;
            }
        }
        unset($v);
        return $data;
    }
}
