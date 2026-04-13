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

    private ReflectionMethod $absolutizeUrlsMethod;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new WpExportService();
        $this->absolutizeUrlsMethod = new ReflectionMethod(WpExportService::class, 'absolutizeUrls');
    }

    // -------------------------------------------------------------------------
    // absolutizeUrls
    // -------------------------------------------------------------------------

    public function testAbsolutizeUrlsConvertsRootRelativeHref(): void
    {
        $input = '<a href="/about/team">チーム</a>';
        $result = $this->absolutizeUrlsMethod->invoke($this->service, $input, 'https://example.com');

        $this->assertEquals('<a href="https://example.com/about/team">チーム</a>', $result);
    }

    public function testAbsolutizeUrlsConvertsSrcAttribute(): void
    {
        $input = '<img src="/uploads/photo.jpg">';
        $result = $this->absolutizeUrlsMethod->invoke($this->service, $input, 'https://example.com');

        $this->assertEquals('<img src="https://example.com/uploads/photo.jpg">', $result);
    }

    public function testAbsolutizeUrlsLeavesAbsoluteUrlsUntouched(): void
    {
        $input = '<a href="https://other.example.com/page">外部リンク</a>';
        $result = $this->absolutizeUrlsMethod->invoke($this->service, $input, 'https://example.com');

        $this->assertEquals($input, $result);
    }

    public function testAbsolutizeUrlsIgnoresProtocolRelativeUrls(): void
    {
        // "//" で始まる URL は変換しない（正規表現条件: \/(?!\/)）
        $input = '<img src="//cdn.example.com/image.jpg">';
        $result = $this->absolutizeUrlsMethod->invoke($this->service, $input, 'https://example.com');

        $this->assertEquals($input, $result);
    }

    public function testAbsolutizeUrlsReturnsSameContentWhenSiteUrlEmpty(): void
    {
        $input = '<a href="/about">About</a>';
        $result = $this->absolutizeUrlsMethod->invoke($this->service, $input, '');

        $this->assertEquals($input, $result);
    }

    public function testAbsolutizeUrlsHandlesTrailingSlashInSiteUrl(): void
    {
        // siteUrl の末尾スラッシュが重複しないこと
        $input = '<a href="/contact">コンタクト</a>';
        $result = $this->absolutizeUrlsMethod->invoke($this->service, $input, 'https://example.com/');

        $this->assertEquals('<a href="https://example.com/contact">コンタクト</a>', $result);
    }

    public function testAbsolutizeUrlsConvertsMultipleInSameContent(): void
    {
        $input = '<a href="/a">A</a> <img src="/b/c.jpg"> <a href="https://external.com/x">X</a>';
        $result = $this->absolutizeUrlsMethod->invoke($this->service, $input, 'https://example.com');

        $this->assertStringContainsString('href="https://example.com/a"', $result);
        $this->assertStringContainsString('src="https://example.com/b/c.jpg"', $result);
        // 絶対 URL はそのまま
        $this->assertStringContainsString('href="https://external.com/x"', $result);
    }
}
