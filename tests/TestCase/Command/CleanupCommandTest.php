<?php
declare(strict_types=1);

namespace BcWpExport\Test\TestCase\Command;

use BaserCore\TestSuite\BcTestCase;
use Cake\Command\Command;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;

class CleanupCommandTest extends BcTestCase
{
    use ConsoleIntegrationTestTrait;

    private array $tempPaths = [];

    public function tearDown(): void
    {
        TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs')
            ->deleteAll(['job_token LIKE' => 'cleanupcommandtest%']);

        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testExecuteReturnsWhenNoExpiredJobs(): void
    {
        $this->exec('BcWpExport.cleanup');

        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertOutputContains('削除対象のジョブはありませんでした。');
    }

    public function testExecuteDryRunDoesNotDeleteExpiredJobs(): void
    {
        $outputPath = $this->createTempFile('cleanupcommandtest-dryrun.xml', '<rss/>');
        $job = $this->createJob('cleanupcommandtest-dryrun', [
            'expires_at' => FrozenTime::now()->addDays(-1),
            'output_path' => $outputPath,
        ]);

        $this->exec('BcWpExport.cleanup --dry-run');

        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertOutputContains('[dry-run] 削除対象のジョブ: 1 件');
        $this->assertFileExists($outputPath);
        $this->assertNotNull(TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs')->find()
            ->where(['id' => $job->id])->first());
    }

    public function testExecuteDeletesExpiredJobsAndFiles(): void
    {
        $expiredOutputPath = $this->createTempFile('cleanupcommandtest-expired.xml', '<rss/>');
        $expiredWarningPath = $this->createTempFile('cleanupcommandtest-expired.log', 'warning');
        $expiredReportPath = $this->createTempFile('cleanupcommandtest-expired.csv', 'report');
        $futureOutputPath = $this->createTempFile('cleanupcommandtest-future.xml', '<rss/>');

        $expiredJob = $this->createJob('cleanupcommandtest-expired', [
            'expires_at' => FrozenTime::now()->addDays(-1),
            'output_path' => $expiredOutputPath,
            'warning_log_path' => $expiredWarningPath,
            'excluded_report_path' => $expiredReportPath,
        ]);
        $futureJob = $this->createJob('cleanupcommandtest-future', [
            'expires_at' => FrozenTime::now()->addDays(1),
            'output_path' => $futureOutputPath,
        ]);

        $this->exec('BcWpExport.cleanup');

        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertOutputContains('クリーンアップ完了: ジョブ 1 件・ファイル 3 件を削除しました。');
        $this->assertFileDoesNotExist($expiredOutputPath);
        $this->assertFileDoesNotExist($expiredWarningPath);
        $this->assertFileDoesNotExist($expiredReportPath);
        $this->assertFileExists($futureOutputPath);

        $jobsTable = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs');
        $this->assertNull($jobsTable->find()->where(['id' => $expiredJob->id])->first());
        $this->assertNotNull($jobsTable->find()->where(['id' => $futureJob->id])->first());
    }

    private function createJob(string $token, array $overrides = [])
    {
        $jobsTable = TableRegistry::getTableLocator()->get('BcWpExport.BcWpExportJobs');
        $data = array_merge([
            'job_token' => $token,
            'status' => 'completed',
            'phase' => 'finalize',
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
            'expires_at' => FrozenTime::now()->addDays(-1),
        ], $overrides);

        return $jobsTable->saveOrFail($jobsTable->newEntity($data));
    }

    private function createTempFile(string $filename, string $contents): string
    {
        $dir = TMP . 'bc_wp_export' . DS;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . $filename;
        file_put_contents($path, $contents);
        $this->tempPaths[] = $path;

        return $path;
    }
}
