<?php
/**
 * @var \Cake\View\View $this
 * @var array $pendingJobs
 * @var array $historyJobs
 * @var array $blogList
 * @var array $categoriesByBlog
 * @var array $userList
 */
$this->BcAdmin->setTitle(__d('baser_core', 'WordPressエクスポート'));
$adminBase = '/baser/admin/bc-wp-export/wp_exports';
$csrfToken = $this->request->getAttribute('csrfToken');
?>

<link rel="stylesheet" href="/bc_wp_export/css/admin/wp_export.css">

<?php if (!empty($pendingJobs)): ?>
<section class="bca-section" data-bca-section-type="form-group" id="js-pending-section">
    <h2 class="bca-main__heading" data-bca-heading-size="lg"><?= __d('baser_core', '未完了ジョブ') ?></h2>
    <table class="bca-table-listup">
        <thead class="bca-table-listup__thead">
        <tr>
            <th class="bca-table-listup__thead-th"><?= __d('baser_core', '作成日時') ?></th>
            <th class="bca-table-listup__thead-th"><?= __d('baser_core', '状態') ?></th>
            <th class="bca-table-listup__thead-th"><?= __d('baser_core', '進捗') ?></th>
            <th class="bca-table-listup__thead-th"><?= __d('baser_core', 'ファイル名') ?></th>
        </tr>
        </thead>
        <tbody class="bca-table-listup__tbody">
        <?php foreach ($pendingJobs as $job): ?>
            <tr>
                <td class="bca-table-listup__tbody-td"><?= h($job->created) ?></td>
                <td class="bca-table-listup__tbody-td"><?= h($job->status) ?></td>
                <td class="bca-table-listup__tbody-td"><?= number_format((int)$job->processed) ?> / <?= number_format((int)$job->total_items) ?></td>
                <td class="bca-table-listup__tbody-td"><?= h($job->output_filename ?: '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<section class="bca-section" data-bca-section-type="form-group" id="js-form-section">
    <h2 class="bca-main__heading" data-bca-heading-size="lg"><?= __d('baser_core', '新規エクスポート') ?></h2>
    <table class="form-table bca-form-table" data-bca-table-type="type2">
        <tbody>
        <tr>
            <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('export_target', __d('baser_core', '出力対象')) ?></th>
            <td class="col-input bca-form-table__input">
                <?= $this->BcAdminForm->control('export_target', [
                    'type' => 'select',
                    'label' => false,
                    'options' => [
                        'all' => __d('baser_core', '固定ページとブログ記事'),
                        'posts' => __d('baser_core', 'ブログ記事のみ'),
                        'pages' => __d('baser_core', '固定ページのみ'),
                    ],
                    'empty' => false,
                ]) ?>
            </td>
        </tr>
        <tr>
            <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('content_status', __d('baser_core', '公開状態')) ?></th>
            <td class="col-input bca-form-table__input">
                <?= $this->BcAdminForm->control('content_status', [
                    'type' => 'select',
                    'label' => false,
                    'options' => [
                        'published' => __d('baser_core', '公開のみ'),
                        'all' => __d('baser_core', 'すべて（非公開・下書きを含む）'),
                        'draft_included' => __d('baser_core', '下書きを含む'),
                    ],
                    'empty' => false,
                ]) ?>
            </td>
        </tr>
        </tbody>
    </table>

    <div class="bca-collapse__action">
        <button type="button"
                class="bca-collapse__btn"
                data-bca-collapse="collapse"
                data-bca-target="#js-filter-body"
                aria-expanded="false"
                aria-controls="js-filter-body">
            <?= __d('baser_core', 'フィルタ条件') ?>&nbsp;&nbsp;<i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
        </button>
    </div>
    <div class="bca-collapse" id="js-filter-body" data-bca-state="" style="display:none;">
        <p class="bca-form__note"><?= __d('baser_core', '指定なしの場合はすべてが対象になります。') ?></p>
        <table class="form-table bca-form-table" data-bca-table-type="type2">
            <tbody>
            <tr>
                <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('blog_content_id', __d('baser_core', 'ブログ')) ?></th>
                <td class="col-input bca-form-table__input">
                    <?= $this->BcAdminForm->control('blog_content_id', [
                        'type' => 'select',
                        'label' => false,
                        'options' => $blogList,
                        'empty' => __d('baser_core', '指定なし'),
                    ]) ?>
                </td>
            </tr>
            <tr>
                <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('category_id', __d('baser_core', 'カテゴリー')) ?></th>
                <td class="col-input bca-form-table__input">
                    <?= $this->BcAdminForm->control('category_id', [
                        'type' => 'select',
                        'label' => false,
                        'options' => [],
                        'empty' => __d('baser_core', '指定なし'),
                    ]) ?>
                    <p class="bca-form__note"><?= __d('baser_core', 'ブログを選択するとカテゴリーを絞り込めます。') ?></p>
                </td>
            </tr>
            <tr>
                <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('author_id', __d('baser_core', 'ユーザー')) ?></th>
                <td class="col-input bca-form-table__input">
                    <?= $this->BcAdminForm->control('author_id', [
                        'type' => 'select',
                        'label' => false,
                        'options' => $userList,
                        'empty' => __d('baser_core', '指定なし'),
                    ]) ?>
                </td>
            </tr>
            <tr>
                <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('date_from', __d('baser_core', '投稿日の範囲')) ?></th>
                <td class="col-input bca-form-table__input">
                    <span class="bca-datetimepicker__group">
                        <span class="bca-datetimepicker__start">
                            <span class="bca-textbox">
                                <input type="date" id="date-from" class="bca-textbox__input">
                            </span>
                        </span>
                        <span class="bca-datetimepicker__delimiter">〜</span>
                        <span class="bca-datetimepicker__end">
                            <span class="bca-textbox">
                                <input type="date" id="date-to" class="bca-textbox__input">
                            </span>
                        </span>
                    </span>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="bca-collapse__action">
        <button type="button"
                class="bca-collapse__btn"
                data-bca-collapse="collapse"
                data-bca-target="#js-option-body"
                aria-expanded="false"
                aria-controls="js-option-body">
            <?= __d('baser_core', '出力オプション') ?>&nbsp;&nbsp;<i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
        </button>
    </div>
    <div class="bca-collapse" id="js-option-body" data-bca-state="" style="display:none;">
        <table class="form-table bca-form-table" data-bca-table-type="type2">
            <tbody>
            <tr>
                <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('include_site_meta', __d('baser_core', 'サイト情報')) ?></th>
                <td class="col-input bca-form-table__input">
                    <?= $this->BcAdminForm->control('include_site_meta', [
                        'type' => 'checkbox',
                        'label' => __d('baser_core', 'サイト情報を channel 要素に含める'),
                        'hiddenField' => false,
                        'default' => 1,
                    ]) ?>
                </td>
            </tr>
            <tr>
                <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('include_media_urls', __d('baser_core', 'メディア URL')) ?></th>
                <td class="col-input bca-form-table__input">
                    <?= $this->BcAdminForm->control('include_media_urls', [
                        'type' => 'checkbox',
                        'label' => __d('baser_core', '本文内の画像・メディア URL を出力する'),
                        'hiddenField' => false,
                        'default' => 1,
                    ]) ?>
                </td>
            </tr>
            <tr>
                <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('absolute_url', __d('baser_core', 'URL 変換')) ?></th>
                <td class="col-input bca-form-table__input">
                    <?= $this->BcAdminForm->control('absolute_url', [
                        'type' => 'checkbox',
                        'label' => __d('baser_core', '本文内の相対 URL を絶対 URL へ変換する'),
                        'hiddenField' => false,
                        'default' => 1,
                    ]) ?>
                </td>
            </tr>
            <tr>
                <th class="col-head bca-form-table__label"><?= $this->BcAdminForm->label('pretty_print', __d('baser_core', 'XML 整形')) ?></th>
                <td class="col-input bca-form-table__input">
                    <?= $this->BcAdminForm->control('pretty_print', [
                        'type' => 'checkbox',
                        'label' => __d('baser_core', 'XML をインデント付きで整形出力する（ファイルサイズが増加します）'),
                        'hiddenField' => false,
                    ]) ?>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <div id="js-export-error" class="bca-notice-message bca-notice-message--alert" style="display:none;"></div>

    <div class="bca-actions">
        <div class="bca-actions__before"></div>
        <div class="bca-actions__main">
            <button id="js-create-export-btn" class="bca-btn bca-actions__item" data-bca-btn-type="save" data-bca-btn-size="lg" data-bca-btn-width="lg">
                <?= __d('baser_core', 'WXR を生成') ?>
            </button>
        </div>
        <div class="bca-actions__sub"></div>
    </div>
</section>

<section class="bca-section" data-bca-section-type="form-group" id="js-result-section" style="display:none;">
    <h2 class="bca-main__heading" data-bca-heading-size="lg"><?= __d('baser_core', '生成完了') ?></h2>
    <table class="form-table bca-form-table" data-bca-table-type="type2">
        <tbody>
        <tr>
            <th class="col-head bca-form-table__label"><?= __d('baser_core', 'ファイル名') ?></th>
            <td class="col-input bca-form-table__input" id="js-res-filename">-</td>
        </tr>
        <tr>
            <th class="col-head bca-form-table__label"><?= __d('baser_core', '総件数') ?></th>
            <td class="col-input bca-form-table__input" id="js-res-total">-</td>
        </tr>
        <tr>
            <th class="col-head bca-form-table__label"><?= __d('baser_core', '固定ページ') ?></th>
            <td class="col-input bca-form-table__input" id="js-res-pages">-</td>
        </tr>
        <tr>
            <th class="col-head bca-form-table__label"><?= __d('baser_core', 'ブログ記事') ?></th>
            <td class="col-input bca-form-table__input" id="js-res-posts">-</td>
        </tr>
        <tr>
            <th class="col-head bca-form-table__label"><?= __d('baser_core', '有効期限') ?></th>
            <td class="col-input bca-form-table__input" id="js-res-expires">-</td>
        </tr>
        </tbody>
    </table>
    <div class="bca-actions">
        <div class="bca-actions__before"></div>
        <div class="bca-actions__main">
            <a id="js-export-download" href="#" class="bca-btn bca-actions__item" data-bca-btn-type="download" data-bca-btn-size="lg" data-bca-btn-width="lg">
                <?= __d('baser_core', 'WXR をダウンロード') ?>
            </a>
        </div>
        <div class="bca-actions__sub">
            <button id="js-restart-btn" class="bca-btn bca-actions__item"><?= __d('baser_core', '別のエクスポートを実行') ?></button>
        </div>
    </div>
</section>

<div class="bca-collapse__action" id="js-history-collapse-action">
    <button type="button"
            class="bca-collapse__btn"
            data-bca-collapse="collapse"
            data-bca-target="#js-history-body"
            aria-expanded="false"
            aria-controls="js-history-body">
        <?= __d('baser_core', '最近の履歴') ?>&nbsp;&nbsp;<i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
    </button>
</div>
<div class="bca-collapse" id="js-history-body" data-bca-state="" style="display:none;">
    <section class="bca-section" data-bca-section-type="form-group">
        <?php if (empty($historyJobs)): ?>
        <p class="bca-form__note" id="js-history-empty"><?= __d('baser_core', '最近の履歴はありません。') ?></p>
        <?php endif; ?>
        <div class="bc-wp-export__scroll-table">
        <table class="bca-table-listup" id="js-history-table"<?= empty($historyJobs) ? ' style="display:none;"' : '' ?>>
            <thead class="bca-table-listup__thead">
            <tr>
                <th class="bca-table-listup__thead-th" style="width:2.5rem;">
                    <input type="checkbox" id="js-history-check-all" title="<?= __d('baser_core', 'すべて選択') ?>">
                </th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', '完了日時') ?></th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', '状態') ?></th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', '件数') ?></th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', 'ファイル名') ?></th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', '有効期限') ?></th>
                <th class="bca-table-listup__thead-th"><?= __d('baser_core', '操作') ?></th>
            </tr>
            </thead>
            <tbody class="bca-table-listup__tbody" id="js-history-tbody">
            <?php foreach ($historyJobs as $job): ?>
                <tr data-job-token="<?= h($job->job_token) ?>">
                    <td class="bca-table-listup__tbody-td">
                        <input type="checkbox" class="js-history-check" value="<?= h($job->job_token) ?>">
                    </td>
                    <td class="bca-table-listup__tbody-td"><?= h(isset($job->ended_at) ? (new DateTime((string)$job->ended_at))->format('Y-m-d H:i:s') : ($job->modified ? (new DateTime((string)$job->modified))->format('Y-m-d H:i:s') : '-')) ?></td>
                    <td class="bca-table-listup__tbody-td"><?= h($job->status) ?></td>
                    <td class="bca-table-listup__tbody-td"><?= number_format((int)$job->success_count) ?> 件</td>
                    <td class="bca-table-listup__tbody-td"><?= h($job->output_filename ?: '-') ?></td>
                    <td class="bca-table-listup__tbody-td"><?= h(isset($job->expires_at) ? (new DateTime((string)$job->expires_at))->format('Y-m-d H:i:s') : '-') ?></td>
                    <td class="bca-table-listup__tbody-td bca-table-listup__tbody-td--actions">
                        <?php if ($job->output_path && file_exists((string)$job->output_path)): ?><a href="<?= h($adminBase . '/download/' . $job->job_token) ?>" class="bca-btn bca-actions__item" data-bca-btn-type="download"><?= __d('baser_core', 'ダウンロード') ?></a><?php endif; ?><button type="button" class="bca-btn bca-actions__item js-history-delete-btn" data-bca-btn-type="delete" data-job-token="<?= h($job->job_token) ?>"><?= __d('baser_core', '削除') ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="bca-actions" id="js-history-bulk-actions" style="<?= empty($historyJobs) ? 'display:none;' : '' ?>">
            <div class="bca-actions__before"></div>
            <div class="bca-actions__main">
                <button type="button" id="js-history-delete-all-btn" class="bca-btn bca-actions__item" data-bca-btn-type="delete" disabled>
                    <?= __d('baser_core', '選択した履歴を削除') ?>
                </button>
            </div>
            <div class="bca-actions__sub"></div>
        </div>
    </section>
</div>

<script>
    window.bcWpExportConfig = {
        adminBase: '<?= h($adminBase) ?>',
        csrfToken: '<?= h($csrfToken) ?>',
        categoriesByBlog: <?= json_encode($categoriesByBlog, JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="/bc_wp_export/js/admin/wp_export.js"></script>
