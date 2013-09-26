<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1380199741_UpdatePageTable extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `page`
			MODIFY `access` DEFAULT '-100'
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `page`
			MODIFY `access` DEFAULT '0'
		");
	}
}