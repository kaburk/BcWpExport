<?php
declare(strict_types=1);

namespace BcWpExport\Service;

use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use RuntimeException;

class WpExportService
{
    public function __construct(protected WxrWriterService $writerService = new WxrWriterService())
    {
    }

    /**
     * ジョブエンティティの初期値配列を生成する（設定で上書き可能）
     */
    public function buildInitialJobData(array $settings = []): array
    {
        return array_merge([
            'status' => 'pending',
            'phase' => 'collect',
            'export_target' => 'all',
            'content_status' => 'published',
            'include_site_meta' => true,
            'include_media_urls' => true,
            'absolute_url' => true,
            'pretty_print' => false,
        ], $settings);
    }

    /**
     * フォームデータを受け取って WXR を生成し、ジョブを保存して返す
     */
    public function createJob(array $data)
    {
        $settings = $this->normalizeSettings($data);
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs');

        $tmpDir = TMP . 'bc_wp_export' . DS;
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $jobToken = bin2hex(random_bytes(16));
        $outputFilename = 'basercms-wxr-' . date('Ymd-His') . '.xml';
        $outputPath = $tmpDir . $jobToken . '.xml';
        $pages = $this->collectPages($settings);
        $posts = $this->collectPosts($settings);
        $summary = $this->buildSourceSummary($pages, $posts);
        $pageItems = $this->buildPageItems($pages, $settings);
        $postItems = $this->buildPostItems($posts, count($pageItems) + 1, $settings);

        // include_media_urls が有効なときはアイキャッチを attachment アイテムとして追加し、
        // 親記事に _thumbnail_id postmeta を付与する
        $attachmentItems = [];
        if (!empty($settings['include_media_urls'])) {
            [$attachmentItems, $thumbnailMap] = $this->buildAttachmentItems(
                $postItems,
                count($pageItems) + count($postItems) + 1
            );
            foreach ($postItems as &$postItem) {
                if (isset($thumbnailMap[$postItem['wp_post_id']])) {
                    $postItem['postmeta'][] = [
                        'key' => '_thumbnail_id',
                        'value' => $thumbnailMap[$postItem['wp_post_id']],
                    ];
                }
            }
            unset($postItem);
        }

        $xml = $this->writerService->buildDocument([
            'job_token' => $jobToken,
            'channel_title' => 'baserCMS Export',
            'language' => 'ja',
            'include_site_meta' => $settings['include_site_meta'],
            'pretty_print' => $settings['pretty_print'],
            'summary' => $summary,
            'authors' => $this->buildAuthors($posts),
            'items' => array_merge($pageItems, $postItems, $attachmentItems),
        ]);
        file_put_contents($outputPath, $xml);

        $job = $jobsTable->newEntity(array_merge(
            $this->buildInitialJobData($settings),
            $settings,
            [
                'job_token' => $jobToken,
                'status' => 'completed',
                'phase' => 'finalize',
                'source_summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'export_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'output_filename' => $outputFilename,
                'output_path' => $outputPath,
                'total_items' => (int) $summary['total_items'],
                'processed' => (int) $summary['total_items'],
                'success_count' => (int) $summary['total_items'],
                'expires_at' => FrozenTime::now()->addDays((int) Configure::read('BcWpExport.jobExpireDays', 3)),
                'started_at' => FrozenTime::now(),
                'ended_at' => FrozenTime::now(),
            ]
        ));
        return $jobsTable->saveOrFail($job);
    }

