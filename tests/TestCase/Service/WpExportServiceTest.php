<?php
declare(strict_types=1);

namespace BcWpExport\Test\TestCase\Service;

use BaserCore\TestSuite\BcTestCase;
use BcWpExport\Service\WpExportService;
use ReflectionMethod;

/**
 * WpExportService のユニットテスト
 *
 * DB に依存するメソッド（createJob・collectPages 等）は結合テストとして別途実施予定。
 * ここでは DB 非依存の protected/private メソッドを ReflectionMethod でテストする。
 */
class WpExportServiceTest extends BcTestCase
{
    private WpExportService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new WpExportService();
    }

    // -------------------------------------------------------------------------
    // absolutizeUrls
    // -------------------------------------------------------------------------

    public function testAbsolutizeUrlsConvertsRootRelativeHref(): void
    {
        $method = new ReflectionMethod(WpExportService::class, 'absolutizeUrls');
        $method->setAccessible(true);

        $input = '<a href="/about/team">チーム</a>';
        $result = $method->invoke($this->service, $input, 'https://example.com');

        $this->assertEquals('<a href="https://example.com/about/team">チーム</a>', $result);
    }

    public function testAbsolutizeUrlsConvertsSrcAttribute(): void
    {
        $method = new ReflectionMethod(WpExportService::class, 'absolutizeUrls');
        $method->setAccessible(true);

        $input = '<img src="/uploads/photo.jpg">';
        $result = $method->invoke($this->service, $input, 'https://example.com');

        $this->assertEquals('<img src="https://example.com/uploads/photo.jpg">', $result);
    }

    public function testAbsolutizeUrlsLeavesAbsoluteUrlsUntouched(): void
    {
        $method = new ReflectionMethod(WpExportService::class, 'absolutizeUrls');
        $method->setAccessible(true);

        $input = '<a href="https://other.example.com/page">外部リンク</a>';
        $result = $method->invoke($this->service, $input, 'https://example.com');

        $this->assertEquals($input, $result);
    }

    public function testAbsolutizeUrlsIgnoresProtocolRelativeUrls(): void
    {
        $method = new ReflectionMethod(WpExportService::class, 'absolutizeUrls');
        $method->setAccessible(true);

        // "//" で始まる URL は変換しない（正規表現条件: \/(?!\/)）
        $input = '<img src="//cdn.example.com/image.jpg">';
        $result = $method->invoke($this->service, $input, 'https://example.com');

        $this->assertEquals($input, $result);
    }

    public function testAbsolutizeUrlsReturnsSameContentWhenSiteUrlEmpty(): void
    {
        $method = new ReflectionMethod(WpExportService::class, 'absolutizeUrls');
        $method->setAccessible(true);

        $input = '<a href="/about">About</a>';
        $result = $method->invoke($this->service, $input, '');

        $this->assertEquals($input, $result);
    }

    public function testAbsolutizeUrlsHandlesTrailingSlashInSiteUrl(): void
    {
        $method = new ReflectionMethod(WpExportService::class, 'absolutizeUrls');
        $method->setAccessible(true);

        // siteUrl の末尾スラッシュが重複しないこと
        $input = '<a href="/contact">コンタクト</a>';
        $result = $method->invoke($this->service, $input, 'https://example.com/');

        $this->assertEquals('<a href="https://example.com/contact">コンタクト</a>', $result);
    }

    public function testAbsolutizeUrlsConvertsMultipleInSameContent(): void
    {
        $method = new ReflectionMethod(WpExportService::class, 'absolutizeUrls');
        $method->setAccessible(true);

        $input = '<a href="/a">A</a> <img src="/b/c.jpg"> <a href="https://external.com/x">X</a>';
        $result = $method->invoke($this->service, $input, 'https://example.com');

        $this->assertStringContainsString('href="https://example.com/a"', $result);
        $this->assertStringContainsString('src="https://example.com/b/c.jpg"', $result);
        // 絶対 URL はそのまま
        $this->assertStringContainsString('href="https://external.com/x"', $result);
    }
}
