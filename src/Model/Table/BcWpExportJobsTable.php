<?php
declare(strict_types=1);

namespace BcWpExport\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class BcWpExportJobsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('bc_wp_export_jobs');
        $this->setPrimaryKey('id');
        $this->setDisplayField('job_token');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('job_token')
            ->maxLength('job_token', 255)
            ->requirePresence('job_token', 'create')
            ->notEmptyString('job_token');

        $validator
            ->scalar('status')
            ->maxLength('status', 30)
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        return $validator;
    }
}
