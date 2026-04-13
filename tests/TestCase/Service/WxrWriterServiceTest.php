<?php
declare(strict_types=1);

namespace BcWpExport\Test\TestCase\Service;

use BaserCore\TestSuite\BcTestCase;
use BcWpExport\Service\WxrWriterService;

class WxrWriterServiceTest extends BcTestCase
{
    public function testBuildEmptyDocument(): void
    {
        $service = new WxrWriterService();
        $xml = $service->buildEmptyDocument();

        $this->assertStringContainsString('<rss version="2.0">', $xml);
    }

    public function testBuildDocument(): void
    {
        $service = new WxrWriterService();
        $xml = $service->buildDocument([
            'channel_title' => 'Example Export',
            'language' => 'ja',
            'include_site_meta' => true,
            'authors' => [
                [
                    'login' => 'admin',
                    'display_name' => 'Admin User',
                    'email' => 'admin@example.com',
                ],
            ],
            'items' => [
                [
                    'title' => 'Hello',
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'post_name' => 'hello',
                    'post_date' => '2026-04-08 10:00:00',
                    'post_date_gmt' => '2026-04-08 01:00:00',
                    'creator' => 'admin',
                    'content' => '<p>Body</p>',
                    'excerpt' => 'Summary',
                    'categories' => [
                        ['slug' => 'news', 'label' => 'News'],
                    ],
                    'tags' => [
                        ['slug' => 'release', 'label' => 'Release'],
                    ],
                    'wp_post_id' => 1,
                    'wp_post_parent' => 0,
                ],
            ],
            'summary' => [
                'posts' => 1,
                'pages' => 2,
            ],
        ]);

        $this->assertStringContainsString('<title>Example Export</title>', $xml);
        $this->assertStringContainsString('<wp:wxr_version>1.2</wp:wxr_version>', $xml);
        $this->assertStringContainsString('<wp:posts>1</wp:posts>', $xml);
        $this->assertStringContainsString('<wp:pages>2</wp:pages>', $xml);
        $this->assertStringContainsString('<wp:author_login><![CDATA[admin]]></wp:author_login>', $xml);
        // item 内の名前空間要素は xmlns 再宣言が入る場合があるため正規化してアサート
        $normalized = preg_replace('/ xmlns:[a-z]+="[^"]*"/', '', $xml);
        $this->assertStringContainsString('<wp:post_type>post</wp:post_type>', $normalized);
        $this->assertStringContainsString('<wp:post_id>1</wp:post_id>', $normalized);
        $this->assertStringContainsString('<wp:post_parent>0</wp:post_parent>', $normalized);
        $this->assertStringContainsString('<category domain="category" nicename="news"><![CDATA[News]]></category>', $xml);
    }

    public function testBuildDocumentAttachmentItem(): void
    {
        $service = new WxrWriterService();
        $xml = $service->buildDocument([
            'summary' => ['total_items' => 2],
            'items' => [
             [
                 'title' => 'Featured Image',
                 'post_type' => 'attachment',
                 'post_status' => 'inherit',
                 'post_name' => 'featured-image',
                 'post_date' => '2026-04-08 10:00:00',
                 'post_date_gmt' => '2026-04-08 01:00:00',
                 'creator' => 'admin',
                 'content' => '',
                 'excerpt' => '',
                 'wp_post_id' => 5,
                 'wp_post_parent' => 3,
                 'attachment_url' => 'https://example.com/wp-content/uploads/image.jpg',
                 'categories' => [],
                 'tags' => [],
             ],
             [
                 'title' => 'Article',
                 'post_type' => 'post',
                 'post_status' => 'publish',
                 'post_name' => 'article',
                 'post_date' => '2026-04-08 10:00:00',
                 'post_date_gmt' => '2026-04-08 01:00:00',
                 'creator' => 'admin',
                 'content' => '<p>Body</p>',
                 'excerpt' => '',
                 'wp_post_id' => 3,
                 'wp_post_parent' => 0,
                 'categories' => [],
                 'tags' => [],
                 'postmeta' => [
                     ['key' => '_thumbnail_id', 'value' => '5'],
                 ],
             ],
         ],
        ]);

        // DOMDocument が名前空間を要素ごとに再宣言することがあるため、
        // xmlns:* 属性を除去して正規化してからアサートする
        $normalized = preg_replace('/ xmlns:[a-z]+="[^"]*"/', '', $xml);

        // attachment アイテムの post_type と URL が出力されること
        $this->assertStringContainsString('<wp:post_type>attachment</wp:post_type>', $normalized);
        $this->assertStringContainsString('<wp:attachment_url>https://example.com/wp-content/uploads/image.jpg</wp:attachment_url>', $normalized);

        // attachment の wp_post_parent が親記事 ID を指していること
        $this->assertStringContainsString('<wp:post_parent>3</wp:post_parent>', $normalized);

        // 親記事に _thumbnail_id postmeta が付与されること
        $this->assertStringContainsString('<wp:meta_key>_thumbnail_id</wp:meta_key>', $normalized);
        $this->assertStringContainsString('<wp:meta_value>5</wp:meta_value>', $normalized);
    }

    public function testBuildDocumentPageHierarchy(): void
    {
        $service = new WxrWriterService();
        $xml = $service->buildDocument([
            'summary' => ['total_items' => 2],
            'items' => [
             [
                 'title' => 'About',
                 'post_type' => 'page',
                 'post_status' => 'publish',
                 'post_name' => 'about',
                 'post_date' => '2026-01-01 00:00:00',
                 'post_date_gmt' => '2025-12-31 15:00:00',
                 'creator' => 'admin',
                 'content' => '',
                 'excerpt' => '',
                 'wp_post_id' => 100,
                 'wp_post_parent' => 0,
                 'categories' => [],
                 'tags' => [],
             ],
             [
                 'title' => 'Team',
                 'post_type' => 'page',
                 'post_status' => 'publish',
                 'post_name' => 'team',
                 'post_date' => '2026-01-01 00:00:00',
                 'post_date_gmt' => '2025-12-31 15:00:00',
                 'creator' => 'admin',
                 'content' => '',
                 'excerpt' => '',
                 'wp_post_id' => 101,
                 'wp_post_parent' => 100,
                 'categories' => [],
                 'tags' => [],
             ],
         ],
        ]);

        // DOMDocument が名前空間を要素ごとに再宣言することがあるため、
        // xmlns:* 属性を除去して正規化してからアサートする
        $normalized = preg_replace('/ xmlns:[a-z]+="[^"]*"/', '', $xml);

        $this->assertStringContainsString('<wp:post_id>100</wp:post_id>', $normalized);
        $this->assertStringContainsString('<wp:post_id>101</wp:post_id>', $normalized);
        // 子ページの wp_post_parent が親の wp_post_id を指していること
        $this->assertStringContainsString('<wp:post_parent>100</wp:post_parent>', $normalized);
        // 親ページの wp_post_parent は 0
        $this->assertStringContainsString('<wp:post_parent>0</wp:post_parent>', $normalized);
    }
}
