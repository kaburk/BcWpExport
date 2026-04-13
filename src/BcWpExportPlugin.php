<?php
declare(strict_types=1);

namespace BcWpExport;

use BaserCore\BcPlugin;
use BcWpExport\Command\CleanupCommand;
use Cake\Console\CommandCollection;

class BcWpExportPlugin extends BcPlugin
{
	public function console(CommandCollection $commands): CommandCollection
	{
		$commands->add('BcWpExport.cleanup', CleanupCommand::class);

		return $commands;
	}
}