    /**
     * トークンでジョブを取得する（出力ファイルが存在しない場合は例外）
     */
    public function getJobByToken(string $token)
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs');
        $job = $jobsTable->find()->where(['job_token' => $token])->firstOrFail();
        if (!$job->output_path || !file_exists((string) $job->output_path)) {
            throw new RuntimeException(__d('baser_core', '出力ファイルが見つかりません。'));
        }
        return $job;
    }

    /**
     * フォーム入力値をホワイトリスト検証・型変換して設定配列に正規化する
     */
    protected function normalizeSettings(array $data): array
    {
        return [
            'export_target' => $this->normalizeSelectValue((string) ($data['export_target'] ?? 'all'), ['all', 'posts', 'pages'], 'all'),
            'blog_content_id' => $this->toNullableInt($data['blog_content_id'] ?? null),
            'author_id' => $this->toNullableInt($data['author_id'] ?? null),
            'category_id' => $this->toNullableInt($data['category_id'] ?? null),
            'content_status' => $this->normalizeSelectValue((string) ($data['content_status'] ?? 'published'), ['published', 'all', 'draft_included'], 'published'),
            'date_from' => $this->toNullableString($data['date_from'] ?? null),
            'date_to' => $this->toNullableString($data['date_to'] ?? null),
            'include_site_meta' => $this->toBool($data['include_site_meta'] ?? false),
            'include_media_urls' => $this->toBool($data['include_media_urls'] ?? false),
            'absolute_url' => $this->toBool($data['absolute_url'] ?? false),
            'pretty_print' => $this->toBool($data['pretty_print'] ?? false),
        ];
    }

    /**
     * 書き出し件数のサマリ（pages/posts/categories/tags/authors/total）を集計して返す
     */
    protected function buildSourceSummary(array $pages, array $posts): array
    {
        $categoryIds = [];
        $tagIds = [];
        $authorIds = [];

        foreach ($posts as $post) {
            if (!empty($post->blog_category_id)) {
                $categoryIds[] = (int) $post->blog_category_id;
            }
            foreach ($post->blog_tags ?? [] as $tag) {
                $tagIds[] = (int) $tag->id;
            }
            if (!empty($post->user_id)) {
                $authorIds[] = (int) $post->user_id;
            }
        }

        return [
            'pages' => count($pages),
            'posts' => count($posts),
            'categories' => count(array_unique($categoryIds)),
            'tags' => count(array_unique($tagIds)),
            'authors' => count(array_unique($authorIds)),
            'excluded_candidates' => 0,
            'total_items' => count($pages) + count($posts),
        ];
    }

    /**
     * 許可リストにない値はデフォルト値にフォールバックする
     */
    protected function normalizeSelectValue(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * 空文字・null を null に、それ以外を int に変換する
     */
    protected function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    /**
     * 空文字・null を null に、それ以外をトリム済み文字列に変換する
     */
    protected function toNullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? null : $value;
    }

    /**
     * フォームの '1' / 'true' / 'on' などを bool に変換する
     */
    protected function toBool(mixed $value): bool
    {
        return in_array($value, ['1', 1, true, 'true', 'on'], true);
    }

    /**
     * 設定条件に従って固定ページを DB から収集する
     */
    protected function collectPages(array $settings): array
    {
        if ($settings['export_target'] === 'posts') {
            return [];
        }

        $pagesTable = TableRegistry::getTableLocator()->get('BaserCore.Pages');
        $query = $pagesTable->find()->contain(['Contents']);
        $query->where(['Contents.type' => 'Page', 'Contents.deleted_date IS' => null]);
        if ($settings['content_status'] === 'published') {
            $query->where(['Contents.status' => true]);
        }
        $this->applyDateRange($query, 'Pages.modified', $settings);
        return $query->all()->toList();
    }

    /**
     * 設定条件に従ってブログ記事を DB から収集する
     */
    protected function collectPosts(array $settings): array
    {
        if ($settings['export_target'] === 'pages') {
            return [];
        }

        $postsTable = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        $query = $postsTable->find()
            ->select([
                'BlogPosts.id',
                'BlogPosts.blog_content_id',
                'BlogPosts.blog_category_id',
                'BlogPosts.user_id',
                'BlogPosts.title',
                'BlogPosts.name',
                'BlogPosts.posted',
                'BlogPosts.created',
                'BlogPosts.status',
                'BlogPosts.detail',
                'BlogPosts.content',
                'BlogPosts.eye_catch',
            ])
            ->contain([
                'BlogCategories' => function (SelectQuery $query) {
                    return $query->select([
                        'BlogCategories.id',
                        'BlogCategories.name',
                        'BlogCategories.title',
                        'BlogCategories.parent_id',
                    ]);
                },
                'BlogTags' => function (SelectQuery $query) {
                    return $query->select([
                        'BlogTags.id',
                        'BlogTags.name',
                    ]);
                },
                'Users' => function (SelectQuery $query) {
                    return $query->select([
                        'Users.id',
                        'Users.name',
                        'Users.real_name_1',
                        'Users.real_name_2',
                        'Users.nickname',
                        'Users.email',
                    ]);
                },
            ]);

        if (!empty($settings['blog_content_id'])) {
            $query->where(['BlogPosts.blog_content_id' => $settings['blog_content_id']]);
        }
        if (!empty($settings['author_id'])) {
            $query->where(['BlogPosts.user_id' => $settings['author_id']]);
        }
        if (!empty($settings['category_id'])) {
            $query->where(['BlogPosts.blog_category_id' => $settings['category_id']]);
        }
        if ($settings['content_status'] === 'published') {
            $query->where(['BlogPosts.status' => true]);
        }
        $this->applyDateRange($query, 'BlogPosts.posted', $settings);
        return $query->all()->toList();
    }

    /**
     * date_from / date_to に基づく日付範囲条件をクエリに追加する
     */
    protected function applyDateRange(SelectQuery $query, string $field, array $settings): void
    {
        if (!empty($settings['date_from'])) {
            $query->where([$field . ' >=' => $settings['date_from'] . ' 00:00:00']);
        }
        if (!empty($settings['date_to'])) {
            $query->where([$field . ' <=' => $settings['date_to'] . ' 23:59:59']);
        }
    }

    /**
     * ブログ記事一覧から投稿者情報（重複なし）を抽出して WXR author 配列を返す
     */
    protected function buildAuthors(array $posts): array
    {
        $authors = [];
        foreach ($posts as $post) {
            if (empty($post->user)) {
                continue;
            }
            $displayName = '';
            if (!empty($post->user->nickname)) {
                $displayName = $post->user->nickname;
            } elseif (!empty($post->user->real_name_1) || !empty($post->user->real_name_2)) {
                $displayName = trim(($post->user->real_name_1 ?? '') . ' ' . ($post->user->real_name_2 ?? ''));
            } else {
                $displayName = $post->user->name ?? '';
            }
            $authors[$post->user->id] = [
                'login' => (string) ($post->user->name ?? ('user-' . $post->user->id)),
                'display_name' => $displayName,
                'email' => (string) ($post->user->email ?? ''),
            ];
        }
        return array_values($authors);
    }

    /**
     * 固定ページ一覧を WXR item 配列に変換する（親子関係・URL絶対化を含む）
     */
    protected function buildPageItems(array $pages, array $settings = []): array
    {
        $siteUrl = !empty($settings['absolute_url']) ? rtrim((string) Configure::read('App.fullBaseUrl'), '/') : '';

        // First pass: build content_id → sequential wp_post_id map
        $contentIdMap = [];
        $postId = 1;
        foreach ($pages as $page) {
            if (!empty($page->content->id)) {
                $contentIdMap[(int) $page->content->id] = $postId;
            }
            $postId++;
        }

        // Second pass: build items
        $items = [];
        $postId = 1;
        foreach ($pages as $page) {
            $parentContentId = (int) ($page->content->parent_id ?? 0);
            $rawContent = (string) ($page->contents ?? '');
            $items[] = [
                'wp_post_id' => $postId,
                'wp_post_parent' => $contentIdMap[$parentContentId] ?? 0,
                'title' => (string) ($page->content->title ?? ''),
                'post_type' => 'page',
                'post_status' => !empty($page->content->status) ? 'publish' : 'draft',
                'post_name' => (string) ($page->content->name ?? ''),
                'post_date' => $this->formatDateTime($page->created ?? $page->modified ?? null),
                'post_date_gmt' => $this->formatDateTime($page->created ?? $page->modified ?? null),
                'creator' => '',
                'content' => $siteUrl ? $this->absolutizeUrls($rawContent, $siteUrl) : $rawContent,
                'excerpt' => '',
                'categories' => [],
                'tags' => [],
            ];
            $postId++;
        }
        return $items;
    }

    /**
     * ブログ記事一覧を WXR item 配列に変換する（カテゴリ・タグ・URL絶対化を含む）
     */
    protected function buildPostItems(array $posts, int $startId = 1, array $settings = []): array
    {
        $siteUrl = !empty($settings['absolute_url']) ? rtrim((string) Configure::read('App.fullBaseUrl'), '/') : '';
        $fullBase = rtrim((string) Configure::read('App.fullBaseUrl'), '/');
        $includeMedia = !empty($settings['include_media_urls']);
        $categoryMap = $this->buildCategoryMap($posts);
        $items = [];
        $postId = $startId;
        foreach ($posts as $post) {
            $categories = [];
            if (!empty($post->blog_category_id)) {
                foreach ($this->buildCategoryAncestors((int) $post->blog_category_id, $categoryMap) as $cat) {
                    $categories[] = [
                        'slug' => (string) ($cat['name'] ?? ''),
                        'label' => (string) ($cat['title'] ?? $cat['name'] ?? ''),
                    ];
                }
            }
            $tags = [];
            foreach ($post->blog_tags ?? [] as $tag) {
                $tags[] = [
                    'slug' => (string) ($tag->name ?? ''),
                    'label' => (string) ($tag->name ?? ''),
                ];
            }
            $rawDetail = (string) ($post->detail ?? '');
            $rawExcerpt = (string) ($post->content ?? '');

            // include_media_urls が有効なときのみアイキャッチの絶対 URL を生成する
            $eyeCatchUrl = null;
            $eyeCatchFilename = null;
            if ($includeMedia && !empty($post->eye_catch)) {
                $blogContentId = (int) ($post->blog_content_id ?? 0);
                $eyeCatchUrl = $fullBase . '/files/blog/' . $blogContentId . '/blog_posts/' . ltrim((string) $post->eye_catch, '/');
                $eyeCatchFilename = basename((string) $post->eye_catch);
            }

            $items[] = [
                'wp_post_id' => $postId,
                'wp_post_parent' => 0,
                'title' => (string) ($post->title ?? ''),
                'post_type' => 'post',
                'post_status' => !empty($post->status) ? 'publish' : 'draft',
                'post_name' => (string) ($post->name ?? ''),
                'post_date' => $this->formatDateTime($post->posted ?? $post->created ?? null),
                'post_date_gmt' => $this->formatDateTime($post->posted ?? $post->created ?? null),
                'creator' => (string) ($post->user->name ?? ''),
                'content' => $siteUrl ? $this->absolutizeUrls($rawDetail, $siteUrl) : $rawDetail,
                'excerpt' => $siteUrl ? $this->absolutizeUrls($rawExcerpt, $siteUrl) : $rawExcerpt,
                'categories' => $categories,
                'tags' => $tags,
                'postmeta' => [],
                'eye_catch_url' => $eyeCatchUrl,
                'eye_catch_filename' => $eyeCatchFilename,
            ];
            $postId++;
        }
        return $items;
    }

    /**
     * 日時値を 'Y-m-d H:i:s' 形式の文字列に変換する（null の場合は現在時刻）
     */
    protected function formatDateTime(mixed $value): string
    {
        if (!$value) {
            return FrozenTime::now()->format('Y-m-d H:i:s');
        }
        if ($value instanceof FrozenTime) {
            return $value->format('Y-m-d H:i:s');
        }
        return (string) $value;
    }

    /**
     * カテゴリエンティティから祖先を遡り、ルートから末端までの階層配列を返す
     */
    protected function buildCategoryAncestors(int $categoryId, array $categoryMap): array
    {
        if (!$categoryId || empty($categoryMap[$categoryId])) {
            return [];
        }

        $parents = [];
        $currentId = $categoryId;
        $visited = [];
        while (!empty($categoryMap[$currentId])) {
            if (in_array($currentId, $visited, true)) {
                break;
            }
            $visited[] = $currentId;
            $category = $categoryMap[$currentId];
            array_unshift($parents, $category);
            $currentId = (int) ($category['parent_id'] ?? 0);
            if (!$currentId) {
                break;
            }
        }

        return $parents;
    }

    /**
     * 投稿に紐づくカテゴリと祖先カテゴリをまとめて取得し、ID をキーにした配列で返す
     */
    protected function buildCategoryMap(array $posts): array
    {
        $categoryIds = [];
        foreach ($posts as $post) {
            if (!empty($post->blog_category_id)) {
                $categoryIds[] = (int) $post->blog_category_id;
            }
        }
        $categoryIds = array_values(array_unique($categoryIds));
        if (!$categoryIds) {
            return [];
        }

        $categoriesTable = TableRegistry::getTableLocator()->get('BcBlog.BlogCategories');
        $categoryMap = [];
        $pendingIds = $categoryIds;

        while ($pendingIds) {
            $rows = $categoriesTable->find()
                ->select(['id', 'name', 'title', 'parent_id'])
                ->where(['id IN' => $pendingIds])
                ->all()
                ->toList();
            $pendingIds = [];
            foreach ($rows as $row) {
                $id = (int) $row->id;
                if (isset($categoryMap[$id])) {
                    continue;
                }
                $categoryMap[$id] = [
                    'id' => $id,
                    'name' => (string) ($row->name ?? ''),
                    'title' => (string) ($row->title ?? $row->name ?? ''),
                    'parent_id' => (int) ($row->parent_id ?? 0),
                ];
                if (!empty($row->parent_id) && !isset($categoryMap[(int) $row->parent_id])) {
                    $pendingIds[] = (int) $row->parent_id;
                }
            }
            $pendingIds = array_values(array_unique($pendingIds));
        }

        return $categoryMap;
    }

    /**
     * post items からアイキャッチ attachment アイテムを生成し、
     * 親記事の wp_post_id と attachment の wp_post_id の対応表も返す
     *
     * @return array{0: array, 1: array<int, int>}  [$attachmentItems, $thumbnailMap]
     */
    protected function buildAttachmentItems(array $postItems, int $startId): array
    {
        $attachmentItems = [];
        $thumbnailMap = []; // [親記事の wp_post_id => attachment の wp_post_id]
        $attachId = $startId;

        foreach ($postItems as $postItem) {
            if (empty($postItem['eye_catch_url'])) {
                continue;
            }
            $url = (string) $postItem['eye_catch_url'];
            $filename = (string) ($postItem['eye_catch_filename'] ?? basename($url));
            $baseName = pathinfo($filename, PATHINFO_FILENAME);

            $thumbnailMap[(int) $postItem['wp_post_id']] = $attachId;
            $attachmentItems[] = [
                'wp_post_id' => $attachId,
                'wp_post_parent' => (int) $postItem['wp_post_id'],
                'title' => $baseName,
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'post_name' => $baseName,
                'post_date' => $postItem['post_date'],
                'post_date_gmt' => $postItem['post_date_gmt'],
                'creator' => '',
                'content' => '',
                'excerpt' => '',
                'categories' => [],
                'tags' => [],
                'postmeta' => [],
                'attachment_url' => $url,
            ];
            $attachId++;
        }

        return [$attachmentItems, $thumbnailMap];
    }

    /**
     * 本文中のルート相対 URL（/path）をサイト URL を付与した絶対 URL に変換する
     */
    protected function absolutizeUrls(string $content, string $siteUrl): string
    {
        if (!$content || !$siteUrl) {
            return $content;
        }
        return (string) preg_replace_callback(
            '/(href|src)=(["\'])(\/(?!\/))/u',
            fn($m) => $m[1] . '=' . $m[2] . rtrim($siteUrl, '/') . '/',
            $content
        );
    }
}
