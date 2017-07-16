<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * Download robo.phar from http://robo.li/robo.phar and type in the root of the repo: $ php robo.phar
 * Or do: $ composer update, and afterwards you will be able to execute robo like $ php vendor/bin/robo
 *
 * @package     Joomla
 * @subpackage  Testing
 *
 * @copyright  Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later, see LICENSE.
 * @see        http://robo.li/
 *
 */

require __DIR__ . '/vendor/autoload.php';

use Joomla\Testing\Docker\Network\Network;
use Joomla\Testing\Docker\Container\MySQLContainer;
use Joomla\Testing\Docker\Container\PHPContainer;
use Joomla\Testing\Docker\Container\TestContainer;
use Joomla\Testing\Coordinator\SelectionList;
use Joomla\Testing\Coordinator\MainCoordiantor;
use Joomla\Testing\Coordinator\MCS;
use GearmanClient;
use GearmanWorker;
use Joomla\Testing\Coordinator\Task;

/**
 * Class RoboFile
 *
 * @package     Joomla
 * @subpackage  Testing
 *
 * @since  __DEPLOY_VERSION__
 */
class RoboFile extends \Robo\Tasks
{
	use Joomla\Testing\Robo\Tasks\loadTasks;

	/**
	 * Runs base servers
	 *
	 * @return  integer
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function runServers()
	{
		// Cleans up and creates the new network
		$dockerNetwork = new Network('joomla');
		$dockerNetwork->remove();
		$dockerNetwork->create();

		$dockerDB = new MySQLContainer;
		$dockerDB->set('name', 'db');
		$dockerDB->set('network', $dockerNetwork);
		$dockerDB->set(
			'params', array(
				'MYSQL_ROOT_PASSWORD' => 'root'
			)
		);
		$dockerDB->set(
			'ports', array(
				'13306' => '3306'
			)
		);
		$dockerDB->pull();
		$dockerDB->run();

		$dockerPHP = new PHPContainer;
		$dockerPHP->set('imageName', 'joomla');
		$dockerPHP->set('imageTag', '3.6.5-apache-php7');
		$dockerPHP->set('name', 'php');
		$dockerPHP->set('network', $dockerNetwork);
		$dockerPHP->set(
			'ports', array(
				'8080' => '80'
			)
		);
		$dockerPHP->set(
			'params', array(
				'JOOMLA_DB_HOST' => 'db',
				'JOOMLA_DB_USER' => 'root',
				'JOOMLA_DB_PASSWORD' => 'root',
				'JOOMLA_DB_NAME' => 'joomla'
			)
		);
		$dockerPHP->pull();
		$dockerPHP->run();

		return 0;
	}

	/**
	 * Stops base servers
	 *
	 * @return  integer
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function stopServers()
	{
		$dockerDB = new MySQLContainer;
		$dockerDB->set('name', 'db');
		$dockerDB->stop();
		$dockerDB->remove();

		$dockerPHP = new PHPContainer;
		$dockerPHP->set('name', 'php');
		$dockerPHP->stop();
		$dockerPHP->remove();

		// Cleans up the network
		$dockerNetwork = new Network('joomla');
		$dockerNetwork->remove();

		return 0;
	}

	/**
	 * Starts tests of a given repository (Github based).  runServers MUST be executed first.
	 *
	 * @param   string  $repoOwner   Repository owner
	 * @param   string  $repoName    Repository name
	 * @param   string  $repoBranch  Branch to use
	 *
	 * @return  integer
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function runTests($repoOwner, $repoName, $repoBranch)
	{
		$this->prepareExtension($repoOwner, $repoName, $repoBranch);

		$tmpDir = __DIR__ . '/.tmp';

		// Docker network (assuming it's started)
		$dockerNetwork = new Network('joomla');

		$dockerTesting = new TestContainer;
		$dockerTesting->set('name', 'client');
		$dockerTesting->set('network', $dockerNetwork);
		$dockerTesting->set(
			'ports', array(
				'5901' => '5900'
			)
		);
		$dockerTesting->set(
			'volumes', array(
				$tmpDir . '/extension' => '/usr/src/tests'
			)
		);
		$dockerTesting->pull();
		$dockerTesting->run();

		return 0;
	}

	public function loadTests($ymlPath)
	{
		$selectionList = new SelectionList($ymlPath);
		$task = $selectionList->pop();
		$selectionList->execute($task);
		for($i=0; $i<5; $i++){
			$task = $selectionList->pop();
		}
		$selectionList->fail($task);
		var_dump($selectionList->getList());
	}

	public function runCoordinator($repoOwner, $repoName, $repoBranch)
	{
//		$this->prepareExtension($repoOwner, $repoName, $repoBranch);

		$tmpDir = __DIR__ . '/.tmp';
		$dockyardPath = $tmpDir . "/dockyard";

		$env = array(
			'php' => ['5.4', '5.5', '5.6', '7.0', '7.1'],
			'joomla' => ['3.6'],
			'selenium.no' => 3,
			'extension.path' => $tmpDir . '/extension',
			'host.dockyard' => '.tmp/dockyard',
		);

		if (!file_exists($dockyardPath))
		{
			$this->_mkdir($dockyardPath);
		}

		$coordinator = new MainCoordiantor($env, $dockyardPath);

		$coordinator->prepare();

//		$coordinator->generateEnv();

//		$coordinator->waitForDbInit();

		//TODO start parallel testing

	}

	public function runCoordinatorStatic($repoOwner, $repoName, $repoBranch)
	{
//		$this->prepareExtension($repoOwner, $repoName, $repoBranch);

		$tmpDir = __DIR__ . '/.tmp';
		$dockyardPath = $tmpDir . "/dockyard";

		$env = array(
			'php' => ['5.4', '5.5', '5.6', '7.0', '7.1'],
			'joomla' => ['3.6'],
			'selenium.no' => 3,
			'extension.path' => $tmpDir . '/extension',
			'host.dockyard' => '.tmp/dockyard',
		);

		if (!file_exists($dockyardPath))
		{
			$this->_mkdir($dockyardPath);
		}

		MCS::prepare($env);

//		$coordinator->generateEnv();

//		$coordinator->waitForDbInit();

		//TODO start parallel testing

	}

	public function runClientTask($codeceptionTask, $server, $client){

		$command = "docker exec $client /bin/sh -c \"cd /usr/src/tests/tests;vendor/bin/robo run:container-tests 
					--single --test $codeceptionTask --server $server\"";

		echo "ajungem aici";

		$result = Command::execute($command);

		if($result)
		{
			echo "success";
		}
		else
		{
			echo "fail";
		}
	}

	public function runAsyncTest(){
		// make sure the environment is up and running;

		$server1 = "dockyard_apachev7p0v3p6_1";
		$server2 = "dockyard_apachev7p1v3p6_1";
		$server3 = "dockyard_apachev5p4v3p6_1";
		$client1 = "dockyard_seleniumv0_1";
		$client2 = "dockyard_seleniumv1_1";
		$client3 = "dockyard_seleniumv2_1";

		$codeceptionTask = "install/InstallWeblinksCest.php:installWeblinks";

//		$task1 = new Task($codeceptionTask, $server1);
//		$task2 = new Task($codeceptionTask, $server2);
//		$task3 = new Task($codeceptionTask, $server3);

//		$task1->run($client1);
//		$task2->run($client2);
//		$task3->run($client3);
		$gmclient= new GearmanClient();

		$gmclient->addServer();

		$job_handle = $gmclient->doBackground("runTask", "$codeceptionTask, $server1, $client1");
		$job_handle = $gmclient->doBackground("runTask", "$codeceptionTask, $server2, $client2");
		$job_handle = $gmclient->doBackground("runTask", "$codeceptionTask, $server3, $client3");

	}

	public function startWorker()
	{
		$worker = new GearmanWorker();

		$worker->addServer();

		function runTask(GearmanJob $job)
		{
			$workload = $job->workload();
			echo "Received job: " . $job->handle() . "\n";
			echo "Workload: $workload\n";
			$result = strrev($workload);
			echo "Result: $result\n";
			return $result;
		}

		$worker->addFunction("runTask", "runTask");

		while (1) {
			print "Waiting for job...\n";
			$ret = $worker->work(); // work() will block execution until a job is delivered
			if ($worker->returnCode() != GEARMAN_SUCCESS) {
				break;
			}
		}


	}

	public function prepareExtension($repoOwner, $repoName, $repoBranch)
	{
		if (empty($repoOwner) || empty($repoName))
		{
			$this->say('Please specify repository owner and name (Github based)');

			return 1;
		}

		// Temporary dir for the testing repository
		$tmpDir = __DIR__ . '/.tmp';

		if (!file_exists($tmpDir))
		{
			$this->_mkdir($tmpDir);
		}

		// Cloning of the repository we're going to test
		$taskCMSSetup = $this->taskCMSSetup()
			->setBaseTestsPath($tmpDir)
			->setCmsRepository($repoOwner . '/' . $repoName)
			->setCmsPath('extension');

		if (!empty($repoBranch))
		{
			$taskCMSSetup->setCmsBranch($repoBranch);
		}

		$taskCMSSetup->cloneCMSRepository()
			->setupCMSPath()
			->run()
			->stopOnFail();

		// Installs composer dependencies prior to tests
		$this->taskComposerInstall(__DIR__ . '/composer.phar')
			->option('working-dir', $tmpDir . '/extension/tests')
			->preferDist()
			->run();
	}
}
