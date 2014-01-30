<?php
/*
 * This file is part of Rocketeer
 *
 * (c) Maxime Fabre <ehtnam6@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Rocketeer\Traits\BashModules;

use Rocketeer\Traits\AbstractLocatorClass;
use Illuminate\Support\Str;

/**
 * Core handling of running commands and returning output
 *
 * @author Maxime Fabre <ehtnam6@gmail.com>
 */
class Core extends AbstractLocatorClass
{
	/**
	 * An history of executed commands
	 *
	 * @var array
	 */
	protected $history = array();

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// HISTORY ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Get the Task's history
	 *
	 * @return array
	 */
	public function getHistory()
	{
		return $this->history;
	}

	////////////////////////////////////////////////////////////////////
	///////////////////////////// CORE METHODS /////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Run actions on the remote server and gather the ouput
	 *
	 * @param  string|array $commands  One or more commands
	 * @param  boolean      $silent    Whether the command should stay silent no matter what
	 * @param  boolean      $array     Whether the output should be returned as an array
	 *
	 * @return string|array
	 */
	public function run($commands, $silent = false, $array = false)
	{
		$output   = null;
		$commands = $this->processCommands($commands);
		$verbose  = $this->getOption('verbose') and !$silent;

		// Log the commands for pretend
		if ($this->getOption('pretend') and !$silent) {
			return $this->addCommandsToHistory($commands);
		}

		// Run commands
		$me = $this;
		$this->remote->run($commands, function ($results) use (&$output, $verbose, $me) {
			$output .= $results;

			if ($verbose) {
				$me->remote->display(trim($results));
			}
		});

		// Explode output if necessary
		if ($array) {
			$output = explode($this->server->getLineEndings(), $output);
		}

		// Trim output
		$output = is_array($output)
			? array_filter($output)
			: trim($output);

		// Append output
		if (!$silent) {
			$this->history[] = $output;
		}

		return $output;
	}

	/**
	 * Run a raw command, without any processing, and
	 * get its output as a string or array
	 *
	 * @param  string|array $commands
	 * @param  boolean      $array     Whether the output should be returned as an array
	 *
	 * @return string
	 */
	public function runRaw($commands, $array = false)
	{
		$output = null;

		// Run commands
		$this->remote->run($commands, function ($results) use (&$output) {
			$output .= $results;
		});

		// Explode output if necessary
		if ($array) {
			$output = explode($this->server->getLineEndings(), $output);
			$output = array_filter($output);
		}

		return $output;
	}

	/**
	 * Run commands silently
	 *
	 * @param string|array  $commands
	 * @param boolean       $array
	 *
	 * @return string
	 */
	public function runSilently($commands, $array = false)
	{
		return $this->run($commands, true, $array);
	}

	/**
	 * Run commands in a folder
	 *
	 * @param  string        $folder
	 * @param  string|array  $tasks
	 *
	 * @return string
	 */
	public function runInFolder($folder = null, $tasks = array())
	{
		// Convert to array
		if (!is_array($tasks)) {
			$tasks = array($tasks);
		}

		// Prepend folder
		array_unshift($tasks, 'cd '.$this->rocketeer->getFolder($folder));

		return $this->run($tasks);
	}

	/**
	 * Check the status of the last run command, return an error if any
	 *
	 * @param  string $error        The message to display on error
	 * @param  string $output       The command's output
	 * @param  string $success      The message to display on success
	 *
	 * @return boolean|string
	 */
	public function checkStatus($error, $output = null, $success = null)
	{
		// If all went well
		if ($this->remote->status() == 0) {
			if ($success) {
				$this->command->comment($success);
			}

			return $output;
		}

		// Else
		$this->command->error($error);
		print $output.PHP_EOL;

		return false;
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// HELPERS ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Get an option from the Command
	 *
	 * @param  string $option
	 *
	 * @return string
	 */
	protected function getOption($option)
	{
		return $this->hasCommand() ? $this->command->option($option) : null;
	}

	/**
	 * Add an array/command to the history
	 *
	 * @param string|array $commands
	 */
	protected function addCommandsToHistory($commands)
	{
		$this->command->line(implode(PHP_EOL, $commands));
		$commands = (sizeof($commands) == 1) ? $commands[0] : $commands;
		$this->history[] = $commands;

		return $commands;
	}

	/**
	 * Process an array of commands
	 *
	 * @param  string|array  $commands
	 *
	 * @return array
	 */
	protected function processCommands($commands)
	{
		$stage     = $this->rocketeer->getStage();
		$separator = $this->server->getSeparator();

		// Cast commands to array
		if (!is_array($commands)) {
			$commands = array($commands);
		}

		// Process commands
		foreach ($commands as &$command) {

			// Replace directory separators
			if (DS !== $separator) {
				$command = str_replace(DS, $separator, $command);
			}

			// Add stage flag to Artisan commands
			if (Str::contains($command, 'artisan') and $stage) {
				$command .= ' --env='.$stage;
			}

		}

		return $commands;
	}
}
