<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class CreateBcWpExportJobs extends BcMigration
{
    public function up()
    {
        $this->table('bc_wp_export_jobs', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('job_token', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'pending'])
            ->addColumn('phase', 'string', ['limit' => 30, 'null' => false, 'default' => 'collect'])
            ->addColumn('export_target', 'string', ['limit' => 30, 'null' => false, 'default' => 'all'])
            ->addColumn('blog_content_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('author_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('category_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('content_status', 'string', ['limit' => 30, 'null' => false, 'default' => 'published'])
            ->addColumn('date_from', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('date_to', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('include_site_meta', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('include_media_urls', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('absolute_url', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('pretty_print', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('source_summary', 'text', ['null' => true, 'default' => null])
            ->addColumn('export_settings', 'text', ['null' => true, 'default' => null])
            ->addColumn('output_filename', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('output_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('warning_log_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('excluded_report_path', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('build_position', 'biginteger', ['null' => true, 'default' => 0])
            ->addColumn('total_items', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('processed', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('success_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('skip_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('warning_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('error_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('expires_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('started_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('ended_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['job_token'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['phase'])
            ->addIndex(['blog_content_id'])
            ->addIndex(['expires_at'])
            ->addIndex(['created'])
            ->create();
    }

    public function down()
    {
        $this->table('bc_wp_export_jobs')->drop()->save();
    }
}
