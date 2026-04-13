<?php
declare(strict_types=1);

namespace BcWpExport\Test\TestCase\Controller\Admin;

use BaserCore\Test\Scenario\InitAppScenario;
use BaserCore\Test\Scenario\RootContentScenario;
use BaserCore\TestSuite\BcTestCase;
use BcBlog\Test\Scenario\BlogContentScenario;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use CakephpFixtureFactories\Scenario\ScenarioAwareTrait;

class WpExportsControllerTest extends BcTestCase
{
    use IntegrationTestTrait;
    use ScenarioAwareTrait;

    private array $tempPaths = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->loadFixtureScenario(InitAppScenario::class);
        $this->loadFixtureScenario(RootContentScenario::class, 1, 1, null, 'root', '/');
        $this->loadFixtureScenario(BlogContentScenario::class, 101, 1, 1, 'wp-export-test-blog', '/wp-export-test-blog/');
        TableRegistry::getTableLocator()->get('BaserCore.Contents')->recover();
    }

    public function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function testIndex(): void
    {
        $job = $this->createJob('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', [
            'status' => 'completed',
            'phase' => 'finalize',
        ]);

        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-export/wp_exports/index'));
        $this->get('/baser/admin/bc-wp-export/wp_exports/index');

        $this->assertResponseOk();
        $vars = $this->_controller->viewBuilder()->getVars();
        $this->assertArrayHasKey('pendingJobs', $vars);
        $this->assertArrayHasKey('historyJobs', $vars);
        $this->assertArrayHasKey('blogList', $vars);
        $this->assertArrayHasKey('categoriesByBlog', $vars);
        $this->assertArrayHasKey('userList', $vars);
        $this->assertNotEmpty($vars['historyJobs']);
        $this->assertEquals($job->job_token, $vars['historyJobs'][0]->job_token);
    }

    public function testCreate(): void
    {
        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-export/wp_exports/create'));
        $this->enableSecurityToken();
        $this->enableCsrfToken();

        $this->post('/baser/admin/bc-wp-export/wp_exports/create', [
            'export_target' => 'posts',
            'blog_content_id' => 101,
            'content_status' => 'published',
            'include_site_meta' => '1',
            'include_media_urls' => '0',
            'absolute_url' => '1',
            'pretty_print' => '0',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('application/json');

        $data = json_decode((string) $this->_response->getBody(), true);
        $this->assertSame('completed', $data['result']['status']);
        $this->assertSame('finalize', $data['result']['phase']);
        $this->assertNotEmpty($data['result']['job_token']);

        $job = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs')
            ->find()->where(['job_token' => $data['result']['job_token']])->firstOrFail();
        $this->assertFileExists((string) $job->output_path);
        $this->tempPaths[] = (string) $job->output_path;
    }

    public function testDownload(): void
    {
        $token = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $outputPath = TMP . 'bc_wp_export' . DS . $token . '.xml';
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0777, true);
        }
        file_put_contents($outputPath, "<rss version=\"2.0\"></rss>");
        $this->tempPaths[] = $outputPath;

        $this->createJob($token, [
            'status' => 'completed',
            'phase' => 'finalize',
            'output_filename' => 'test-export.xml',
            'output_path' => $outputPath,
        ]);

        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-export/wp_exports/download/' . $token));
        $this->get('/baser/admin/bc-wp-export/wp_exports/download/' . $token);

        $this->assertResponseOk();
        $this->assertHeaderContains('Content-Disposition', 'attachment; filename="test-export.xml"');
        $this->assertStringContainsString('<rss version="2.0"></rss>', (string) $this->_response->getBody());
    }

    public function testDelete(): void
    {
        $token = 'cccccccccccccccccccccccccccccccc';
        $outputPath = TMP . 'bc_wp_export' . DS . $token . '.xml';
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0777, true);
        }
        file_put_contents($outputPath, "dummy");

        $this->createJob($token, [
            'output_path' => $outputPath,
            'status' => 'completed',
            'phase' => 'finalize',
        ]);

        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-export/wp_exports/delete/' . $token));
        $this->enableSecurityToken();
        $this->enableCsrfToken();
        $this->post('/baser/admin/bc-wp-export/wp_exports/delete/' . $token);

        $this->assertResponseOk();
        $data = json_decode((string) $this->_response->getBody(), true);
        $this->assertTrue($data['result']);
        $this->assertFileDoesNotExist($outputPath);

        $count = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs')
            ->find()->where(['job_token' => $token])->count();
        $this->assertSame(0, $count);
    }

    public function testDeleteAll(): void
    {
        $token1 = 'dddddddddddddddddddddddddddddddd';
        $token2 = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
        $outputPath1 = TMP . 'bc_wp_export' . DS . $token1 . '.xml';
        $outputPath2 = TMP . 'bc_wp_export' . DS . $token2 . '.xml';
        if (!is_dir(dirname($outputPath1))) {
            mkdir(dirname($outputPath1), 0777, true);
        }
        file_put_contents($outputPath1, 'dummy1');
        file_put_contents($outputPath2, 'dummy2');

        $this->createJob($token1, ['output_path' => $outputPath1, 'status' => 'completed', 'phase' => 'finalize']);
        $this->createJob($token2, ['output_path' => $outputPath2, 'status' => 'completed', 'phase' => 'finalize']);

        $this->loginAdmin($this->getRequest('/baser/admin/bc-wp-export/wp_exports/delete_all'));
        $this->enableSecurityToken();
        $this->enableCsrfToken();
        $this->post('/baser/admin/bc-wp-export/wp_exports/delete_all', ['tokens' => [$token1, $token2]]);

        $this->assertResponseOk();
        $data = json_decode((string) $this->_response->getBody(), true);
        $this->assertTrue($data['result']);
        $this->assertFileDoesNotExist($outputPath1);
        $this->assertFileDoesNotExist($outputPath2);

        $count = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs')
            ->find()->where(['job_token IN' => [$token1, $token2]])->count();
        $this->assertSame(0, $count);
    }

    private function createJob(string $token, array $overrides = [])
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs');

        $data = array_merge([
            'job_token' => $token,
            'status' => 'pending',
            'phase' => 'collect',
            'export_target' => 'all',
            'content_status' => 'published',
            'include_site_meta' => true,
            'include_media_urls' => true,
            'absolute_url' => true,
            'pretty_print' => false,
            'total_items' => 0,
            'processed' => 0,
            'success_count' => 0,
            'skip_count' => 0,
            'warning_count' => 0,
            'error_count' => 0,
            'expires_at' => FrozenTime::now()->addDays(1),
        ], $overrides);

        $job = $jobsTable->newEntity($data);
        return $jobsTable->saveOrFail($job);
    }
}
