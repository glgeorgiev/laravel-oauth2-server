<?php

use DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStorageTables extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->string('id');
            $table->string('secret');
            $table->string('name');
            $table->primary('id');
        });

        Schema::create('oauth_client_redirect_uris', function (Blueprint $table) {
            $table->increments('id');
            $table->string('client_id');
            $table->string('redirect_uri');
            $table->string('logout_uri');
        });

        Schema::create('oauth_scopes', function (Blueprint $table) {
            $table->string('id');
            $table->string('description');
            $table->primary('id');
        });

        Schema::create('oauth_sessions', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('owner_type');
            $table->string('owner_id');
            $table->string('client_id');
            $table->string('client_redirect_uri')->nullable();

            $table->foreign('client_id')->references('id')->on('oauth_clients')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->string('access_token')->primary();
            $table->integer('session_id')->unsigned();
            $table->integer('expire_time');

            $table->foreign('session_id')->references('id')->on('oauth_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->string('refresh_token')->primary();
            $table->integer('expire_time');
            $table->string('access_token');

            $table->foreign('access_token')->references('access_token')->on('oauth_access_tokens')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('oauth_auth_codes', function (Blueprint $table) {
            $table->string('auth_code')->primary();
            $table->integer('session_id')->unsigned();
            $table->integer('expire_time');
            $table->string('client_redirect_uri');

            $table->foreign('session_id')->references('id')->on('oauth_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('oauth_access_token_scopes', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('access_token');
            $table->string('scope');

            $table->foreign('access_token')->references('access_token')->on('oauth_access_tokens')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('scope')->references('id')->on('oauth_scopes')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('oauth_auth_code_scopes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('auth_code');
            $table->string('scope');

            $table->foreign('auth_code')->references('auth_code')->on('oauth_auth_codes')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('scope')->references('id')->on('oauth_scopes')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('oauth_session_scopes', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('session_id')->unsigned();
            $table->string('scope');

            $table->foreign('session_id')->references('id')->on('oauth_sessions')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('scope')->references('id')->on('oauth_scopes')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        //insert the scope, that will be used:
        DB::insert('insert into oauth_scopes (id, description) values (?, ?)', ['uid', 'Your User ID']);
    }

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::drop('oauth_session_scopes');
        Schema::drop('oauth_auth_code_scopes');
        Schema::drop('oauth_access_token_scopes');
        Schema::drop('oauth_auth_codes');
        Schema::drop('oauth_refresh_tokens');
        Schema::drop('oauth_access_tokens');
        Schema::drop('oauth_sessions');
        Schema::drop('oauth_scopes');
        Schema::drop('oauth_client_redirect_uris');
        Schema::drop('oauth_clients');
	}

}
