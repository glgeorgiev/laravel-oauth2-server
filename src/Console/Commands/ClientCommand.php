<?php namespace GLGeorgiev\LaravelOAuth2Server\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

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
	protected $description = 'List, Add Or Remove Client';

	/**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        if ($this->argument('action') == 'list') {
            $this->listCommand();
        } elseif ($this->argument('action') == 'add') {
            $this->addCommand();
        } elseif ($this->argument('action') == 'remove') {
            $this->removeCommand();
        } else {
            $this->error('Invalid argument action!');
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['action', InputArgument::REQUIRED, 'add or remove'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['id', null, InputOption::VALUE_REQUIRED, 'Client ID'],
            ['name', null, InputOption::VALUE_REQUIRED, 'Client Name'],
            ['uri', null, InputOption::VALUE_REQUIRED, 'Client Redirect URI'],
        ];
    }

    private function listCommand()
    {
    	$results = DB::select('select client_id, secret, name, redirect_uri
            from oauth_clients join oauth_client_redirect_uris
            on oauth_clients.id = oauth_client_redirect_uris.client_id');
        $results = json_decode(json_encode($results), true);    //small hack to convert object to array
        $this->table(['client_id', 'secret', 'name', 'redirect_uri'], $results);
    }

    private function addCommand()
    {
		if ($this->option('name') && $this->option('uri')) {
            $result = DB::select('select count(*) as count from oauth_clients where name = ?',
                [$this->option('name')]);
            if (!$result[0]->count) {
                $bytes = openssl_random_pseudo_bytes(25);
                $id = str_replace(['/', '+', '='], '', base64_encode($bytes));
                $bytes = openssl_random_pseudo_bytes(50);
                $secret = str_replace(['/', '+', '='], '', base64_encode($bytes));
                DB::insert('insert into oauth_clients (id, secret, name) values (?, ?, ?)',
                    [$id, $secret, $this->option('name')]);
                DB::insert('insert into oauth_client_redirect_uris (client_id, redirect_uri) values (?, ?)',
                    [$id, $this->option('uri')]);
                $this->comment('Successfully added client with:');
                $this->info('Name        :' . $this->option('name'));
                $this->info('ID          :' . $id);
                $this->info('Secret      :' . $secret);
                $this->info('Redirect URI:' . $this->option('uri'));
            } else {
                $this->error('Such client already exists!');
            }
        } else {
            $this->error('There are missing options, please fill them!');
        }
    }

    private function removeCommand()
    {
		if ($this->option('id') || $this->option('name')) {
            $result = DB::select('select id from oauth_clients where id = ? or name = ?',
                [$this->option('id'), $this->option('name')]);
            if (isset($result[0])) {
                DB::delete('delete from oauth_client_redirect_uris where client_id = ?',
                    [$result[0]->id]);
                DB::delete('delete from oauth_clients where id = ?',
                    [$result[0]->id]);
                $this->comment('Successfully deleted client.');
            } else {
                $this->error('There is no such client!');
            }
        } else {
            $this->error('There are missing options, please fill them!');
        }
    }
}