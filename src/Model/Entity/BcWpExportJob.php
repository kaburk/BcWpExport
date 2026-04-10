<?php
declare(strict_types=1);

namespace BcWpExport\Model\Entity;

use Cake\ORM\Entity;

class BcWpExportJob extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
