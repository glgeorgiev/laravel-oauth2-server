<?php namespace GLGeorgiev\LaravelOAuth2Server\Console\Commands;

use Illuminate\Console\Command;

class ClientCommand extends Command{

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'oauth:client';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Add Or Delete Client';

	/**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
    	$this->line('firing!');
    }
}