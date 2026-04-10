<?php
declare(strict_types=1);

namespace BcWpExport\Controller\Admin;

use BaserCore\Controller\Admin\BcAdminAppController;
use BcWpExport\Service\Admin\WpExportAdminService;
use BcWpExport\Service\WpExportService;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Throwable;

class WpExportsController extends BcAdminAppController
{
    /**
     * CSRF 保護から除外するアクションを設定する
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->FormProtection->setConfig('unlockedActions', [
            'create',
            'delete',
            'delete_all',
        ]);
    }

    /**
     * エクスポート一覧画面を表示する
     */
    public function index(): void
    {
        $service = new WpExportAdminService();
        $this->set($service->getViewVarsForIndex());
    }

    /**
     * エクスポートを実行し WXR ファイルを生成してジョブを保存する
     */
    public function create(): Response
    {
        $this->request->allowMethod('post');

        try {
            $service = new WpExportService();
            $job = $service->createJob((array) $this->request->getData());
            return $this->jsonResponse(['result' => $job]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * 指定トークンの WXR ファイルをダウンロードする
     */
    public function download(string $token): Response
    {
        $service = new WpExportService();
        $job = $service->getJobByToken($token);

        return $this->response->withFile((string) $job->output_path, [
            'download' => true,
            'name' => $job->output_filename ?: basename((string) $job->output_path),
        ]);
    }

    /**
     * 指定トークンのジョブ（ファイル＋DBレコード）を削除する
     */
    public function delete(string $token): Response
    {
        $this->request->allowMethod('post');

        try {
            $service = new WpExportAdminService();
            $service->deleteJob($token);
            return $this->jsonResponse(['result' => true]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * tokens[] で受け取った複数ジョブを一括削除する
     */
    public function delete_all(): Response
    {
        $this->request->allowMethod('post');

        try {
            $tokens = (array) $this->request->getData('tokens');
            $service = new WpExportAdminService();
            $service->deleteJobs($tokens);
            return $this->jsonResponse(['result' => true]);
        } catch (Throwable $e) {
            return $this->jsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * JSON レスポンスを生成して返す
     */
    private function jsonResponse(array $data, int $status = 200): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
