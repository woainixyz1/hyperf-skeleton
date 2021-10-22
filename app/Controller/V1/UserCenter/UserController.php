<?php

declare(strict_types=1);

namespace App\Controller\V1\UserCenter;

use App\Common\Sms;
use App\Common\Utils;
use App\Constants\UserCenterStatus;
use App\Controller\AbstractController;
use App\Model\Noticelook;
use App\Request\User;
use App\Services\SmsService;
use App\Services\UserService;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;

/**
 * 用户中心/资料处理.
 */
class UserController extends AbstractController
{
    #[Inject]
    protected SmsService $smsService;

    #[Inject]
    protected UserService $userService;

    /**
     * 获取用户信息.
     */
    public function getUserinfo(): ResponseInterface
    {
        $field = [
            'u.id', 'u.nickname', 'u.imghead', 'u.email', 'u.sex', 'u.address', 'u.content', 'u.score', 'u.dc',
            'u.money', 'u.qi', 'u.fans', 'u.guan', 'u.isview', 'd.qq', 'u.wx', 'u.mobile', 'd.tel',
        ];
        return $this->success($this->userService->getUserMerge(user()['id'], $field));
    }

    /**
     * 绑定手机号.
     */
    public function bindMobile(User $request): ResponseInterface
    {
        $request->scene('bind_mobile')->validateResolved();
        $mobile  = $request->input('mobile');
        $captcha = $request->input('captcha');
        $this->smsService->check($mobile, $captcha);

        $user         = $this->userService->getUser(user()['id']);
        $user->mobile = $mobile;
        $user->save();
        Sms::flush($mobile);
        return $this->success();
    }

    /**
     * 用户资料.
     */
    public function profile(User $request): ResponseInterface
    {
        $request->scene('profile')->validateResolved();
        $params = $request->all();
        $user   = $this->userService->getUser(user()['id']);
        $user->fill($params)->save();
        return $this->success();
    }

    /**
     * 申请认证
     */
    public function certification(User $request): ResponseInterface
    {
        $request->scene('certification')->validateResolved();
        $params   = $request->all();
        $userData = $this->userService->getUserData(user()['id']);

        if ($userData->status == UserCenterStatus::USER_CERT_IS_PASS) {
            $this->error('已通过审核,不能修改');
        }
        $params['status'] = UserCenterStatus::USER_CERT_IS_SUBMIT;
        $userData->fill($params)->save();
        return $this->success();
    }

    /**
     * 获取认证信息.
     */
    public function getCertification(): ResponseInterface
    {
        $hidden   = [
            'status', 'text', 'shoucang', 'shoucolor', 'shouling', 'shouwen', 'shousucai', 'zhuanji', 'zuopin', 'sucainum', 'wenkunum',
            'tui', 'tuitime', 'total', 'time',
        ];
        $userData = $this->userService->getUserData(user()['id'])->makeHidden($hidden);

        $userData['cardimg']  = get_img_path($userData['cardimg']);
        $userData['cardimg1'] = get_img_path($userData['cardimg1']);
        return $this->success($userData);
    }

    /**
     * 上传头像.
     */
    public function uploadHeadImg(User $request): ResponseInterface
    {
        $request->scene('upload_head')->validateResolved();
        $user = $this->userService->getUser(user()['id']);

        $user->imghead = $request->input('head_image');
        $user->save();
        return $this->success();
    }

    /**
     * 获取动态
     */
    public function getMoving(): ResponseInterface
    {
        $query = $this->request->all();
        $field = ['w.id', 'w.cid', 'w.time', 'w.type', 'w.uid', 'u.nickname', 'u.imghead'];
        $data  = $this->userService->getMoving(user()['id'], $query, $field);
        return $this->success($data);
    }

    /**
     * 获取用户收入统计
     */
    public function getUserIncome(): ResponseInterface
    {
        $userid     = user()['id'];
        $userMerge  = $this->userService->getUserMerge($userid, ['u.dc', 'u.score', 'u.money', 'd.total']);
        $userIncome = $this->userService->getUserIncome($userid);
        return $this->success(array_merge($userMerge->toArray(), $userIncome));
    }

    /**
     * 资金记录.
     */
    public function getMoneyLog(): ResponseInterface
    {
        $query = $this->request->all();
        $field = ['w.*', 'u.nickname'];
        $data  = $this->userService->getMoneyLog(user()['id'], $query, $field);
        return $this->success($data);
    }

    /**
     * 获取共享分记录.
     */
    public function getScoreLog(): ResponseInterface
    {
        $query = $this->request->all();
        $field = ['w.*', 'u.nickname'];
        $data  = $this->userService->getScoreLog(user()['id'], $query, $field);
        return $this->success($data);
    }

    /**
     * 获取提现记录.
     */
    public function getCashLog(): ResponseInterface
    {
        $page     = (int)$this->request->input('page', 1) ?: 1;
        $pageSize = (int)$this->request->input('page_size', 10);
        $field    = ['id', 'name', 'zhi', 'money', 'status', 'time'];
        $data     = $this->userService->getCashLog(user()['id'], $page, $pageSize, $field);
        return $this->success($data);
    }

    /**
     * 消息盒.
     */
    public function messageBox(): ResponseInterface
    {
        //获取前最新的5条私信通知
        return $this->success($this->userService->getMessageBox(user()['id']));
    }

    /**
     * 获取私信
     */
    public function getPrivateMessage(): ResponseInterface
    {
        $query = $this->request->all();
        $data  = $this->userService->getPrivateMessage(user()['id'], $query);
        return $this->success($data);
    }

