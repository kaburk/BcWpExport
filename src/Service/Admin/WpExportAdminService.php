<?php
declare(strict_types=1);

namespace BcWpExport\Service\Admin;

use Cake\ORM\TableRegistry;

class WpExportAdminService
{
    /**
     * 一覧画面に必要な view 変数（ジョブ一覧・ブログ・カテゴリ・ユーザー）を返す
     */
    public function getViewVarsForIndex(): array
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs');
        $pendingJobs = $jobsTable->find()
            ->where(['status IN' => ['pending', 'processing', 'waiting', 'failed']])
            ->orderBy(['created' => 'DESC'])
            ->all()
            ->toList();
        $historyJobs = $jobsTable->find()
            ->where(['status IN' => ['completed', 'cancelled']])
            ->orderBy(['created' => 'DESC'])
            ->limit(20)
            ->all()
            ->toList();

        return [
            'pendingJobs' => $pendingJobs,
            'historyJobs' => $historyJobs,
            'blogList' => $this->getBlogList(),
            'categoriesByBlog' => $this->getCategoriesByBlog(),
            'userList' => $this->getUserList(),
        ];
    }

    /**
     * ブログコンテンツの一覧を「サイト名 - ブログ名」形式で返す
     */
    protected function getBlogList(): array
    {
        $table = TableRegistry::getTableLocator()->get('BcBlog.BlogContents');
        $blogContents = $table->find()->contain(['Contents' => ['Sites']])->all();
        $list = [];
        foreach ($blogContents as $bc) {
            $siteName = $bc->content->site->display_name ?? $bc->content->site->name ?? '';
            $blogTitle = $bc->content->title ?? '';
            $list[$bc->id] = $siteName ? $siteName . ' - ' . $blogTitle : $blogTitle;
        }
        return $list;
    }

    /**
     * ブログ ID をキーにしたカテゴリ一覧を返す（カテゴリ連動プルダウン用）
     */
    protected function getCategoriesByBlog(): array
    {
        $table = TableRegistry::getTableLocator()->get('BcBlog.BlogCategories');
        $categories = $table->find()->select(['id', 'title', 'blog_content_id'])->all();
        $result = [];
        foreach ($categories as $cat) {
            $result[$cat->blog_content_id][$cat->id] = $cat->title;
        }
        return $result;
    }

    /**
     * ユーザー一覧をプルダウン用配列で返す
     */
    protected function getUserList(): array
    {
        $table = TableRegistry::getTableLocator()->get('BaserCore.Users');
        return $table->getUserList();
    }

    /**
     * 指定トークンのジョブを削除する（出力ファイルと DB レコードの両方を削除）
     */
    public function deleteJob(string $token): void
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs');
        $job = $jobsTable->find()->where(['job_token' => $token])->firstOrFail();
        if (!empty($job->output_path) && file_exists((string) $job->output_path)) {
            unlink((string) $job->output_path);
        }
        $jobsTable->deleteOrFail($job);
    }

    /**
     * 複数のジョブをトークン配列で一括削除する
     */
    public function deleteJobs(array $tokens): void
    {
        foreach ($tokens as $token) {
            $this->deleteJob((string) $token);
        }
    }
}
