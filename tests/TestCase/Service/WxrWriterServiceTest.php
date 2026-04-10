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
        $this->assertStringContainsString('<wp:post_type>post</wp:post_type>', $xml);
        $this->assertStringContainsString('<wp:post_id>1</wp:post_id>', $xml);
        $this->assertStringContainsString('<wp:post_parent>0</wp:post_parent>', $xml);
        $this->assertStringContainsString('<category domain="category" nicename="news"><![CDATA[News]]></category>', $xml);
    }
}