    /**
     * 获取系统公告.
     */
    public function getSystemMessage(): ResponseInterface
    {
        $query = $this->request->all();
        $data  = $this->userService->getSystemMessage(user()['id'], $query);
        return $this->success($data);
    }

    /**
     * 获取公告详情.
     */
    public function getMessageDetail(User $request): ResponseInterface
    {
        $request->scene('notice')->validateResolved();
        $id   = (int)$request->input('notice_id');
        $data = $this->userService->getMessageDetail($id);
        Noticelook::updateOrCreate(['uid' => user()['id'], 'nid' => $id]);
        return $this->success($data);
    }

    /**
     * 提现.
     */
    public function cash(): ResponseInterface
    {
        $money = $this->request->input('money');
        $this->userService->cash(user()['id'], $money);
        return $this->success();
    }

    /**
     * 上传作品
     */
    public function uploadWork(User $request): ResponseInterface
    {
        $request->scene('upload')->validateResolved();
        $file = $this->request->file('upload');
        $type = (int)$this->request->input('type');
        $data = Utils::upload($file, ['rar', 'zip']);
        $this->userService->uploadWork(user()['id'], $data, $type);
        return $this->success();
    }

    /**
     * 作品管理-素材.
     */
    public function worksManageForMaterial(User $request): ResponseInterface
    {
        $request->scene('work')->validateResolved();
        $query  = $request->all();
        $column = ['id', 'name', 'size', 'img', 'time', 'status', 'text', 'price', 'downnum', 'leixing', 'unnum'];
        $data   = $this->userService->worksManageForMaterial(user()['id'], $query, $column);
        return $this->success($data);
    }

    /**
     * 作品管理-文库.
     */
    public function worksManageForLibrary(User $request): ResponseInterface
    {
        $request->scene('work')->validateResolved();
        $query  = $request->all();
        $column = ['id', 'name', 'size', 'img', 'time', 'status', 'text', 'price', 'downnum', 'leixing', 'unnum'];
        $data   = $this->userService->worksManageForLibrary(user()['id'], $query, $column);
        return $this->success($data);
    }

    /**
     * 填写信息-素材.
     */
    public function writeInformationForMaterial(User $request): ResponseInterface
    {
        $request->scene('information')->validateResolved();
        return $this->success($this->userService->writeInformationForMaterial($request->all()));
    }

    /**
     * 获取素材详情.
     */
    public function getDetailForMaterial(User $request): ResponseInterface
    {
        $request->scene('get_material')->validateResolved();
        $id     = $request->input('material_id');
        $column = ['id', 'name', 'size', 'mulu_id', 'geshi_id', 'title', 'guanjianci', 'leixing', 'price', 'img', 'status'];
        return $this->success($this->userService->getDetailForMaterial((int)$id, $column));
    }

    /**
     * 填写信息-文库.
     */
    public function writeInformationForLibrary(User $request): ResponseInterface
    {
        $request->scene('information_library')->validateResolved();
        return $this->success($this->userService->writeInformationForLibrary($request->all()));
    }

    /**
     * 获取文库详情.
     */
    public function getDetailForLibrary(User $request): ResponseInterface
    {
        $request->scene('get_library')->validateResolved();
        $id     = $request->input('library_id');
        $column = ['id', 'name', 'size', 'title', 'guanjianci', 'leixing', 'price', 'img', 'status', 'free_num'];
        return $this->success($this->userService->getDetailForLibrary((int)$id, $column));
    }

    /**
     * 删除素材管理.
     */
    public function deleteForMaterial(User $request): ResponseInterface
    {
        $request->scene('del_material')->validateResolved();
        $id = $request->input('material_id');
        return $this->success($this->userService->deleteForMaterial([$id]));
    }

    /**
     * 删除文库.
     */
    public function deleteForLibrary(User $request): ResponseInterface
    {
        $request->scene('get_library')->validateResolved();
        $id = $request->input('library_id');
        return $this->success($this->userService->deleteForLibrary([$id]));
    }

    /**
     * 批量删除素材.
     */
    public function batchDeleteMaterial(User $request): ResponseInterface
    {
        $request->scene('batch_del_material')->validateResolved();
        $ids   = $request->input('material_ids');
        $idArr = explode(',', $ids);
        return $this->success($this->userService->deleteForMaterial($idArr));
    }

    /**
     * 批量删除文库.
     */
    public function batchDeleteLibrary(User $request): ResponseInterface
    {
        $request->scene('batch_del_library')->validateResolved();
        $ids   = $request->input('library_ids');
        $idArr = explode(',', $ids);
        return $this->success($this->userService->deleteForLibrary($idArr));
    }

    /**
     * 获取素材分类.
     */
    public function getMaterialCategory(): ResponseInterface
    {
        return $this->success($this->userService->getMaterialCategory());
    }

    /**
     * 获取素材格式.
     */
    public function getMaterialFormat(): ResponseInterface
    {
        return $this->success($this->userService->getMaterialFormat());
    }

    /**
     * 素材下载日志.
     */
    public function getMaterialDownLog(): ResponseInterface
    {
        return $this->success($this->userService->getMaterialDownLog(user()['id'], $this->request->all()));
    }

    /**
     * 素材下载日志.
     */
    public function getLibraryDownLog(): ResponseInterface
    {
        return $this->success($this->userService->getLibraryDownLog(user()['id'], $this->request->all()));
    }
}
