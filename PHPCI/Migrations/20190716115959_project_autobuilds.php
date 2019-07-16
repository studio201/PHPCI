<?php


use Phinx\Migration\AbstractMigration;

class ProjectAutobuilds extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $project = $this->table('project');
        $project->addColumn('disable_autobuild', 'boolean');
        $project->addColumn('build_running', 'boolean');
        $project->save();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $project = $this->table('project');
        $project->removeColumn('disable_autobuild');
        $project->removeColumn('build_running');
        $project->save();
    }
}