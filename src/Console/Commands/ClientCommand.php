<?php namespace GLGeorgiev\LaravelOAuth2Server\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class ClientCommand
 * @author Georgi Georgiev georgi.georgiev@delta.bg
 * @package GLGeorgiev\LaravelOAuth2Server\Console\Commands\ClientCommand
 */
class ClientCommand extends Command
{

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
    protected $description = 'List, Add, Remove Or Show Client';

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
        } elseif ($this->argument('action') == 'show') {
            $this->showCommand();
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
            ['action', InputArgument::REQUIRED, 'add/remove/list/show'],
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
            ['id',      null, InputOption::VALUE_REQUIRED, 'Client ID'],
            ['name',    null, InputOption::VALUE_REQUIRED, 'Client Name'],
            ['login',   null, InputOption::VALUE_REQUIRED, 'Client Login URI'],
            ['logout',  null, InputOption::VALUE_REQUIRED, 'Client Logout URI'],
        ];
    }

    /**
     * List Clients Command
     */
    private function listCommand()
    {
        $results = DB::select('select client_id, name, redirect_uri, logout_uri
            from oauth_clients join oauth_client_redirect_uris
            on oauth_clients.id = oauth_client_redirect_uris.client_id');
        $results = json_decode(json_encode($results), true);    //small hack to convert object to array
        $this->table(['client_id', 'name', 'redirect_uri', 'logout_uri'], $results);
    }

    /**
     * Add Client Command
     */
    private function addCommand()
    {
        if ($this->option('name') && $this->option('login') && $this->option('logout')) {
            $result = DB::select('select count(*) as count from oauth_clients where name = ?',
                [$this->option('name')]);
            if (!$result[0]->count) {
                $bytes = openssl_random_pseudo_bytes(25);
                $id = str_replace(['/', '+', '='], '', base64_encode($bytes));
                $bytes = openssl_random_pseudo_bytes(50);
                $secret = str_replace(['/', '+', '='], '', base64_encode($bytes));
                DB::insert('insert into oauth_clients (id, secret, name) values (?, ?, ?)',
                    [$id, $secret, $this->option('name')]);
                DB::insert('insert into oauth_client_redirect_uris (client_id, redirect_uri, logout_uri) values (?, ?, ?)',
                    [$id, $this->option('login'), $this->option('logout')]);
                $this->comment('Successfully added client with:');
                $this->info('      Name: ' . $this->option('name'));
                $this->info('        ID: ' . $id);
                $this->info('    Secret: ' . $secret);
                $this->info(' Login URI: ' . $this->option('login'));
                $this->info('Logout URI: ' . $this->option('logout'));
            } else {
                $this->error('Such client already exists!');
            }
        } else {
            $this->error('There are missing options, please fill them!');
        }
    }

    /**
     * Remove Client Command
     */
    private function removeCommand()
    {
        if ($this->option('id') || $this->option('name')) {
            $result = DB::select('select id from oauth_clients where id = ? or name = ? limit 1',
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

    /**
     * Show Client Command
     */
    private function showCommand()
    {
        if ($this->option('id') || $this->option('name')) {
            $result = DB::select('select client_id, name, secret, redirect_uri, logout_uri
                from oauth_clients join oauth_client_redirect_uris
                on oauth_clients.id = oauth_client_redirect_uris.client_id
                where client_id = ? or name = ? limit 1',
                [$this->option('id'), $this->option('name')]);
            if (isset($result[0])) {
                $this->comment('Info for client:');
                $this->info('      Name: ' . $result[0]->name);
                $this->info('        ID: ' . $result[0]->client_id);
                $this->info('    Secret: ' . $result[0]->secret);
                $this->info(' Login URI: ' . $result[0]->redirect_uri);
                $this->info('Logout URI: ' . $result[0]->logout_uri);
            } else {
                $this->error('There is no such client!');
            }
        } else {
            $this->error('There are missing options, please fill them!');
        }
    }
}